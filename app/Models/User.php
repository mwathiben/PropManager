<?php

namespace App\Models;

use App\Enums\KycSubmissionStatus;
use App\Traits\Auditable;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $role
 * @property bool $is_archived
 * @property \Carbon\Carbon|null $archived_at
 * @property string|null $mobile_number
 * @property int|null $landlord_id
 * @property string|null $national_id
 * @property string|null $bank_details
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string|null $profile_photo_path
 * @property \Carbon\Carbon|null $kyc_completed_at
 * @property string|null $timezone
 * @property \Carbon\Carbon|null $email_verified_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read string|null $profile_photo_url
 * @property-read SubscriptionPlan|null $plan
 * @property-read User|null $landlord
 * @property-read Lease|null $lease
 * @property-read Subscription|null $subscription
 * @property-read \Illuminate\Database\Eloquent\Collection<Property> $properties
 * @property-read \Illuminate\Database\Eloquent\Collection<Lease> $leases
 * @property-read \Illuminate\Database\Eloquent\Collection<User> $caretakers
 * @property-read \Illuminate\Database\Eloquent\Collection<Document> $documents
 * @property-read \Illuminate\Database\Eloquent\Collection<Building> $assignedBuildings
 * @property-read \Illuminate\Database\Eloquent\Collection<Ticket> $reportedTickets
 * @property-read \Illuminate\Database\Eloquent\Collection<Ticket> $assignedTickets
 * @property-read \Illuminate\Database\Eloquent\Collection<TenantKycSubmission> $kycSubmissions
 */
