<?php

declare(strict_types=1);

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Services\Growth\ReferralAttributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase-34 GROWTH-REFERRAL-2: landlord self-serve referral surface.
 *
 *   POST /referrals/redeem  — accept a code at signup or any time
 *                              after; idempotent + race-safe.
 *   GET  /referrals/mine    — landlord's own code + their referral
 *                              ledger (per-landlord, NOT super_admin).
 */
class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralAttributionService $service,
    ) {}

    public function redeem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|min:4|max:16',
        ]);

        $user = $request->user();
        $referral = $this->service->redeem($user, $validated['code']);

        if (! $referral) {
            return response()->json([
                'error' => 'invalid_code',
                'message' => 'Referral code not recognised or self-referral attempted.',
            ], 422);
        }

        return response()->json([
            'referral_id' => $referral->id,
            'status' => $referral->status,
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->referral_code) {
            $this->service->generateCodeFor($user);
            $user->refresh();
        }

        $referrals = Referral::query()
            ->where('referrer_user_id', $user->id)
            ->latest()
            ->limit(50)
            ->get(['id', 'referred_user_id', 'status', 'attributed_at', 'created_at']);

        return response()->json([
            'referral_code' => $user->referral_code,
            'referrals' => $referrals,
            'counts' => [
                'pending' => $referrals->where('status', Referral::STATUS_PENDING)->count(),
                'attributed' => $referrals->where('status', Referral::STATUS_ATTRIBUTED)->count(),
                'rewarded' => $referrals->where('status', Referral::STATUS_REWARDED)->count(),
            ],
        ]);
    }
}
