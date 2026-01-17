<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    protected const STATUS_MAP = [
        'queued' => 'pending',
        'sent' => 'sent',
        'delivered' => 'delivered',
        'read' => 'read',
        'failed' => 'failed',
        'undelivered' => 'failed',
    ];

    public function statusCallback(Request $request)
    {
        Log::channel('whatsapp')->info('WhatsApp status webhook received', [
            'message_sid' => $request->input('MessageSid'),
            'status' => $request->input('MessageStatus'),
            'to' => $request->input('To'),
            'error_code' => $request->input('ErrorCode'),
        ]);

        $messageSid = $request->input('MessageSid');
        $twilioStatus = $request->input('MessageStatus');

        if (! $messageSid || ! $twilioStatus) {
            Log::channel('whatsapp')->warning('WhatsApp webhook missing required fields', [
                'has_sid' => (bool) $messageSid,
                'has_status' => (bool) $twilioStatus,
            ]);

            return response('Missing required fields', 400);
        }

        $internalStatus = self::STATUS_MAP[$twilioStatus] ?? null;

        if (! $internalStatus) {
            Log::channel('whatsapp')->info('WhatsApp webhook unknown status', [
                'status' => $twilioStatus,
            ]);

            return response('OK', 200);
        }

        try {
            DB::beginTransaction();

            $notification = Notification::where('external_id', $messageSid)
                ->where('channel', Notification::CHANNEL_WHATSAPP)
                ->lockForUpdate()
                ->first();

            if (! $notification) {
                DB::rollBack();
                Log::channel('whatsapp')->info('WhatsApp webhook: no matching notification', [
                    'message_sid' => $messageSid,
                ]);

                return response('OK', 200);
            }

            $notification->updateFromWebhook(
                $internalStatus,
                $request->input('ErrorCode'),
                $request->input('ErrorMessage')
            );

            DB::commit();

            Log::channel('whatsapp')->info('WhatsApp notification status updated', [
                'notification_id' => $notification->id,
                'old_status' => $notification->getOriginal('status'),
                'new_status' => $internalStatus,
            ]);

            return response('OK', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('whatsapp')->error('WhatsApp webhook processing failed', [
                'message_sid' => $messageSid,
                'error' => $e->getMessage(),
            ]);

            return response('OK', 200);
        }
    }

    public function validateTwilioSignature(Request $request, string $authToken): bool
    {
        $signature = $request->header('X-Twilio-Signature');

        if (! $signature) {
            return false;
        }

        $url = $request->fullUrl();
        $params = $request->post();

        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        $expectedSignature = base64_encode(hash_hmac('sha1', $data, $authToken, true));

        return hash_equals($expectedSignature, $signature);
    }
}
