<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Legal\UpdateHoldSettingsRequest;
use App\Models\LandlordHoldSettings;
use App\Services\Legal\HoldSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-72 HOLD-SETTINGS: per-landlord legal-hold preferences. A landlord only
 * ever reads/writes their own row (keyed on the authed user), so no cross-tenant
 * surface; the route is landlord-only.
 */
class LegalHoldSettingsController extends Controller
{
    public function __construct(private readonly HoldSettingsResolver $resolver) {}

    public function show(Request $request): Response
    {
        $row = LandlordHoldSettings::query()->where('landlord_id', $request->user()->id)->first();

        return Inertia::render('LegalHolds/Settings', [
            'settings' => [
                'stale_after_days' => $row?->stale_after_days,
                'reminder_cooldown_days' => $row?->reminder_cooldown_days,
                'matter_reference_format' => $row?->matter_reference_format,
                'reminder_recipients' => $row?->reminder_recipients ?? [],
                'auto_hold_on_eviction' => (bool) ($row?->auto_hold_on_eviction ?? false),
            ],
            'defaults' => [
                'stale_after_days' => (int) config('legal_hold.stale_after_days', 365),
                'reminder_cooldown_days' => (int) config('legal_hold.stale_reminder_cooldown_days', 30),
            ],
        ]);
    }

    public function update(UpdateHoldSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $landlordId = (int) $request->user()->id;

        LandlordHoldSettings::updateOrCreate(
            ['landlord_id' => $landlordId],
            [
                'stale_after_days' => $data['stale_after_days'] ?? null,
                'reminder_cooldown_days' => $data['reminder_cooldown_days'] ?? null,
                'matter_reference_format' => $data['matter_reference_format'] ?? null,
                'reminder_recipients' => array_values(array_filter($data['reminder_recipients'] ?? [])),
                'auto_hold_on_eviction' => (bool) ($data['auto_hold_on_eviction'] ?? false),
            ],
        );

        $this->resolver->flush($landlordId);

        return redirect()->route('legal-holds.settings')
            ->with('success', __('legal_holds.settings.saved'));
    }
}
