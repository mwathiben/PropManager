<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\TenantStatementMail;
use App\Models\Lease;
use App\Models\TenantStatementPreference;
use App\Models\User;
use App\Services\Reports\XlsxExportService;
use App\Services\Tenant\StatementService;
use App\Services\Wallet\WalletService;
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
 * Tenant/Statement.vue + PDF + xlsx download + self-email-me.
 *
 * Phase-45 STATEMENT-DEPTH extensions:
 *  - periods: calendar_year, last_12_months, custom (was: current_month,
 *    last_month, last_3_months, year_to_date)
 *  - filters: ?types[]=charge,payment&min_amount=&max_amount=
 *  - xlsx multi-sheet: "Monthly Summary" added alongside the detail rows
 *    when the window spans 2+ months
 *  - column preferences: tenants persist column choice via PATCH /preferences
 */
class TenantStatementController extends Controller
{
    private const ALLOWED_PERIODS = [
        'current_month',
        'last_month',
        'last_3_months',
        'year_to_date',
        'calendar_year',
        'last_12_months',
        'custom',
    ];

    public function __construct(
        private readonly StatementService $statements,
        private readonly WalletService $wallet,
    ) {}

    public function index(Request $request): Response
    {
        [$from, $to, $period] = $this->resolveRange($request);
        $filters = $this->resolveFilters($request);
        $rows = $this->statements->forTenant($request->user(), $from, $to, $filters);

        return Inertia::render('Tenant/Statement', [
            'period' => $period,
            'allowedPeriods' => self::ALLOWED_PERIODS,
            'allowedColumns' => TenantStatementPreference::ALLOWED_COLUMNS,
            'selectedColumns' => TenantStatementPreference::columnsFor($request->user()),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'filters' => $filters,
            'rows' => $rows,
            'walletBalances' => $this->walletBalances($request->user()),
        ]);
    }

    /**
     * Phase-76 STATEMENT-WALLET-3: current wallet credit per currency, summed
     * across the tenant's leases, for the statement balance header.
     *
     * @return array<int, array{currency: string, balance: float}>
     */
    private function walletBalances(User $tenant): array
    {
        $totals = [];

        Lease::where('tenant_id', $tenant->id)->get()->each(function (Lease $lease) use (&$totals) {
            foreach ($this->wallet->balancesFor($lease) as $currency => $balance) {
                $totals[$currency] = ($totals[$currency] ?? 0.0) + $balance;
            }
        });

        return collect($totals)
            ->filter(fn (float $balance) => abs($balance) > 0.001)
            ->map(fn (float $balance, string $currency) => ['currency' => $currency, 'balance' => round($balance, 2)])
            ->values()
            ->all();
    }

    public function pdf(Request $request): HttpResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $filters = $this->resolveFilters($request);
        $rows = $this->statements->forTenant($request->user(), $from, $to, $filters);

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
        $filters = $this->resolveFilters($request);
        $rows = $this->statements->forTenant($request->user(), $from, $to, $filters);

        $selected = TenantStatementPreference::columnsFor($request->user());
        $columns = $this->buildColumnSpec($selected);

        // Multi-month windows get a second "Monthly Summary" sheet
        // with charges/payments/net/closing-balance per month.
        $sheets = [['title' => __('tenant.statement.title'), 'columns' => $columns, 'rows' => $rows->all()]];
        if ($from->format('Y-m') !== $to->format('Y-m')) {
            $monthly = $this->statements->monthlySubtotals($request->user(), $from, $to);
            $sheets[] = [
                'title' => __('tenant.statement.monthly_summary_title'),
                'columns' => [
                    ['label' => __('tenant.statement.col_month'), 'key' => 'month', 'type' => 'string'],
                    ['label' => __('tenant.statement.col_charges'), 'key' => 'charges', 'type' => 'currency'],
                    ['label' => __('tenant.statement.col_payments'), 'key' => 'payments', 'type' => 'currency'],
                    ['label' => __('tenant.statement.col_net'), 'key' => 'net', 'type' => 'currency'],
                    ['label' => __('tenant.statement.col_closing_balance'), 'key' => 'closing_balance', 'type' => 'currency'],
                ],
                'rows' => $monthly->all(),
            ];
        }

