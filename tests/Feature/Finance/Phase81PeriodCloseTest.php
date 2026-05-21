<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\AccountingPeriod;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-81 PERIOD-CLOSE: readiness guard + reopen audit.
 */
class Phase81PeriodCloseTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Lease $lease;

    private string $month;

    private CarbonImmutable $monthStart;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->lease = Model::withoutEvents(
            fn () => $this->createTenantWithActiveLease($this->landlord, $setup['units']->get(0))['lease'],
        );
        $prev = CarbonImmutable::now()->subMonthNoOverflow();
        $this->month = $prev->format('Y-m');
        $this->monthStart = $prev->startOfMonth();
    }

    private function draftInvoiceInMonth(): void
    {
        Model::withoutEvents(fn () => Invoice::factory()->draft()->create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'created_at' => $this->monthStart->addDays(5),
        ]));
    }

    public function test_close_is_blocked_by_a_draft_invoice_in_the_period(): void
    {
        $this->draftInvoiceInMonth();

        $this->actingAs($this->landlord)
            ->post(route('finances.periods.close'), ['month' => $this->month])
            ->assertSessionHasErrors('period');

        $this->assertSame(0, AccountingPeriod::where('landlord_id', $this->landlord->id)->where('status', 'closed')->count());
    }

    public function test_close_succeeds_when_period_is_clean(): void
    {
        $this->actingAs($this->landlord)
            ->post(route('finances.periods.close'), ['month' => $this->month])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, AccountingPeriod::where('landlord_id', $this->landlord->id)->where('status', 'closed')->count());
    }

    public function test_force_overrides_blockers(): void
    {
        $this->draftInvoiceInMonth();

        $this->actingAs($this->landlord)
            ->post(route('finances.periods.close'), ['month' => $this->month, 'force' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, AccountingPeriod::where('landlord_id', $this->landlord->id)->where('status', 'closed')->count());
    }

    public function test_reopen_records_audit(): void
    {
        $period = AccountingPeriod::create([
            'landlord_id' => $this->landlord->id,
            'period_start' => $this->monthStart->toDateString(),
            'period_end' => $this->monthStart->endOfMonth()->toDateString(),
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by_user_id' => $this->landlord->id,
        ]);

        $this->actingAs($this->landlord)
            ->post(route('finances.periods.reopen', $period->id), ['reopen_reason' => 'correction'])
            ->assertRedirect();

        $fresh = $period->fresh();
        $this->assertSame('open', $fresh->status);
        $this->assertSame($this->landlord->id, $fresh->reopened_by_user_id);
        $this->assertSame('correction', $fresh->reopen_reason);
    }
}
