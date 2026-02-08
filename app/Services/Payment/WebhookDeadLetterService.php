<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Mail\FailedWebhookAlert;
use App\Models\User;
use App\Models\WebhookDeadLetter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WebhookDeadLetterService
{
    public function capture(
        string $provider,
        string $eventType,
        array $payload,
        string $errorReason,
        string $errorClass,
        ?int $landlordId = null,
        ?array $headers = null
    ): ?WebhookDeadLetter {
        if (! $landlordId) {
            Log::warning('DLQ capture skipped: no landlord_id', [
                'provider' => $provider,
                'event_type' => $eventType,
                'error_reason' => $errorReason,
            ]);

            return null;
        }

        $isTransient = $errorClass === WebhookDeadLetter::ERROR_TRANSIENT;
        $maxRetries = $isTransient ? (int) config('payments.dead_letter.max_retries', 5) : 0;
        $jitterSeconds = $isTransient ? random_int(0, 60) : 0;

        $deadLetter = WebhookDeadLetter::withoutGlobalScope('landlord')->create([
            'landlord_id' => $landlordId,
            'provider' => $provider,
            'event_type' => $eventType,
            'payload' => $this->sanitizePayload($payload),
            'headers' => $headers,
            'error_reason' => $errorReason,
            'error_class' => $errorClass,
            'attempts' => 1,
            'max_retries' => $maxRetries,
            'next_retry_at' => $isTransient ? now()->addMinutes(5)->addSeconds($jitterSeconds) : null,
        ]);

        $this->sendAlertIfNotThrottled($deadLetter);

        return $deadLetter;
    }

    public function resolve(WebhookDeadLetter $deadLetter, User $user, string $notes): void
    {
        $deadLetter->markResolved($user, $notes);
    }

    private function sendAlertIfNotThrottled(WebhookDeadLetter $deadLetter): void
    {
        $throttleMinutes = (int) config('payments.dead_letter.alert_throttle_minutes', 15);
        $throttleKey = "dlq_alert:{$deadLetter->provider}:{$deadLetter->landlord_id}";

        if (! Cache::add($throttleKey, true, $throttleMinutes * 60)) {
            return;
        }

        $recipients = $this->resolveRecipients($deadLetter);

        if (empty($recipients)) {
            return;
        }

        Mail::to($recipients)->queue(new FailedWebhookAlert($deadLetter));
    }

    private function resolveRecipients(WebhookDeadLetter $deadLetter): array
    {
        $emails = [];

        $landlord = User::select('id', 'email')->find($deadLetter->landlord_id);
        if ($landlord && filter_var($landlord->email, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $landlord->email;
        }

        $adminEmails = User::where('role', 'super_admin')
            ->pluck('email')
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->toArray();

        return array_values(array_unique(array_merge($emails, $adminEmails)));
    }

    private function sanitizePayload(array $payload): array
    {
        $sensitiveFields = config('payments.dead_letter.sanitize_fields', []);

        return $this->recursiveSanitize($payload, $sensitiveFields);
    }

    private function recursiveSanitize(array $data, array $sensitiveFields): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if ($this->isSensitiveField($lowerKey, $sensitiveFields)) {
                $sanitized[$key] = is_string($value) ? $this->maskPhoneOrRedact($lowerKey, $value) : '***REDACTED***';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->recursiveSanitize($value, $sensitiveFields);

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function isSensitiveField(string $key, array $sensitiveFields): bool
    {
        foreach ($sensitiveFields as $field) {
            if ($key === $field || str_contains($key, $field)) {
                return true;
            }
        }

        return false;
    }

    private function maskPhoneOrRedact(string $key, string $value): string
    {
        if (in_array($key, ['phone', 'msisdn']) && strlen($value) > 4) {
            return str_repeat('*', strlen($value) - 4).substr($value, -4);
        }

        return '***REDACTED***';
    }
}
