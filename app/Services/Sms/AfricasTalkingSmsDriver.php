<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Services\Sms\Contracts\SmsDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Phase-45 EMERGENCY-CONTACT-SMS-1: Africa's Talking SMS adapter.
 * The dominant Kenyan SMS provider; default for KE deployments when
 * SMS_DRIVER=africastalking + the api_key + username are set.
 *
 * If credentials are missing, send() throws — the calling service is
 * responsible for falling back to the stub at the config layer.
 */
class AfricasTalkingSmsDriver implements SmsDriver
{
    public function __construct(
        private readonly ?string $username,
        private readonly ?string $apiKey,
        private readonly ?string $senderId,
        private readonly string $endpoint = 'https://api.africastalking.com/version1/messaging',
    ) {}

    public function send(string $phone, string $message): string
    {
        if ($this->username === null || $this->username === '' || $this->apiKey === null || $this->apiKey === '') {
            throw new RuntimeException('AfricasTalkingSmsDriver requires sms.africastalking.username + api_key.');
        }

        $response = Http::asForm()
            ->withHeaders([
                'apiKey' => $this->apiKey,
                'Accept' => 'application/json',
            ])
            ->post($this->endpoint, array_filter([
                'username' => $this->username,
                'to' => $phone,
                'message' => $message,
                'from' => $this->senderId,
            ]));

        if (! $response->successful()) {
            Log::warning('[sms-africastalking] non-2xx response', [
                'phone' => $phone,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Africa\'s Talking returned '.$response->status());
        }

        $body = $response->json();

        // Response shape: {SMSMessageData: {Message: '...', Recipients: [{number, status, messageId, ...}]}}
        $first = $body['SMSMessageData']['Recipients'][0] ?? null;

        return is_array($first) ? ($first['messageId'] ?? '') : '';
    }
}
