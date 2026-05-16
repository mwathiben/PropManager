<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Services\Reconciliation\TransactionAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-41 GATEWAY-RECONCILE-DEEP-1/2/3: Stripe charges.list +
 * compareLedgers hoisted into shared helper + TransactionAdapter.
 */
class Phase41ReconcileDeepTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_adapter_normalises_paystack_row(): void
    {
        $row = [
            'reference' => 'PSK_ref_123',
            'amount' => 150000,
            'currency' => 'KES',
            'status' => 'success',
        ];

        $normalised = TransactionAdapter::fromPaystack($row);

        $this->assertSame('PSK_ref_123', $normalised['reference']);
        $this->assertSame(150000, $normalised['amount_minor']);
        $this->assertSame('KES', $normalised['currency']);
        $this->assertSame('success', $normalised['status']);
    }

    public function test_transaction_adapter_handles_paystack_missing_fields(): void
    {
        $normalised = TransactionAdapter::fromPaystack([]);

        $this->assertSame('', $normalised['reference']);
        $this->assertSame(0, $normalised['amount_minor']);
        $this->assertSame('KES', $normalised['currency']);
        $this->assertSame('unknown', $normalised['status']);
    }

    public function test_transaction_adapter_normalises_stripe_charge(): void
    {
        $charge = \Stripe\Charge::constructFrom([
            'id' => 'ch_test_abc',
            'amount' => 12500,
            'currency' => 'usd',
            'status' => 'succeeded',
        ]);

        $normalised = TransactionAdapter::fromStripe($charge);

        $this->assertSame('ch_test_abc', $normalised['reference']);
        $this->assertSame(12500, $normalised['amount_minor']);
        $this->assertSame('USD', $normalised['currency']);
        $this->assertSame('succeeded', $normalised['status']);
    }

    public function test_stripe_service_list_charges_method_exists(): void
    {
        $this->assertTrue(method_exists(\App\Services\StripeService::class, 'listCharges'));
        $reflection = new \ReflectionMethod(\App\Services\StripeService::class, 'listCharges');
        $params = array_map(fn ($p) => $p->getName(), $reflection->getParameters());
        $this->assertSame(['from', 'to'], $params);
    }

    public function test_compare_ledgers_helper_exists_on_reconciliation_service(): void
    {
        $reflection = new \ReflectionClass(\App\Services\Reconciliation\PaymentReconciliationService::class);
        $this->assertTrue($reflection->hasMethod('compareLedgers'));
    }

    public function test_old_compare_method_was_removed(): void
    {
        $reflection = new \ReflectionClass(\App\Services\Reconciliation\PaymentReconciliationService::class);
        $this->assertFalse(
            $reflection->hasMethod('compare'),
            'Old per-Paystack compare() method should have been removed in favour of compareLedgers.',
        );
    }
}
