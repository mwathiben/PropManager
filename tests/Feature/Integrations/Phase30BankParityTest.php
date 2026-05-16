<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Models\BankWebhookLog;
use App\Services\Banking\FamilyBankService;
use App\Services\Banking\PostBankService;
use DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase30BankParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_postbank_validates_hmac_sha256_signature(): void
    {
        $service = new PostBankService;
        $payload = json_encode(['x' => 1]);
        $secret = 'pb-secret-1';

        $sig = hash_hmac('sha256', $payload, $secret);

        $this->assertTrue($service->validateWebhook($sig, $payload, $secret));
        $this->assertFalse($service->validateWebhook('deadbeef', $payload, $secret));
        $this->assertFalse($service->validateWebhook($sig, $payload, ''));
    }

    public function test_postbank_parses_credit_alert_payload(): void
    {
        $service = new PostBankService;

        $notif = $service->parsePaymentNotification([
            'transactionId' => 'TXN-PB-1',
            'amount' => '5000.00',
            'accountNumber' => '1100100200',
            'narration' => 'Rent for May',
            'payerName' => 'Jane Wanjiku',
            'payerMobile' => '+254712345678',
            'transactionDate' => '2026-05-16T10:00:00',
        ]);

        $this->assertSame('postbank', $notif->bankCode);
        $this->assertSame('TXN-PB-1', $notif->transactionId);
        $this->assertSame(5000.00, $notif->amount);
        $this->assertSame('Rent for May', $notif->reference);
    }

    public function test_familybank_validates_bearer_token(): void
    {
        $service = new FamilyBankService;
        $secret = 'fb-token-xyz';

        $this->assertTrue($service->validateWebhook('Bearer '.$secret, '{}', $secret));
        $this->assertTrue($service->validateWebhook($secret, '{}', $secret));
        $this->assertFalse($service->validateWebhook('Bearer wrong-token', '{}', $secret));
        $this->assertFalse($service->validateWebhook('Bearer '.$secret, '{}', ''));
    }

    public function test_familybank_parses_credit_alert_with_alt_field_names(): void
    {
        $service = new FamilyBankService;

        $notif = $service->parsePaymentNotification([
            'TransactionRef' => 'FB-001',
            'CreditAmount' => '12500.50',
            'BeneficiaryAccount' => '055123456',
            'Reference' => 'INV-2026-01',
            'PayerName' => 'Acme Co',
            'PayerMobile' => '254711111111',
            'ValueDate' => '2026-05-16',
        ]);

        $this->assertSame('familybank', $notif->bankCode);
        $this->assertSame('FB-001', $notif->transactionId);
        $this->assertSame(12500.50, $notif->amount);
        $this->assertSame('INV-2026-01', $notif->reference);
    }

    public function test_bank_reconciliation_audit_emits_per_bank_counters(): void
    {
        BankWebhookLog::create([
            'bank_code' => 'postbank',
            'payload' => ['x' => 1],
            'status' => 'success',
            'processed_payment_id' => null,
            'ip_address' => '127.0.0.1',
        ]);
        BankWebhookLog::create([
            'bank_code' => 'familybank',
            'payload' => ['x' => 1],
            'status' => 'error',
            'error_message' => 'invalid signature',
            'ip_address' => '127.0.0.1',
        ]);

        $this->artisan('bank-reconciliation:audit')
            ->assertSuccessful()
            ->expectsOutputToContain('postbank')
            ->expectsOutputToContain('familybank');
    }

    public function test_postbank_route_exists(): void
    {
        $this->assertNotNull(\Illuminate\Support\Facades\Route::getRoutes()->match(
            \Illuminate\Http\Request::create('/api/webhooks/bank/postbank', 'POST'),
        ));
    }

    public function test_familybank_route_exists(): void
    {
        $this->assertNotNull(\Illuminate\Support\Facades\Route::getRoutes()->match(
            \Illuminate\Http\Request::create('/api/webhooks/bank/familybank', 'POST'),
        ));
    }
}
