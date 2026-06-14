<?php

namespace Tests\Feature\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class InvoiceControllerTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected User $landlord;

    protected array $setupData;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];
    }

    public function test_landlord_can_view_invoice_index(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $this->createInvoiceForLease($lease, 'sent');

        $response = $this->actingAs($this->landlord)
            ->get(route('invoices.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invoices/Index')
            ->has('invoices.data', 1)
        );
    }

    public function test_invoice_index_orders_deterministically_when_created_at_ties(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);

        // Force an identical created_at across a batch — this mirrors
        // LoadTestSeeder inserting 27 invoices in the same instant. Without a
        // stable tiebreaker the paginator returns ties in undefined order, so
        // the RTL visual-snapshot rows reorder run-to-run and the pixel diff
        // blows past the 1% gate.
        $sharedTimestamp = now()->subMinute();
        $ids = [];
        foreach (range(1, 5) as $i) {
            $invoice = $this->createInvoiceForLease($lease, 'sent');
            $invoice->forceFill([
                'created_at' => $sharedTimestamp,
                'updated_at' => $sharedTimestamp,
            ])->save();
            $ids[] = $invoice->id;
        }

        $expectedOrder = collect($ids)->sortDesc()->values()->all();

        $response = $this->actingAs($this->landlord)->get(route('invoices.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where(
            'invoices.data',
            fn ($data) => collect($data)->pluck('id')->all() === $expectedOrder
        ));
    }

    public function test_landlord_can_filter_invoices_by_status(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);

        $this->createInvoiceForLease($lease, 'draft');
        $this->createInvoiceForLease($lease, 'sent');
        $this->createInvoiceForLease($lease, 'paid');

        $response = $this->actingAs($this->landlord)
            ->get(route('invoices.index', ['status' => 'sent']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('invoices.data', 1)
            ->where('invoices.data.0.status', 'sent')
        );
    }

    public function test_landlord_can_search_invoices(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);

        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $response = $this->actingAs($this->landlord)
            ->get(route('invoices.index', ['search' => $invoice->invoice_number]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('invoices.data', 1)
        );
    }

    public function test_landlord_can_view_single_invoice(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $response = $this->actingAs($this->landlord)
            ->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invoices/Show')
            ->where('invoice.id', $invoice->id)
        );
    }

    public function test_landlord_can_generate_invoices_for_billing_period(): void
    {
        $unit = $this->setupData['units']->first();
        $this->createTenantWithActiveLease($this->landlord, $unit);

        $response = $this->actingAs($this->landlord)
            ->post(route('invoices.generate'), [
                'month' => now()->month,
                'year' => now()->year,
            ]);

        $response->assertRedirect(route('invoices.index'));
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_invoice_generation_requires_valid_month_and_year(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('invoices.generate'), [
                'month' => 13,
                'year' => 2019,
            ]);

        $response->assertSessionHasErrors(['month', 'year']);
    }

    /**
     * SCOPE-S1 regression: when a landlord triggers POST /invoices/generate,
     * only THEIR active leases should produce invoices — not every landlord's.
     */
    public function test_invoice_generation_scopes_to_requesting_landlord(): void
    {
        $unit = $this->setupData['units']->first();
        $this->createTenantWithActiveLease($this->landlord, $unit);

        $otherSetup = $this->createLandlordWithFullSetup();
        $this->createTenantWithActiveLease($otherSetup['landlord'], $otherSetup['units']->first());

        $this->actingAs($this->landlord)
            ->post(route('invoices.generate'), [
                'month' => now()->month,
                'year' => now()->year,
            ])
            ->assertRedirect(route('invoices.index'));

        // Only the requesting landlord's lease produced an invoice.
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('invoices', [
            'landlord_id' => $this->landlord->id,
        ]);
        $this->assertDatabaseMissing('invoices', [
            'landlord_id' => $otherSetup['landlord']->id,
        ]);
    }

    public function test_landlord_can_update_invoice_status(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'draft');

        $response = $this->actingAs($this->landlord)
            ->put(route('invoices.updateStatus', $invoice), [
                'status' => 'sent',
            ]);

        $response->assertRedirect();
        $this->assertEquals(InvoiceStatus::Sent, $invoice->fresh()->status);
    }

    public function test_landlord_can_record_manual_payment(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $response = $this->actingAs($this->landlord)
            ->post(route('invoices.recordPayment', $invoice), [
                'amount' => 25000,
                'payment_method' => 'cash',
                'reference' => 'CASH-123',
            ]);

        $response->assertRedirect();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 25000,
            'payment_method' => 'cash',
        ]);
    }

    public function test_partial_payment_updates_invoice_status(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $this->actingAs($this->landlord)
            ->post(route('invoices.recordPayment', $invoice), [
                'amount' => 10000,
                'payment_method' => 'bank_transfer',
            ]);

        $this->assertEquals(InvoiceStatus::Partial, $invoice->fresh()->status);
        $this->assertEquals(10000, $invoice->fresh()->amount_paid);
    }

    public function test_overpayment_credits_to_wallet(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $overpaymentAmount = 30000;
        $expectedWallet = $overpaymentAmount - $invoice->total_due;

        $this->actingAs($this->landlord)
            ->post(route('invoices.recordPayment', $invoice), [
                'amount' => $overpaymentAmount,
                'payment_method' => 'mobile_money',
            ]);

        $lease->refresh();
        $this->assertEquals($expectedWallet, $lease->wallet_balance);
    }

    public function test_landlord_can_delete_draft_invoice(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'draft');

        $response = $this->actingAs($this->landlord)
            ->delete(route('invoices.destroy', $invoice));

        $response->assertRedirect(route('invoices.index'));
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_landlord_cannot_delete_paid_invoice(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $response = $this->actingAs($this->landlord)
            ->delete(route('invoices.destroy', $invoice));

        $response->assertForbidden();
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    public function test_invoice_status_validation(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'draft');

        $response = $this->actingAs($this->landlord)
            ->put(route('invoices.updateStatus', $invoice), [
                'status' => 'invalid_status',
            ]);

        $response->assertSessionHasErrors('status');
    }

    public function test_payment_validation_requires_amount(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $response = $this->actingAs($this->landlord)
            ->post(route('invoices.recordPayment', $invoice), [
                'payment_method' => 'cash',
            ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_landlord_can_preview_invoice_as_pdf(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'draft');

        $response = $this->actingAs($this->landlord)
            ->get(route('invoices.preview', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_landlord_can_download_invoice_as_pdf(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'draft');

        $response = $this->actingAs($this->landlord)
            ->get(route('invoices.download', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_landlord_can_void_draft_invoice(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'draft');

        $response = $this->actingAs($this->landlord)
            ->post(route('invoices.void', $invoice), [
                'reason' => 'Test void reason',
            ]);

        $response->assertRedirect();
        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Voided, $invoice->status);
        $this->assertNotNull($invoice->voided_at);
        $this->assertEquals('Test void reason', $invoice->void_reason);
    }

    public function test_landlord_cannot_void_paid_invoice(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $response = $this->actingAs($this->landlord)
            ->post(route('invoices.void', $invoice), [
                'reason' => 'Test void reason',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_landlord_can_reissue_voided_invoice(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'draft');

        $invoice->update([
            'status' => 'voided',
            'voided_at' => now(),
            'void_reason' => 'Test void',
        ]);

        $response = $this->actingAs($this->landlord)
            ->post(route('invoices.reissue', $invoice));

        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', [
            'lease_id' => $lease->id,
            'status' => 'draft',
        ]);
    }

    public function test_landlord_cannot_reissue_non_voided_invoice(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $response = $this->actingAs($this->landlord)
            ->post(route('invoices.reissue', $invoice));

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
    }

    public function test_tenant_cannot_update_invoice_status(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease, 'tenant' => $tenant] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'draft');

        $response = $this->actingAs($tenant)
            ->put(route('invoices.updateStatus', $invoice), [
                'status' => 'sent',
            ]);

        $response->assertForbidden();
    }
}
