<?php

namespace App\Http\Middleware;

use App\Enums\Currency;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\MoveOut;
use App\Models\Notification;
use App\Models\PaymentConfiguration;
use App\Models\TenantInvitation;
use App\Models\TenantMessage;
use App\Models\TenantPaymentVerification;
use App\Models\TenantVerification;
use App\Models\Ticket;
use App\Models\WaterReading;
use Illuminate\Http\Request;
use Inertia\Inertia;
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
            ],
            'impersonating' => session('impersonating') !== null,
            'impersonating_name' => session('impersonating_name'),
            'currency' => fn () => $this->getEffectiveCurrency($request),
            'navBadges' => fn () => $this->getNavBadges($request),
            'featureAccess' => $this->getFeatureAccess($request),
            'pendingInvitations' => Inertia::defer(fn () => $this->getPendingInvitations($request)),
        ];
    }

    protected function getEffectiveCurrency(Request $request): ?array
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $landlordId = match (true) {
            $user->isLandlord() => $user->id,
            $user->isCaretaker(), $user->isTenant() => $user->landlord_id,
            default => null,
        };

        if (! $landlordId) {
            $default = Currency::default();

            return ['code' => $default->value, 'symbol' => $default->symbol()];
        }

        $config = PaymentConfiguration::where('landlord_id', $landlordId)->first();
        $currency = $config?->default_currency ?? Currency::default();

        return ['code' => $currency->value, 'symbol' => $currency->symbol()];
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

        // SCOPE-D6: defense-in-depth. Each landlord/caretaker badge count
        // adds an explicit landlord_id filter alongside the implicit
        // TenantScope. If TenantScope ever fails to apply (impersonation
        // edge, future refactor, queue context), counts stay scoped instead
        // of leaking system-wide totals into the navigation chrome.
        $landlordId = $user->isLandlord() ? $user->id : $user->landlord_id;

        return match ($user->role) {
            'landlord' => array_filter([
                // Aggregated hub badges
                'tenants' => TenantPaymentVerification::where('landlord_id', $landlordId)->where('status', 'pending')->count()
                    + MoveOut::where('landlord_id', $landlordId)->active()->count()
                    + TenantVerification::where('landlord_id', $landlordId)->pending()->count(),
                'invoices' => Invoice::where('landlord_id', $landlordId)->where('status', 'overdue')->count(),
                'tickets' => Ticket::where('landlord_id', $landlordId)->open()->count(),
                'readings' => $user->canAccessFeature('water_billing')
                    ? WaterReading::where('landlord_id', $landlordId)->where('status', 'pending')->count()
                    : null,
                'notifications' => Notification::where('recipient_id', $user->id)
                    ->whereNull('read_at')
                    ->count(),
                'inbox' => TenantMessage::where('landlord_id', $landlordId)->where('status', TenantMessage::STATUS_RECEIVED)->count(),
            ], fn ($v) => $v !== null && $v > 0),
            'caretaker' => array_filter([
                'tickets' => Ticket::where('landlord_id', $landlordId)->where('assigned_to', $user->id)->open()->count(),
                'readings' => ($user->landlord?->canAccessFeature('water_billing') ?? false)
                    ? WaterReading::where('landlord_id', $landlordId)->where('status', 'pending')->count()
                    : null,
                'notifications' => Notification::withoutGlobalScope('landlord')
                    ->where('recipient_id', $user->id)
                    ->whereNull('read_at')
                    ->count(),
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
     * Get pending invitations details for the dashboard banner.
     *
     * SCOPE-P6: this method intentionally bypasses the landlord TenantScope
     * via withoutGlobalScope('landlord') on TenantInvitation and its
     * unit/building/property eager loads. The user inviting a tenant lives
     * under one landlord; the tenant being invited (existing_user_id)
     * almost always lives under a *different* landlord (or none yet).
     * Filtering by the viewer's landlord_id would hide every legitimate
     * cross-tenant invitation and break the banner. The security control
     * is `where('existing_user_id', $user->id)` — the viewer can only see
     * invitations addressed to them by user id, not by landlord. Callers
     * adding new invitation types here MUST replicate that exact filter.
     */
    protected function getPendingInvitations(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [];
        }

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
