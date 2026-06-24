<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Models\NotificationSchedule;
use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * createDefaultSchedules() seeds a scope owner's 3 starter notification
 * schedules on signup. It used a raw insert() with no existence guard, so any
 * second call (a future signup/promotion path) silently duplicated all of them.
 * It must be idempotent — AND it must NOT collapse the legitimate feature where
 * a landlord runs several schedules of the same type at different offsets
 * (NotificationScheduleController::storeSchedule has no per-type unique guard),
 * which is exactly why a DB unique index on (landlord_id, type) would be wrong.
 */
class DefaultSchedulesIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function seedDefaults(User $scopeOwner): void
    {
        $method = new \ReflectionMethod(UserObserver::class, 'createDefaultSchedules');
        $method->invoke(new UserObserver, $scopeOwner);
    }

    public function test_seeding_default_schedules_twice_does_not_duplicate(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        NotificationSchedule::where('landlord_id', $landlord->id)->delete();

        $this->seedDefaults($landlord);
        $this->seedDefaults($landlord);

        $this->assertSame(3, NotificationSchedule::where('landlord_id', $landlord->id)->count());
    }

    public function test_re_seeding_preserves_a_user_created_same_type_schedule(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        NotificationSchedule::where('landlord_id', $landlord->id)->delete();

        $this->seedDefaults($landlord);

        // A landlord may run a second rent reminder at a different offset — a
        // valid feature the idempotency must not break.
        NotificationSchedule::create([
            'landlord_id' => $landlord->id,
            'name' => 'Rent Reminder (3 days before)',
            'type' => 'rent_reminder',
            'trigger' => 'days_before_due',
            'days_offset' => 3,
            'send_time' => '09:00',
            'channels' => ['email'],
            'is_active' => true,
        ]);

        $this->seedDefaults($landlord);

        $this->assertSame(4, NotificationSchedule::where('landlord_id', $landlord->id)->count());
        $this->assertSame(2, NotificationSchedule::where('landlord_id', $landlord->id)->where('type', 'rent_reminder')->count());
    }
}
