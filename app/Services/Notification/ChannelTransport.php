<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Repositories\Contracts\NotificationConfigRepositoryInterface;
use App\Services\PushNotificationService;
use App\Services\WhatsAppTemplateService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Physical per-channel delivery for notifications, extracted from
 * NotificationService (M2 decomposition). Owns the transport mechanics for
 * each channel — in-app event, email (NotificationMail), SMS (Twilio /
 * Africa's Talking), WhatsApp (Twilio templates), and web push — plus the
 * shared HTTP resilience (timeout + retry) and channel-failure logging.
 * Behaviour is locked by NotificationServiceTest + Phase16ResilienceTest;
 * this was a verbatim move.
 */
class ChannelTransport
{
    public function __construct(
        private readonly NotificationConfigRepositoryInterface $configRepository,
        private readonly WhatsAppTemplateService $whatsAppTemplateService,
    ) {}

    public function sendViaChannel(Notification $notification, User $recipient, ?string $overrideChannel = null): bool
    {
        $channel = $overrideChannel ?? $notification->channel;

        return match ($channel) {
            'email' => $this->sendEmail($notification, $recipient),
            'sms' => $this->sendSms($notification, $recipient),
            'whatsapp' => $this->sendWhatsApp($notification, $recipient),
            'push' => $this->sendPush($notification, $recipient),
            'in_app' => $this->sendInApp($notification, $recipient),
            default => false,
        };
    }

    /**
     * OBS-4: every notification-channel failure must land in the
     * 'notifications' log channel with full context. Pre-fix the catch
     * blocks only stamped markAsFailed() on the row; ops had no way to
     * detect a wedged Twilio account or expired Africa's Talking key
     * because the failure rate was buried in DB rows that nobody queries.
     */
    public function logChannelFailure(Notification $notification, \Throwable $e): void
    {
        Log::channel(config('logging.notifications_channel', 'stack'))->error(
            'Notification channel failure',
            [
                'notification_id' => $notification->id,
                'landlord_id' => $notification->landlord_id,
                'recipient_id' => $notification->recipient_id ?? $notification->user_id ?? null,
                'channel' => $notification->channel,
                'type' => $notification->type,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]
        );
    }

    /**
     * Send in-app notification (just marks as sent - stored in DB for display)
     */
    public function sendInApp(Notification $notification, User $recipient): bool
    {
        // In-app notifications are immediately available once created in the database
        // They're visible in the notification bell and notifications page
        $notification->markAsSent();

        // Broadcast real-time update to recipient's notification bell
        event(new \App\Events\NewNotification($notification));

        return true;
    }

    /**
     * Send email notification
     */
    private function sendEmail(Notification $notification, User $recipient): bool
    {
        if (empty($recipient->email)) {
            $notification->markAsFailed('Recipient has no email address');
            Log::warning('Notification email skipped: no email address', [
                'notification_id' => $notification->id,
                'recipient_id' => $recipient->id,
            ]);

            return false;
        }

        try {
            // Phase-24 I18N-BACKEND-3: pass the User object (not just
            // the email string) so Laravel honours HasLocalePreference
            // and renders the notification under the recipient's
            // chosen locale — even when the dispatcher is running in
            // an admin or system request whose App::getLocale() is
            // different.
            Mail::to($recipient)->send(new NotificationMail(
                $notification->subject,
                $notification->message,
                $notification->data,
                $recipient
            ));

            $notification->markAsSent();

            return true;
        } catch (\Exception $e) {
            Log::error('Notification email failed', [
                'notification_id' => $notification->id,
                'recipient_id' => $recipient->id,
                'subject' => $notification->subject,
                'error' => $e->getMessage(),
            ]);
            $notification->markAsFailed($e->getMessage());

            return false;
        }
    }

    /**
     * Send SMS notification
     */
    private function sendSms(Notification $notification, User $recipient): bool
    {
        $provider = $this->configRepository->getSmsProvider($notification->landlord_id);

        if ($provider === 'none') {
            $notification->markAsFailed('SMS provider not configured');

            return false;
        }

        return match ($provider) {
            'twilio' => $this->sendViaTwilio($notification, $recipient),
            'africas_talking' => $this->sendViaAfricasTalking($notification, $recipient),
            default => false,
        };
    }

    /**
     * Send SMS via Twilio
     */
    private function sendViaTwilio(Notification $notification, User $recipient): bool
    {
        $credentials = $this->configRepository->getTwilioCredentials($notification->landlord_id);
        $accountSid = $credentials['account_sid'];
        $authToken = $credentials['auth_token'];
        $fromNumber = $credentials['phone_number'];

        if (! $accountSid || ! $authToken || ! $fromNumber) {
            $notification->markAsFailed('Twilio credentials not configured');

            return false;
        }

        try {
            // Phase-16 RESIL-2: explicit timeout + retry on transient
            // ConnectionException / 5xx / 429. Pre-fix this call was
            // unbounded (Laravel default 30s, no retry) so a wedged
            // Twilio routinely starved the worker.
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->connectTimeout(5)
                ->timeout(15)
                ->retry(2, $this->resilientBackoff(), $this->resilientRetryFilter(), throw: false)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'From' => $fromNumber,
                    'To' => $recipient->mobile_number,
                    'Body' => $notification->message,
                ]);

            if ($response->successful()) {
                $notification->markAsSent($response->json('sid'));

                return true;
            }

            $notification->markAsFailed($response->json('message', 'Unknown error'));

            return false;
        } catch (ConnectionException $e) {
            $notification->markAsFailed('Twilio unreachable: '.$e->getMessage());
            $this->logChannelFailure($notification, $e);

            return false;
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            $this->logChannelFailure($notification, $e);

            return false;
        }
    }

    /**
     * Send SMS via Africa's Talking
     */
    private function sendViaAfricasTalking(Notification $notification, User $recipient): bool
    {
        $credentials = $this->configRepository->getAfricasTalkingCredentials($notification->landlord_id);
        $apiKey = $credentials['api_key'];
        $username = $credentials['username'];
        $from = $credentials['from'];

        if (! $apiKey || ! $username) {
            $notification->markAsFailed("Africa's Talking credentials not configured");

            return false;
        }

        try {
            // Phase-16 RESIL-2: same resilience treatment as Twilio above.
            // The standalone AfricasTalkingService already had timeout +
            // retry; this inline path was the regression.
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'apiKey' => $apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])
                ->connectTimeout(5)
                ->timeout(15)
                ->retry(2, $this->resilientBackoff(), $this->resilientRetryFilter(), throw: false)
                ->asForm()
                ->post('https://api.africastalking.com/version1/messaging', [
                    'username' => $username,
                    'to' => $recipient->mobile_number,
                    'message' => $notification->message,
                    'from' => $from,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['SMSMessageData']['Recipients'][0]['status'])
                    && $data['SMSMessageData']['Recipients'][0]['status'] === 'Success') {
                    $notification->markAsSent($data['SMSMessageData']['Recipients'][0]['messageId'] ?? null);

                    return true;
                }
            }

            $notification->markAsFailed($response->body());

            return false;
        } catch (ConnectionException $e) {
            $notification->markAsFailed("Africa's Talking unreachable: ".$e->getMessage());
            $this->logChannelFailure($notification, $e);

            return false;
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            $this->logChannelFailure($notification, $e);

            return false;
        }
    }

    /**
     * Send WhatsApp notification via Twilio
     *
     * Uses Meta-approved templates when available (ContentSid + ContentVariables).
     * Falls back to plain text (Body) when template is not approved.
     */
    private function sendWhatsApp(Notification $notification, User $recipient): bool
    {
        $twilioCredentials = $this->configRepository->getTwilioCredentials($notification->landlord_id);
        $accountSid = $twilioCredentials['account_sid'];
        $authToken = $twilioCredentials['auth_token'];
        $fromNumber = $this->configRepository->getWhatsAppNumber($notification->landlord_id);

        if (! $accountSid || ! $authToken || ! $fromNumber) {
            $notification->markAsFailed('WhatsApp credentials not configured');

            return false;
        }

        $preferences = NotificationPreference::where('user_id', $recipient->id)
            ->where('landlord_id', $notification->landlord_id)
            ->first();

        $toNumber = $preferences?->whatsapp_number ?? $recipient->mobile_number;

        try {
            $payload = [
                'From' => 'whatsapp:'.$fromNumber,
                'To' => 'whatsapp:'.$toNumber,
            ];

            $templateType = $this->mapNotificationTypeToTemplate($notification->type);

            if ($templateType && $this->whatsAppTemplateService->isApproved($templateType, $notification->landlord_id)) {
                $templateData = $notification->data ?? [];
                $payload['ContentSid'] = $this->whatsAppTemplateService->getContentSid($templateType, $notification->landlord_id);
                $payload['ContentVariables'] = json_encode(
                    $this->whatsAppTemplateService->renderVariables($templateType, $templateData)
                );
            } else {
                $payload['Body'] = $notification->message;
            }

            // Phase-16 RESIL-2: timeout + retry + connection-exception
            // handling. Same shape as Twilio SMS path above.
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->connectTimeout(5)
                ->timeout(15)
                ->retry(2, $this->resilientBackoff(), $this->resilientRetryFilter(), throw: false)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", $payload);

            if ($response->successful()) {
                $notification->markAsSent($response->json('sid'));

                return true;
            }

            $notification->markAsFailed($response->json('message', 'Unknown error'));

            return false;
        } catch (ConnectionException $e) {
            $notification->markAsFailed('Twilio WhatsApp unreachable: '.$e->getMessage());
            $this->logChannelFailure($notification, $e);

            return false;
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            $this->logChannelFailure($notification, $e);

            return false;
        }
    }

    /**
     * Phase-16 RESIL-2 + RESIL-4: shared backoff + retry-filter for the
     * inline Twilio + AfricasTalking calls. Retries on ConnectionException
     * + 5xx + 429 only; honors Retry-After header when the server sets it
     * (RESIL-4). Exponential 200 → 400 → 800 ms otherwise.
     */
    private function resilientBackoff(): \Closure
    {
        return function (int $attempt, ?\Throwable $exception = null): int {
            if ($exception instanceof \Illuminate\Http\Client\RequestException
                && $exception->response?->header('Retry-After')) {
                $hint = (int) $exception->response->header('Retry-After');
                if ($hint > 0 && $hint <= 30) {
                    return $hint * 1000;
                }
            }

            return 200 * (2 ** ($attempt - 1));
        };
    }

    private function resilientRetryFilter(): \Closure
    {
        return function (\Throwable $exception): bool {
            if ($exception instanceof ConnectionException) {
                return true;
            }
            if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                $status = $exception->response?->status() ?? 0;

                return $status === 429 || $status >= 500;
            }

            return false;
        };
    }

    /**
     * Map notification type to WhatsApp template type.
     */
    private function mapNotificationTypeToTemplate(string $notificationType): ?string
    {
        return match ($notificationType) {
            'rent_reminder' => 'rent_reminder',
            'arrears_notice' => 'arrears_notice',
            'invoice' => 'invoice_ready',
            'receipt' => 'payment_received',
            'maintenance_update' => 'maintenance_update',
            'lease_renewal' => 'lease_renewal',
            default => null,
        };
    }

    /**
     * Send push notification
     */
    private function sendPush(Notification $notification, User $recipient): bool
    {
        try {
            $pushService = app(PushNotificationService::class);

            // Check if push is configured
            if (! $pushService->isConfigured($notification->landlord_id)) {
                $notification->markAsFailed('Push notifications not configured');

                return false;
            }

            // Check if user has push subscriptions
            $subscriptions = $pushService->getUserSubscriptions($recipient->id);
            if ($subscriptions->isEmpty()) {
                $notification->markAsFailed('No push subscriptions for user');

                return false;
            }

            $success = $pushService->send(
                $recipient->id,
                $notification->subject ?? 'New Notification',
                $notification->message,
                $notification->data,
                $notification->landlord_id
            );

            if ($success) {
                $notification->markAsSent();

                return true;
            }

            $notification->markAsFailed('Push notification delivery failed');

            return false;
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            $this->logChannelFailure($notification, $e);

            return false;
        }
    }
}
