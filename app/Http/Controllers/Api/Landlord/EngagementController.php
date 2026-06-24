<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Models\LandlordEngagementScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase-36 INSIGHT-API-1: landlord-scoped engagement history.
 *
 * GET /api/v1/landlord/engagement?days=30 — JSON timeseries.
 * GET /api/v1/landlord/engagement/export.csv — INSIGHT-EXPORTS-2.
 */
class EngagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $days = max(1, min(365, (int) $request->query('days', 30)));
        $landlordId = $this->resolveLandlordId($request);

        $rows = LandlordEngagementScore::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->where('day', '>=', now()->subDays($days)->toDateString())
            ->orderBy('day', 'desc')
            ->get(['day', 'score', 'components']);

        return response()->json([
            'window_days' => $days,
            'scores' => $rows,
        ]);
    }

    /**
     * Phase-36 INSIGHT-EXPORTS-2: csv stream, last 90 days fixed.
     */
    public function export(Request $request): StreamedResponse
    {
        $landlordId = $this->resolveLandlordId($request);

        $rows = LandlordEngagementScore::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->where('day', '>=', now()->subDays(90)->toDateString())
            ->orderBy('day', 'desc')
            ->get(['day', 'score', 'components']);

        $filename = 'engagement-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['day', 'score', 'login', 'milestones', 'usage', 'property', 'tenant']);
            foreach ($rows as $row) {
                $c = $row->components ?? [];
                fputcsv($out, [
                    $row->day?->format('Y-m-d'),
                    $row->score,
                    $c['login'] ?? '',
                    $c['milestones'] ?? '',
                    $c['usage'] ?? '',
                    $c['property'] ?? '',
                    $c['tenant'] ?? '',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function resolveLandlordId(Request $request): int
    {
        $user = $request->user();

        return $user->effectiveScopeId();
    }
}
