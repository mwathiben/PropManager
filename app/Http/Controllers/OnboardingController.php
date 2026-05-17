<?php

namespace App\Http\Controllers;

use App\Models\CaretakerAssignment;
use App\Models\OnboardingProgress;
use App\Models\OnboardingSession;
use App\Models\User;
use App\Onboarding\OnboardingFlow;
use App\Services\Onboarding\CaretakerOnboardingService;
use App\Services\Onboarding\OnboardingSessionService;
use App\Services\Onboarding\OnboardingStepProcessor;
use App\Services\Onboarding\TenantOnboardingService;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

class OnboardingController extends Controller
{
    public function __construct(
        protected OnboardingService $onboardingService,
        protected TenantOnboardingService $tenantOnboardingService,
        protected CaretakerOnboardingService $caretakerOnboardingService,
        protected OnboardingSessionService $sessionService,
    ) {}

    private function processorForUser(User $user): OnboardingStepProcessor
    {
        return match ($user->role) {
            'tenant' => $this->tenantOnboardingService,
            'caretaker' => $this->caretakerOnboardingService,
            default => $this->onboardingService,
        };
    }

    public function index()
    {
        $user = auth()->user();
        $progress = $user->getOrCreateOnboardingProgress();

        if ($progress->is_complete) {
            return redirect()->route('dashboard');
        }

        $progress->start();

        return redirect()->route('onboarding.step', ['step' => $progress->current_step]);
    }

