<?php

namespace App\Observers;

use App\Models\NotificationSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        if ($user->role === 'landlord') {
            $this->createDefaultSchedules($user);
        }
    }

    /**
     * Create default notification schedules for a new landlord.
     */
    private function createDefaultSchedules(User $landlord): void
    {
        DB::transaction(function () use ($landlord) {
            NotificationSchedule::insert([
                [
                    'landlord_id' => $landlord->id,
                    'name' => 'Rent Reminder (7 days before)',
                    'type' => 'rent_reminder',
                    'trigger' => 'days_before_due',
                    'days_offset' => 7,
                    'send_time' => '09:00',
                    'channels' => json_encode(['email']),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'landlord_id' => $landlord->id,
                    'name' => 'Arrears Notice (3 days overdue)',
                    'type' => 'arrears_notice',
                    'trigger' => 'days_after_overdue',
                    'days_offset' => 3,
                    'send_time' => '09:00',
                    'channels' => json_encode(['email']),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'landlord_id' => $landlord->id,
                    'name' => 'Lease Expiry Reminder',
                    'type' => 'lease_expiry',
                    'trigger' => 'days_before_expiry',
                    'days_offset' => 30,
                    'send_time' => '09:00',
                    'channels' => json_encode(['email']),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        });
    }
}
