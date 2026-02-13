<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\SmsServiceInterface;
use App\Models\IntaSendTransaction;
use App\Models\PaymentConfiguration;
use App\Models\QueuedPaymentIntent;
use App\Services\IntaSendService;
use App\Services\MpesaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessQueuedPaymentIntents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function handle(MpesaService $mpesaService, SmsServiceInterface $smsService): void
    {
        $this->recoverStaleProcessingIntents();
        $this->markExpiredIntents($smsService);
        $this->processRetryableIntents($mpesaService, $smsService);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessQueuedPaymentIntents job failed', [
            'error' => $exception->getMessage(),
        ]);
    }

    private function recoverStaleProcessingIntents(): void
    {
        QueuedPaymentIntent::where('status', QueuedPaymentIntent::STATUS_PROCESSING)
            ->where('last_attempt_at', '<', now()->subMinutes(10))
            ->update([
                'status' => QueuedPaymentIntent::STATUS_PENDING,
                'next_retry_at' => now(),
            ]);
    }

    private function markExpiredIntents(SmsServiceInterface $smsService): void
    {
        QueuedPaymentIntent::pending()
            ->where('expires_at', '<', now())
            ->chunkById(50, function ($intents) use ($smsService) {
                foreach ($intents as $intent) {
                    $intent->markExpired();
                    $this->sendSms($smsService, $intent, 'expired');
                }
            });
    }

    private function processRetryableIntents(MpesaService $mpesaService, SmsServiceInterface $smsService): void
    {
        $maxAttempts = (int) config('payments.queued_intents.max_attempts', 3);

        QueuedPaymentIntent::retryable()
            ->with(['tenant:id,name,mobile_number', 'invoice:id,invoice_number,landlord_id', 'landlord:id,name'])
            ->chunkById(50, function ($intents) use ($mpesaService, $smsService, $maxAttempts) {
                foreach ($intents as $intent) {
                    if ($intent->attempts >= $maxAttempts) {
                        $intent->markFailed("Maximum retry attempts ({$maxAttempts}) exceeded");
                        $this->sendSms($smsService, $intent, 'failed');

                        continue;
                    }

                    $this->attemptPayment($intent, $mpesaService, $smsService);
                }
            });
    }

    private function attemptPayment(
        QueuedPaymentIntent $intent,
        MpesaService $mpesaService,
        SmsServiceInterface $smsService,
    ): void {
        $config = PaymentConfiguration::where('landlord_id', $intent->landlord_id)->first();
        if (! $config) {
            $intent->markFailed('Payment gateway not configured for landlord');
            $this->sendSms($smsService, $intent, 'failed');

            return;
        }

        $claimed = $this->claimIntent($intent);
        if (! $claimed) {
            return;
        }

        try {
            $result = match ($intent->payment_method) {
                'mpesa', 'mobile_money' => $this->initiateMpesa($intent, $mpesaService, $config),
                'intasend' => $this->initiateIntaSend($intent, $config),
                default => throw new \InvalidArgumentException(
                    "Payment method '{$intent->payment_method}' not supported for offline queue"
                ),
            };

            if ($result !== null) {
                $this->sendSms($smsService, $intent, 'initiated');
                Log::info('Queued payment intent initiated', [
                    'intent_id' => $intent->id,
                    'method' => $intent->payment_method,
                    'phone' => substr($intent->phone_number, -4),
                ]);
            } else {
                $intent->update(['status' => QueuedPaymentIntent::STATUS_PENDING]);
            }
        } catch (\InvalidArgumentException $e) {
            $intent->markFailed($e->getMessage());
            $this->sendSms($smsService, $intent, 'failed');
        } catch (\Throwable $e) {
            $intent->update(['status' => QueuedPaymentIntent::STATUS_PENDING]);
            Log::warning('Queued payment attempt failed', [
                'intent_id' => $intent->id,
                'attempt' => $intent->attempts,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function claimIntent(QueuedPaymentIntent $intent): bool
    {
        $affected = DB::table('queued_payment_intents')
            ->where('id', $intent->id)
            ->where('status', QueuedPaymentIntent::STATUS_PENDING)
            ->update([
                'status' => QueuedPaymentIntent::STATUS_PROCESSING,
                'attempts' => $intent->attempts + 1,
                'last_attempt_at' => now(),
                'next_retry_at' => $this->calculateNextRetry($intent->attempts + 1),
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            return false;
        }

        $intent->refresh();

        return true;
    }

    private function calculateNextRetry(int $attempts): \Carbon\Carbon
    {
        $backoff = config('payments.queued_intents.backoff', [10, 30, 60, 120, 300]);
        $index = min($attempts - 1, count($backoff) - 1);

        return now()->addSeconds($backoff[$index]);
    }

    private function initiateMpesa(
        QueuedPaymentIntent $intent,
        MpesaService $mpesaService,
        PaymentConfiguration $config,
    ): ?array {
        return $mpesaService->initiateSTKPush([
            'phone' => $intent->phone_number,
            'amount' => $intent->amount,
            'account_reference' => $intent->invoice?->invoice_number ?? 'OFFLINE-'.$intent->id,
            'description' => 'Offline queued payment',
            'callback_url' => route('webhooks.mpesa.stk-callback'),
        ], $config);
    }

    private function initiateIntaSend(
        QueuedPaymentIntent $intent,
        PaymentConfiguration $config,
    ): ?array {
        if (! $intent->invoice_id) {
            throw new \InvalidArgumentException('IntaSend requires an invoice reference');
        }

        $intaSendService = app(IntaSendService::class, ['config' => $config]);
        $reference = IntaSendService::generateReference('QPI');

        $transaction = IntaSendTransaction::create([
            'landlord_id' => $intent->landlord_id,
            'invoice_id' => $intent->invoice_id,
            'api_ref' => $reference,
            'phone_number' => $intaSendService->formatPhoneNumber($intent->phone_number),
            'amount' => $intent->amount,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $result = $intaSendService->initializeMpesaStkPush(
            (float) $intent->amount,
            $intent->phone_number,
            $reference,
        );

        if ($result && isset($result['invoice']['invoice_id'])) {
            $transaction->update(['intasend_invoice_id' => $result['invoice']['invoice_id']]);

            return $result;
        }

        $transaction->markFailed('STK Push initiation failed from offline queue');

        return null;
    }

    private function sendSms(SmsServiceInterface $smsService, QueuedPaymentIntent $intent, string $status): void
    {
        try {
            $phone = $intent->phone_number;
            if (! $phone) {
                return;
            }

            $currencySymbol = $intent->currency?->symbol() ?? 'KSh';
            $amount = number_format((float) $intent->amount, 2);

            $message = match ($status) {
                'initiated' => "{$currencySymbol} {$amount} payment initiated. Check your phone for the payment prompt.",
                'failed' => "Payment of {$currencySymbol} {$amount} failed after multiple attempts. Please retry in the app.",
                'expired' => "Your {$currencySymbol} {$amount} payment request has expired. Please submit a new request.",
                default => null,
            };

            if (! $message) {
                return;
            }

            $smsService->send($intent->landlord_id, $phone, $message);
        } catch (\Throwable $e) {
            Log::warning('SMS notification failed for queued intent', [
                'intent_id' => $intent->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