    public function step(int $step)
    {
        $user = auth()->user();
        $progress = $user->getOrCreateOnboardingProgress();
        // Phase-47 ROLE-DISPATCH-1: validate step bounds against the user's
        // per-role OnboardingFlow rather than the legacy hardcoded
        // OnboardingProgress.total_steps (which is always 8).
        $flow = OnboardingFlow::forRole($user->role ?? 'landlord');

        if (! $flow->isValidStep($step)) {
            return redirect()->route('onboarding.index');
        }

        $props = $this->onboardingService->getStepProps($step, $user, $progress);

        // Phase-51 TENANT-WIZARD-POLISH-1/3: inject role-specific step props
        // the role-dispatched Vue components need (caretaker step 2 pending
        // assignments list; tenant step 2 KYC progress snapshot).
        if ($user->role === 'caretaker' && $step === 2) {
            $props['pendingAssignments'] = CaretakerAssignment::query()
                ->where('caretaker_id', $user->id)
                ->where('status', CaretakerAssignment::STATUS_PENDING)
                ->with('building:id,name')
                ->get(['id', 'building_id', 'created_at'])
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'building_id' => $a->building_id,
                    'building_name' => $a->building?->name ?? "Building #{$a->building_id}",
                    'created_at' => $a->created_at?->toIso8601String(),
                ])->all();
        }

        if ($user->role === 'tenant' && $step === 2) {
            $props['kycProgress'] = $user->kycProgress();
        }

        return Inertia::render('Onboarding/Index', $props);
    }

    public function saveStep(Request $request, int $step)
    {
        $user = auth()->user();
        $progress = $user->getOrCreateOnboardingProgress();
        $session = OnboardingSession::firstFor($user);
        $flow = OnboardingFlow::forRole($user->role ?? 'landlord');

        if (! $flow->isValidStep($step)) {
            return redirect()->route('onboarding.index');
        }

        // VALID-3: pass only the validated subset to the service so unknown
        // attacker-injected fields can't ride along into model creation.
        $validated = $this->validateStep($request, $step);

        // Phase-47 LANDLORD-MIGRATE-1: canonical writes are routed through
        // OnboardingSessionService so a writer that throws does NOT advance
        // the session nor commit half-canonical state. The session helper
        // wraps the writer in DB::transaction; on success the wizard cursor
        // moves forward + step_history captures the transition.
        // Phase-47 ROLE-DISPATCH-2/3: route to the per-role step processor.
        $processor = $this->processorForUser($user);
        $writer = fn () => $processor->processStep($step, $validated, $user, $progress);
        $nextStep = $flow->nextStep($step);

        try {
            if ($nextStep !== null && $step >= $session->current_step) {
                // Forward progress: advance the session to next step.
                $result = true;
                $this->sessionService->advance($session, $nextStep, function () use ($writer, &$result) {
                    $result = $writer();
                    if ($result === false) {
                        throw new \RuntimeException('writer returned false');
                    }
                });
            } else {
                // Re-edit a past step OR final step: write but don't advance.
                $result = $this->sessionService->writeAt($session, $writer);
            }
        } catch (Throwable $e) {
            return back()->withErrors(['error' => 'Failed to save step data.']);
        }

        if ($result === false) {
            return back()->withErrors(['error' => 'Failed to save step data.']);
        }

        $progress->completeStep($step);

        if ($step >= $flow->lastStep()) {
            $progress->markComplete();
            if ($session->isActive()) {
                $this->sessionService->complete($session);
            }

            return redirect()->route('dashboard');
        }

        return redirect()->route('onboarding.step', ['step' => $nextStep ?? $step + 1]);
    }

    public function skip(int $step)
    {
        $user = auth()->user();
        $progress = $user->getOrCreateOnboardingProgress();
        $flow = OnboardingFlow::forRole($user->role ?? 'landlord');

        if (! OnboardingProgress::isStepOptional($step)) {
            return back()->withErrors(['error' => 'This step cannot be skipped.']);
        }

        $progress->skipStep($step);

        if ($step >= $flow->lastStep()) {
            $progress->markComplete();

            return redirect()->route('dashboard');
        }

        $nextStep = $flow->nextStep($step) ?? $step + 1;

        return redirect()->route('onboarding.step', ['step' => $nextStep]);
    }

    public function complete()
    {
        $user = auth()->user();
        $progress = $user->getOrCreateOnboardingProgress();
        $progress->markComplete();

        return redirect()->route('dashboard');
    }

    public function reset()
    {
        $user = auth()->user();
        $progress = $user->onboardingProgress;

        if ($progress) {
            $progress->reset();
        }

        return redirect()->route('onboarding.index');
    }

    public function getProgress()
    {
        $user = auth()->user();

        return response()->json($this->onboardingService->getProgress($user));
    }

    public function uploadProfilePhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|max:2048',
        ]);

        $result = $this->onboardingService->uploadProfilePhoto(auth()->user(), $request->file('photo'));

        return response()->json($result);
    }

    public function create()
    {
        $user = auth()->user();
        $progress = $user->getOrCreateOnboardingProgress();

        if ($progress->is_complete) {
            $this->onboardingService->resetForNewProperty($user);

            return redirect()->route('onboarding.step', ['step' => 3]);
        }

        return $this->index();
    }

    public function store(Request $request)
    {
        $request->validate([
            'propertyName' => 'required|string|max:255',
            'propertyType' => 'required|string',
            'hasWings' => 'boolean',
            'floors' => 'required_if:hasWings,false|integer|min:1|max:100',
            'unitsPerFloor' => 'required_if:hasWings,false|integer|min:1|max:50',
            'baseRent' => 'required|numeric|min:0',
            'wings' => 'required_if:hasWings,true|array',
        ]);

        $this->onboardingService->storeLegacy($request->all(), auth()->user());

        return redirect()->route('dashboard');
    }

    private function validateStep(Request $request, int $step): array
    {
        $role = auth()->user()->role ?? 'landlord';
        if ($role === 'tenant') {
            return $this->validateTenantStep($request, $step);
        }
        if ($role === 'caretaker') {
            return $this->validateCaretakerStep($request, $step);
        }

        $rules = match ($step) {
            2 => [
                'name' => 'required|string|max:255',
                'mobile_number' => 'nullable|string|max:20',
                'company_name' => 'nullable|string|max:255',
                'business_registration_number' => 'nullable|string|max:100',
                'address' => 'nullable|string|max:500',
                'city' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
            ],
            3 => [
                'property_name' => 'required|string|max:255',
                'property_type' => 'required|in:residential,estate,commercial,mixed',
                'property_address' => 'nullable|string|max:500',
            ],
            4 => [
                'has_wings' => 'boolean',
                'floors' => 'required_if:has_wings,false|integer|min:1|max:100',
                'units_per_floor' => 'required_if:has_wings,false|integer|min:1|max:50',
                'wings' => 'required_if:has_wings,true|array',
                'wings.*.name' => 'required_with:wings|string|max:255',
                'wings.*.prefix' => 'required_with:wings|string|max:3',
                'wings.*.floors' => 'required_with:wings|integer|min:1|max:100',
                'wings.*.units_per_floor' => 'required_with:wings|integer|min:1|max:50',
            ],
            5 => [
                'default_rent' => 'required|numeric|min:0',
                'water_billing_type' => 'required|in:consumption,flat_rate,none',
                'flat_water_rate' => 'nullable|numeric|min:0',
                'water_unit_rate' => 'nullable|numeric|min:0',
                'accepted_payment_methods' => 'required|array|min:1',
                'accepted_payment_methods.*' => 'in:cash,bank_transfer,mobile_money,paystack',
                'bank_name' => 'nullable|string|max:255',
                'bank_account_name' => 'nullable|string|max:255',
                'bank_account_number' => 'nullable|string|max:50',
                'mpesa_paybill' => 'nullable|string|max:20',
            ],
            6 => [
                'invitations' => 'nullable|array',
                'invitations.*.email' => 'required_with:invitations|email',
                // VALID-3: scope property_id to the onboarding landlord so a
                // hostile signed-up landlord can't plant invitations on a
                // competitor's property tree.
                'invitations.*.property_id' => [
                    'nullable',
                    \Illuminate\Validation\Rule::exists('properties', 'id')
                        ->where('landlord_id', auth()->id()),
                ],
            ],
            7 => [
                // VALID-3: same scope on unit_id. Pre-fix, a logged-in landlord
                // could submit step 7 with another landlord's unit_id and
                // attach victim tenants to attacker-chosen units.
                'unit_id' => [
                    'required',
                    \Illuminate\Validation\Rule::exists('units', 'id')
                        ->where('landlord_id', auth()->id()),
                ],
                'tenant_email' => 'required|email',
                'tenant_name' => 'nullable|string|max:255',
                'tenant_phone' => 'nullable|string|max:20',
                // VALID-8: explicit money-field max — see StorePaymentRequest
                // for the same pattern. decimal:0,2 rejects scientific notation.
                'rent_amount' => ['required', 'decimal:0,2', 'min:0', 'max:9999999.99'],
                'deposit_amount' => ['required', 'decimal:0,2', 'min:0', 'max:9999999.99'],
                'start_date' => 'required|date|after_or_equal:today',
            ],
            default => [],
        };

        if (empty($rules)) {
            return $request->only([]);
        }

        return $request->validate($rules);
    }

    private function validateTenantStep(Request $request, int $step): array
    {
        $rules = match ($step) {
            1 => [
                'name' => 'required|string|max:255',
                'mobile_number' => 'nullable|string|max:20',
                'national_id' => 'nullable|string|max:32',
            ],
            2 => [
                'acknowledged' => 'nullable|boolean',
            ],
            3 => [
                // Phase-48 TENANT-PAYMENT-METHOD-3: persistable shape.
                // type + details required-together; absence means
                // acknowledgement-only (still advances).
                'type' => 'nullable|in:mpesa,bank,card',
                'details' => 'nullable|required_with:type|array',
                'details.phone' => 'required_if:type,mpesa|string|max:20',
                'details.bank_name' => 'required_if:type,bank|string|max:255',
                'details.account_number' => 'required_if:type,bank|string|max:50',
                'details.account_name' => 'required_if:type,bank|string|max:255',
                'details.brand' => 'nullable|string|max:50',
                'details.last4' => 'nullable|string|size:4',
                'details.stripe_payment_method_id' => 'nullable|string|max:64',
                'is_default' => 'nullable|boolean',
                'acknowledged' => 'nullable|boolean',
            ],
            default => [],
        };

        if (empty($rules)) {
            return $request->only([]);
        }

        return $request->validate($rules);
    }

    private function validateCaretakerStep(Request $request, int $step): array
    {
        $rules = match ($step) {
            1 => [
                'name' => 'required|string|max:255',
                'mobile_number' => 'nullable|string|max:20',
            ],
            2 => [
                'acknowledged' => 'nullable|boolean',
                // Phase-48 CARETAKER-ASSIGNMENT-UX-3: explicit accept/decline.
                'decline' => 'nullable|array',
                'decline.*' => 'integer',
                'decline_reason' => 'nullable|array',
                'decline_reason.*' => 'nullable|string|max:255',
            ],
            3 => [
                'email_enabled' => 'nullable|boolean',
                'sms_enabled' => 'nullable|boolean',
                'whatsapp_enabled' => 'nullable|boolean',
                'push_enabled' => 'nullable|boolean',
                // Phase-48 CARETAKER-NOTIF-PREFS-2: per-type granularity.
                'maintenance_notice_enabled' => 'nullable|boolean',
                'general_enabled' => 'nullable|boolean',
                'caretaker_invitation_enabled' => 'nullable|boolean',
                'tenant_invitation_enabled' => 'nullable|boolean',
                'lease_expiry_enabled' => 'nullable|boolean',
            ],
            default => [],
        };

        if (empty($rules)) {
            return $request->only([]);
        }

        return $request->validate($rules);
    }
}