        $tmpDir = storage_path('app/tmp/tenant-statements');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0o755, true);
        }
        $path = $tmpDir.'/'.$request->user()->id.'-'.now()->format('YmdHis').'.xlsx';

        $xlsx->writeMultiSheet($sheets, $path);

        return response()->download(
            $path,
            $this->filename($request, $from, $to, 'xlsx', $filters),
        )->deleteFileAfterSend(true);
    }

    public function email(Request $request): RedirectResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $filters = $this->resolveFilters($request);
        $rows = $this->statements->forTenant($request->user(), $from, $to, $filters);

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
     * Phase-45 STATEMENT-DEPTH-3: tenant column persistence.
     */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string', 'in:'.implode(',', TenantStatementPreference::ALLOWED_COLUMNS)],
        ]);

        TenantStatementPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['columns' => array_values(array_unique($validated['columns']))],
        );

        return Redirect::route('tenant.statement.index', $request->only('period'))
            ->with('success', __('tenant.statement.preferences_saved'));
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
            'calendar_year' => [$now->startOfYear(), $now->endOfYear()],
            'last_12_months' => [$now->subMonths(12)->startOfMonth(), $now->endOfMonth()],
            'custom' => $this->resolveCustomRange($request, $now),
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };

        return [$from, $to, $period];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveCustomRange(Request $request, CarbonImmutable $now): array
    {
        $fromInput = $request->input('from');
        $toInput = $request->input('to');

        $from = $fromInput ? CarbonImmutable::parse($fromInput)->startOfDay() : $now->startOfMonth();
        $to = $toInput ? CarbonImmutable::parse($toInput)->endOfDay() : $now->endOfMonth();

        // Clamp to today as upper bound + 5 years back as lower bound.
        $earliest = $now->subYears(5)->startOfDay();
        if ($from->lessThan($earliest)) {
            $from = $earliest;
        }
        if ($to->greaterThan($now->endOfDay())) {
            $to = $now->endOfDay();
        }
        if ($from->greaterThan($to)) {
            $from = $to->startOfMonth();
        }

        return [$from, $to];
    }

    /**
     * @return array{types?: list<string>, min_amount?: float, max_amount?: float}
     */
    private function resolveFilters(Request $request): array
    {
        $filters = [];

        $types = $request->input('types');
        if (is_array($types) && $types !== []) {
            $allowed = array_intersect(['charge', 'payment'], $types);
            if ($allowed !== []) {
                $filters['types'] = array_values($allowed);
            }
        }

        $min = $request->input('min_amount');
        if (is_numeric($min) && (float) $min >= 0) {
            $filters['min_amount'] = (float) $min;
        }

        $max = $request->input('max_amount');
        if (is_numeric($max) && (float) $max >= 0) {
            $filters['max_amount'] = (float) $max;
        }

        return $filters;
    }

    /**
     * Phase-45 STATEMENT-DEPTH-3: derive the XlsxExportService column
     * spec from the tenant's persisted preference list.
     *
     * @param  list<string>  $selected
     * @return list<array{label: string, key: string, type: string}>
     */
    private function buildColumnSpec(array $selected): array
    {
        $catalog = [
            'date' => ['label' => __('tenant.statement.col_date'), 'key' => 'date', 'type' => 'date'],
            'description' => ['label' => __('tenant.statement.col_description'), 'key' => 'description', 'type' => 'string'],
            'reference' => ['label' => __('tenant.statement.col_reference'), 'key' => 'reference', 'type' => 'string'],
            'charge' => ['label' => __('tenant.statement.col_charge'), 'key' => 'charge', 'type' => 'currency'],
            'payment' => ['label' => __('tenant.statement.col_payment'), 'key' => 'payment', 'type' => 'currency'],
            'running_balance' => ['label' => __('tenant.statement.col_balance'), 'key' => 'running_balance', 'type' => 'currency'],
        ];

        return array_values(array_filter(array_map(
            static fn (string $key) => $catalog[$key] ?? null,
            $selected,
        )));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filename(Request $request, CarbonImmutable $from, CarbonImmutable $to, string $ext, array $filters = []): string
    {
        $base = sprintf(
            'tenant-statement-%d-%s-to-%s',
            $request->user()->id,
            $from->format('Ymd'),
            $to->format('Ymd'),
        );

        // Encode the filter summary in the filename so a tenant who
        // saves multiple exports can tell them apart.
        if (! empty($filters['types']) && count($filters['types']) === 1) {
            $base .= '-'.$filters['types'][0];
        }

        return $base.'.'.$ext;
    }
}
