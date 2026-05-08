<?php

namespace App\Http\Controllers;

use App\Models\OnboardingProgress;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function __construct(
        protected OnboardingService $onboardingService
    ) {}

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

        if ($step < 1 || $step > $progress->total_steps) {
            return redirect()->route('onboarding.index');
        }

        $props = $this->onboardingService->getStepProps($step, $user, $progress);

        return Inertia::render('Onboarding/Index', $props);
    }

    public function saveStep(Request $request, int $step)
    {
        $user = auth()->user();
        $progress = $user->getOrCreateOnboardingProgress();

        $this->validateStep($request, $step);

        $result = $this->onboardingService->processStep($step, $request->all(), $user, $progress);

        if ($result === false) {
            return back()->withErrors(['error' => 'Failed to save step data.']);
        }

        $progress->completeStep($step);

        if ($step >= $progress->total_steps) {
            $progress->markComplete();

            return redirect()->route('dashboard');
        }

        return redirect()->route('onboarding.step', ['step' => $step + 1]);
    }

    public function skip(int $step)
    {
        $user = auth()->user();
        $progress = $user->getOrCreateOnboardingProgress();

        if (! OnboardingProgress::isStepOptional($step)) {
            return back()->withErrors(['error' => 'This step cannot be skipped.']);
        }

        $progress->skipStep($step);

        if ($step >= $progress->total_steps) {
            $progress->markComplete();

            return redirect()->route('dashboard');
        }

        return redirect()->route('onboarding.step', ['step' => $step + 1]);
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

    private function validateStep(Request $request, int $step): void
    {
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
                'invitations.*.property_id' => 'nullable|exists:properties,id',
            ],
            7 => [
                'unit_id' => 'required|exists:units,id',
                'tenant_email' => 'required|email',
                'tenant_name' => 'nullable|string|max:255',
                'tenant_phone' => 'nullable|string|max:20',
                'rent_amount' => 'required|numeric|min:0',
                'deposit_amount' => 'required|numeric|min:0',
                'start_date' => 'required|date|after_or_equal:today',
            ],
            default => [],
        };

        if (! empty($rules)) {
            $request->validate($rules);
        }
    }
}
