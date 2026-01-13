<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Services\PaystackSubscriptionService;
use App\Services\SubscriptionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    protected SubscriptionService $subscriptionService;

    protected PaystackSubscriptionService $paystackService;

    public function __construct(
        SubscriptionService $subscriptionService,
        PaystackSubscriptionService $paystackService
    ) {
        $this->subscriptionService = $subscriptionService;
        $this->paystackService = $paystackService;
    }

    /**
     * Show subscription management page.
     */
    public function index(): Response
    {
        $user = auth()->user();

        // Only landlords can manage subscriptions
        if ($user->role !== 'landlord') {
            abort(403, 'Only landlords can manage subscriptions.');
        }

        $subscription = $user->subscription?->load('plan');
        $plans = SubscriptionPlan::active()->get();
        $payments = $user->subscriptionPayments()
            ->with('subscription.plan')
            ->latest()
            ->take(10)
            ->get();

        // Calculate current usage
        $usage = [
            'properties' => [
                'current' => $user->properties()->count(),
                'limit' => $user->getLimit('properties'),
            ],
            'buildings' => [
                'current' => Building::where('landlord_id', $user->id)->count(),
                'limit' => $user->getLimit('buildings'),
            ],
            'units' => [
                'current' => Unit::where('landlord_id', $user->id)->count(),
                'limit' => $user->getLimit('units'),
            ],
            'caretakers' => [
                'current' => $user->caretakers()->count(),
                'limit' => $user->getLimit('caretakers'),
            ],
        ];

        return Inertia::render('Subscription/Index', [
            'subscription' => $subscription,
            'currentPlan' => $subscription?->plan ?? SubscriptionPlan::free(),
            'plans' => $plans,
            'payments' => $payments,
            'usage' => $usage,
            'paystackPublicKey' => $this->paystackService->getPublicKey(),
            'paystackConfigured' => $this->paystackService->isConfigured(),
        ]);
    }

    /**
     * Show available plans for comparison.
     */
    public function plans(): Response
    {
        $user = auth()->user();

        if ($user->role !== 'landlord') {
            abort(403);
        }

        $plans = SubscriptionPlan::active()->get()->map(function ($plan) {
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price_monthly' => $plan->price_monthly,
                'price_yearly' => $plan->price_yearly,
                'yearly_savings' => $plan->yearly_savings,
                'yearly_savings_percent' => $plan->yearly_savings_percent,
                'features' => $plan->getFeaturesList(),
                'is_free' => $plan->isFree(),
            ];
        });

        $currentPlan = $user->subscription?->plan;

        return Inertia::render('Subscription/Plans', [
            'plans' => $plans,
            'currentPlan' => $currentPlan,
            'billingCycle' => $user->subscription?->billing_cycle ?? 'monthly',
        ]);
    }

    /**
     * Initialize subscription to a plan.
     */
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $user = auth()->user();

        if ($user->role !== 'landlord') {
            return back()->with('error', 'Only landlords can subscribe.');
        }

        $plan = SubscriptionPlan::findOrFail($validated['plan_id']);

        // Free plan - no payment needed
        if ($plan->isFree()) {
            $this->subscriptionService->create($user, $plan, 'monthly', false);

            return redirect()->route('subscription.index')
                ->with('success', 'Successfully subscribed to '.$plan->name);
        }

        // Check if Paystack is configured
        if (! $this->paystackService->isConfigured()) {
            return back()->with('error', 'Payment system is not configured. Please contact support.');
        }

        // Initialize Paystack payment
        $paymentData = $this->paystackService->initializePayment(
            $user,
            $plan,
            $validated['billing_cycle']
        );

        if (! $paymentData) {
            return back()->with('error', 'Failed to initialize payment. Please try again.');
        }

        return response()->json([
            'authorization_url' => $paymentData['authorization_url'],
            'reference' => $paymentData['reference'],
        ]);
    }

    /**
     * Handle Paystack callback.
     */
    public function callback(Request $request)
    {
        $reference = $request->query('reference');

        if (! $reference) {
            return redirect()->route('subscription.index')
                ->with('error', 'Payment reference not found.');
        }

        $verification = $this->paystackService->verifyPayment($reference);

        if (! $verification || ($verification['data']['status'] ?? '') !== 'success') {
            Log::warning('Subscription payment verification failed', [
                'reference' => $reference,
                'verification' => $verification,
            ]);

            return redirect()->route('subscription.index')
                ->with('error', 'Payment verification failed. If you were charged, please contact support.');
        }

        $metadata = $verification['data']['metadata'] ?? [];
        $userId = $metadata['user_id'] ?? null;
        $planId = $metadata['plan_id'] ?? null;
        $billingCycle = $metadata['billing_cycle'] ?? 'monthly';

        if (! $userId || ! $planId) {
            return redirect()->route('subscription.index')
                ->with('error', 'Invalid payment metadata. Please contact support.');
        }

        $user = User::findOrFail($userId);
        $plan = SubscriptionPlan::findOrFail($planId);

        // Create subscription without trial since they paid
        $subscription = $this->subscriptionService->create(
            $user,
            $plan,
            $billingCycle,
            false
        );

        // Record the payment
        $this->subscriptionService->recordPayment($subscription, [
            'amount' => $verification['data']['amount'] / 100,
            'currency' => $verification['data']['currency'],
            'payment_method' => 'paystack',
            'reference' => $reference,
            'paystack_reference' => $reference,
            'paystack_response' => $verification['data'],
        ]);

        return redirect()->route('subscription.index')
            ->with('success', 'Successfully subscribed to '.$plan->name.'!');
    }

    /**
     * Cancel subscription.
     */
    public function cancel(Request $request)
    {
        $user = auth()->user();
        $subscription = $user->subscription;

        if (! $subscription) {
            return back()->with('error', 'No active subscription found.');
        }

        $immediately = $request->boolean('immediately', false);
        $this->subscriptionService->cancel($subscription, $immediately);

        $message = $immediately
            ? 'Subscription cancelled immediately.'
            : 'Subscription will end on '.$subscription->current_period_end->format('M j, Y').'.';

        return back()->with('success', $message);
    }

    /**
     * Resume cancelled subscription.
     */
    public function resume()
    {
        $user = auth()->user();
        $subscription = $user->subscription;

        if (! $subscription?->onGracePeriod()) {
            return back()->with('error', 'Cannot resume subscription.');
        }

        $this->subscriptionService->resume($subscription);

        return back()->with('success', 'Subscription resumed successfully.');
    }

    /**
     * Download payment receipt/invoice.
     */
    public function downloadInvoice(SubscriptionPayment $payment)
    {
        $user = auth()->user();

        // Check authorization
        if ($payment->user_id !== $user->id && ! $user->isSuperAdmin()) {
            abort(403);
        }

        $payment->load(['subscription.plan', 'user']);

        $pdf = Pdf::loadView('receipts.subscription-receipt', [
            'payment' => $payment,
        ]);

        return $pdf->download('subscription-receipt-'.$payment->reference.'.pdf');
    }
}
