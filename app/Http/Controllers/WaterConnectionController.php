<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Water\SetupWaterClientsRequest;
use App\Http\Requests\Water\StoreWaterConnectionRequest;
use App\Http\Traits\WithLandlordScope;
use App\Models\PaymentConfiguration;
use App\Models\WaterConnection;

/**
 * Phase-94 WATER-CLIENTS-FOUNDATION: landlord-only setup + management of water
 * connections (the "water lines" supplied to non-tenant clients). Setup opts the
 * landlord in and sets the default client rate; the CRUD manages the water lines.
 * Client onboarding (a real water_client user) + billing land in Phases 95 / 97.
 */
class WaterConnectionController extends Controller
{
    use WithLandlordScope;

    public function setup(SetupWaterClientsRequest $request)
    {
        // Seed the row from the canonical defaults first so opting in never leaves
        // a half-initialised PaymentConfiguration (the gate can be satisfied by a
        // building, with no config row yet).
        PaymentConfiguration::getOrCreateForLandlord($this->getLandlordId())->update([
            'supplies_water_clients' => $request->boolean('supplies_water_clients'),
            'water_client_rate' => $request->input('water_client_rate'),
        ]);

        return back()->with('success', __('water.clients.setup_saved'));
    }

    public function store(StoreWaterConnectionRequest $request)
    {
        WaterConnection::create(array_merge($request->validated(), [
            'landlord_id' => $this->getLandlordId(),
        ]));

        return back()->with('success', __('water.clients.connection_saved'));
    }

    public function update(StoreWaterConnectionRequest $request, WaterConnection $waterConnection)
    {
        $this->authorize('update', $waterConnection);

        $waterConnection->update($request->validated());

        return back()->with('success', __('water.clients.connection_saved'));
    }

    public function destroy(WaterConnection $waterConnection)
    {
        $this->authorize('delete', $waterConnection);

        $waterConnection->delete();

        return back()->with('success', __('water.clients.connection_deleted'));
    }
}
