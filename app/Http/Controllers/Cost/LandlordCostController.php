<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cost;

use App\Http\Controllers\Controller;
use App\Models\LandlordUsageMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase-33 COST-ATTRIB-3: top-N costliest landlords surface for the
 * ops dashboard. Super-admin only. Returns same per-unit math as
 * cost:attribute cron so the dashboard and the gauge agree.
 */
class LandlordCostController extends Controller
{
    public function topN(Request $request): JsonResponse
    {
        $days = max(1, min(90, (int) $request->query('days', 30)));
        $limit = max(1, min(50, (int) $request->query('limit', 10)));
        $rates = (array) config('cost.rates');

        $cutoff = now()->subDays($days)->toDateString();
        $rows = DB::table('landlord_usage_metrics')
            ->select('landlord_id', 'metric', DB::raw('SUM(value) as total'))
            ->where('day', '>=', $cutoff)
            ->groupBy('landlord_id', 'metric')
            ->get();

        $byLandlord = [];
        foreach ($rows as $row) {
            $byLandlord[$row->landlord_id]['metrics'][$row->metric] = (int) $row->total;
            $byLandlord[$row->landlord_id]['cost_kes'] = ($byLandlord[$row->landlord_id]['cost_kes'] ?? 0.0)
                + $this->kesFor((string) $row->metric, (int) $row->total, $rates);
        }

        uasort($byLandlord, fn ($a, $b) => $b['cost_kes'] <=> $a['cost_kes']);

        $top = array_slice($byLandlord, 0, $limit, preserve_keys: true);

        $data = [];
        foreach ($top as $landlordId => $payload) {
            $data[] = [
                'landlord_id' => (int) $landlordId,
                'cost_kes' => round($payload['cost_kes'], 2),
                'metrics' => $payload['metrics'] ?? [],
            ];
        }

        return response()->json([
            'window_days' => $days,
            'landlords' => $data,
        ]);
    }

    private function kesFor(string $metric, int $value, array $rates): float
    {
        return match ($metric) {
            LandlordUsageMetric::METRIC_DB_QUERIES => ($value / 1_000_000) * (float) ($rates['kes_per_million_queries'] ?? 0),
            LandlordUsageMetric::METRIC_S3_BYTES => ($value / (1024 ** 3)) * (float) ($rates['kes_per_gb_s3_standard'] ?? 0),
            LandlordUsageMetric::METRIC_SMS_SENDS => $value * (float) ($rates['kes_per_sms'] ?? 0),
            LandlordUsageMetric::METRIC_CRON_MINUTES => $value * (float) ($rates['kes_per_cron_minute'] ?? 0),
            LandlordUsageMetric::METRIC_LOG_BYTES => ($value / (1024 ** 2)) * (float) ($rates['kes_per_mb_log'] ?? 0),
            default => 0.0,
        };
    }
}
