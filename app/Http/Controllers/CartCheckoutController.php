<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CheckoutSession;
use App\Services\Checkout\CartCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase-42 CART-2: thin HTTP layer over CartCheckoutService.
 * The Vue tenant cart view (CART-3) is queued for the Phase 2
 * polish commit — this controller is the API contract it will
 * call.
 */
class CartCheckoutController extends Controller
{
    public function initialize(Request $request, CheckoutSession $session, CartCheckoutService $service): JsonResponse
    {
        // Tenants can only initialize their own checkout sessions;
        // landlords can initialize any session they own.
        $user = $request->user();
        $isOwner = $user !== null && (
            (int) $session->tenant_id === (int) $user->id
            || (int) $session->landlord_id === (int) $user->id
            || $user->isSuperAdmin()
        );

        abort_unless($isOwner, 403);
        abort_unless($session->isOpen(), 422, __('payments.cart.expired_session_message'));

        $groups = $service->initialize($session);

        return response()->json([
            'session_id' => $session->id,
            'status' => $session->fresh()?->status ?? $session->status,
            'currency_groups' => $groups,
        ]);
    }
}
