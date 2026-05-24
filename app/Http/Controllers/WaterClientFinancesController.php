<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\WaterConnection;
use App\Services\Water\WaterAccountService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-97 WATER-CLIENT-BILLING: the water client's own charges + outstanding
 * balance — the destination of the dashboard's "pay" link. Read-only for the
 * client (a neighbour settles with the supplier, who records it); the page shows
 * what's owed per water line and how to pay.
 */
class WaterClientFinancesController extends Controller
{
    public function __construct(private WaterAccountService $accountService) {}

    public function index(): Response
    {
        $user = auth()->user();
        abort_unless($user->isWaterClient(), 403);

        $lines = WaterConnection::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get()
            ->map(fn (WaterConnection $c) => [
                'id' => $c->id,
                'identifier' => $c->identifier,
                'status' => $c->status,
                'outstanding' => Invoice::outstandingForWaterConnection($c->id),
                'charges' => $this->accountService->chargeHistoryForConnection($c),
            ]);

        return Inertia::render('WaterClient/Finances', [
            'lines' => $lines->values(),
            'totalOutstanding' => round((float) $lines->sum('outstanding'), 2),
            'supplierName' => $user->landlord?->name,
        ]);
    }
}
