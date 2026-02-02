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
        $expiredCount = 0;
        $archivedCount = 0;
        $errorCount = 0;

        // Phase 1: Mark stale pending invitations as expired
        TenantInvitation::withoutGlobalScope('landlord')
            ->where('status', 'pending')
            ->where('expires_at', '<', $thirtyDaysAgo)
            ->chunkById(500, function ($invitations) use (&$expiredCount, &$errorCount) {
                foreach ($invitations as $invitation) {
                    try {
                        $invitation->markAsExpired();
                        $expiredCount++;

                        Log::info('Tenant invitation marked as expired', [
                            'invitation_id' => $invitation->id,
                            'expires_at' => $invitation->expires_at->toIso8601String(),
                        ]);
                    } catch (\Exception $e) {
                        $errorCount++;
                        Log::error('Failed to expire invitation', [
                            'invitation_id' => $invitation->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        // Phase 2: Archive incomplete users from accepted invitations
        $processedUserIds = [];

        TenantInvitation::withoutGlobalScope('landlord')
            ->where('status', 'accepted')
            ->where('accepted_at', '<', $thirtyDaysAgo)
            ->whereNotNull('existing_user_id')
            ->with(['existingUser.lease', 'existingUser.leases.paymentVerification'])
            ->chunkById(500, function ($invitations) use (&$archivedCount, &$errorCount, &$processedUserIds) {
                foreach ($invitations as $invitation) {
                    try {
                        $user = $invitation->existingUser;

                        if (! $user) {
                            continue;
                        }

                        // Skip if already processed this user in this run
                        if (in_array($user->id, $processedUserIds)) {
                            $invitation->markAsExpired();

                            continue;
                        }

                        // Skip already archived
                        if ($user->is_archived) {
                            continue;
                        }

                        // Skip users with active lease (productive tenants)
                        if ($user->lease) {
                            continue;
                        }

                        // Check KYC completion
                        $hasKyc = $user->hasCompletedKyc();

                        // Check if any lease has verified payment
                        $hasVerifiedPayment = $user->leases()
                            ->whereHas('paymentVerification', function ($q) {
                                $q->where('status', TenantPaymentVerification::STATUS_PAYMENT_VERIFIED);
                            })
                            ->exists();

                        // Archive only if BOTH incomplete
                        if (! $hasKyc && ! $hasVerifiedPayment) {
                            DB::transaction(function () use ($user, $invitation, &$archivedCount) {
                                $user->update([
                                    'is_archived' => true,
                                    'archived_at' => now(),
                                ]);

                                $invitation->markAsExpired();
                                $archivedCount++;

                                Log::info('Incomplete tenant user archived', [
                                    'user_id' => $user->id,
                                    'invitation_id' => $invitation->id,
                                    'reason' => 'No KYC and no verified payment after 30 days',
                                ]);
                            });

                            $processedUserIds[] = $user->id;
                        }
                    } catch (\Exception $e) {
                        $errorCount++;
                        Log::error('Failed to process invitation for archival', [
                            'invitation_id' => $invitation->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Expired {$expiredCount} pending invitation(s).");
        $this->info("Archived {$archivedCount} incomplete user(s).");

        if ($errorCount > 0) {
            $this->warn("Encountered {$errorCount} error(s). Check logs for details.");
        }

        return Command::SUCCESS;
    }
}
