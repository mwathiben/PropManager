<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\Currency;
use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\NotificationPreference;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\Property;
use App\Models\TenantPaymentVerification;
use App\Models\Unit;
use App\Models\User;
use App\Services\Payment\InitialPaymentResult;
use App\Services\Payment\ManualPaymentResult;
use App\Services\Payment\PaymentProcessResult;
use App\Services\PaymentQrCodeService;
use App\Services\RefundService;
use App\Services\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServiceCurrencyHardcodeTest extends TestCase
{
    use RefreshDatabase;

    private function createUsdSetup(): array
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);
        $building = Building::factory()
            ->forProperty($property)
            ->withCurrency(Currency::USD)
            ->create();

        $unit = Unit::factory()->forBuilding($building)->create();
        $lease = Lease::factory()->forUnit($unit)->active()->create();
        $tenant = User::findOrFail($lease->tenant_id);

        return compact('landlord', 'property', 'building', 'unit', 'lease', 'tenant');
    }

    private function createUsdPayment(array $setup, float $amount = 500.00): Payment
    {
        $invoice = Invoice::factory()
            ->forLease($setup['lease'])
            ->withCurrency(Currency::USD)
            ->sent()
            ->create();

        return Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $setup['lease']->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => $amount,
            'currency' => Currency::USD,
            'payment_method' => 'bank_transfer',
            'payment_date' => now(),
            'reference' => 'TEST-'.uniqid(),
        ]);
    }

    // --- InitialPaymentResult ---

    public function test_initial_payment_result_uses_dynamic_currency(): void
    {
        $setup = $this->createUsdSetup();
        $payment = $this->createUsdPayment($setup, 500.00);
        $verification = TenantPaymentVerification::create([
            'lease_id' => $setup['lease']->id,
            'landlord_id' => $setup['landlord']->id,
            'status' => TenantPaymentVerification::STATUS_PAYMENT_VERIFIED,
            'deposit_required' => 250,
            'first_rent_required' => 250,
            'other_charges' => 0,
            'total_required' => 500,
            'amount_paid' => 500,
            'verified_at' => now(),
        ]);

        $result = InitialPaymentResult::success($payment, $verification, 500.00, true);

        $this->assertStringContainsString('$', $result->successMessage());
        $this->assertStringNotContainsString('KES', $result->successMessage());
    }

    // --- ManualPaymentResult ---

    public function test_manual_payment_result_uses_dynamic_currency(): void
    {
        $setup = $this->createUsdSetup();
        $payment = $this->createUsdPayment($setup, 600.00);

        $result = new ManualPaymentResult($payment, $payment->invoice, 0);

        $this->assertStringContainsString('$', $result->successMessage());
        $this->assertStringNotContainsString('KES', $result->successMessage());
    }

    public function test_manual_payment_result_overpayment_uses_dynamic_currency(): void
    {
        $setup = $this->createUsdSetup();
        $payment = $this->createUsdPayment($setup, 600.00);

        $result = new ManualPaymentResult($payment, $payment->invoice, 100.00);

        $message = $result->successMessage();
        $this->assertStringNotContainsString('KES', $message);
        $this->assertStringContainsString('$', $message);
    }

    // --- PaymentProcessResult ---

    public function test_payment_process_result_uses_dynamic_currency(): void
    {
        $setup = $this->createUsdSetup();
        $invoice = Invoice::factory()
            ->forLease($setup['lease'])
            ->withCurrency(Currency::USD)
            ->sent()
            ->create();

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $setup['lease']->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 500.00,
            'currency' => Currency::USD,
            'payment_method' => 'paystack',
            'payment_date' => now(),
            'reference' => 'TEST-'.uniqid(),
        ]);

        $result = PaymentProcessResult::success($payment, $invoice);

        $this->assertStringContainsString('$', $result->getSuccessMessage());
        $this->assertStringNotContainsString('KES', $result->getSuccessMessage());
    }

    public function test_payment_process_result_overpayment_uses_dynamic_currency(): void
    {
        $setup = $this->createUsdSetup();
        $invoice = Invoice::factory()
            ->forLease($setup['lease'])
            ->withCurrency(Currency::USD)
            ->sent()
            ->create();

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $setup['lease']->id,
            'landlord_id' => $setup['landlord']->id,
            'amount' => 600.00,
            'currency' => Currency::USD,
            'payment_method' => 'paystack',
            'payment_date' => now(),
            'reference' => 'TEST-'.uniqid(),
        ]);

        $result = PaymentProcessResult::success($payment, $invoice, 100.00);

        $message = $result->getSuccessMessage();
        $this->assertStringNotContainsString('KES', $message);
        $this->assertStringContainsString('$', $message);
    }

    // --- PaymentQrCodeService ---

    public function test_receipt_qr_code_uses_dynamic_currency(): void
    {
        $setup = $this->createUsdSetup();
        $payment = $this->createUsdPayment($setup, 500.00);

        $service = new PaymentQrCodeService;
        $svg = $service->generateReceiptQrCode($payment);

        $this->assertNotEmpty($svg);

        $qrData = $this->invokeProtectedMethod($service, 'buildReceiptQrData', [$payment]);
        $this->assertStringContainsString('$', $qrData);
        $this->assertStringNotContainsString('KES', $qrData);
    }

    public function test_invoice_qr_code_uses_dynamic_currency(): void
    {
        $setup = $this->createUsdSetup();
        $invoice = Invoice::factory()
            ->forLease($setup['lease'])
            ->withCurrency(Currency::USD)
            ->sent()
            ->create();

        $service = new PaymentQrCodeService;
        $qrData = $this->invokeProtectedMethod($service, 'buildInvoiceQrData', [$invoice]);

        $this->assertStringContainsString('$', $qrData);
        $this->assertStringNotContainsString('KES', $qrData);
    }

    // --- RefundService ---

    public function test_refund_validation_error_uses_dynamic_currency(): void
    {
        $setup = $this->createUsdSetup();
        $payment = $this->createUsdPayment($setup, 500.00);

        $service = app(RefundService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/\$/');

        $this->invokeProtectedMethod($service, 'validateRefundEligibility', [$payment, 99999.00]);
    }

    public function test_refund_validation_error_does_not_contain_hardcoded_kes(): void
    {
        $setup = $this->createUsdSetup();
        $payment = $this->createUsdPayment($setup, 500.00);

        $service = app(RefundService::class);

        try {
            $this->invokeProtectedMethod($service, 'validateRefundEligibility', [$payment, 99999.00]);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringNotContainsString('KES', $e->getMessage());
        }
    }

    // --- TemplateService ---

    public function test_build_tenant_context_includes_currency_symbol(): void
    {
        $setup = $this->createUsdSetup();

        $service = new TemplateService;
        $context = $service->buildTenantContext($setup['tenant'], $setup['lease']);

        $this->assertArrayHasKey('currency_symbol', $context);
        $this->assertEquals('$', $context['currency_symbol']);
    }

    public function test_default_template_bodies_use_currency_placeholder(): void
    {
        $service = new TemplateService;
        $templates = $service->getDefaultTemplates();

        $templatesWithAmounts = $templates->filter(function ($template) {
            return str_contains($template['body'], 'rent_amount')
                || str_contains($template['body'], 'arrears_amount')
                || str_contains($template['body'], 'total_amount')
                || str_contains($template['body'], 'payment_amount')
                || str_contains($template['body'], 'old_rent')
                || str_contains($template['body'], 'new_rent');
        });

        $this->assertGreaterThan(0, $templatesWithAmounts->count());

        foreach ($templatesWithAmounts as $template) {
            $this->assertStringNotContainsString(
                'KES ',
                $template['body'],
                "Template '{$template['name']}' still contains hardcoded 'KES '"
            );
            $this->assertStringContainsString(
                '{{currency_symbol}}',
                $template['body'],
                "Template '{$template['name']}' missing {{currency_symbol}} placeholder"
            );
        }
    }

    // --- NotificationService ---

    public function test_rent_reminder_message_uses_dynamic_currency(): void
    {
        $setup = $this->createUsdSetup();
        PaymentConfiguration::create([
            'landlord_id' => $setup['landlord']->id,
            'default_currency' => Currency::USD,
        ]);
        Http::fake();

        $service = app(\App\Services\NotificationService::class);
        $service->sendRentReminder(
            $setup['tenant']->id,
            [
                'amount' => 500.00,
                'due_date' => 'March 1, 2026',
                'currency_symbol' => '$',
            ],
            $setup['landlord']->id
        );

        $notification = \App\Models\Notification::where('recipient_id', $setup['tenant']->id)
            ->where('type', 'rent_reminder')
            ->latest()
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringNotContainsString('KES', $notification->message);
        $this->assertStringContainsString('$', $notification->message);
    }

    public function test_arrears_notice_message_uses_dynamic_currency(): void
    {
        $setup = $this->createUsdSetup();
        PaymentConfiguration::create([
            'landlord_id' => $setup['landlord']->id,
            'default_currency' => Currency::USD,
        ]);
        NotificationPreference::create([
            'user_id' => $setup['tenant']->id,
            'landlord_id' => $setup['landlord']->id,
            'email_enabled' => true,
            'whatsapp_enabled' => false,
            'sms_enabled' => false,
            'push_enabled' => false,
            'quiet_hours_enabled' => false,
        ]);
        Http::fake();

        $service = app(\App\Services\NotificationService::class);
        $service->sendArrearsNotice(
            $setup['tenant']->id,
            [
                'arrears_amount' => 1500.00,
                'days_overdue' => 15,
                'currency_symbol' => '$',
            ],
            $setup['landlord']->id
        );

        $notification = \App\Models\Notification::where('recipient_id', $setup['tenant']->id)
            ->where('type', 'arrears_notice')
            ->latest()
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringNotContainsString('KES', $notification->message);
        $this->assertStringContainsString('$', $notification->message);
    }

    public function test_tenant_invitation_message_uses_dynamic_currency(): void
    {
        $setup = $this->createUsdSetup();
        PaymentConfiguration::create([
            'landlord_id' => $setup['landlord']->id,
            'default_currency' => Currency::USD,
        ]);
        Http::fake();

        $service = app(\App\Services\NotificationService::class);
        $service->sendTenantInvitation(
            $setup['tenant']->id,
            [
                'landlord_name' => $setup['landlord']->name,
                'unit_number' => $setup['unit']->unit_number,
                'property_name' => $setup['property']->name,
                'rent_amount' => 500.00,
                'deposit_amount' => 500.00,
                'currency_symbol' => '$',
            ],
            $setup['landlord']->id
        );

        $notification = \App\Models\Notification::where('recipient_id', $setup['tenant']->id)
            ->latest()
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringNotContainsString('KES', $notification->message);
        $this->assertStringContainsString('$', $notification->message);
    }

    public function test_notification_resolves_currency_from_payment_config_when_not_supplied(): void
    {
        $setup = $this->createUsdSetup();
        PaymentConfiguration::create([
            'landlord_id' => $setup['landlord']->id,
            'default_currency' => Currency::USD,
        ]);
        Http::fake();

        $service = app(\App\Services\NotificationService::class);
        $service->sendRentReminder(
            $setup['tenant']->id,
            [
                'amount' => 500.00,
                'due_date' => 'March 1, 2026',
            ],
            $setup['landlord']->id
        );

        $notification = \App\Models\Notification::where('recipient_id', $setup['tenant']->id)
            ->where('type', 'rent_reminder')
            ->latest()
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('$', $notification->message);
        $this->assertStringNotContainsString('KES', $notification->message);
    }

    // --- KES currency still renders correctly ---

    public function test_kes_payment_shows_ksh_symbol(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);
        $building = Building::factory()
            ->forProperty($property)
            ->withCurrency(Currency::KES)
            ->create();

        $unit = Unit::factory()->forBuilding($building)->create();
        $lease = Lease::factory()->forUnit($unit)->active()->create();

        $invoice = Invoice::factory()
            ->forLease($lease)
            ->withCurrency(Currency::KES)
            ->sent()
            ->create();

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'amount' => 25000.00,
            'currency' => Currency::KES,
            'payment_method' => 'mpesa',
            'payment_date' => now(),
            'reference' => 'TEST-'.uniqid(),
        ]);

        $result = new ManualPaymentResult($payment, $invoice, 0);

        $this->assertStringContainsString('KSh', $result->successMessage());
        $this->assertStringNotContainsString('KES ', $result->successMessage());
    }

    // --- Helper to invoke protected/private methods ---

    private function invokeProtectedMethod(object $object, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);

        return $reflection->invoke($object, ...$args);
    }
}
