<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DraftPurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-54 PARTS-REORDER-3: landlord-facing surface over the
 * draft_purchase_orders the cron materialises.
 */
class PartsPurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $landlordId = $this->landlordIdFor($request);

        $orders = DraftPurchaseOrder::query()
            ->withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
            ->whereIn('status', [
                DraftPurchaseOrder::STATUS_DRAFT,
                DraftPurchaseOrder::STATUS_SENT,
            ])
            ->with(['vendor:id,name,email', 'lines.part:id,name,sku,reorder_threshold,qty_available'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (DraftPurchaseOrder $order) => [
                'id' => $order->id,
                'status' => $order->status,
                'sent_at' => $order->sent_at?->toIso8601String(),
                'vendor' => $order->vendor ? [
                    'id' => $order->vendor->id,
                    'name' => $order->vendor->name,
                    'email' => $order->vendor->email,
                ] : null,
                'lines' => $order->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'part' => [
                        'id' => $line->part->id,
                        'name' => $line->part->name,
                        'sku' => $line->part->sku,
                        'qty_available' => $line->part->qty_available,
                        'reorder_threshold' => $line->part->reorder_threshold,
                    ],
                    'qty_suggested' => $line->qty_suggested,
                    'cost_per_unit_cents_snapshot' => $line->cost_per_unit_cents_snapshot,
                ]),
                'total_cents' => $order->lines->sum(
                    fn ($line) => $line->qty_suggested * $line->cost_per_unit_cents_snapshot,
                ),
            ]);

        return Inertia::render('Parts/PurchaseOrders', [
            'orders' => $orders,
        ]);
    }

    public function confirm(Request $request, DraftPurchaseOrder $order): RedirectResponse
    {
        $this->ensureOwnership($request, $order);

        if ($order->status !== DraftPurchaseOrder::STATUS_DRAFT) {
            abort(422, 'Only draft orders can be confirmed.');
        }

        $order->status = DraftPurchaseOrder::STATUS_SENT;
        $order->sent_at = now();
        $order->save();

        return redirect()->route('parts.purchase-orders.index')
            ->with('success', 'Order marked as sent.');
    }

    public function cancel(Request $request, DraftPurchaseOrder $order): RedirectResponse
    {
        $this->ensureOwnership($request, $order);

        if ($order->status === DraftPurchaseOrder::STATUS_CANCELLED) {
            abort(422, 'Order already cancelled.');
        }

        $order->status = DraftPurchaseOrder::STATUS_CANCELLED;
        $order->save();

        return redirect()->route('parts.purchase-orders.index')
            ->with('success', 'Order cancelled.');
    }

    private function ensureOwnership(Request $request, DraftPurchaseOrder $order): void
    {
        $landlordId = $this->landlordIdFor($request);
        if ($order->landlord_id !== $landlordId) {
            abort(403, 'Order does not belong to this landlord.');
        }
    }

    private function landlordIdFor(Request $request): int
    {
        $user = $request->user();

        return $user->role === 'landlord' ? (int) $user->id : (int) $user->landlord_id;
    }
}
