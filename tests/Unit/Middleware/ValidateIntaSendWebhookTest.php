<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ValidateIntaSendWebhook;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * IntaSend webhook IP allowlist (audit S3). The allowlist is optional
 * defense-in-depth on top of the per-landlord challenge: empty => allow
 * (IntaSend's documented challenge-based model), configured => enforce.
 */
class ValidateIntaSendWebhookTest extends TestCase
{
    private function handleFromIp(string $ip): Response
    {
        $request = Request::create('/webhooks/intasend/mpesa', 'POST', server: ['REMOTE_ADDR' => $ip]);

        return (new ValidateIntaSendWebhook)->handle($request, fn () => response('ok', 200));
    }

    public function test_empty_allowlist_passes_by_design(): void
    {
        config(['intasend.webhook_allowed_ips' => []]);

        $this->assertSame(200, $this->handleFromIp('5.5.5.5')->getStatusCode());
    }

    public function test_configured_allowlist_rejects_unlisted_ip(): void
    {
        config(['intasend.webhook_allowed_ips' => ['9.9.9.9']]);

        $this->assertSame(403, $this->handleFromIp('1.1.1.1')->getStatusCode());
    }

    public function test_configured_allowlist_admits_listed_ip(): void
    {
        config(['intasend.webhook_allowed_ips' => ['9.9.9.9']]);

        $this->assertSame(200, $this->handleFromIp('9.9.9.9')->getStatusCode());
    }
}
