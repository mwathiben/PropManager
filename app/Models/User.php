<?php

namespace App\Models;

use App\Enums\KycSubmissionStatus;
use App\Traits\Auditable;
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
class User extends Authenticatable
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
        // Phase-13 DPA-4: Article 18 restriction-of-processing flag.
        // When non-null the account is read-only; the Gate::before
        // hook in AuthServiceProvider denies write-side abilities.
        'restricted_at' => 'datetime',
    ];

    /**
     * Phase-13 DPA-4: Article 18 / Kenya DPA Section 26(d).
     */
    public function isRestricted(): bool
    {
        return $this->restricted_at !== null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isLandlord(): bool
    {
        return $this->role === 'landlord';
    }

    public function isCaretaker(): bool
    {
        return $this->role === 'caretaker';
    }

    public function isTenant(): bool
    {
        return $this->role === 'tenant';
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
        if (! $this->isLandlord()) {
            return true; // Non-landlords don't need onboarding
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

        // Only landlords have subscription limits
        if (! $this->isLandlord()) {
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

        // Only landlords have subscription limits
        if (! $this->isLandlord()) {
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
