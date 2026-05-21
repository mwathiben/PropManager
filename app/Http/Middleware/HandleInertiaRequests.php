<?php

namespace App\Http\Middleware;

use App\Enums\Currency;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\MoveOut;
use App\Models\Notification;
use App\Models\PaymentConfiguration;
use App\Models\Property;
use App\Models\TenantInvitation;
use App\Models\TenantMessage;
use App\Models\TenantPaymentVerification;
use App\Models\TenantVerification;
use App\Models\Ticket;
use App\Models\WaterReading;
use App\Support\UserDto;
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

        // Phase-63 INBOX-NOTIFY-1: presence cursor — debounced to one
        // write per 60s so a chatty Inertia page doesn't write-storm
        // the users table. The SendUnreadMessageFallback listener
        // reads this to decide whether to fan out fallback channels.
        if ($user !== null) {
            $now = now();
            if (
                $user->last_active_at === null
                || $user->last_active_at->lessThan($now->copy()->subSeconds(60))
            ) {
                \App\Models\User::withoutGlobalScope('landlord')
                    ->where('id', $user->id)
                    ->update(['last_active_at' => $now]);
                $user->last_active_at = $now;
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                // Phase-20 AUTHZ-FRONT-1/6: slim DTO + abilities map.
                // Previously shared the full Eloquent User instance —
                // see App\Support\UserDto for the explicit field list +
                // App\Support\AuthAbilities for the computed gate map.
                'user' => $user ? UserDto::from($user) : null,
                // Phase-28 TENANT-CI-2: per-tenant abilities map for
                // Vue conditional rendering. Returns null on landlord
                // pages so the Vue layer doesn't accidentally
                // interpret an empty map as a tenant context.
                'tenant_abilities' => $user && $user->isTenant()
                    ? \App\Support\TenantAbilities::for($user)
                    : null,
                // Phase-63 INBOX-REALTIME-2: total unread count across
                // every thread the user participates in. Cached 30s to
                // bound the cost of the per-request fan-out join.
                'inbox_unread_total' => $user
                    ? \Illuminate\Support\Facades\Cache::remember(
                        'inbox:unread:'.$user->id,
                        30,
                        fn () => $this->computeInboxUnread($user->id),
                    )
                    : 0,
                // Phase-65 HOLD-UI-3: active legal-hold count for the
                // landlord's own subjects — drives the sidebar badge.
                // Cache::remember 60s bounds the polymorphic count cost.
                'legal_holds_active_count' => $user && $user->isLandlord()
                    ? \Illuminate\Support\Facades\Cache::remember(
                        'legal_holds:active:'.$user->id,
                        60,
                        fn () => \App\Support\LegalHoldRegistry::activeCountForLandlord((int) $user->id),
                    )
                    : 0,
                // Phase-66 NPS-SURVEY-2: server-authoritative NPS prompt
                // payload (null when ineligible). The service caches the
                // decision 60s and busts on every state mutation, so this
                // adds at most one indexed lookup per cold render.
                'nps_prompt' => $user
                    ? app(\App\Services\Growth\NpsEligibilityService::class)->promptPayloadFor($user)
                    : null,
                // Phase-66 ONBOARDING-TOUR-3: the user's active in-app
                // tour payload (null when terminal, role-less, or every
                // step is milestone-satisfied). Two indexed lookups on a
                // cold render; returns after one for the terminal majority.
                'onboarding_tour' => $user
                    ? app(\App\Services\Onboarding\TourService::class)->payloadFor($user)
                    : null,
            ],
            'impersonating' => session('impersonating') !== null,
            'impersonating_name' => session('impersonating_name'),
            // Phase-23 A11Y-SR-1: surface session flash so the layout's
            // LiveAnnouncer can read it to a screen reader. Plain
            // closures (always evaluated, like navBadges) — controllers
            // already redirect with ->with('success'|'error'|'message').
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'message' => fn () => $request->session()->get('message'),
            ],
            // Phase-24 I18N-INFRA-3: the active locale (resolved by the
            // SetLocale middleware, which runs before this one), the
            // supported-locale list for the selector UI, and the
            // frontend message bundle vue-i18n hydrates from — sharing
            // it inline avoids a second round-trip and guarantees the
            // messages match the locale the server resolved.
            'locale' => app()->getLocale(),
            'availableLocales' => config('app.available_locales'),
            'i18n' => fn () => $this->getI18nBundle(),
            'currency' => fn () => $this->getEffectiveCurrency($request),
            'navBadges' => fn () => $this->getNavBadges($request),
            'featureAccess' => $this->getFeatureAccess($request),
            // Phase-78 PROPERTY-SWITCH-2: the landlord's property switcher.
            'propertySwitcher' => fn () => $this->getPropertySwitcher($request),
            'pendingInvitations' => Inertia::defer(fn () => $this->getPendingInvitations($request)),
            // Phase-28 TENANT-DOCS-3: tenant-only banner data for
            // documents within 30 days of expiry.
            'tenantExpiringDocs' => fn () => $this->getTenantExpiringDocs($request),
            // Phase-35 PLATFORM-EXP-3: map of active experiment_key =>
            // variant_key for the auth user. Cached 60s in the service
            // so this doesn't hit DB per request.
            'experiments' => fn () => $user
                ? app(\App\Services\Platform\ExperimentService::class)->activeFor($user)
                : (object) [],
            // Phase-36 INSIGHT-OPS-3: super-admin operator nav. Null
            // for non-super_admin so the Vue layout short-circuits the
            // render. Static array — no DB hit.
            'opsNav' => fn () => $user && $user->isSuperAdmin()
                ? [
                    ['label' => 'Overview', 'route' => 'ops.index'],
                    ['label' => 'MRR', 'route' => 'ops.mrr.trend'],
                    ['label' => 'Landlord cost', 'route' => 'ops.landlord-cost.top-n'],
                    ['label' => 'Incidents', 'route' => 'ops.incidents.index'],
                ]
                : null,
        ];
    }

    /**
     * Phase-28 TENANT-DOCS-3: documents within 30 days of expires_at
     * for the authenticated tenant. Returns [] for non-tenants so the
     * banner short-circuits on landlord pages. Joins through
     * TenantKycSubmission/Lease via the polymorphic documentable
     * relation; per-tenant cap of 10 entries to keep the prop bounded.
     *
     * @return array<int, array{
     *     id: int,
     *     title: string,
     *     document_type: string,
     *     expires_at: string,
     *     days_remaining: int
     * }>
     */
    /**
     * Phase-63 INBOX-REALTIME-2: sum unread message counts across
     * every thread the user participates in. Excludes own messages
     * and respects message_thread_participants.last_read_at.
     */
    protected function computeInboxUnread(int $userId): int
    {
        return (int) \Illuminate\Support\Facades\DB::table('messages')
            ->join(
                'message_thread_participants',
                'message_thread_participants.thread_id',
                '=',
                'messages.thread_id',
            )
            ->where('message_thread_participants.user_id', $userId)
            ->where('messages.sender_id', '!=', $userId)
            ->whereNull('messages.deleted_at')
            ->where(function ($q) {
                $q->whereNull('message_thread_participants.last_read_at')
                    ->orWhereColumn(
                        'messages.created_at',
                        '>',
                        'message_thread_participants.last_read_at',
                    );
            })
            ->count();
    }

    protected function getTenantExpiringDocs(Request $request): array
    {
        $user = $request->user();
        if (! $user || ! $user->isTenant()) {
            return [];
        }

        $leaseIds = $user->leases()->pluck('id');
        $kycIds = \App\Models\TenantKycSubmission::query()
            ->where('user_id', $user->id)
            ->pluck('id');

        return \App\Models\Document::query()
            ->expiringSoon(30)
            ->where(function ($query) use ($leaseIds, $kycIds) {
                $query->where(function ($inner) use ($leaseIds) {
                    $inner->where('documentable_type', \App\Models\Lease::class)
                        ->whereIn('documentable_id', $leaseIds);
                })->orWhere(function ($inner) use ($kycIds) {
                    $inner->where('documentable_type', \App\Models\TenantKycSubmission::class)
                        ->whereIn('documentable_id', $kycIds);
                });
            })
            ->orderBy('expires_at')
            ->limit(10)
            ->get(['id', 'title', 'document_type', 'expires_at'])
            ->map(fn ($doc) => [
                'id' => $doc->id,
                'title' => $doc->title,
                'document_type' => $doc->document_type,
                'expires_at' => $doc->expires_at?->toDateString(),
                'days_remaining' => (int) now()->startOfDay()->diffInDays($doc->expires_at, false),
            ])
            ->all();
    }

    /**
     * Phase-24 I18N-INFRA-3: the frontend message bundle for the
     * active locale (lang/<locale>.json), falling back to the
     * fallback locale's bundle, then an empty array — never throws.
     *
     * @return array<string, mixed>
     */
    protected function getI18nBundle(): array
    {
        // Front-end vue-i18n bundle = top-level lang/{locale}.json (basic UI
        // strings, validation, simple keys) MERGED with namespaced PHP files
        // (insight.*, growth.*, payments.*, …) so $t('insight.landlord_growth.X')
        // resolves on the client. Without the merge, namespaced keys silently
        // fall back to rendering the literal key path — caught when Phase-36
        // dashboard growth cards displayed
        // 'insight.landlord_growth.engagement_card_heading' instead of headings.
        foreach ([app()->getLocale(), config('app.fallback_locale')] as $locale) {
            if (! is_string($locale) || $locale === '') {
                continue;
            }
            $bundle = [];

            $jsonPath = base_path("lang/{$locale}.json");
            if (is_file($jsonPath)) {
                $bundle = json_decode(file_get_contents($jsonPath), true) ?: [];
            }

            $namespaceDir = base_path("lang/{$locale}");
            if (is_dir($namespaceDir)) {
                foreach (glob($namespaceDir.'/*.php') ?: [] as $file) {
                    $namespace = basename($file, '.php');
                    $bundle[$namespace] = require $file;
                }
            }

            if ($bundle !== []) {
                return $bundle;
            }
        }

        return [];
    }

    /**
     * Phase-78 PROPERTY-SWITCH-2: the landlord's property switcher payload —
     * the active property + the option list. Null for non-landlords.
     *
     * @return array{active_id: int|null, options: list<array{id:int, name:string}>}|null
     */
    protected function getPropertySwitcher(Request $request): ?array
    {
        $user = $request->user();

        if (! $user || ! $user->isLandlord()) {
            return null;
        }

        $properties = Property::query()
            ->where('landlord_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($properties->isEmpty()) {
            return null;
        }

        // Derive the active id from the rows already loaded for the option
        // list (CodeRabbit M2/M3 — no second resolver query on this hot path
        // that runs on every Inertia response): the stored choice when it is
        // still owned, else the first property by id (the resolver's rule).
        $ids = $properties->pluck('id');
        $stored = (int) ($user->active_property_id ?? 0);
        $activeId = $ids->contains($stored) ? $stored : (int) $ids->min();

        return [
            'active_id' => $activeId,
            'options' => $properties->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all(),
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

        // Phase-60 FEATURE-GATES-3: expand from water_billing-only to
        // the full 6-feature plan-gate bundle so Vue components can
        // render lock icons + upgrade CTAs without each controller
        // having to assemble the bundle. PlanGateService caches the
        // lookups 5m so this stays cheap on every Inertia render.
        // Caretakers gate on their landlord's plan rather than their
        // own (caretaker User has no subscription of its own).
        $gateUser = $user->isCaretaker() && $user->landlord
            ? $user->landlord
            : $user;

        $features = app(\App\Services\Subscriptions\PlanGateService::class)->featuresFor($gateUser);

        // Phase-79 WATER-GATE-2: water_billing is a conditional MODULE, not a
        // plain plan flag — it also requires the landlord to actually charge
        // for water. Resolve on the original user so tenants (who have no
        // subscription of their own) gate on their landlord's water config.
        $features['water_billing'] = \App\Services\Water\WaterModuleAccess::enabledFor($user);

        return $features;
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
                // Phase-80 ESCALATION-VIEW-2: open caretaker escalations awaiting the landlord.
                'escalations' => Ticket::where('landlord_id', $landlordId)->escalated()->count(),
                'readings' => $user->canAccessFeature('water_billing')
                    ? WaterReading::where('landlord_id', $landlordId)->where('status', 'pending')->count()
                    : null,
                'notifications' => Notification::where('recipient_id', $user->id)
                    ->whereNull('read_at')
                    ->count(),
                'inbox' => TenantMessage::where('landlord_id', $landlordId)->where('status', TenantMessage::STATUS_RECEIVED)->count(),
                'legalHoldsActive' => \Illuminate\Support\Facades\Cache::remember(
                    'legal_holds:active:'.$user->id,
                    60,
                    fn () => \App\Support\LegalHoldRegistry::activeCountForLandlord((int) $user->id),
                ),
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
