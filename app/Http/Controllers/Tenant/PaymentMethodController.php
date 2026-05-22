<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantPaymentMethodRequest;
use App\Models\TenantPaymentMethod;
use App\Services\Tenant\TenantPaymentMethodService;
use Inertia\Inertia;

/**
 * Phase-84 PAY-METHODS: tenant self-management of saved payment methods. Phase 48
 * created the model + service but exposed them only at onboarding; this is the
 * post-onboarding portal surface. Self-scoped to the authenticated tenant —
 * details are returned MASKED (the raw encrypted blob never leaves the server).
 */
class PaymentMethodController extends Controller
{
    public function index()
    {
        $methods = TenantPaymentMethod::where('user_id', auth()->id())
            ->orderByDesc('is_default')
            ->orderBy('type')
            ->get()
            ->map(fn (TenantPaymentMethod $m) => [
                'id' => $m->id,
                'type' => $m->type,
                'is_default' => $m->is_default,
                'summary' => $this->mask($m),
            ])
            ->all();

        return Inertia::render('Tenant/PaymentMethods', [
            'methods' => $methods,
        ]);
    }

    public function store(StoreTenantPaymentMethodRequest $request, TenantPaymentMethodService $service)
    {
        $validated = $request->validated();

        $service->store(
            auth()->user(),
            $validated['type'],
            $this->detailsFor($validated),
            (bool) ($validated['is_default'] ?? false),
        );

        return back()->with('success', __('tenant_payment_method.added'));
    }

    public function setDefault(TenantPaymentMethod $paymentMethod, TenantPaymentMethodService $service)
    {
        abort_unless($paymentMethod->user_id === auth()->id(), 403);

        $service->setDefault($paymentMethod);

        return back()->with('success', __('tenant_payment_method.default_set'));
    }

    public function destroy(TenantPaymentMethod $paymentMethod, TenantPaymentMethodService $service)
    {
        abort_unless($paymentMethod->user_id === auth()->id(), 403);

        $service->softDelete($paymentMethod);

        return back()->with('success', __('tenant_payment_method.removed'));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function detailsFor(array $validated): array
    {
        return match ($validated['type']) {
            'mpesa' => ['phone' => $validated['phone']],
            'bank' => [
                'bank_name' => $validated['bank_name'],
                'account_name' => $validated['account_name'],
                'account_number' => $validated['account_number'],
            ],
            'card' => [
                'brand' => $validated['brand'],
                'last4' => $validated['last4'],
            ],
            default => [],
        };
    }

    private function mask(TenantPaymentMethod $method): string
    {
        $d = $method->details_encrypted ?? [];

        return match ($method->type) {
            'mpesa' => $this->tail((string) ($d['phone'] ?? '')),
            'bank' => ($d['bank_name'] ?? '').' ••••'.substr((string) ($d['account_number'] ?? ''), -4),
            'card' => ucfirst((string) ($d['brand'] ?? 'card')).' ••••'.($d['last4'] ?? ''),
            default => '',
        };
    }

    private function tail(string $value): string
    {
        return $value === '' ? '' : '••••'.substr($value, -4);
    }
}
