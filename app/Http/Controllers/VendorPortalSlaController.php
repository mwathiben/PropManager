<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Services\Vendors\VendorSlaService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-70 SLA-DASHBOARD-2: the vendor's own SLA performance. Scoped to
 * the SESSION vendor (request->attributes('portal_vendor')).
 */
class VendorPortalSlaController extends Controller
{
    public function __construct(private readonly VendorSlaService $sla) {}

    public function index(Request $request): Response
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('portal_vendor');

        $window = (int) $request->integer('window', 90);
        if (! in_array($window, [30, 90, 365], true)) {
            $window = 90;
        }

        return Inertia::render('VendorPortal/Sla', [
            'vendor' => ['id' => $vendor->id, 'name' => $vendor->name],
            'window' => $window,
            'metrics' => $this->sla->forVendor($vendor, $window),
        ]);
    }
}
