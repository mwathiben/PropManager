<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase-64 TELEMETRY-WIRE-1: receive client-side PWA gauge writes
 * (offline_writes_dead_letter_count, offline_photo_quota_evictions_
 * count, offline_shell_boot_count) so they reach the Prometheus
 * exporter via the existing MetricsService::gauge bridge.
 *
 * Allow-list enforced — arbitrary metric names cannot be injected
 * from the browser (Prometheus label cardinality DoS vector). Labels
 * keys validated against Prometheus label-name regex.
 */
class PwaTelemetryController extends Controller
{
    /**
     * Phase-62 Sub-scope follow-up gauges + future PWA gauges land
     * here. Anything not on this list is rejected at 422.
     *
     * @var array<int, string>
     */
    public const ALLOWED_METRICS = [
        'offline_writes_dead_letter_count',
        'offline_photo_quota_evictions_count',
        'offline_shell_boot_count',
    ];

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'metric' => ['required', 'string'],
            'value' => ['required', 'integer', 'min:0'],
            'labels' => ['nullable', 'array'],
            'labels.*' => ['string', 'max:120'],
        ]);

        if (! in_array($validated['metric'], self::ALLOWED_METRICS, true)) {
            return response()->json(['error' => 'unknown_metric'], 422);
        }

        $labels = $validated['labels'] ?? [];
        foreach (array_keys($labels) as $labelKey) {
            if (! is_string($labelKey) || preg_match('/^[a-z_][a-z0-9_]*$/', $labelKey) !== 1) {
                return response()->json(['error' => 'invalid_label_key'], 422);
            }
        }

        // Coerce labels to all-string values (Prometheus contract).
        $labels = array_map(static fn ($v) => (string) $v, $labels);

        app(MetricsService::class)->gauge(
            $validated['metric'],
            (int) $validated['value'],
            $labels,
        );

        return response()->json(null, 204);
    }
}