class User extends Authenticatable implements HasLocalePreference
{
    use Auditable, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'archived_at',
        'mobile_number',
        'national_id',
        'bank_details',
        'emergency_contact_name',
        'emergency_contact_phone',
        'profile_photo_path',
        'kyc_completed_at',
        'timezone',
        'locale',
        'dob',
        'parental_consent_artefact_url',
        'parental_consent_provided_at',
        'payment_gateway_preference',
        'acquisition_source',
        // Phase-63 INBOX-NOTIFY-1: presence signal for unread-message
        // fallback gating. Touched by HandleInertiaRequests debounced.
        'last_active_at',
        // Phase-66 REFERRAL-LEADERBOARD-3: DPA opt-out from public
        // referral leaderboard display.
        'leaderboard_opt_out',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'national_id',
        'bank_details',
    ];

    // ENCRYPTION: Laravel handles the encryption/decryption automatically
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'national_id' => 'encrypted',
        'bank_details' => 'encrypted',
        'kyc_completed_at' => 'datetime',
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
        'onboarding_checklist_dismissed_at' => 'datetime',
        // Phase-13 DPA-4: Article 18 restriction-of-processing flag.
        // When non-null the account is read-only; the Gate::before
        // hook in AuthServiceProvider denies write-side abilities.
        'restricted_at' => 'datetime',
        // Phase-63 INBOX-NOTIFY-1: presence cursor (datetime cast).
        'last_active_at' => 'datetime',
        // Phase-66 REFERRAL-LEADERBOARD-3: leaderboard display opt-out.
        'leaderboard_opt_out' => 'boolean',
        // Phase-21 DEFER-DPA-1: Kenya DPA Article 8 / Section 33 —
        // children's data. dob feeds KenyaDpaService::isMinor; consent
        // artefact + timestamp gate downstream processing when dob says
        // the tenant is a minor.
        'dob' => 'date',
        'parental_consent_provided_at' => 'datetime',
    ];

    /**
     * A manager is its own scope owner: keep landlord_id pointing at its own id
     * so TenantScope and the ubiquitous `isLandlord() ? id : landlord_id`
     * scope-owner resolution treat a manager exactly like a self-managing
     * landlord. Enforced on create so the invariant can never drift.
     */
    protected static function booted(): void
    {
        // `saved` (not `created`): the UserFactory sets the guarded `role` in a
        // follow-up update, so the invariant must also apply on update. saveQuietly
        // suppresses the event, so this never recurses.
        static::saved(function (self $user): void {
            if ($user->role === 'manager' && (int) $user->landlord_id !== (int) $user->id) {
                $user->forceFill(['landlord_id' => $user->id])->saveQuietly();
            }
        });
    }

    /**
     * Phase-13 DPA-4: Article 18 / Kenya DPA Section 26(d).
     */
    public function isRestricted(): bool
    {
        return $this->restricted_at !== null;
    }

    /**
     * Phase-24 I18N-INFRA-1: the user's resolved locale. `locale` is
     * nullable (no preference) — callers should never have to deal
     * with the null themselves. If the stored value is not a
     * supported locale it falls through to the app default too, so a
     * stale row can never leave the app in an unsupported locale.
     */
    public function effectiveLocale(): string
    {
        $supported = array_keys(config('app.available_locales', ['en' => 'English']));

        // fallback_locale (not locale) is the stable base-language
        // anchor — App::setLocale() mutates config('app.locale') for
        // the rest of the request, which would make a no-preference
        // user inherit whatever locale the request happens to be in.
        return in_array($this->locale, $supported, true)
            ? $this->locale
            : config('app.fallback_locale');
    }

    /**
     * Phase-24 I18N-BACKEND-1: HasLocalePreference contract. Laravel's
     * mailer + notifier picks this up automatically for queued mail
     * via `Mail::to($user)` — every notification destined for this
     * user is rendered in their chosen language, with no send-site
     * `Mail::locale(...)` wiring required.
     */
    public function preferredLocale(): string
    {
        return $this->effectiveLocale();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isLandlord(): bool
    {
        return $this->role === 'landlord';
    }

    /**
     * A management firm or individual who runs properties on owners' behalf for
     * a fee. Like a landlord, a manager is its own scope owner — it keeps
     * landlord_id == its own id (enforced in booted()), so the system-wide
     * `isLandlord() ? id : landlord_id` scope resolution treats it identically.
     */
    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    /** A scope owner — owns a tenancy scope keyed on its own id (vs an attached account). */
    public function isScopeOwner(): bool
    {
        return in_array($this->role, ['landlord', 'manager'], true);
    }

    public function isCaretaker(): bool
    {
        return $this->role === 'caretaker';
    }

    public function isTenant(): bool
    {
        return $this->role === 'tenant';
    }

    /**
     * Phase-94 WATER-CLIENTS: a non-tenant who only receives a water supply from
     * a landlord (a borehole neighbour). Scoped to their supplier landlord_id.
     */
    public function isWaterClient(): bool
    {
        return $this->role === 'water_client';
    }

    /**
     * Phase-102 OWNER-PORTAL: a property owner who logs in to view the properties a PM
     * manages on their behalf. Scoped to their PM (landlord_id); linked to a PropertyOwner.
     */
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /** Phase-102: the PropertyOwner contact this login is linked to (owner role). */
    public function propertyOwner(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PropertyOwner::class, 'user_id');
    }

    public function isArchived(): bool
    {
        return $this->is_archived === true;
    }

    /**
     * Phase-19 POLICY-6: full-system audit-log visibility predicate.
     * Pre-Phase-19 AuditLogController scoped via inline isSuperAdmin()
     * AFTER the Gate authorized the read — a DPA-4 restricted super-admin
     * passed the Gate (view-audit-logs is on the allow-list) but kept
     * full-system visibility. This helper bakes the restriction state
     * into the scoping predicate so restricted super-admins fall back to
     * the landlord-scoped query path (which then returns their own
     * landlord_id rows; for true super-admins with no landlord_id, the
     * query collapses to landlord_id=null and returns nothing — also
     * acceptable for the restricted state).
     */
    public function canAccessAllAuditLogs(): bool
    {
        return $this->isSuperAdmin() && ! $this->isRestricted();
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('is_archived', true);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    // --- KYC ---

    public function kycSubmissions(): HasMany
    {
        return $this->hasMany(TenantKycSubmission::class, 'user_id');
    }

    /**
     * Check if tenant has completed KYC verification.
     * Uses the dynamic KYC requirements system. All required requirements
     * must have approved submissions for this to return true.
     * Non-tenants always return true (they don't need KYC).
     */
    public function hasCompletedKyc(): bool
    {
        if ($this->role !== 'tenant') {
            return true;
        }

        $activeLease = $this->lease;
        $buildingId = $activeLease?->unit?->building_id;
        $landlordId = $this->landlord_id;

        $requiredRequirements = KycRequirement::withoutGlobalScope('landlord')
            ->where(function ($query) use ($landlordId) {
                $query->where('landlord_id', $landlordId)
                    ->orWhereNull('landlord_id');
            })
            ->where(function ($query) use ($buildingId) {
                $query->where('building_id', $buildingId)
                    ->orWhereNull('building_id');
            })
            ->active()
            ->required()
            ->pluck('id');

        if ($requiredRequirements->isEmpty()) {
            return true;
        }

        $approvedCount = $this->kycSubmissions()
            ->whereIn('requirement_id', $requiredRequirements)
            ->where('status', KycSubmissionStatus::Approved)
            ->count();

        return $approvedCount >= $requiredRequirements->count();
    }

    /**
     * Phase-46 CANONICAL-FIX-1: read-through accessor for the verified-at
     * timestamp. Returns the MAX(approved_at) across this user's approved
     * KYC submissions, or NULL if none are approved. Replaces the
     * deprecated users.kyc_completed_at column (mirror_exempt, remove_at
     * 2026-08-17). Cached 1h per user-id to avoid re-aggregating per
     * page render.
     */
    public function kycVerifiedAt(): ?\Carbon\Carbon
    {
        $cacheKey = "user:{$this->id}:kyc_verified_at";

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHour(), function (): ?\Carbon\Carbon {
            $reviewedAt = $this->kycSubmissions()
                ->where('status', \App\Enums\KycSubmissionStatus::Approved)
                ->max('reviewed_at');

            return $reviewedAt ? \Carbon\Carbon::parse($reviewedAt) : null;
        });
    }

    /**
     * Phase-46 CANONICAL-FIX-3: dedupe-aware national_id read.
     * Prefers the latest APPROVED tenant_kyc_submissions row with
     * requirement_type='national_id' (the audit-grade record);
     * falls back to users.national_id only when no submission exists.
     * Cached 1h per user-id.
     */
    public function canonicalNationalId(): ?string
    {
        $cacheKey = "user:{$this->id}:canonical_national_id";

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHour(), function (): ?string {
            $submitted = $this->kycSubmissions()
                ->whereHas('requirement', fn ($q) => $q->where('requirement_type', 'national_id'))
                ->where('status', \App\Enums\KycSubmissionStatus::Approved)
                ->orderByDesc('reviewed_at')
                ->value('submission_value');

            return $submitted ?? $this->national_id;
        });
    }

    /**
     * Phase-48 TENANT-KYC-BRIDGE-1: wizard-ready KYC progress shape.
     *
     * Returns:
     *   - required: count of active+required KycRequirement rows for this
     *     tenant's landlord/building/global cascade
     *   - submitted: count of distinct requirements where a submission row
     *     exists in any status (the wizard's advance gate)
     *   - approved / pending / rejected: per-status counts
     *   - percent: 0..100 of approved / required
     *   - remaining_labels: human-readable labels of requirements NOT yet
     *     submitted (drives the wizard checklist UI)
     *
     * Cached 5 minutes per user-id (wizard scope; submissions don't change
     * minute-to-minute during onboarding). Cache invalidates via the
     * TenantKycSubmission saved listener.
     *
     * For non-tenants the accessor returns an empty/satisfied shape.
     *
     * @return array{required:int,submitted:int,approved:int,pending:int,rejected:int,percent:int,remaining_labels:list<string>}
     */
    public function kycProgress(): array
    {
        if ($this->role !== 'tenant') {
            return [
                'required' => 0, 'submitted' => 0, 'approved' => 0,
                'pending' => 0, 'rejected' => 0, 'percent' => 100,
                'remaining_labels' => [],
            ];
        }

        $cacheKey = "user:{$this->id}:kyc-progress";

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function (): array {
            $activeLease = $this->lease;
            $buildingId = $activeLease?->unit?->building_id;
            $landlordId = $this->landlord_id;

            $requirements = KycRequirement::withoutGlobalScope('landlord')
                ->where(function ($query) use ($landlordId) {
                    $query->where('landlord_id', $landlordId)
                        ->orWhereNull('landlord_id');
                })
                ->where(function ($query) use ($buildingId) {
                    $query->where('building_id', $buildingId)
                        ->orWhereNull('building_id');
                })
                ->active()
                ->required()
                ->get(['id', 'label', 'requirement_type']);

            $requiredCount = $requirements->count();
            if ($requiredCount === 0) {
                return [
                    'required' => 0, 'submitted' => 0, 'approved' => 0,
                    'pending' => 0, 'rejected' => 0, 'percent' => 100,
                    'remaining_labels' => [],
                ];
            }

            $requirementIds = $requirements->pluck('id');
            $submissions = $this->kycSubmissions()
                ->whereIn('requirement_id', $requirementIds)
                ->get(['requirement_id', 'status']);

            $submittedReqIds = $submissions->pluck('requirement_id')->unique();
            $approved = $submissions->where('status', \App\Enums\KycSubmissionStatus::Approved)->count();
            $pending = $submissions->where('status', \App\Enums\KycSubmissionStatus::Pending)->count();
            $rejected = $submissions->where('status', \App\Enums\KycSubmissionStatus::Rejected)->count();

            $remaining = $requirements->reject(fn ($r) => $submittedReqIds->contains($r->id))
                ->pluck('label')
                ->values()
                ->all();

            return [
                'required' => $requiredCount,
                'submitted' => $submittedReqIds->count(),
                'approved' => $approved,
                'pending' => $pending,
                'rejected' => $rejected,
                'percent' => (int) round(($approved / $requiredCount) * 100),
                'remaining_labels' => $remaining,
            ];
        });
    }

    /**
     * Get the URL for the user's profile photo.
     */
    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path
            ? Storage::url($this->profile_photo_path)
            : null;
    }

    // --- TIMEZONE ---

    /**
     * Get the user's timezone, defaulting to Africa/Nairobi for Kenya.
     */
    public function getTimezone(): string
    {
        return $this->timezone ?? 'Africa/Nairobi';
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'landlord_id');
    }

    public function lease(): HasOne
    {
        return $this->hasOne(Lease::class, 'tenant_id')->where('is_active', true);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class, 'tenant_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class, 'tenant_id');
    }

    public function issuedCreditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class, 'landlord_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'landlord_id');
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function caretakers(): HasMany
    {
        return $this->hasMany(User::class, 'landlord_id')->where('role', 'caretaker');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function assignedBuildings(): HasMany
    {
        return $this->hasMany(Building::class, 'caretaker_id');
    }

    public function reportedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'reporter_id');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function tenantNotes(): HasMany
    {
        return $this->hasMany(TenantNote::class, 'tenant_id');
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class, 'tenant_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TenantActivity::class, 'tenant_id')->orderBy('created_at', 'desc');
    }

    public function tenantInvitations(): HasMany
    {
        return $this->hasMany(TenantInvitation::class, 'landlord_id');
    }

    public function landlordProfile(): HasOne
    {
        return $this->hasOne(LandlordProfile::class);
    }

    public function onboardingProgress(): HasOne
    {
        return $this->hasOne(OnboardingProgress::class);
    }

    public function paymentConfiguration(): HasOne
    {
        return $this->hasOne(PaymentConfiguration::class, 'landlord_id');
    }

    public function invoiceSetting(): HasOne
    {
        return $this->hasOne(InvoiceSetting::class, 'landlord_id');
    }

    /**
     * Phase-48 TENANT-PAYMENT-METHOD-1: stored M-Pesa/bank/card credentials
     * for tenants to enable auto-debit + recurring payments.
     */
    public function tenantPaymentMethods(): HasMany
    {
        return $this->hasMany(TenantPaymentMethod::class);
    }

    public function invoiceTemplates(): HasMany
    {
        return $this->hasMany(InvoiceTemplate::class, 'landlord_id');
    }

    // For Landlords: Get or create invoice settings
    public function getOrCreateInvoiceSetting(): InvoiceSetting
    {
        return $this->invoiceSetting ?? InvoiceSetting::create([
            'landlord_id' => $this->id,
        ]);
    }

    /**
     * Check if landlord has completed onboarding
     */
    public function hasCompletedOnboarding(): bool
    {
        if (! $this->isScopeOwner()) {
            return true; // Only scope owners (landlord/manager) onboard
        }

        return $this->onboardingProgress?->is_complete ?? false;
    }

    /**
     * Get or create onboarding progress for this user
     */
    public function getOrCreateOnboardingProgress(): OnboardingProgress
    {
        return OnboardingProgress::getOrCreateForUser($this->id);
    }

    /**
     * Get or create payment configuration for this landlord
     */
    public function getOrCreatePaymentConfiguration(): PaymentConfiguration
    {
        return PaymentConfiguration::getOrCreateForLandlord($this->id);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latest();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    // Subscription helper methods
    public function subscribed(): bool
    {
        return $this->subscription?->isActive() ?? false;
    }

    public function onTrial(): bool
    {
        return $this->subscription?->onTrial() ?? false;
    }

    public function subscribedToPlan(string $planSlug): bool
    {
        return $this->subscription?->plan?->slug === $planSlug;
    }

    /**
     * PERF-R1: memoize the resolved plan on first access. CheckPlanLimits
     * middleware reads $user->plan from canAccessFeature(), withinLimit(),
     * and getLimit() inside a single request — without memoization the
     * subscription/plan lazy loads fire repeatedly per request.
     */
    private ?SubscriptionPlan $resolvedPlan = null;

    public function getPlanAttribute(): ?SubscriptionPlan
    {
        return $this->resolvedPlan ??= ($this->subscription?->plan ?? SubscriptionPlan::free());
    }

    // Feature access checking
    public function canAccessFeature(string $feature): bool
    {
        // Super admins can access everything
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Only scope owners (landlord/manager) have subscription limits
        if (! $this->isScopeOwner()) {
            return true;
        }

        $plan = $this->plan;
        if (! $plan) {
            return false;
        }

        return match ($feature) {
            'water_billing' => $plan->water_billing_enabled,
            'ocr' => $plan->ocr_enabled,
            'reports' => $plan->reports_enabled,
            'bulk_operations' => $plan->bulk_operations_enabled,
            'documents' => $plan->document_storage_enabled,
            'sms' => $plan->sms_notifications_enabled,
            default => true,
        };
    }

    // Usage tracking
    public function getUsage(string $feature): int
    {
        return UsageRecord::forUserAndFeature($this->id, $feature)?->quantity ?? 0;
    }

    public function withinLimit(string $feature): bool
    {
        // Super admins have no limits
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Only scope owners (landlord/manager) have subscription limits
        if (! $this->isScopeOwner()) {
            return true;
        }

        $plan = $this->plan;
        if (! $plan) {
            return false;
        }

        return match ($feature) {
            'properties' => $this->properties()->count() < $plan->max_properties,
            'buildings' => Building::where('landlord_id', $this->id)->count() < $plan->max_buildings,
            'units' => Unit::where('landlord_id', $this->id)->count() < $plan->max_units,
            'caretakers' => $this->caretakers()->count() < $plan->max_caretakers,
            default => true,
        };
    }

    public function getLimit(string $feature): int
    {
        $plan = $this->plan;
        if (! $plan) {
            return 0;
        }

        return match ($feature) {
            'properties' => $plan->max_properties,
            'buildings' => $plan->max_buildings,
            'units' => $plan->max_units,
            'caretakers' => $plan->max_caretakers,
            'documents_mb' => $plan->document_storage_mb,
            default => 0,
        };
    }

    public function getCurrentUsage(string $feature): int
    {
        return match ($feature) {
            'properties' => $this->properties()->count(),
            'buildings' => Building::where('landlord_id', $this->id)->count(),
            'units' => Unit::where('landlord_id', $this->id)->count(),
            'caretakers' => $this->caretakers()->count(),
            default => 0,
        };
    }

    // --- AUDIT LOGGING ---

    /**
     * Get fields to exclude from audit logging (sensitive data).
     */
    public function getAuditExclude(): array
    {
        return [
            'national_id',
            'bank_details',
        ];
    }
}
