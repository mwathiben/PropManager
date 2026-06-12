<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\NotificationSchedule;
use App\Models\User;
use App\Services\SchedulerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * M2 decomposition safety net: characterizes the notification-schedule CRUD
 * routes BEFORE the schedule actions are split out of the 1185-line
 * NotificationsController into a dedicated NotificationScheduleController.
 * These routes had no coverage; the split is a verbatim move + route
 * re-point (names unchanged), so these lock the end-to-end behaviour.
 */
class NotificationScheduleControllerTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    public function test_landlord_can_view_schedules_page(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        $this->actingAs($landlord)
            ->get(route('notifications.schedules'))
            ->assertOk();
    }

    public function test_landlord_can_store_a_schedule(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        $this->actingAs($landlord)
            ->post(route('notifications.schedules.store'), [
                'name' => 'Rent Due Reminder',
                'type' => 'rent_reminder',
                'trigger' => 'days_before_due',
                'days_offset' => 3,
                'send_time' => '09:00',
                'channels' => ['email'],
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notification_schedules', [
            'landlord_id' => $landlord->id,
            'name' => 'Rent Due Reminder',
            'type' => 'rent_reminder',
        ]);
    }

    public function test_landlord_can_run_a_schedule_now(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();
        // Pin the type/trigger so runNow() exercises a deterministic path
        // (the factory randomizes type, which would otherwise flake).
        $schedule = NotificationSchedule::factory()->create([
            'landlord_id' => $landlord->id,
            'type' => 'rent_reminder',
            'trigger' => 'days_before_due',
            'days_offset' => 3,
        ]);

        $this->actingAs($landlord)
            ->post(route('notifications.schedules.run', $schedule))
            ->assertRedirect();
    }

    public function test_run_schedule_now_does_not_500_when_processing_throws(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();
        $schedule = NotificationSchedule::factory()->create([
            'landlord_id' => $landlord->id,
            'type' => 'rent_reminder',
            'trigger' => 'days_before_due',
            'days_offset' => 3,
        ]);

        $service = Mockery::mock(SchedulerService::class)->makePartial();
        $service->shouldReceive('processSchedule')->andThrow(new \RuntimeException('malformed lease row'));
        $this->instance(SchedulerService::class, $service);

        $this->actingAs($landlord)
            ->post(route('notifications.schedules.run', $schedule))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_landlord_can_toggle_and_delete_own_schedule(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();
        $schedule = NotificationSchedule::factory()->create(['landlord_id' => $landlord->id, 'is_active' => true]);

        $this->actingAs($landlord)
            ->post(route('notifications.schedules.toggle', $schedule))
            ->assertRedirect();
        $this->assertFalse($schedule->fresh()->is_active);

        $this->actingAs($landlord)
            ->delete(route('notifications.schedules.destroy', $schedule))
            ->assertRedirect();
        $this->assertDatabaseMissing('notification_schedules', ['id' => $schedule->id]);
    }

    public function test_cannot_modify_another_landlords_schedule(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();
        $other = User::factory()->create(['role' => 'landlord']);
        $schedule = NotificationSchedule::factory()->create(['landlord_id' => $other->id]);

        $response = $this->actingAs($landlord)
            ->delete(route('notifications.schedules.destroy', $schedule));

        // Denied either by the authorizeSchedule guard (403) or tenant scope (404).
        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseHas('notification_schedules', ['id' => $schedule->id]);
    }
}
