<?php

namespace Tests\Browser;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\Payment\PaystackCallbackHandler;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class PaystackMultiCurrencyE2ETest extends DuskTestCase
{
    use DatabaseMigrations;

    protected User $landlord;

    protected User $tenant;

    protected Lease $lease;

    protected PaymentConfiguration $paymentConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $setup = $this->createLandlordWithProperty();
        $this->landlord = $setup['landlord'];
        $unit = $setup['units']->first();

        $this->tenant = User::factory()->create([
            'role' => 'tenant',
            'email' => 'tenant-currency@test.com',
            'password' => bcrypt('password'),
            'landlord_id' => $this->landlord->id,
        ]);

        $this->lease = Lease::create([
            'unit_id' => $unit->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addYear(),
            'rent_amount' => 100,
            'deposit_amount' => 200,
            'is_active' => true,
        ]);

        $this->paymentConfig = PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'accepted_payment_methods' => ['cash', 'bank_transfer'],
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_multi_currency',
            'paystack_secret_key' => 'sk_test_multi_currency',
        ]);

        Mail::fake();
    }

    public function test_tenant_sees_usd_symbol_on_pay_page(): void
    {
        $invoice = $this->createInvoiceWithCurrency('USD', 100);

        $this->browse(function (Browser $browser) use ($invoice) {
            $browser->loginAs($this->tenant)
                ->visit("/tenant/finances/pay/{$invoice->id}")
                ->waitForText('Amount Due')
                ->assertSee('$')
                ->assertDontSee('KSh');
        });
    }

    public function test_tenant_sees_kes_symbol_on_pay_page(): void
    {
        $invoice = $this->createInvoiceWithCurrency('KES', 25000);

        $this->browse(function (Browser $browser) use ($invoice) {
            $browser->loginAs($this->tenant)
                ->visit("/tenant/finances/pay/{$invoice->id}")
                ->waitForText('Amount Due')
                ->assertSee('KSh')
                ->assertSee('25,000');
        });
    }

    public function test_tenant_sees_eur_symbol_on_pay_page(): void
    {
        $invoice = $this->createInvoiceWithCurrency('EUR', 500);

        $this->browse(function (Browser $browser) use ($invoice) {
            $browser->loginAs($this->tenant)
                ->visit("/tenant/finances/pay/{$invoice->id}")
                ->waitForText('Amount Due')
                ->assertSee('€');
        });
    }

    public function test_tenant_sees_gbp_symbol_on_pay_page(): void
    {
        $invoice = $this->createInvoiceWithCurrency('GBP', 750);

        $this->browse(function (Browser $browser) use ($invoice) {
            $browser->loginAs($this->tenant)
                ->visit("/tenant/finances/pay/{$invoice->id}")
                ->waitForText('Amount Due')
                ->assertSee('£');
        });
    }

    public function test_pay_button_displays_usd_amount(): void
    {
        $invoice = $this->createInvoiceWithCurrency('USD', 100);

        $this->browse(function (Browser $browser) use ($invoice) {
            $browser->loginAs($this->tenant)
                ->visit("/tenant/finances/pay/{$invoice->id}")
                ->waitForText('Select Payment Method')
                ->click('button[class*="border-gray-200"]')
                ->pause(500)
                ->assertSee('$')
                ->assertSee('100');
        });
    }

    public function test_paystack_callback_creates_payment_with_usd_currency(): void
    {
        $invoice = $this->createInvoiceWithCurrency('USD', 100);
        $reference = 'PSK_USD_'.uniqid();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => 10000,
                    'currency' => 'USD',
                    'channel' => 'card',
                    'metadata' => [
                        'invoice_id' => $invoice->id,
                        'landlord_id' => $this->landlord->id,
                    ],
                ],
            ]),
        ]);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processCallback($reference, $this->landlord->id);

        $this->assertTrue($result->isSuccess());
        $this->assertDatabaseHas('payments', [
            'paystack_reference' => $reference,
            'invoice_id' => $invoice->id,
            'currency' => 'USD',
        ]);

        $payment = Payment::where('paystack_reference', $reference)->first();
        $this->assertEquals(100.00, (float) $payment->amount);

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_paystack_callback_creates_payment_with_kes_currency_regression(): void
    {
        $invoice = $this->createInvoiceWithCurrency('KES', 25000);
        $reference = 'PSK_KES_'.uniqid();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => 2500000,
                    'currency' => 'KES',
                    'channel' => 'card',
                    'metadata' => [
                        'invoice_id' => $invoice->id,
                        'landlord_id' => $this->landlord->id,
                    ],
                ],
            ]),
        ]);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processCallback($reference, $this->landlord->id);

        $this->assertTrue($result->isSuccess());
        $this->assertDatabaseHas('payments', [
            'paystack_reference' => $reference,
            'invoice_id' => $invoice->id,
            'currency' => 'KES',
        ]);

        $payment = Payment::where('paystack_reference', $reference)->first();
        $this->assertEquals(25000.00, (float) $payment->amount);

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_paystack_callback_defaults_to_kes_when_no_currency_in_response(): void
    {
        $invoice = $this->createInvoiceWithCurrency('KES', 5000);
        $reference = 'PSK_NOCRCY_'.uniqid();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => 500000,
                    'channel' => 'card',
                    'metadata' => [
                        'invoice_id' => $invoice->id,
                        'landlord_id' => $this->landlord->id,
                    ],
                ],
            ]),
        ]);

        $handler = app(PaystackCallbackHandler::class);
        $result = $handler->processCallback($reference, $this->landlord->id);

        $this->assertTrue($result->isSuccess());

        $payment = Payment::where('paystack_reference', $reference)->first();
        $this->assertEquals(\App\Enums\Currency::KES, $payment->currency);
        $this->assertEquals(5000.00, (float) $payment->amount);
    }

    protected function createInvoiceWithCurrency(string $currency, float $amount): Invoice
    {
        return Invoice::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'invoice_number' => 'INV-'.now()->format('Ymd').'-'.str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'total_due' => $amount,
            'rent_due' => $amount,
            'rent_amount' => $amount,
            'water_due' => 0,
            'arrears' => 0,
            'amount_paid' => 0,
            'status' => 'sent',
            'currency' => $currency,
            'due_date' => now()->addDays(7),
            'billing_period_start' => now()->startOfMonth(),
            'billing_period_end' => now()->endOfMonth(),
        ]);
    }
}
