<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Traits\ResolvesCurrentOwner;
use App\Models\Property;
use App\Services\Property\PropertyMetricsService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-102 OWNER-PORTAL: the owner's own view of the properties a PM manages for them.
 * Every query is bound to BOTH the owner's PropertyOwner id AND their PM's landlord_id —
 * an owner can never see another owner's (or the PM's other) data, regardless of the
 * boot-order window where TenantScope's global scope may not have registered.
 */
class OwnerPortalDashboardController extends Controller
{
    use ResolvesCurrentOwner;

    public function index(PropertyMetricsService $metrics): Response
    {
        $owner = $this->currentOwner();

        $properties = Property::query()
            ->where('property_owner_id', $owner->id)
            ->where('landlord_id', $owner->landlord_id)
            ->orderBy('name')
            ->get()
            ->map(fn (Property $p) => $metrics->forProperty($p))
            ->values();

        return Inertia::render('Owner/Dashboard', [
            'owner' => ['name' => $owner->name],
            'properties' => $properties,
        ]);
    }
}
