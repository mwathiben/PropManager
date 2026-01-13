<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use Auditable, HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'mobile_number',
        'landlord_id',
        'national_id',
        'bank_details',
        'emergency_contact_name',
        'emergency_contact_phone',
        'profile_photo_path',
        'kyc_completed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'national_id', // Always hide PII by default in array output
        'bank_details',
    ];

    // ENCRYPTION: Laravel handles the encryption/decryption automatically
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'national_id' => 'encrypted',
        'bank_details' => 'encrypted',
        'kyc_completed_at' => 'datetime',
    ];

    // --- ROLES ---

    public function isSuperAdmin()
    {
        return $this->role === 'super_admin';
    }

    public function isLandlord()
    {
        return $this->role === 'landlord';
    }

    public function isCaretaker()
    {
        return $this->role === 'caretaker';
    }

    public function isTenant()
    {
        return $this->role === 'tenant';
    }

    // --- KYC ---

    /**
     * Check if tenant has completed KYC verification.
     * Non-tenants always return true (they don't need KYC).
     */
    public function hasCompletedKyc(): bool
    {
        if ($this->role !== 'tenant') {
            return true; // Non-tenants don't need KYC
        }

        return ! empty($this->mobile_number)
            && ! empty($this->national_id)
            && ! empty($this->emergency_contact_name)
            && ! empty($this->emergency_contact_phone)
            && ! empty($this->profile_photo_path);
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

    // --- RELATIONSHIPS ---

    public function properties()
    {
        return $this->hasMany(Property::class, 'landlord_id');
    }

    // For Tenants: Their active lease
    public function lease()
    {
        return $this->hasOne(Lease::class, 'tenant_id')->where('is_active', true);
    }

    // For Tenants: All their leases (current and past)
    public function leases()
    {
        return $this->hasMany(Lease::class, 'tenant_id');
    }

    // For Tenants: Credit notes issued to them
    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class, 'tenant_id');
    }

    // For Landlords: Credit notes they've issued
    public function issuedCreditNotes()
    {
        return $this->hasMany(CreditNote::class, 'landlord_id');
    }

    // For Landlords: Invitations they've sent
    public function invitations()
    {
        return $this->hasMany(Invitation::class, 'landlord_id');
    }

    // For Caretakers: The landlord they work for
    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    // For Landlords: Caretakers working for them
    public function caretakers()
    {
        return $this->hasMany(User::class, 'landlord_id')->where('role', 'caretaker');
    }

    // For Tenants: Their documents (ID, passport, etc.)
    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    // For Caretakers: Buildings they are assigned to
    public function assignedBuildings()
    {
        return $this->hasMany(Building::class, 'caretaker_id');
    }

    // For Tenants: Tickets they have reported
    public function reportedTickets()
    {
        return $this->hasMany(Ticket::class, 'reporter_id');
    }

    // For Caretakers: Tickets assigned to them
    public function assignedTickets()
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    // --- TENANT MODULE RELATIONSHIPS ---

    // For Tenants: Notes about this tenant (landlord's private notes)
    public function tenantNotes()
    {
        return $this->hasMany(TenantNote::class, 'tenant_id');
    }

    // For Tenants: Emergency contacts
    public function emergencyContacts()
    {
        return $this->hasMany(EmergencyContact::class, 'tenant_id');
    }

    // For Tenants: Activity timeline
    public function activities()
    {
        return $this->hasMany(TenantActivity::class, 'tenant_id')->orderBy('created_at', 'desc');
    }

    // For Landlords: Tenant invitations they've sent
    public function tenantInvitations()
    {
        return $this->hasMany(TenantInvitation::class, 'landlord_id');
    }

    // --- ONBOARDING MODULE RELATIONSHIPS ---

    // For Landlords: Their profile information
    public function landlordProfile()
    {
        return $this->hasOne(LandlordProfile::class);
    }

    // For Landlords: Their onboarding progress
    public function onboardingProgress()
    {
        return $this->hasOne(OnboardingProgress::class);
    }

    // For Landlords: Their payment configuration
    public function paymentConfiguration()
    {
        return $this->hasOne(PaymentConfiguration::class, 'landlord_id');
    }

    // For Landlords: Their invoice settings
    public function invoiceSetting()
    {
        return $this->hasOne(InvoiceSetting::class, 'landlord_id');
    }

    // For Landlords: Their invoice templates
    public function invoiceTemplates()
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

    // --- SUBSCRIPTIONS (for Landlords) ---

    public function subscription()
    {
        return $this->hasOne(Subscription::class)->latest();
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscriptionPayments()
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function usageRecords()
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

    public function getPlanAttribute(): ?SubscriptionPlan
    {
        return $this->subscription?->plan ?? SubscriptionPlan::free();
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
