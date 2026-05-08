<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SmsServiceInterface;
use App\Repositories\Contracts\NotificationConfigRepositoryInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AfricasTalkingService implements SmsServiceInterface
{
    private const API_URL = 'https://api.africastalking.com/version1/messaging';

    public function __construct(
        private readonly NotificationConfigRepositoryInterface $configRepository,
    ) {}

    public function send(int $landlordId, string $phoneNumber, string $message): array
    {
        $credentials = $this->configRepository->getAfricasTalkingCredentials($landlordId);

        if (empty($credentials['api_key']) || empty($credentials['username'])) {
            return ['success' => false, 'message_id' => null, 'error' => 'credentials_missing'];
        }

        try {
            $body = array_filter([
                'username' => $credentials['username'],
                'to' => $phoneNumber,
                'message' => $message,
                'from' => $credentials['from'] ?? null,
            ]);

            $response = Http::withHeaders(['apiKey' => $credentials['api_key']])
                ->timeout(30)
                ->retry(2, fn (int $attempt) => 100 * (2 ** ($attempt - 1)), fn ($e) => $this->shouldRetry($e))
                ->asForm()
                ->post(self::API_URL, $body);

            return $this->parseResponse($response->json(), $landlordId, $phoneNumber);
        } catch (RequestException $e) {
            return ['success' => false, 'message_id' => null, 'error' => "HTTP {$e->response->status()}"];
        } catch (ConnectionException $e) {
            Log::warning('Africa\'s Talking SMS connection failed', [
                'landlord_id' => $landlordId,
                'phone' => substr($phoneNumber, -4),
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message_id' => null, 'error' => 'connection_failed'];
        }
    }

    private function shouldRetry(\Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        if ($e instanceof RequestException) {
            return $e->response->serverError();
        }

        return false;
    }

    private function parseResponse(?array $data, int $landlordId, string $phoneNumber): array
    {
        $recipient = is_array($data)
            && isset($data['SMSMessageData']['Recipients'][0])
            ? $data['SMSMessageData']['Recipients'][0]
            : null;

        if ($recipient && ($recipient['status'] ?? '') === 'Success') {
            Log::info('SMS sent via Africa\'s Talking', [
                'landlord_id' => $landlordId,
                'phone' => substr($phoneNumber, -4),
            ]);

            return [
                'success' => true,
                'message_id' => $recipient['messageId'] ?? null,
                'error' => null,
            ];
        }

        $status = $recipient['status'] ?? 'Unknown';

        return ['success' => false, 'message_id' => null, 'error' => "Recipient status: {$status}"];
    }
}
