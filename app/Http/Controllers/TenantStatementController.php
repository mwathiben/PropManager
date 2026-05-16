<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\TenantStatementMail;
use App\Services\Reports\XlsxExportService;
use App\Services\Tenant\StatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Phase-28 TENANT-STATEMENT-2/3: surfaces StatementService rows in
 * Tenant/Statement.vue + PDF + xlsx download + self-email-me. The
 * period query string is restricted to a small enum so a tenant cannot
 * scan arbitrary historic windows beyond their own lease history.
 */
class TenantStatementController extends Controller
{
    private const ALLOWED_PERIODS = ['current_month', 'last_month', 'last_3_months', 'year_to_date'];

    public function __construct(private readonly StatementService $statements)
    {
    }

    public function index(Request $request): Response
    {
        [$from, $to, $period] = $this->resolveRange($request);
        $rows = $this->statements->forTenant($request->user(), $from, $to);

        return Inertia::render('Tenant/Statement', [
            'period' => $period,
            'allowedPeriods' => self::ALLOWED_PERIODS,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => $rows,
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $rows = $this->statements->forTenant($request->user(), $from, $to);

        $pdf = Pdf::loadView('tenant.statement', [
            'tenant' => $request->user(),
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
        ]);

        return $pdf->download($this->filename($request, $from, $to, 'pdf'));
    }

    public function xlsx(Request $request, XlsxExportService $xlsx): BinaryFileResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $rows = $this->statements->forTenant($request->user(), $from, $to);

        $columns = [
            ['label' => __('tenant.statement.col_date'), 'key' => 'date', 'type' => 'date'],
            ['label' => __('tenant.statement.col_description'), 'key' => 'description', 'type' => 'string'],
            ['label' => __('tenant.statement.col_reference'), 'key' => 'reference', 'type' => 'string'],
            ['label' => __('tenant.statement.col_charge'), 'key' => 'charge', 'type' => 'currency'],
            ['label' => __('tenant.statement.col_payment'), 'key' => 'payment', 'type' => 'currency'],
            ['label' => __('tenant.statement.col_balance'), 'key' => 'running_balance', 'type' => 'currency'],
        ];

        $tmpDir = storage_path('app/tmp/tenant-statements');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0o755, true);
        }
        $path = $tmpDir.'/'.$request->user()->id.'-'.now()->format('YmdHis').'.xlsx';

        $xlsx->write(
            __('tenant.statement.title'),
            $columns,
            $rows->all(),
            $path,
        );

        return response()->download(
            $path,
            $this->filename($request, $from, $to, 'xlsx'),
        )->deleteFileAfterSend(true);
    }

    public function email(Request $request): RedirectResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $rows = $this->statements->forTenant($request->user(), $from, $to);

        // Phase-13 PERSONAL-DATA-1 compliance: recipient is the
        // authenticated user only — no email-address parameter is
        // accepted from the request body.
        Mail::to($request->user())->queue(new TenantStatementMail(
            $request->user(),
            $rows->all(),
            $from,
            $to,
        ));

        return Redirect::route('tenant.statement.index', $request->only('period'))
            ->with('success', __('tenant.statement.emailed'));
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function resolveRange(Request $request): array
    {
        $period = $request->input('period', 'current_month');
        if (! in_array($period, self::ALLOWED_PERIODS, true)) {
            $period = 'current_month';
        }

        $now = CarbonImmutable::now();

        [$from, $to] = match ($period) {
            'last_month' => [$now->subMonth()->startOfMonth(), $now->subMonth()->endOfMonth()],
            'last_3_months' => [$now->subMonths(3)->startOfMonth(), $now->endOfMonth()],
            'year_to_date' => [$now->startOfYear(), $now->endOfDay()],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };

        return [$from, $to, $period];
    }

    private function filename(Request $request, CarbonImmutable $from, CarbonImmutable $to, string $ext): string
    {
        return sprintf(
            'tenant-statement-%d-%s-to-%s.%s',
            $request->user()->id,
            $from->format('Ymd'),
            $to->format('Ymd'),
            $ext,
        );
    }
}
