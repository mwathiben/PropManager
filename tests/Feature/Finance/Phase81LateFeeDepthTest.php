<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use App\Models\LateFee;
use App\Models\LateFeePolicy;
use App\Models\Lease;
use App\Models\User;
use App\Services\LateFeeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-81 LATE-FEE-DEPTH: on-demand manual apply + tenant-facing projection.
 */
class Phase81LateFeeDepthTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->lease = Model::withoutEvents(
            fn () => $this->createTenantWithActiveLease($this->landlord, $setup['units']->get(0))['lease'],
        );
    }

    private function policy(int $grace): LateFeePolicy
    {
        return Model::withoutEvents(fn () => LateFeePolicy::factory()->create([
            'landlord_id' => $this->landlord->id,
            'property_id' => null,
            'building_id' => null,
            'grace_period_days' => $grace,
            'fee_type' => 'flat_amount',
            'fee_amount' => 500,
            'fee_percentage' => null,
            'is_compounding' => false,
            'max_fee_cap' => null,
            'is_active' => true,
        ]));
    }

    private function overdueInvoice(int $daysOverdue): Invoice
    {
        return Model::withoutEvents(fn () => Invoice::factory()->create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'status' => 'overdue',
            'rent_due' => 10000,
            'total_due' => 10000,
            'amount_paid' => 0,
            'due_date' => now()->subDays($daysOverdue),
        ]));
    }

    public function test_manual_apply_adds_late_fee_to_eligible_invoice(): void
    {
        $this->policy(0);
        $invoice = $this->overdueInvoice(10);

        $this->actingAs($this->landlord)
            ->post(route('finances.late-fees.apply-now'))
            ->assertRedirect();

        $this->assertSame(1, LateFee::where('invoice_id', $invoice->id)->count());
    }

    public function test_manual_apply_skips_invoice_within_grace(): void
    {
        $this->policy(30);
        $invoice = $this->overdueInvoice(5);

        $this->actingAs($this->landlord)
            ->post(route('finances.late-fees.apply-now'))
            ->assertRedirect();

        $this->assertSame(0, LateFee::where('invoice_id', $invoice->id)->count());
    }

    public function test_projection_returns_fee_for_overdue_invoice(): void
    {
        $this->policy(0);
        $invoice = $this->overdueInvoice(10);

        $preview = app(LateFeeService::class)->previewLateFee($invoice);

        $this->assertNotNull($preview);
        $this->assertEqualsWithDelta(500, $preview['projected_fee'], 0.01);
    }
}
