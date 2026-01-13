<?php

namespace App\Http\Middleware;

use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\TenantInvitation;
use App\Models\Ticket;
use App\Models\WaterReading;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'kyc_complete' => $user?->hasCompletedKyc() ?? true,
                'profile_photo_url' => $user?->profile_photo_url,
            ],
            'impersonating' => session('impersonating') !== null,
            'impersonating_name' => session('impersonating_name'),
            'navBadges' => $this->getNavBadges($request),
            'featureAccess' => $this->getFeatureAccess($request),
            'pendingInvitationsCount' => $this->getPendingInvitationsCount($request),
            'pendingInvitations' => $this->getPendingInvitations($request),
        ];
    }

    /**
     * Get feature access flags based on user's subscription.
     */
    protected function getFeatureAccess(Request $request): ?array
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        // Super admins have full access
        if ($user->isSuperAdmin()) {
            return [
                'water_billing' => true,
            ];
        }

        // For landlords, check their own subscription
        if ($user->isLandlord()) {
            return [
                'water_billing' => $user->canAccessFeature('water_billing'),
            ];
        }

        // For caretakers, check their landlord's subscription
        if ($user->isCaretaker() && $user->landlord) {
            return [
                'water_billing' => $user->landlord->canAccessFeature('water_billing'),
            ];
        }

        return ['water_billing' => false];
    }

    /**
     * Get badge counts for navigation items based on user role.
     */
    protected function getNavBadges(Request $request): ?array
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        return match ($user->role) {
            'landlord' => array_filter([
                'invoices' => Invoice::where('status', 'overdue')->count(),
                'tickets' => Ticket::open()->where('priority', 'urgent')->count(),
                'readings' => $user->canAccessFeature('water_billing')
                    ? WaterReading::where('status', 'pending')->count()
                    : null,
            ], fn ($v) => $v !== null),
            'caretaker' => array_filter([
                'tickets' => Ticket::where('assigned_to', $user->id)->open()->count(),
                'readings' => ($user->landlord?->canAccessFeature('water_billing') ?? false)
                    ? WaterReading::where('status', 'pending')->count()
                    : null,
            ], fn ($v) => $v !== null),
            'tenant' => [
                'invoices' => $user->lease
                    ? Invoice::where('lease_id', $user->lease->id)
                        ->whereIn('status', ['overdue', 'partial'])
                        ->count()
                    : 0,
                'tickets' => Ticket::where('reporter_id', $user->id)
                    ->whereNotIn('status', ['resolved', 'closed'])
                    ->count(),
                'notifications' => Notification::withoutGlobalScope('landlord')
                    ->where('recipient_id', $user->id)
                    ->whereNull('read_at')
                    ->count(),
            ],
            default => null,
        };
    }

    /**
     * Get the count of pending invitations for the current user.
     */
    protected function getPendingInvitationsCount(Request $request): int
    {
        $user = $request->user();

        if (! $user) {
            return 0;
        }

        $count = 0;

        // Caretaker invitations (any user can be invited as caretaker)
        $count += Invitation::where('target_user_id', $user->id)
            ->pending()
            ->count();

        // Tenant invitations (for existing users) - bypass TenantScope to see invitations from any landlord
        $count += TenantInvitation::withoutGlobalScope('landlord')
            ->where('existing_user_id', $user->id)
            ->valid()
            ->count();

        return $count;
    }

    /**
     * Get pending invitations details for the dashboard banner.
     */
    protected function getPendingInvitations(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [];
        }

        $invitations = [];

        // Caretaker invitations
        $caretakerInvitations = Invitation::where('target_user_id', $user->id)
            ->pending()
            ->with(['landlord', 'property'])
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'type' => 'caretaker',
                    'landlord_name' => $invitation->landlord->name,
                    'property_name' => $invitation->property->name,
                    'expires_at' => $invitation->getExpiresAt()->format('M d, Y'),
                    'token' => $invitation->token,
                ];
            });

        // Tenant invitations - bypass TenantScope on all relationships to see invitations from any landlord
        $tenantInvitations = TenantInvitation::withoutGlobalScope('landlord')
            ->where('existing_user_id', $user->id)
            ->valid()
            ->with([
                'unit' => function ($query) {
                    $query->withoutGlobalScope('landlord');
                },
                'unit.building' => function ($query) {
                    $query->withoutGlobalScope('landlord');
                },
                'unit.building.property' => function ($query) {
                    $query->withoutGlobalScope('landlord');
                },
                'landlord',
            ])
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'type' => 'tenant',
                    'landlord_name' => $invitation->landlord->name,
                    'property_name' => $invitation->unit->building->property->name,
                    'building_name' => $invitation->unit->building->name,
                    'unit_number' => $invitation->unit->unit_number,
                    'rent_amount' => $invitation->rent_amount,
                    'deposit_amount' => $invitation->deposit_amount,
                    'expires_at' => $invitation->expires_at->format('M d, Y'),
                ];
            });

        return $caretakerInvitations->concat($tenantInvitations)->values()->toArray();
    }
}
