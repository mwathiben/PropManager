<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\LandlordDashboard;
use Illuminate\Validation\ValidationException;

/**
 * Phase-50 LANDLORD-DASHBOARDS-2 + Phase-74 CARD-REGISTRY: assemble a dashboard
 * render payload by delegating each card to its registered renderer
 * (DashboardCardRegistry). Card-type logic + per-card landlord-ownership
 * validation now live in the renderers (App\Services\Reports\Cards\*); this
 * service owns the layout loop + fail-closed envelope only.
 *
 * Every card is re-validated for landlord ownership at render time — the layout
 * JSON is opaque storage and could have been tampered with via support edits /
 * direct DB writes. Anything malformed throws ValidationException so the
 * dashboard fails closed instead of silently dropping cards.
 */
class DashboardService
{
    public function __construct(
        protected DashboardCardRegistry $registry,
    ) {}

    /**
     * @return array{
     *   dashboard: array{id: int, slug: string, name: string, description: ?string},
     *   cards: list<array<string, mixed>>
     * }
     */
    public function buildPayload(LandlordDashboard $dashboard): array
    {
        try {
            return $this->buildPayloadInner($dashboard);
        } catch (\Throwable $e) {
            // Phase-53 GAUGE-WIRING-2: count card-render failures so the sev3
            // report_render_failure_count alert fires when a card wedges.
            try {
                app(\App\Services\MetricsService::class)
                    ->increment('report_render_failure_count', 1, ['surface' => 'dashboard']);
            } catch (\Throwable) {
                // best-effort
            }
            throw $e;
        }
    }

    /**
     * @return array{
     *   dashboard: array{id: int, slug: string, name: string, description: ?string},
     *   cards: list<array<string, mixed>>
     * }
     */
    private function buildPayloadInner(LandlordDashboard $dashboard): array
    {
        $landlordId = (int) $dashboard->landlord_id;
        $layout = $dashboard->layout ?? [];
        if (! is_array($layout)) {
            throw ValidationException::withMessages(['layout' => 'Dashboard layout is malformed.']);
        }

        $cards = [];
        foreach ($layout as $i => $cardRaw) {
            $cards[] = $this->resolve((int) $i, $cardRaw)->render((int) $i, $cardRaw, $landlordId);
        }

        return [
            'dashboard' => [
                'id' => $dashboard->id,
                'slug' => $dashboard->slug,
                'name' => $dashboard->name,
                'description' => $dashboard->description,
            ],
            'cards' => $cards,
        ];
    }

    /**
     * Phase-73 DASHBOARD-EDITOR: validate a posted layout for landlord
     * ownership + structure WITHOUT running the reports, returning the
     * normalised card list to persist. Fail-closed (throws on any bad card).
     *
     * @param  array<int, mixed>  $layout
     * @return list<array<string, mixed>>
     */
    public function validateLayout(array $layout, int $landlordId): array
    {
        $normalised = [];
        foreach ($layout as $i => $cardRaw) {
            $normalised[] = $this->resolve((int) $i, $cardRaw)->validate((int) $i, $cardRaw, $landlordId);
        }

        return $normalised;
    }

    /**
     * Resolve a raw card to its renderer, validating the envelope (must be an
     * object with a registered type) before delegating.
     *
     * @param  mixed  $cardRaw
     */
    private function resolve(int $index, $cardRaw): Cards\DashboardCardRenderer
    {
        if (! is_array($cardRaw)) {
            throw ValidationException::withMessages(["layout.{$index}" => 'Card must be an object.']);
        }

        $type = $cardRaw['type'] ?? null;
        if (! is_string($type)) {
            throw ValidationException::withMessages(["layout.{$index}.type" => 'Card type is required.']);
        }

        return $this->registry->get($index, $type);
    }
}
