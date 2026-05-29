<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Exceptions\AccountingPeriodLockedException;
use App\Models\AccountingPeriod;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class Phase30PeriodLockTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private $lease;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['lease' => $this->lease] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );
    }

    public function test_is_date_locked_true_for_closed_window_match(): void
    {
        AccountingPeriod::create([
            'landlord_id' => $this->landlord->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'status' => AccountingPeriod::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $this->assertTrue(AccountingPeriod::isDateLocked($this->landlord->id, '2026-04-15'));
        $this->assertFalse(AccountingPeriod::isDateLocked($this->landlord->id, '2026-05-15'));
    }

    public function test_is_date_locked_false_for_open_period(): void
    {
        AccountingPeriod::create([
            'landlord_id' => $this->landlord->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'status' => AccountingPeriod::STATUS_OPEN,
        ]);

        $this->assertFalse(AccountingPeriod::isDateLocked($this->landlord->id, '2026-04-15'));
    }

    public function test_expense_save_inside_closed_period_throws(): void
    {
        AccountingPeriod::create([
            'landlord_id' => $this->landlord->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'status' => AccountingPeriod::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $this->expectException(AccountingPeriodLockedException::class);
        Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'Repairs',
            'amount' => 100.0,
            'expense_date' => '2026-04-15',
        ]);
    }

    public function test_expense_save_outside_closed_period_succeeds(): void
    {
        AccountingPeriod::create([
            'landlord_id' => $this->landlord->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'status' => AccountingPeriod::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $exp = Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'Repairs',
            'amount' => 100.0,
            'expense_date' => '2026-05-15',
        ]);
        $this->assertNotNull($exp->id);
    }

    public function test_expense_update_inside_closed_period_throws(): void
    {
        $exp = Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'Repairs',
            'amount' => 100.0,
            'expense_date' => '2026-04-15',
        ]);

        AccountingPeriod::create([
            'landlord_id' => $this->landlord->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'status' => AccountingPeriod::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $this->expectException(AccountingPeriodLockedException::class);
        $exp->update(['description' => 'Repairs (revised)']);
    }

    public function test_expense_delete_inside_closed_period_throws(): void
    {
        $exp = Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'Repairs',
            'amount' => 100.0,
            'expense_date' => '2026-04-15',
        ]);

        AccountingPeriod::create([
            'landlord_id' => $this->landlord->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'status' => AccountingPeriod::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $this->expectException(AccountingPeriodLockedException::class);
        $exp->delete();
    }

    public function test_close_month_command_closes_previous_month_idempotently(): void
    {
        $previous = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

        $this->artisan('finance:close-month')->assertSuccessful();
        $this->artisan('finance:close-month')->assertSuccessful();

        $count = AccountingPeriod::query()
            ->where('landlord_id', $this->landlord->id)
            ->where('period_start', $previous->toDateString())
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_controller_close_endpoint_creates_period(): void
    {
        $this->actingAs($this->landlord)
            ->post(route('finances.periods.close'), [
                'month' => '2026-03',
                'close_notes' => 'manual close',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('accounting_periods', [
            'landlord_id' => $this->landlord->id,
            'period_start' => '2026-03-01',
            'status' => AccountingPeriod::STATUS_CLOSED,
        ]);
    }

    public function test_controller_reopen_endpoint_reopens_period(): void
    {
        $period = AccountingPeriod::create([
            'landlord_id' => $this->landlord->id,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'status' => AccountingPeriod::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $this->actingAs($this->landlord)
            ->post(route('finances.periods.reopen', ['period' => $period->id]))
            ->assertRedirect();

        $period->refresh();
        $this->assertSame(AccountingPeriod::STATUS_OPEN, $period->status);
        $this->assertNull($period->closed_at);
    }

    public function test_other_landlord_cannot_reopen_period(): void
    {
        $period = AccountingPeriod::create([
            'landlord_id' => $this->landlord->id,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'status' => AccountingPeriod::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
        $other = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($other)
            ->post(route('finances.periods.reopen', ['period' => $period->id]))
            ->assertForbidden();
    }
}
