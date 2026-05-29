<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Enums\Currency;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\Tax\StripeTaxService;
use App\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-42 TAX-1/2/3: invoice_items tax columns + payment_configurations
 * KRA fields + StripeTaxService VAT computation + Stripe Tax opt-in.
 */
class Phase42TaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_items_has_tax_columns(): void
    {
        $cols = Schema::getColumnListing('invoice_items');
        $this->assertContains('tax_amount_cents', $cols);
        $this->assertContains('tax_rate_bps', $cols);
    }

    public function test_payment_configurations_has_kra_fields(): void
    {
        $cols = Schema::getColumnListing('payment_configurations');
        foreach (['kra_pin', 'vat_rate_bps_override', 'stripe_tax_enabled'] as $c) {
            $this->assertContains($c, $cols);
        }
    }

    public function test_stripe_tax_service_computes_kenya_vat_at_16_percent(): void
    {
        $service = new StripeTaxService;
        $subtotal = Money::fromString('1000.00');

        $vat = $service->computeKenyanVat($subtotal);

        $this->assertSame('160.00', $vat->toDecimalString());
        $this->assertSame(16000, $vat->toMinorUnits());
    }

    public function test_stripe_tax_service_honours_vat_rate_override(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $config = PaymentConfiguration::factory()->forLandlord($landlord)->create([
            'vat_rate_bps_override' => 800, // 8% reduced rate
        ]);

        $service = new StripeTaxService;
        $subtotal = Money::fromString('1000.00');

        $vat = $service->computeKenyanVat($subtotal, $config);

        $this->assertSame('80.00', $vat->toDecimalString());
    }

    public function test_vat_line_item_returns_null_for_non_kes(): void
    {
        $service = new StripeTaxService;
        $this->assertNull($service->vatLineItem(Money::fromString('100.00'), Currency::USD));
    }

    public function test_vat_line_item_for_kes_returns_canonical_shape(): void
    {
        $service = new StripeTaxService;
        $line = $service->vatLineItem(Money::fromString('500.00'), Currency::KES);

        $this->assertIsArray($line);
        $this->assertArrayHasKey('description', $line);
        $this->assertSame(8000, $line['amount_cents']); // 500 * 16% = 80.00 = 8000 minor units
        $this->assertSame(1600, $line['rate_bps']);
    }

    public function test_payment_configuration_is_vat_registered_only_with_valid_kra_pin(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $config = PaymentConfiguration::factory()->forLandlord($landlord)->create();

        $this->assertFalse($config->isVatRegistered());

        $config->kra_pin = 'A001234567Z';
        $this->assertTrue($config->isVatRegistered());

        $config->kra_pin = 'invalid';
        $this->assertFalse($config->isVatRegistered());

        $config->kra_pin = 'X001234567Z'; // X is not a valid prefix
        $this->assertFalse($config->isVatRegistered());
    }

    public function test_payment_configuration_stripe_tax_enabled_defaults_false(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $config = PaymentConfiguration::factory()->forLandlord($landlord)->create();

        $this->assertFalse($config->hasStripeTaxEnabled());
    }

    public function test_invoice_item_tax_amount_returns_zero_when_untaxed(): void
    {
        $item = new \App\Models\InvoiceItem;
        $item->total = '1000.00';
        $item->tax_amount_cents = null;

        $this->assertSame('0.00', $item->taxAmount()->toDecimalString());
        $this->assertFalse($item->isTaxed());
    }

    public function test_invoice_item_tax_amount_returns_money_when_stamped(): void
    {
        $item = new \App\Models\InvoiceItem;
        $item->total = '1160.00';
        $item->tax_amount_cents = 16000;
        $item->tax_rate_bps = 1600;

        $this->assertSame('160.00', $item->taxAmount()->toDecimalString());
        $this->assertTrue($item->isTaxed());
        $this->assertSame('1000.00', $item->subtotalExclusiveOfTax()->toDecimalString());
    }

    public function test_kra_pin_field_round_trips_when_assigned(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $config = PaymentConfiguration::factory()->forLandlord($landlord)->create([
            'kra_pin' => 'A001234567Z',
        ]);

        $reloaded = PaymentConfiguration::find($config->id);
        $this->assertSame('A001234567Z', $reloaded->kra_pin);
    }

    public function test_admin_tax_config_update_persists_kra_pin(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $landlord = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($admin)->post("/admin/gateways/{$landlord->id}/tax-config", [
            'kra_pin' => 'A001234567Z',
            'vat_rate_bps_override' => null,
            'stripe_tax_enabled' => true,
        ]);

        $response->assertRedirect();
        $config = PaymentConfiguration::where('landlord_id', $landlord->id)->first();
        $this->assertNotNull($config);
        $this->assertTrue($config->isVatRegistered());
        $this->assertTrue($config->hasStripeTaxEnabled());
    }

    public function test_admin_tax_config_update_rejects_invalid_kra_pin(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $landlord = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($admin)
            ->from('/admin/gateways')
            ->post("/admin/gateways/{$landlord->id}/tax-config", [
                'kra_pin' => 'not-a-pin',
            ]);

        $response->assertSessionHasErrors('kra_pin');
    }
}
