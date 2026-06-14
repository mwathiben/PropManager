<?php

namespace App\Services;

use App\Models\Building;
use App\Models\Invitation;
use App\Models\LandlordProfile;
use App\Models\OnboardingProgress;
use App\Models\PaymentConfiguration;
use App\Models\Property;
use App\Models\TenantInvitation;
use App\Models\Unit;
use App\Models\User;
use App\Services\Onboarding\BuildingStructureBuilder;
use App\Services\Onboarding\BuildingStructureSpec;
use App\Services\Onboarding\OnboardingStepProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OnboardingService implements OnboardingStepProcessor
{
    public const STEPS = [
        1 => 'welcome',
        2 => 'profile',
        3 => 'property',
        4 => 'structure',
        5 => 'financial',
        6 => 'team',
        7 => 'first-tenant',
        8 => 'complete',
    ];

    public function __construct(
        private readonly BuildingStructureBuilder $structureBuilder,
    ) {}

    public function getProgress(User $user): array
    {
        $progress = $user->getOrCreateOnboardingProgress();

        return [
            'currentStep' => $progress->current_step,
            'totalSteps' => $progress->total_steps,
            'completedSteps' => $progress->completed_steps ?? [],
            'isComplete' => $progress->is_complete,
            'progressPercentage' => $progress->progress_percentage,
        ];
    }

    public function getStepProps(int $step, User $user, OnboardingProgress $progress): array
    {
        $stepName = self::STEPS[$step] ?? 'unknown';

        $props = [
            'currentStep' => $step,
            'totalSteps' => $progress->total_steps,
            'completedSteps' => $progress->completed_steps ?? [],
            'stepName' => $stepName,
            'isOptionalStep' => OnboardingProgress::isStepOptional($step),
        ];

        return match ($step) {
            2 => $this->getProfileStepProps($props, $user),
            3 => $this->getPropertyStepProps($props, $user),
            4 => $this->getStructureStepProps($props, $user),
            5 => $this->getFinancialStepProps($props, $user),
            6 => $this->getTeamStepProps($props, $user),
            7 => $this->getFirstTenantStepProps($props, $user),
            8 => $this->getCompleteStepProps($props, $user),
            default => $props,
        };
    }

    public function processStep(int $step, array $data, User $user, OnboardingProgress $progress): bool
    {
        return match ($step) {
            1 => $this->processWelcome($data, $user),
            2 => $this->processProfile($data, $user, $progress),
            3 => $this->processProperty($data, $user, $progress),
            4 => $this->processStructure($data, $user, $progress),
            5 => $this->processFinancial($data, $user, $progress),
            6 => $this->processTeam($data, $user, $progress),
            7 => $this->processFirstTenant($data, $user, $progress),
            8 => $this->processComplete($progress),
            default => false,
        };
    }

    public function uploadProfilePhoto(User $user, $photo): array
    {
        $profile = $user->landlordProfile ?? LandlordProfile::create(['user_id' => $user->id]);

        if ($profile->profile_photo_path) {
            Storage::disk('public')->delete($profile->profile_photo_path);
        }

        $path = $photo->store('profile-photos', 'public');
        $profile->update(['profile_photo_path' => $path]);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ];
    }

    public function resetForNewProperty(User $user): void
    {
        $progress = $user->getOrCreateOnboardingProgress();

        if ($progress->is_complete) {
            $progress->reset();
            $progress->start();
            $progress->completeStep(1);
            $progress->completeStep(2);
        }
    }

    public function storeLegacy(array $data, User $user): void
    {
        $progress = $user->getOrCreateOnboardingProgress();

        DB::transaction(function () use ($data, $user, $progress) {
            // Phase-47 STEP-DATA-DEPRECATE-2: Property is canonical and must be
            // captured — the structure spec resolves property_id and landlord_id
            // from it.
            $property = Property::create([
                'landlord_id' => $user->id,
                'name' => $data['propertyName'],
                'type' => $data['propertyType'],
            ]);

            $this->structureBuilder->build(
                BuildingStructureSpec::fromLegacyPayload($data, $property),
            );

            $progress->markComplete();
        });
    }

    private function getProfileStepProps(array $props, User $user): array
    {
        $props['profile'] = $user->landlordProfile;
        $props['user'] = [
            'name' => $user->name,
            'email' => $user->email,
            'mobile_number' => $user->mobile_number,
        ];

        return $props;
    }

    private function getPropertyStepProps(array $props, User $user): array
    {
        $props['existingProperty'] = $user->properties()->first();

        return $props;
    }

    private function getStructureStepProps(array $props, User $user): array
    {
        // Phase-47 STEP-DATA-DEPRECATE-2: read the canonical Property instead
        // of the step_data(3) JSON mirror. Onboarding creates exactly one
        // Property per landlord in step 3, so latest('id') is unambiguous.
        $props['property'] = Property::where('landlord_id', $user->id)
            ->latest('id')
            ->first();

        return $props;
    }

    private function getFinancialStepProps(array $props, User $user): array
    {
        $props['paymentConfig'] = $user->paymentConfiguration;

        return $props;
    }

    private function getTeamStepProps(array $props, User $user): array
    {
        $props['existingInvitations'] = Invitation::where('landlord_id', $user->id)->get();

        return $props;
    }

    private function getFirstTenantStepProps(array $props, User $user): array
    {
        $props['vacantUnits'] = Unit::where('landlord_id', $user->id)
            ->where('status', 'vacant')
            ->with('building.property')
            ->get()
            ->map(fn ($unit) => [
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'building_name' => $unit->building->name,
                'property_name' => $unit->building->property->name,
                'target_rent' => $unit->target_rent,
            ]);

        return $props;
    }

    private function getCompleteStepProps(array $props, User $user): array
    {
        $props['summary'] = [
            'properties' => $user->properties()->count(),
            'buildings' => Building::where('landlord_id', $user->id)->count(),
            'units' => Unit::where('landlord_id', $user->id)->count(),
            'hasProfile' => $user->landlordProfile !== null,
            'hasPaymentConfig' => $user->paymentConfiguration !== null,
        ];

        return $props;
    }

    /**
     * Phase-2a MANAGEMENT-CONTEXT: capture how the user runs PropManager and
     * provision the matching scope-owner role. manage_for_owners upgrades to
     * manager (the saved-hook stamps landlord_id = self); self_manage keeps
     * landlord. Idempotent — re-submitting never corrupts a settled role, and
     * only landlord/manager are ever touched.
     *
     * Runs inside OnboardingSessionService's transactional write path, so a
     * failed save never half-applies the role change.
     *
     * @param  array<string, mixed>  $data
     */
    private function processWelcome(array $data, User $user): bool
    {
        $context = $data['management_context'] ?? null;

        if ($context === 'manage_for_owners' && $user->role !== 'manager') {
            $user->role = 'manager';
            $user->save();
        } elseif ($context === 'self_manage' && in_array($user->role, ['landlord', 'manager'], true)) {
            $user->role = 'landlord';
            $user->save();
        }

        return true;
    }

    private function processProfile(array $data, User $user, OnboardingProgress $progress): bool
    {
        // Phase-47 LANDLORD-MIGRATE-2: canonical writes only. The User row +
        // LandlordProfile row are the source of truth; getProfileStepProps
        // reads them directly to repopulate the form on re-entry.
        $user->update([
            'name' => $data['name'],
            'mobile_number' => $data['mobile_number'] ?? null,
        ]);

        LandlordProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_name' => $data['company_name'] ?? null,
                'business_registration_number' => $data['business_registration_number'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? 'Kenya',
            ]
        );

        return true;
    }

    private function processProperty(array $data, User $user, OnboardingProgress $progress): bool
    {
        // Phase-47 LANDLORD-MIGRATE-3: Property is canonical. processStructure
        // resolves the Property via Property::latest('id') instead of
        // step_data(3).
        Property::updateOrCreate(
            [
                'landlord_id' => $user->id,
                'name' => $data['property_name'],
            ],
            [
                'type' => $data['property_type'],
                'address' => $data['property_address'] ?? null,
            ]
        );

        return true;
    }

    private function processStructure(array $data, User $user, OnboardingProgress $progress): bool
    {
        // Phase-47 STEP-DATA-DEPRECATE-2: read canonical Property instead of
        // step_data(3)['property_id']. Onboarding writes exactly one Property
        // per landlord at step 3, so latest('id') is unambiguous.
        $property = Property::where('landlord_id', $user->id)
            ->latest('id')
            ->first();

        if (! $property) {
            return false;
        }

        // Phase-47 STEP-DATA-DEPRECATE-3: canonical default_rent. The builder
        // throws a ValidationException when units carry leases/water readings,
        // so a structure re-edit can't silently destroy tenancy or billing data.
        $baseRent = (float) (PaymentConfiguration::where('landlord_id', $user->id)
            ->value('default_rent') ?? 10000);

        return $this->structureBuilder->replaceForProperty(
            BuildingStructureSpec::fromCanonicalStep($data, $property, $baseRent),
        );
    }

    private function processFinancial(array $data, User $user, OnboardingProgress $progress): bool
    {
        // Phase-47 STEP-DATA-DEPRECATE-3: capture old rent from canonical
        // PaymentConfiguration BEFORE the updateOrCreate so the Unit bulk
        // update sees the actual previous value.
        $oldRent = PaymentConfiguration::where('landlord_id', $user->id)
            ->value('default_rent') ?? 10000;

        PaymentConfiguration::updateOrCreate(
            ['landlord_id' => $user->id],
            [
                'default_rent' => $data['default_rent'],
                'water_billing_type' => $data['water_billing_type'],
                'flat_water_rate' => $data['flat_water_rate'] ?? null,
                'water_unit_rate' => $data['water_unit_rate'] ?? config('propmanager.water.default_rate', 150),
                'accepted_payment_methods' => $data['accepted_payment_methods'],
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account_name' => $data['bank_account_name'] ?? null,
                'bank_account_number' => $data['bank_account_number'] ?? null,
                'mpesa_paybill' => $data['mpesa_paybill'] ?? null,
            ]
        );

        // Opt-in only, skipped when the default is unchanged, and only vacant
        // never-leased units — an occupied/previously-leased unit's rent is deliberate.
        if (($data['apply_default_to_existing'] ?? false) && (float) $oldRent !== (float) $data['default_rent']) {
            $unitsUpdated = Unit::where('landlord_id', $user->id)
                ->where('target_rent', $oldRent)
                ->where('status', 'vacant')
                ->whereDoesntHave('leases')
                ->update(['target_rent' => $data['default_rent']]);

            Log::info('Onboarding bulk rent update', [
                'landlord_id' => $user->id,
                'old_rent' => $oldRent,
                'new_rent' => $data['default_rent'],
                'units_updated' => $unitsUpdated,
            ]);
        }

        // Phase-79 WATER-GATE: onboarding is where the landlord first chooses
        // whether to charge for water — bust the module-access cache.
        \App\Services\Water\WaterModuleAccess::forget((int) $user->id);

        return true;
    }

    private function processTeam(array $data, User $user, OnboardingProgress $progress): bool
    {
        $invitations = $data['invitations'] ?? [];

        foreach ($invitations as $inviteData) {
            $exists = Invitation::where('landlord_id', $user->id)
                ->where('email', $inviteData['email'])
                ->whereNull('accepted_at')
                ->exists();

            if (! $exists) {
                Invitation::create([
                    'landlord_id' => $user->id,
                    'email' => $inviteData['email'],
                    'property_id' => $inviteData['property_id'] ?? $user->properties()->first()?->id,
                    'token' => Invitation::generateToken(),
                ]);
            }
        }

        // Phase-47 LANDLORD-MIGRATE-5: Invitation rows are canonical.
        return true;
    }

    private function processFirstTenant(array $data, User $user, OnboardingProgress $progress): bool
    {
        $unit = Unit::where('id', $data['unit_id'])
            ->where('landlord_id', $user->id)
            ->first();

        if (! $unit) {
            return false;
        }

        TenantInvitation::create([
            'landlord_id' => $user->id,
            'initiated_by' => $user->id,
            'unit_id' => $unit->id,
            'email' => $data['tenant_email'],
            'tenant_name' => $data['tenant_name'] ?? null,
            'tenant_phone' => $data['tenant_phone'] ?? null,
            'rent_amount' => $data['rent_amount'],
            'deposit_amount' => $data['deposit_amount'],
            'service_charge' => 0,
            'start_date' => $data['start_date'],
            'token' => TenantInvitation::generateToken(),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
            'notification_channels' => ['email'],
        ]);

        // Phase-47 LANDLORD-MIGRATE-5: TenantInvitation rows are canonical.
        return true;
    }

    private function processComplete(OnboardingProgress $progress): bool
    {
        $progress->markComplete();

        return true;
    }
}
