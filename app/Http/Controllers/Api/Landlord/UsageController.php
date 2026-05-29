<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Models\UsageRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase-36 INSIGHT-API-2: landlord-scoped usage + plan limit
 * snapshot for the current period.
 */
class UsageController extends Controller
{
    private const FEATURES = ['properties', 'units', 'caretakers', 'buildings'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $landlordId = $user->role === 'landlord' ? (int) $user->id : (int) $user->landlord_id;
        $landlord = User::find($landlordId);

        $features = [];
        foreach (self::FEATURES as $feature) {
            $limit = $landlord ? (int) $landlord->getLimit($feature) : 0;
            $usage = $landlord ? (int) $landlord->getUsage($feature) : 0;
            $ratio = $limit > 0 ? round($usage / $limit, 4) : 0.0;
            $features[] = [
                'feature' => $feature,
                'usage' => $usage,
                'limit' => $limit,
                'ratio' => $ratio,
            ];
        }

        $period = UsageRecord::currentPeriod();

        return response()->json([
            'period_start' => $period['start']->toDateString(),
            'period_end' => $period['end']->toDateString(),
            'features' => $features,
        ]);
    }
}
