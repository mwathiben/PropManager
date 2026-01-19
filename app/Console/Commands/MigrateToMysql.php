<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateToMysql extends Command
{
    protected $signature = 'migrate:sqlite-to-mysql
                            {--dry-run : Show what would be migrated without executing}
                            {--tables= : Migrate only specific tables (comma-separated)}
                            {--skip-tables= : Skip specific tables (comma-separated)}';

    protected $description = 'Migrate data from SQLite to MySQL';

    protected array $defaultSkipTables = [
        'migrations',
        'cache',
        'cache_locks',
        'sessions',
        'jobs',
        'failed_jobs',
        'password_reset_tokens',
    ];

    protected array $tableOrder = [
        // Level 0 - Root (no dependencies)
        'subscription_plans',
        'help_topics',
        'platform_billing_settings',
        'legal_documents',
        'faqs',
        'invoice_types',

        // Level 1 - Users (root)
        'users',

        // Level 2 - Direct user dependencies
        'landlord_profiles',
        'onboarding_progress',
        'settings',
        'personal_access_tokens',
        'password_histories',
        'payment_configurations',
        'subscriptions',
        'landlord_payout_accounts',
        'tenant_notes',
        'emergency_contacts',
        'invoice_settings',
        'invoice_templates',
        'receipt_templates',
        'notification_provider_configs',
        'push_subscriptions',
        'notification_templates',
        'security_logs',
        'audit_logs',
        'deletion_requests',
        'consents',
        'security_incidents',
        'usage_records',
        'notification_defaults',

        // Level 3 - Properties
        'properties',
        'help_articles',

        // Level 4 - Buildings & Invitations
        'buildings',
        'invitations',
        'move_out_inspection_items',

        // Level 5 - Units & Policies
        'units',
        'late_fee_policies',
        'verification_templates',
        'water_settings',

        // Level 6 - Leases & Tenants
        'tenant_invitations',
        'leases',
        'verification_items',

        // Level 7 - Lease-dependent
        'rent_histories',
        'water_readings',
        'move_outs',
        'tenant_verifications',
        'tenant_activities',

        // Level 8 - Invoices
        'invoices',

        // Level 9 - Invoice-dependent
        'invoice_items',
        'late_fees',
        'credit_notes',
        'payment_links',

        // Level 10 - Payments
        'payments',
        'wallet_transactions',
        'refunds',
        'deposit_transactions',

        // Level 11 - Tickets & Documents
        'tickets',
        'documents',

        // Level 12 - Ticket children
        'ticket_activities',
        'ticket_comments',
        'ticket_feedback',

        // Level 13 - Notifications
        'notifications',
        'notification_preferences',
        'notification_schedules',

        // Level 14 - Receipts & Billing
        'receipts',
        'subscription_payments',
        'platform_fees',
        'billing_model_changes',
        'tenant_payment_verifications',
        'tenant_messages',
        'imports',
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $onlyTables = $this->option('tables')
            ? explode(',', $this->option('tables'))
            : null;
        $skipTables = $this->option('skip-tables')
            ? array_merge($this->defaultSkipTables, explode(',', $this->option('skip-tables')))
            : $this->defaultSkipTables;

        $this->info('SQLite to MySQL Data Migration');
        $this->info('==============================');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be modified');
        }

        if (! $this->verifyConnections()) {
            return self::FAILURE;
        }

        $tables = $this->getOrderedTables($onlyTables, $skipTables);

        if (empty($tables)) {
            $this->error('No tables to migrate');

            return self::FAILURE;
        }

        $this->info(sprintf('Tables to migrate: %d', count($tables)));
        $this->newLine();

        if (! $dryRun) {
            DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0');
        }

        $results = [];
        $totalRows = 0;
        $failedTables = [];

        foreach ($tables as $table) {
            $result = $this->migrateTable($table, $dryRun);
            $results[$table] = $result;
            $totalRows += $result['rows'];

            if (! $result['success']) {
                $failedTables[] = $table;
            }
        }

        if (! $dryRun) {
            DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->newLine();
        $this->displaySummary($results, $totalRows, $failedTables, $dryRun);

        if (! empty($failedTables)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function verifyConnections(): bool
    {
        $this->info('Verifying database connections...');

        try {
            $sqliteVersion = DB::connection('sqlite')->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
            $this->line(sprintf('  SQLite: Connected (v%s)', $sqliteVersion));
        } catch (\Exception $e) {
            $this->error('  SQLite: Connection failed - '.$e->getMessage());

            return false;
        }

        try {
            $mysqlVersion = DB::connection('mysql')->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
            $this->line(sprintf('  MySQL: Connected (v%s)', $mysqlVersion));
        } catch (\Exception $e) {
            $this->error('  MySQL: Connection failed - '.$e->getMessage());

            return false;
        }

        $this->newLine();

        return true;
    }

    protected function getOrderedTables(?array $onlyTables, array $skipTables): array
    {
        $sqliteTables = collect(DB::connection('sqlite')
            ->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
            ->pluck('name')
            ->toArray();

        $orderedTables = [];
        foreach ($this->tableOrder as $table) {
            if (in_array($table, $sqliteTables) && ! in_array($table, $skipTables)) {
                if ($onlyTables === null || in_array($table, $onlyTables)) {
                    $orderedTables[] = $table;
                }
            }
        }

        foreach ($sqliteTables as $table) {
            if (! in_array($table, $orderedTables) && ! in_array($table, $skipTables)) {
                if ($onlyTables === null || in_array($table, $onlyTables)) {
                    $orderedTables[] = $table;
                }
            }
        }

        return $orderedTables;
    }

    protected function migrateTable(string $table, bool $dryRun): array
    {
        $result = [
            'success' => true,
            'rows' => 0,
            'error' => null,
        ];

        try {
            $sourceCount = DB::connection('sqlite')->table($table)->count();
            $result['rows'] = $sourceCount;

            if ($sourceCount === 0) {
                $this->line(sprintf('  [SKIP] %s - empty', $table));

                return $result;
            }

            if ($dryRun) {
                $this->line(sprintf('  [DRY] %s - %d rows', $table, $sourceCount));

                return $result;
            }

            if (! Schema::connection('mysql')->hasTable($table)) {
                $this->warn(sprintf('  [SKIP] %s - table does not exist in MySQL', $table));
                $result['rows'] = 0;

                return $result;
            }

            DB::connection('mysql')->table($table)->truncate();

            $bar = $this->output->createProgressBar($sourceCount);
            $bar->setFormat('  %message% [%bar%] %current%/%max%');
            $bar->setMessage($table);
            $bar->start();

            $chunk = [];
            $chunkSize = 500;

            DB::connection('sqlite')->table($table)->orderBy(
                Schema::connection('sqlite')->hasColumn($table, 'id') ? 'id' : DB::raw('rowid')
            )->cursor()->each(function ($row) use ($table, &$chunk, $chunkSize, $bar) {
                $rowArray = (array) $row;
                $rowArray = $this->prepareRowForMysql($rowArray, $table);
                $chunk[] = $rowArray;

                if (count($chunk) >= $chunkSize) {
                    DB::connection('mysql')->table($table)->insert($chunk);
                    $bar->advance(count($chunk));
                    $chunk = [];
                }
            });

            if (! empty($chunk)) {
                DB::connection('mysql')->table($table)->insert($chunk);
                $bar->advance(count($chunk));
            }

            $bar->finish();
            $this->newLine();

            $destCount = DB::connection('mysql')->table($table)->count();
            if ($destCount !== $sourceCount) {
                throw new \RuntimeException(
                    sprintf('Row count mismatch: source=%d, dest=%d', $sourceCount, $destCount)
                );
            }

        } catch (\Exception $e) {
            $this->newLine();
            $this->error(sprintf('  [FAIL] %s - %s', $table, $e->getMessage()));
            $result['success'] = false;
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    protected function prepareRowForMysql(array $row, string $table): array
    {
        foreach ($row as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_string($value) && $this->isJson($value)) {
                continue;
            }
        }

        return $row;
    }

    protected function isJson(string $value): bool
    {
        if (strlen($value) < 2) {
            return false;
        }

        $firstChar = $value[0];
        if ($firstChar !== '{' && $firstChar !== '[') {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    protected function displaySummary(array $results, int $totalRows, array $failedTables, bool $dryRun): void
    {
        $this->info('Migration Summary');
        $this->info('=================');

        $successful = count(array_filter($results, fn ($r) => $r['success']));
        $failed = count($failedTables);

        $this->line(sprintf('Tables processed: %d', count($results)));
        $this->line(sprintf('Successful: %d', $successful));

        if ($failed > 0) {
            $this->error(sprintf('Failed: %d', $failed));
            $this->error('Failed tables: '.implode(', ', $failedTables));
        }

        $this->line(sprintf('Total rows %s: %d', $dryRun ? 'to migrate' : 'migrated', $totalRows));

        if (! $dryRun && $failed === 0) {
            $this->newLine();
            $this->info('Migration completed successfully!');
        }
    }
}
