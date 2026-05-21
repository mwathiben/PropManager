<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\Invitation;
use App\Models\TenantInvitation;
use Illuminate\Database\Eloquent\Builder;

/**
 * Phase-77 INVITE-FUNNEL-1: invitation conversion funnel (sent / viewed /
 * accepted / pending / expired + acceptance rate) over BOTH the caretaker/
 * landlord `invitations` table and the `tenant_invitations` table. platform()
 * is super-admin-wide; forLandlord() is strictly landlord-scoped. TenantScope is
 * bypassed explicitly so the scoping is governed by the $landlordId argument,
 * not the authed user.
 */
class InvitationFunnelService
{
    /** invitations (no expires_at column) expire 30 days after creation. */
    private const INVITATION_TTL_DAYS = 30;

    /**
     * @return array{sent:int, viewed:int, accepted:int, pending:int, expired:int, acceptance_rate:float}
     */
    public function platform(): array
    {
        return $this->compute(null);
    }

    /**
     * @return array{sent:int, viewed:int, accepted:int, pending:int, expired:int, acceptance_rate:float}
     */
    public function forLandlord(int $landlordId): array
    {
        return $this->compute($landlordId);
    }

    /**
     * @return array{sent:int, viewed:int, accepted:int, pending:int, expired:int, acceptance_rate:float}
     */
    private function compute(?int $landlordId): array
    {
        $cutoff = now()->subDays(self::INVITATION_TTL_DAYS);

        $inv = fn (): Builder => Invitation::query()
            ->when($landlordId !== null, fn (Builder $q) => $q->where('landlord_id', $landlordId));

        $sent = $inv()->count();
        $viewed = $inv()->whereNotNull('viewed_at')->count();
        $accepted = $inv()->whereNotNull('accepted_at')->count();
        $expired = $inv()->whereNull('accepted_at')->where('created_at', '<', $cutoff)->count();

        $ti = fn (): Builder => TenantInvitation::query()
            ->withoutGlobalScopes()
            ->when($landlordId !== null, fn (Builder $q) => $q->where('landlord_id', $landlordId));

        $sent += $ti()->count();
        $viewed += $ti()->whereNotNull('viewed_at')->count();
        $accepted += $ti()->where('status', 'accepted')->count();
        $expired += $ti()
            ->where(function (Builder $q) {
                $q->where('status', 'expired')
                    ->orWhere(fn (Builder $x) => $x->where('status', 'pending')->where('expires_at', '<', now()));
            })
            ->count();

        // Explicit positive count (NOT a residual) so declined tenant invites —
        // which are sent but neither accepted nor expired — don't inflate pending.
        $pending = $inv()->whereNull('accepted_at')->where('created_at', '>=', $cutoff)->count()
            + $ti()->where('status', 'pending')->where('expires_at', '>=', now())->count();

        return [
            'sent' => $sent,
            'viewed' => $viewed,
            'accepted' => $accepted,
            'pending' => $pending,
            'expired' => $expired,
            'acceptance_rate' => $sent > 0 ? round($accepted / $sent * 100, 1) : 0.0,
        ];
    }
}
