<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Services\Growth\ReferralAttributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase-36 INSIGHT-API-2: API parity for the existing
 * /referrals/mine web-context endpoint. Same payload shape;
 * lives under /api/v1/landlord/ so mobile + Zapier-style
 * integrations can hit it with sanctum tokens.
 */
class ReferralApiController extends Controller
{
    public function __construct(
        private readonly ReferralAttributionService $service,
    ) {}

    public function index(Request $request): JsonResponse
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
