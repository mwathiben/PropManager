<?php

namespace App\Console\Commands;

use App\Models\TenantInvitation;
use App\Models\TenantPaymentVerification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupExpiredInvitations extends Command
{
    protected $signature = 'tenant-invitations:cleanup';

    protected $description = 'Clean up expired tenant invitations and archive incomplete users';

    public function handle(): int
    {
        $this->info('Cleaning up expired tenant invitations...');

        $thirtyDaysAgo = now()->subDays(30);
        $counters = ['expired' => 0, 'archived' => 0, 'errors' => 0];
        $processedUserIds = [];

        $this->expireStalePendingInvitations($thirtyDaysAgo, $counters);
        $this->archiveIncompleteUsers($thirtyDaysAgo, $counters, $processedUserIds);

        $this->info("Expired {$counters['expired']} pending invitation(s).");
        $this->info("Archived {$counters['archived']} incomplete user(s).");

        if ($counters['errors'] > 0) {
            $this->warn("Encountered {$counters['errors']} error(s). Check logs for details.");
        }

        return Command::SUCCESS;
    }

    private function expireStalePendingInvitations(\Carbon\Carbon $thirtyDaysAgo, array &$counters): void
    {
        TenantInvitation::withoutGlobalScope('landlord')
            ->where('status', 'pending')
            ->where('expires_at', '<', $thirtyDaysAgo)
            ->chunkById(500, function ($invitations) use (&$counters) {
                foreach ($invitations as $invitation) {
                    $this->expireSinglePendingInvitation($invitation, $counters);
                }
            });
    }

    private function expireSinglePendingInvitation(TenantInvitation $invitation, array &$counters): void
    {
        try {
            $invitation->markAsExpired();
            $counters['expired']++;

            Log::info('Tenant invitation marked as expired', [
                'invitation_id' => $invitation->id,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            $counters['errors']++;
            Log::error('Failed to expire invitation', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function archiveIncompleteUsers(\Carbon\Carbon $thirtyDaysAgo, array &$counters, array &$processedUserIds): void
    {
        TenantInvitation::withoutGlobalScope('landlord')
            ->where('status', 'accepted')
            ->where('accepted_at', '<', $thirtyDaysAgo)
            ->whereNotNull('existing_user_id')
            ->with(['existingUser.lease', 'existingUser.leases.paymentVerification'])
            ->chunkById(500, function ($invitations) use (&$counters, &$processedUserIds) {
                foreach ($invitations as $invitation) {
                    $this->processAcceptedInvitationForArchival($invitation, $counters, $processedUserIds);
                }
            });
    }

    private function processAcceptedInvitationForArchival(TenantInvitation $invitation, array &$counters, array &$processedUserIds): void
    {
        try {
            $user = $invitation->existingUser;

            if (! $user) {
                return;
            }

            if (in_array($user->id, $processedUserIds)) {
                $invitation->markAsExpired();

                return;
            }

            if ($user->is_archived || $user->lease) {
                return;
            }

            $this->archiveUserIfIncomplete($user, $invitation, $counters, $processedUserIds);
        } catch (\Exception $e) {
            $counters['errors']++;
            Log::error('Failed to process invitation for archival', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function archiveUserIfIncomplete($user, TenantInvitation $invitation, array &$counters, array &$processedUserIds): void
    {
        $hasKyc = $user->hasCompletedKyc();

        $hasVerifiedPayment = $user->leases()
            ->whereHas('paymentVerification', function ($q) {
                $q->where('status', TenantPaymentVerification::STATUS_PAYMENT_VERIFIED);
            })
            ->exists();

        if ($hasKyc || $hasVerifiedPayment) {
            return;
        }

        DB::transaction(function () use ($user, $invitation, &$counters) {
            $user->is_archived = true;
            $user->archived_at = now();
            $user->save();

            $invitation->markAsExpired();
            $counters['archived']++;

            Log::info('Incomplete tenant user archived', [
                'user_id' => $user->id,
                'invitation_id' => $invitation->id,
                'reason' => 'No KYC and no verified payment after 30 days',
            ]);
        });

        $processedUserIds[] = $user->id;
    }
}
