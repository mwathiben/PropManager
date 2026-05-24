<?php

namespace App\Http\Controllers;

use App\Http\Traits\WithLandlordScope;
use App\Models\Building;
use App\Models\Unit;
use App\Models\WaterReading;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaterHubController extends Controller
{
    use WithLandlordScope;

    public function index(Request $request): Response
    {
        // Phase-79 WATER-GATE-3: the conditional-module gate now lives in the
        // 'water.module' route middleware (plan AND charges-for-water), so the
        // old plan-only check here is gone.
        $landlordId = $this->getLandlordId();

        // Phase-79 WATER-ROLES-1: the hub is role-aware. The caretaker RECORDS
        // readings (input); the landlord only REVIEWS (approve/reject). Neither
        // sees the other's primary tab.
        $isCaretaker = auth()->user()->isCaretaker();
        // Phase-83 follow-up: every hub now opens on an Overview homepage rather
        // than a working first tab. The role-specific tab (caretaker records /
        // landlord reviews) is one click away and still role-guarded below.
        $tab = $request->query('tab', 'overview');
        if ($tab === 'readings' && ! $isCaretaker) {
            $tab = 'review';
        }
        if ($tab === 'review' && $isCaretaker) {
            $tab = 'readings';
        }
        // Phase-86 ROLE-SPLIT: water billing configuration is landlord-only. A
        // caretaker requesting ?tab=settings is bounced to the overview so the
        // settings payload is never computed or rendered for them.
        if ($tab === 'settings' && $isCaretaker) {
            $tab = 'overview';
        }
        // Phase-91 WATER-HUB-INTELLIGENCE: the analytics surface is landlord-only
        // (production costs + margin are business data) — a caretaker requesting
        // ?tab=intelligence is bounced so the payload is never computed for them.
        if ($tab === 'intelligence' && $isCaretaker) {
            $tab = 'overview';
        }
        // Phase-92 WATER-COMPLIANCE: the borehole compliance surface (permits,
        // certificates, abstraction limits) is landlord-only too.
        if ($tab === 'compliance' && $isCaretaker) {
            $tab = 'overview';
        }
        // Phase-94 WATER-CLIENTS: managing external water clients + their water
        // lines is landlord-only.
        if ($tab === 'clients' && $isCaretaker) {
            $tab = 'overview';
        }

        $baseProps = [
            'activeTab' => $tab,
            'role' => $isCaretaker ? 'caretaker' : 'landlord',
            'canInput' => $isCaretaker,
            'canReview' => ! $isCaretaker,
            'canSettings' => ! $isCaretaker,
            'filters' => $request->only(['building_id', 'unit_id', 'date_from', 'date_to', 'status']),
            'buildings' => $this->getBuildings($landlordId),
            'counts' => $this->getCounts($landlordId),
        ];

        $tabData = match ($tab) {
            'overview' => $this->getOverviewData($landlordId),
            'readings' => $this->getReadingsData($landlordId),
            'review' => $this->getReviewData($landlordId),
            'history' => $this->getHistoryData($request, $landlordId),
            'settings' => $this->getSettingsData($landlordId),
            'intelligence' => $this->getIntelligenceData($landlordId),
            'compliance' => $this->getComplianceData($landlordId),
            'clients' => $this->getClientsData($landlordId),
            default => $this->getOverviewData($landlordId),
        };

        return Inertia::render('Water/Hub', array_merge($baseProps, $tabData));
    }

    private function getReadingsData(int $landlordId): array
    {
        // Phase-79: landlord-wide (all properties' buildings, not just the
        // first) and no has_water_meter filter — that column never existed, so
        // the old query 500'd the readings tab. Every unit can hold a reading.
        $buildings = Building::query()
            ->where('landlord_id', $landlordId)
            ->with(['units' => function ($q) {
                $q->orderBy('unit_number')
                    ->with(['waterReadings' => function ($q) {
                        $q->latest('reading_date')->limit(1);
                    }]);
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($building) {
                return [
                    'id' => $building->id,
                    'name' => $building->name,
                    'units' => $building->units->map(function ($unit) {
                        $lastReading = $unit->waterReadings->first();

                        return [
                            'id' => $unit->id,
                            'unit_number' => $unit->unit_number,
                            'last_reading' => $lastReading ? [
                                'current_reading' => $lastReading->current_reading,
                                'reading_date' => $lastReading->reading_date,
                            ] : null,
                        ];
                    }),
                ];
            });

        return ['buildingsData' => $buildings];
    }

    /**
     * Phase-79 WATER-ROLES-3: the landlord's review surface — pending readings
     * awaiting approve/reject, landlord-wide (all buildings, not just the first
     * property).
     */
    private function getReviewData(int $landlordId): array
    {
        $pending = WaterReading::query()
            ->whereIn('unit_id', $this->unitIdsForLandlord($landlordId))
            ->where('status', 'pending')
            ->with(['unit.building', 'recorder:id,name'])
            ->orderBy('reading_date', 'desc')
            ->paginate(20)
            ->withQueryString();

        return ['pendingReadings' => $pending];
    }

    /**
     * @return list<int>
     */
    private function unitIdsForLandlord(int $landlordId): array
    {
        return Unit::query()->where('landlord_id', $landlordId)->pluck('id')->all();
    }

    private function getHistoryData(Request $request, int $landlordId): array
    {
        // Phase-79: landlord-wide history (all properties, not just the first).
        $buildings = Building::query()->where('landlord_id', $landlordId)->with('units')->get();
        $unitIds = $buildings->flatMap(fn ($b) => $b->units->pluck('id'));

        $query = WaterReading::whereIn('unit_id', $unitIds)
            ->with(['unit.building']);

        if ($request->filled('building_id')) {
            $query->whereHas('unit', fn ($q) => $q->where('building_id', $request->building_id));
        }

        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->unit_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('reading_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('reading_date', '<=', $request->date_to);
        }

        if ($request->filled('status')) {
            if ($request->status === 'pending') {
                $query->where('status', 'pending');
            } elseif ($request->status === 'approved') {
                $query->where('status', 'approved');
            } elseif ($request->status === 'invoiced') {
                $query->where('is_invoiced', true);
            }
        }

        $readings = $query->orderBy('reading_date', 'desc')
            ->paginate(20)
            ->withQueryString();

        return [
            'readings' => $readings,
            'buildingsList' => $buildings,
        ];
    }

    private function getSettingsData(int $landlordId): array
    {
        // Phase-83 follow-up WATER-SETTINGS-UNIFY: the Settings tab now renders
        // the SAME canonical editor as /water/settings (global PaymentConfiguration
        // + per-building overrides — what WaterRateService actually bills from).
        // The old WaterSetting model (rate_per_unit/billing_day/is_enabled) was an
        // orphan no billing path read, so it is retired here.
        return [
            'settings' => \App\Services\Water\WaterSettingsData::forLandlord($landlordId),
        ];
    }

    /**
     * Phase-91 WATER-HUB-INTELLIGENCE: the landlord-only analytics payload —
     * consumption trends, leak signals, billing-vs-collection, and the
     * cost-of-production margin (see WaterIntelligenceService).
     */
    private function getIntelligenceData(int $landlordId): array
    {
        return [
            'intelligence' => app(\App\Services\Water\WaterIntelligenceService::class)->forLandlord($landlordId),
            'costCategories' => \App\Models\WaterProductionCost::CATEGORIES,
        ];
    }

    /**
     * Phase-92 WATER-COMPLIANCE: the landlord-only borehole compliance payload —
     * per borehole building, the WRA permit + quality cert (expiry tracked via the
     * Phase-82 document lifecycle) and abstraction limit vs actual abstraction.
     */
    private function getComplianceData(int $landlordId): array
    {
        return [
            'compliance' => app(\App\Services\Water\WaterComplianceService::class)->forLandlord($landlordId),
        ];
    }

    /**
     * Phase-94 WATER-CLIENTS: the landlord-only setup + water-line management
     * payload — the opt-in flag + default client rate, the connections (water
     * lines), and the meters available to attach. Onboarding a real water_client
     * user + billing land in Phases 95 / 97.
     */
    private function getClientsData(int $landlordId): array
    {
        $config = \App\Models\PaymentConfiguration::where('landlord_id', $landlordId)->first();

        // Phase-97: outstanding water-client balance per connection (one batched query,
        // shared formula with the client finances surface).
        $outstanding = \App\Models\WaterClientCharge::outstandingByConnection($landlordId);
        $defaultRate = ($config && $config->water_client_rate !== null && (float) $config->water_client_rate > 0)
            ? (float) $config->water_client_rate
            : null;

        $connections = \App\Models\WaterConnection::query()
            ->where('landlord_id', $landlordId)
            ->with(['meter:id,serial_number', 'unit:id,unit_number', 'client:id,name'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (\App\Models\WaterConnection $c) => [
                'id' => $c->id,
                'identifier' => $c->identifier,
                'client_name' => $c->client?->name ?? $c->client_name,
                'has_account' => $c->user_id !== null,
                'billing_mode' => $c->billing_mode,
                'client_rate' => $c->client_rate !== null ? (float) $c->client_rate : null,
                'status' => $c->status,
                'meter_id' => $c->meter_id,
                'meter' => $c->meter?->serial_number,
                'unit_id' => $c->unit_id,
                'unit' => $c->unit?->unit_number,
                'connected_at' => $c->connected_at?->toDateString(),
                'notes' => $c->notes,
                'outstanding' => round((float) ($outstanding[$c->id] ?? 0), 2),
                // Surface why a line won't bill, so the landlord can fix it (the biller
                // refuses these — it never silently bills 0).
                'billing_issue' => $this->billingIssue($c, $defaultRate),
            ]);

        $meters = \App\Models\Meter::query()
            ->where('landlord_id', $landlordId)
            ->orderBy('serial_number')
            ->get(['id', 'serial_number', 'unit_id'])
            ->map(fn (\App\Models\Meter $m) => [
                'id' => $m->id,
                'label' => $m->serial_number ?: '#'.$m->id,
            ]);

        return [
            'clients' => [
                'supplies_water_clients' => (bool) ($config->supplies_water_clients ?? false),
                'water_client_rate' => $config && $config->water_client_rate !== null ? (float) $config->water_client_rate : null,
                'connections' => $connections,
                'meters' => $meters,
                'billing_modes' => \App\Models\WaterConnection::BILLING_MODES,
            ],
        ];
    }

    /**
     * Phase-97: why the biller would refuse this line (so the landlord can fix it).
     * Mirrors WaterClientBillingService's guards: metered-without-meter, or no rate.
     */
    private function billingIssue(\App\Models\WaterConnection $c, ?float $defaultRate): ?string
    {
        if ($c->status !== 'active') {
            return null;
        }
        if ($c->billing_mode === 'metered' && $c->meter === null) {
            return 'no_meter';
        }
        $rate = ($c->client_rate !== null && (float) $c->client_rate > 0) ? (float) $c->client_rate : $defaultRate;
        if ($rate === null) {
            return 'no_rate';
        }

        return null;
    }

    private function getCounts(int $landlordId): array
    {
        return [
            'pendingReadings' => WaterReading::whereIn('unit_id', $this->unitIdsForLandlord($landlordId))
                ->where('status', 'pending')
                ->count(),
        ];
    }

    private function getOverviewData(int $landlordId): array
    {
        $unitIds = $this->unitIdsForLandlord($landlordId);
        $pending = WaterReading::whereIn('unit_id', $unitIds)->where('status', 'pending')->count();
        $approvedMonth = WaterReading::whereIn('unit_id', $unitIds)
            ->where('status', 'approved')
            ->where('reading_date', '>=', now()->startOfMonth())
            ->count();
        $buildings = Building::where('landlord_id', $landlordId)->count();

        return [
            'overviewStats' => [
                ['label' => __('water.overview.pending'), 'value' => $pending, 'tone' => $pending > 0 ? 'amber' : 'emerald'],
                ['label' => __('water.overview.approved_month'), 'value' => $approvedMonth, 'tone' => 'default'],
                ['label' => __('water.overview.buildings'), 'value' => $buildings, 'tone' => 'default'],
            ],
        ];
    }
}
