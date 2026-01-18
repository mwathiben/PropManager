<?php

namespace App\Http\Traits;

use App\Models\Building;
use App\Models\Property;
use Illuminate\Support\Collection;

trait WithLandlordScope
{
    protected function getLandlordId(): int
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        return $user->isCaretaker() ? $user->landlord_id : $user->id;
    }

    protected function getBuildings(int $landlordId): array
    {
        return Building::where('landlord_id', $landlordId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    protected function getBuildingsWithWings(int $landlordId): array
    {
        return Building::where('landlord_id', $landlordId)
            ->select('id', 'name')
            ->with(['wings:id,building_id,name'])
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    protected function getBuildingsForDropdown(): Collection
    {
        return Building::where('landlord_id', $this->getLandlordId())
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    protected function getBuildingsWithProperty(): Collection
    {
        return Building::where('landlord_id', $this->getLandlordId())
            ->with('property:id,name')
            ->select('id', 'name', 'property_id')
            ->orderBy('name')
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'property_name' => $b->property?->name ?? 'Unknown Property',
                'display_name' => ($b->property?->name ?? 'Unknown').' - '.$b->name,
            ]);
    }

    protected function getProperties(int $landlordId): array
    {
        return Property::where('landlord_id', $landlordId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();
    }
}
