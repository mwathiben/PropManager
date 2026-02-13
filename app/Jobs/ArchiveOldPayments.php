<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Payment\PaymentArchivalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArchiveOldPayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function handle(PaymentArchivalService $service): void
    {
        $archivedCount = 0;
        $errorCount = 0;

        Payment::withoutGlobalScope('landlord')
            ->archivable()
            ->with(['platformFee', 'receipt'])
            ->chunkById(500, function ($payments) use ($service, &$archivedCount, &$errorCount) {
                foreach ($payments as $payment) {
                    try {
                        DB::transaction(fn () => $service->archivePayment($payment));
                        $archivedCount++;
                    } catch (\Throwable $e) {
                        Log::error('Payment archival failed', [
                            'payment_id' => $payment->id,
                            'landlord_id' => $payment->landlord_id,
                            'error' => $e->getMessage(),
                        ]);
                        $errorCount++;
                    }
                }
            });

        Log::info('Payment archival complete', [
            'archived' => $archivedCount,
            'errors' => $errorCount,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ArchiveOldPayments job failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
