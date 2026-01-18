<?php

namespace App\Console\Commands;

use App\Services\PaymentLinkService;
use Illuminate\Console\Command;

class CleanupExpiredPaymentLinks extends Command
{
    protected $signature = 'payment-links:cleanup';

    protected $description = 'Remove expired payment links older than 7 days';

    public function handle(PaymentLinkService $paymentLinkService): int
    {
        $this->info('Cleaning up expired payment links...');

        $deleted = $paymentLinkService->cleanupExpired();

        $this->info("Deleted {$deleted} expired payment link(s).");

        return Command::SUCCESS;
    }
}
