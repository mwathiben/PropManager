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
        // Phase-31 ONB-TTFI-1 quirk: users.role + users.landlord_id are NOT
        // in User::$fillable (Phase-13 PRIV-1 guarded list). UserFactory works
        // around that by stripping them from the mass-assignment array and
        // re-saving them AFTER insertion — which means the `created` event
        // fires with role=null. The DB default for role is 'landlord' but the
        // in-memory model hasn't refreshed yet.
        //
        // We re-route the milestone recording into recordRoleMilestone() and
        // also call it from `updated` so the factory's post-insert role-set
        // pattern is covered. The recorder is idempotent.
        $user->refresh();
        if ($user->isScopeOwner()) {
            $this->createDefaultSchedules($user);
            // Phase-34 GROWTH-REFERRAL-1: assign a viral-loop code to
            // landlords on signup. Idempotent — service returns the
            // existing code if one is already set.
            app(\App\Services\Growth\ReferralAttributionService::class)
                ->generateCodeFor($user);
        }
        $this->recordRoleMilestone($user);
    }

    public function updated(User $user): void
    {
        // Fires after the factory's post-insert role/landlord_id save.
        if ($user->wasChanged(['role', 'landlord_id'])) {
            $this->recordRoleMilestone($user);
        }
    }

    private function recordRoleMilestone(User $user): void
    {
        $recorder = app(\App\Services\Onboarding\OnboardingMilestoneRecorder::class);

        if ($user->isScopeOwner()) {
            $recorder->record(
                landlordId: (int) $user->id,
                milestone: \App\Models\OnboardingMilestone::SIGNED_UP,
                metadata: ['user_id' => $user->id],
            );
        }

        if ($user->role === 'tenant' && $user->landlord_id !== null) {
            $recorder->record(
                landlordId: (int) $user->landlord_id,
                milestone: \App\Models\OnboardingMilestone::FIRST_TENANT,
                metadata: ['tenant_id' => $user->id],
            );
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
