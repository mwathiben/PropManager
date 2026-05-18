<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LandlordDashboard;
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

    public function update(Request $request): RedirectResponse
    {
        $landlord = $request->user();
        abort_unless($landlord !== null && $landlord->isLandlord(), 403);

        $validated = $request->validate([
            'widget_order' => ['required', 'array', 'min:1', 'max:'.count(self::ALLOWED_WIDGETS)],
            'widget_order.*' => ['string', Rule::in(self::ALLOWED_WIDGETS)],
        ]);

        $order = array_values(array_unique($validated['widget_order']));

        LandlordDashboard::updateOrCreate(
            [
                'landlord_id' => $landlord->id,
                'slug' => self::MAIN_DASHBOARD_SLUG,
            ],
            [
                'name' => 'Main dashboard',
                'description' => 'Landlord widget ordering preferences (Phase-55).',
                'layout' => $order,
                'is_default' => false,
            ],
        );

        return back();
    }
}
