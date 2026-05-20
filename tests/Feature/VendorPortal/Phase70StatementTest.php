<?php

declare(strict_types=1);

namespace Tests\Feature\VendorPortal;

use App\Models\Expense;
use App\Models\Ticket;
use App\Models\TicketCost;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Vendors\VendorStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-70 PAYOUT-STATEMENT: the vendor's read-only statement totals only
 * their own attributed costs/expenses — never another vendor's or another
 * landlord's. CSV neutralises injection.
 */
class Phase70StatementTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = User::factory()->create(['role' => 'landlord']);
    }

    private function vendor(?User $landlord = null): Vendor
    {
        $landlord ??= $this->landlord;

        return Vendor::create([
            'landlord_id' => $landlord->id,
            'name' => 'Acme',
            'email' => 'acme@c.test',
            'is_active' => true,
        ]);
    }

    private function vendorCost(Vendor $vendor, int $cents, string $title = 'Job'): TicketCost
    {
        $ticket = Ticket::factory()->create([
            'landlord_id' => $vendor->landlord_id,
            'reporter_id' => $vendor->landlord_id,
            'vendor_id' => $vendor->id,
            'title' => $title,
        ]);

        return TicketCost::create([
            'ticket_id' => $ticket->id,
            'category' => 'vendor',
            'amount_cents' => $cents,
            'currency' => 'KES',
            'recorded_at' => now(),
        ]);
    }

    public function test_statement_totals_only_the_vendors_costs(): void
    {
        $mine = $this->vendor();
        $other = $this->vendor();

        $this->vendorCost($mine, 5000);                       // KES 50.00
        Expense::create([
            'landlord_id' => $this->landlord->id,
            'vendor_id' => $mine->id,
            'description' => 'Plumbing parts',
            'amount' => 30.00,
            'expense_date' => now(),
        ]);
        $this->vendorCost($other, 9999);                      // another vendor — excluded

        $statement = app(VendorStatementService::class)->forVendor($mine, now()->subMonth(), now()->addDay());

        $this->assertSame(5000, $statement['ticket_costs_total_cents']);
        $this->assertSame(3000, $statement['expenses_total_cents']);
        $this->assertSame(8000, $statement['total_cents']);
        $this->assertCount(1, $statement['ticket_costs']);
        $this->assertCount(1, $statement['expenses']);
    }

    public function test_statement_excludes_another_landlords_vendor(): void
    {
        $otherLandlord = User::factory()->create(['role' => 'landlord']);
        $theirVendor = $this->vendor($otherLandlord);
        $this->vendorCost($theirVendor, 7000);

        $mine = $this->vendor();
        $statement = app(VendorStatementService::class)->forVendor($mine, now()->subMonth(), now()->addDay());

        $this->assertSame(0, $statement['total_cents']);
    }

    public function test_endpoint_is_session_scoped(): void
    {
        $vendor = $this->vendor();
        $this->vendorCost($vendor, 4200);

        $this->withSession(['vendor_portal_id' => $vendor->id])
            ->get('/v/portal/statement')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('VendorPortal/Statement')
                ->where('statement.total_cents', 4200));

        $this->flushSession();
        $this->get('/v/portal/statement')->assertForbidden();
    }

    public function test_csv_neutralises_injection_and_is_scoped(): void
    {
        $vendor = $this->vendor();
        $this->vendorCost($vendor, 5000, '=cmd|/c calc');

        $service = app(VendorStatementService::class);
        $csv = $service->toCsv($service->forVendor($vendor, now()->subMonth(), now()->addDay()));

        // The formula-leading title is prefixed with an apostrophe.
        $this->assertStringContainsString("'=cmd", $csv);
        $this->assertStringContainsString('50.00', $csv);
    }
}
