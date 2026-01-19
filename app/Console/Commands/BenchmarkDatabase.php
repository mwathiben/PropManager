<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\FinanceStatsService;
use App\Services\ReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BenchmarkDatabase extends Command
{
    protected $signature = 'benchmark:database
                            {--connection= : Database connection to use (sqlite or mysql)}
                            {--iterations=5 : Number of iterations per benchmark}
                            {--output=table : Output format (table or json)}';

    protected $description = 'Benchmark database query performance for SQLite vs MySQL comparison';

    private array $results = [];

    private int $iterations;

    public function handle(): int
    {
        $connection = $this->option('connection') ?: config('database.default');
        $this->iterations = (int) $this->option('iterations');

        $this->info('Database Performance Benchmark');
        $this->info('==============================');
        $this->info("Connection: {$connection}");
        $this->info("Iterations: {$this->iterations}");
        $this->newLine();

        DB::setDefaultConnection($connection);
        Cache::flush();

        $landlord = $this->getLandlordForBenchmark();
        if (! $landlord) {
            $this->error('No landlord found for benchmarking. Please seed the database first.');

            return 1;
        }

        $this->info("Using landlord: {$landlord->name} (ID: {$landlord->id})");
        $this->newLine();

        $this->runBenchmarks($landlord);

        $this->displayResults();

        if ($this->option('output') === 'json') {
            $this->line(json_encode($this->results, JSON_PRETTY_PRINT));
        }

        return 0;
    }

    private function getLandlordForBenchmark(): ?User
    {
        return User::where('role', 'landlord')
            ->whereHas('properties')
            ->first();
    }

    private function runBenchmarks(User $landlord): void
    {
        $this->info('Running Finance Stats benchmarks...');
        $this->benchmarkFinanceStats($landlord);

        $this->info('Running Dashboard benchmarks...');
        $this->benchmarkDashboard($landlord);

        $this->info('Running Report benchmarks...');
        $this->benchmarkReports($landlord);

        $this->info('Running Raw Query benchmarks...');
        $this->benchmarkRawQueries($landlord);

        $this->info('Running Concurrent Query benchmark...');
        $this->benchmarkConcurrentQueries($landlord);
    }

    private function benchmarkFinanceStats(User $landlord): void
    {
        $service = new FinanceStatsService;

        $this->benchmark('finance_overview_stats', function () use ($service, $landlord) {
            return $service->getOverviewStats($landlord->id);
        });

        $this->benchmark('finance_hub_stats', function () use ($service, $landlord) {
            return $service->getHubStats($landlord->id);
        });

        $this->benchmark('finance_arrears_stats', function () use ($service, $landlord) {
            return $service->getArrearsStats($landlord->id);
        });

        $this->benchmark('finance_monthly_trend', function () use ($service, $landlord) {
            return $service->getMonthlyTrend($landlord->id, 6);
        });

        $this->benchmark('finance_expense_stats', function () use ($service, $landlord) {
            return $service->getExpenseStats($landlord->id);
        });
    }

    private function benchmarkDashboard(User $landlord): void
    {
        $service = new DashboardService;

        $this->benchmark('dashboard_quick_metrics', function () use ($service, $landlord) {
            return $service->calculateQuickMetrics($landlord->id);
        });

        $this->benchmark('dashboard_arrears_0_30', function () use ($service) {
            return $service->getArrearsInRange(0, 30);
        });

        $this->benchmark('dashboard_arrears_31_60', function () use ($service) {
            return $service->getArrearsInRange(31, 60);
        });
    }

    private function benchmarkReports(User $landlord): void
    {
        $service = app(ReportService::class);

        $this->benchmark('report_dashboard_analytics', function () use ($service, $landlord) {
            return $service->getDashboardAnalytics($landlord->id, 'month');
        });

        $this->benchmark('report_export_financial', function () use ($service, $landlord) {
            return $service->exportData($landlord->id, 'financial', 'month');
        });

        $this->benchmark('report_export_arrears', function () use ($service, $landlord) {
            return $service->exportData($landlord->id, 'arrears', 'month');
        });
    }

    private function benchmarkRawQueries(User $landlord): void
    {
        $this->benchmark('query_count_units', function () use ($landlord) {
            return Unit::where('landlord_id', $landlord->id)->count();
        });

        $this->benchmark('query_count_leases', function () use ($landlord) {
            return Lease::where('landlord_id', $landlord->id)->where('is_active', true)->count();
        });

        $this->benchmark('query_sum_payments', function () use ($landlord) {
            return Payment::where('landlord_id', $landlord->id)
                ->whereMonth('payment_date', now()->month)
                ->sum('amount');
        });

        $this->benchmark('query_invoices_aggregate', function () use ($landlord) {
            return Invoice::where('landlord_id', $landlord->id)
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as paid,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as overdue,
                    SUM(total_due) as total_due,
                    SUM(amount_paid) as total_paid
                ', ['paid', 'overdue'])
                ->first();
        });

        $this->benchmark('query_units_with_relations', function () use ($landlord) {
            return Unit::where('landlord_id', $landlord->id)
                ->with(['building', 'activeLease.tenant'])
                ->limit(100)
                ->get();
        });

        $this->benchmark('query_properties_nested', function () use ($landlord) {
            return Property::where('landlord_id', $landlord->id)
                ->with(['buildings.units.activeLease'])
                ->get();
        });
    }

    private function benchmarkConcurrentQueries(User $landlord): void
    {
        $this->benchmark('concurrent_dashboard_load', function () use ($landlord) {
            $results = [];

            $results['units'] = Unit::where('landlord_id', $landlord->id)->count();
            $results['leases'] = Lease::where('landlord_id', $landlord->id)->where('is_active', true)->count();
            $results['payments'] = Payment::where('landlord_id', $landlord->id)
                ->whereMonth('payment_date', now()->month)
                ->sum('amount');
            $results['invoices'] = Invoice::where('landlord_id', $landlord->id)
                ->whereIn('status', ['sent', 'partial', 'overdue'])
                ->count();
            $results['arrears'] = Invoice::where('landlord_id', $landlord->id)
                ->where('status', 'overdue')
                ->selectRaw('COALESCE(SUM(total_due - amount_paid), 0) as total')
                ->value('total');

            return $results;
        });
    }

    private function benchmark(string $name, callable $callback): void
    {
        $times = [];

        for ($i = 0; $i < $this->iterations; $i++) {
            Cache::flush();

            $start = microtime(true);
            $callback();
            $end = microtime(true);

            $times[] = ($end - $start) * 1000;
        }

        $this->results[$name] = [
            'min' => round(min($times), 2),
            'max' => round(max($times), 2),
            'avg' => round(array_sum($times) / count($times), 2),
            'iterations' => $this->iterations,
        ];

        $avg = $this->results[$name]['avg'];
        $status = $avg < 50 ? '<fg=green>FAST</>' : ($avg < 200 ? '<fg=yellow>OK</>' : '<fg=red>SLOW</>');
        $this->line("  {$name}: {$avg}ms {$status}");
    }

    private function displayResults(): void
    {
        $this->newLine();
        $this->info('Benchmark Results Summary');
        $this->info('=========================');

        $headers = ['Benchmark', 'Min (ms)', 'Avg (ms)', 'Max (ms)', 'Status'];
        $rows = [];

        foreach ($this->results as $name => $data) {
            $status = $data['avg'] < 50 ? 'FAST' : ($data['avg'] < 200 ? 'OK' : 'SLOW');
            $rows[] = [
                $name,
                $data['min'],
                $data['avg'],
                $data['max'],
                $status,
            ];
        }

        $this->table($headers, $rows);

        $totalAvg = array_sum(array_column($this->results, 'avg'));
        $this->newLine();
        $this->info("Total average time: {$totalAvg}ms");
        $this->info('Connection: '.DB::getDefaultConnection());
    }
}
