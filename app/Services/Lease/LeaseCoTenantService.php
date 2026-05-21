<?php

declare(strict_types=1);

namespace App\Services\Lease;

use App\Models\Lease;
use App\Models\LeaseCoTenant;

/**
 * Phase-83 CO-TENANT-1: add / remove co-tenants on a joint tenancy.
 */
class LeaseCoTenantService
{
    /**
     * @param  array{name:string, email?:string|null, phone?:string|null, national_id?:string|null, relationship?:string|null, is_responsible_for_rent?:bool, liability_share?:float|string|null}  $data
     */
    public function add(Lease $lease, array $data): LeaseCoTenant
    {
        return LeaseCoTenant::create([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'national_id' => $data['national_id'] ?? null,
            'relationship' => $data['relationship'] ?? null,
            'is_responsible_for_rent' => $data['is_responsible_for_rent'] ?? false,
            'liability_share' => $data['liability_share'] ?? null,
        ]);
    }

    public function remove(LeaseCoTenant $coTenant): bool
    {
        if ($coTenant->removed_at !== null) {
            return false;
        }

        return $coTenant->update(['removed_at' => now()]);
    }
}
