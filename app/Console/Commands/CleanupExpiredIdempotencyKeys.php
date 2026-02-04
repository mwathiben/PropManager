<?php

namespace App\Console\Commands;

use App\Services\IdempotencyService;
use Illuminate\Console\Command;

class CleanupExpiredIdempotencyKeys extends Command
{
    protected $signature = 'idempotency:cleanup';

    protected $description = 'Remove expired idempotency keys older than 24 hours';

    public function handle(IdempotencyService $service): int
    {
        $this->info('Cleaning up expired idempotency keys...');

        $deleted = $service->cleanupExpired();

        $this->info("Deleted {$deleted} expired key(s).");

        return Command::SUCCESS;
    }
}
