<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Http\Traits\ResolvesCurrentOwner;
use App\Models\PaymentConfiguration;
use App\Services\FinanceReportService;
use App\Services\OwnerStatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-102 OWNER-PORTAL: the owner views + downloads their own consolidated statement.
 * Reuses OwnerStatementService::forOwner with the owner's OWN ids (resolved from auth,
 * never from a request param) — so it can only ever return the authed owner's figures.
 */
class OwnerPortalStatementsController extends Controller
{
    use ResolvesCurrentOwner;

    public function index(Request $request, OwnerStatementService $statements, FinanceReportService $reports): Response
    {
        $owner = $this->currentOwner();
        [$start, $end] = $this->resolveRange($request, $reports, (int) $owner->landlord_id);

        $data = $statements->forOwner((int) $owner->landlord_id, $owner->id, $start, $end);
        // forOwner() returns null if the owner row vanished between currentOwner()'s
        // firstOrFail and here (owners are hard-deleted) — honor its "caller 404s" contract.
        abort_if($data === null, 404);

        return Inertia::render('Owner/Statements', [
            'statement' => $data,
            'currencySymbol' => $this->resolveCurrency((int) $owner->landlord_id)->symbol(),
            'period' => $request->query('period', '12'),
        ]);
    }

    public function download(Request $request, OwnerStatementService $statements, FinanceReportService $reports): \Symfony\Component\HttpFoundation\Response
    {
        $owner = $this->currentOwner();
        [$start, $end] = $this->resolveRange($request, $reports, (int) $owner->landlord_id);

        $data = $statements->forOwner((int) $owner->landlord_id, $owner->id, $start, $end);
        abort_if($data === null, 404);

        $currency = $this->resolveCurrency((int) $owner->landlord_id);

        return Pdf::loadView('reports.owner-statement-multi', [
            'data' => $data,
            'landlord' => (object) ['name' => $owner->landlord?->name ?? config('app.name')],
            'generated_at' => $data['generated_at'],
            'currency_symbol' => $currency->symbol(),
            'currency_code' => $currency->value,
        ])->download('owner_statement_'.Str::slug($owner->name).'_'.$start->format('Y_m_d').'.pdf');
    }

    private function resolveCurrency(int $landlordId): Currency
    {
        return PaymentConfiguration::where('landlord_id', $landlordId)->first()?->default_currency
            ?? Currency::default();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request, FinanceReportService $reports, int $landlordId): array
    {
        // No 'custom' — owners have no date-range picker, and 'custom' without dates falls
        // through getReportDateRange to subMonths(0) (this month so far), silently
        // understating the statement. Anything off this list normalizes to 12 months.
        $period = (string) $request->query('period', '12');
        $named = ['this_month', 'last_month', 'this_quarter', 'last_quarter', 'ytd', 'this_fy', 'last_fy'];
        if (! in_array($period, $named, true) && ! ctype_digit($period)) {
            $period = '12';
        }

        $range = $reports->getReportDateRange(
            $period,
            $request->query('date_from'),
            $request->query('date_to'),
            $landlordId,
        );

        return [Carbon::parse($range['start']), Carbon::parse($range['end'])];
    }
}
