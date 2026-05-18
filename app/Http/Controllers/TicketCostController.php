<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketCost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase-54 COST-UI-2/3: landlord-only manual cost entry for
 * vendor / labor / other categories. The 'parts' category is
 * auto-recorded via TicketResolutionService::recordParts (Phase 49)
 * and is rejected here to keep a single source of truth.
 */
class TicketCostController extends Controller
{
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('createCost', $ticket);

        $validated = $request->validate([
            'category' => ['required', 'string', 'in:vendor,labor,other'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($ticket, $validated, $request) {
            TicketCost::create([
                'ticket_id' => $ticket->id,
                'category' => $validated['category'],
                'amount_cents' => $validated['amount_cents'],
                'currency' => 'KES',
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => $request->user()->id,
                'recorded_at' => now(),
            ]);

            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'action' => 'cost_recorded',
                'metadata' => [
                    'category' => $validated['category'],
                    'amount_cents' => $validated['amount_cents'],
                    'currency' => 'KES',
                ],
                'created_at' => now(),
            ]);
        });

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Cost recorded.');
    }
}
