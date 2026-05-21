<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LandlordDashboard;
use App\Services\MetricsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardPreferenceController extends Controller
{
    public const MAIN_DASHBOARD_SLUG = 'main_dashboard';

    public const ALLOWED_WIDGETS = [
        'recent-payments',
        'recent-tickets',
        'expiring-leases',
    ];

    public const SCOPES = ['active_building', 'all_buildings'];

    public const DEFAULT_SCOPE = 'active_building';

    public function update(Request $request): RedirectResponse
    {
        $landlord = $request->user();
        abort_unless($landlord !== null && $landlord->isLandlord(), 403);

        $validated = $request->validate([
            'widget_order' => ['required', 'array', 'min:1', 'max:'.count(self::ALLOWED_WIDGETS)],
            'widget_order.*' => ['string', Rule::in(self::ALLOWED_WIDGETS)],
        ]);

        $order = array_values(array_unique($validated['widget_order']));

        // Preserve any persisted scope so re-ordering widgets doesn't reset it.
        $this->persist($landlord->id, $order, $this->scopeFrom($this->layoutFor($landlord->id)));

        return back();
    }

    /**
     * Phase-74 CROSS-BUILDING-1: persist the main dashboard's building scope on
     * the same main_dashboard row, preserving the widget order.
     */
    public function updateScope(Request $request): RedirectResponse
    {
        $landlord = $request->user();
        abort_unless($landlord !== null && $landlord->isLandlord(), 403);

        $validated = $request->validate([
            'scope' => ['required', Rule::in(self::SCOPES)],
        ]);

        $layout = $this->layoutFor($landlord->id);
        $this->persist($landlord->id, $this->widgetsFrom($layout), $validated['scope']);

        if ($validated['scope'] === 'all_buildings') {
            app(MetricsService::class)->gauge('dashboard_all_buildings_landlords', 1);
        }

        return back();
    }

    /**
     * Normalise the stored main_dashboard layout into the structured shape
     * {widgets, scope}. Backward-compatible with the legacy flat-array layout
     * (widget order only).
     *
     * @return array<int, string>
     */
    public static function widgetsFrom(mixed $layout): array
    {
        if (is_array($layout) && array_is_list($layout)) {
            return $layout; // legacy shape: a flat widget-order list
        }
        if (is_array($layout) && isset($layout['widgets']) && is_array($layout['widgets'])) {
            return array_values($layout['widgets']);
        }

        return [];
    }

    public static function scopeFrom(mixed $layout): string
    {
        if (is_array($layout) && isset($layout['scope']) && in_array($layout['scope'], self::SCOPES, true)) {
            return $layout['scope'];
        }

        return self::DEFAULT_SCOPE;
    }

    /**
     * @return mixed the raw layout JSON of the main_dashboard row, or null
     */
    private function layoutFor(int $landlordId): mixed
    {
        return LandlordDashboard::query()
            ->where('landlord_id', $landlordId)
            ->where('slug', self::MAIN_DASHBOARD_SLUG)
            ->value('layout');
    }

    /**
     * @param  array<int, string>  $widgets
     */
    private function persist(int $landlordId, array $widgets, string $scope): void
    {
        LandlordDashboard::updateOrCreate(
            [
                'landlord_id' => $landlordId,
                'slug' => self::MAIN_DASHBOARD_SLUG,
            ],
            [
                'name' => 'Main dashboard',
                'description' => 'Landlord dashboard preferences (widget order + scope).',
                'layout' => ['widgets' => $widgets, 'scope' => $scope],
                'is_default' => false,
            ],
        );
    }
}
