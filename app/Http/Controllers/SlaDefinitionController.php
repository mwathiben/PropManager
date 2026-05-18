<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SlaDefinition;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-54 SLA-LANDLORD-UI-1: landlord-scoped CRUD over SLA overrides.
 *
 * Routes (registered in routes/web.php under role:landlord):
 *   GET    /sla                index — list landlord rows + read-only globals
 *   POST   /sla                store — create a landlord override
 *   PATCH  /sla/{sla}          update — edit the landlord's own override
 *   DELETE /sla/{sla}          destroy — remove the landlord's override
 *
 * NOT under /admin — that namespace is super-admin only. Landlord
 * overrides + global defaults coexist via the Phase-49 cascade in
 * SlaDefinitionService::resolveFor.
 */
class SlaDefinitionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SlaDefinition::class);
        $landlordId = $this->landlordIdFor($request);

        $overrides = SlaDefinition::query()
            ->withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
            ->orderByDesc('id')
            ->get();

        $globals = SlaDefinition::query()
            ->withoutGlobalScope('landlord')
            ->whereNull('landlord_id')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Sla/Index', [
            'overrides' => $overrides,
            'globals' => $globals,
            'categoryOptions' => $this->categoryOptions(),
            'priorityOptions' => array_keys(Ticket::SLA_SECONDS),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SlaDefinition::class);

        $validated = $this->validatePayload($request);
        $landlordId = $this->landlordIdFor($request);

        SlaDefinition::create(array_merge($validated, [
            'landlord_id' => $landlordId,
            'is_active' => true,
        ]));

        return redirect()->route('sla.index')->with('success', __('maintenance.sla.flash.created'));
    }

    public function update(Request $request, SlaDefinition $sla): RedirectResponse
    {
        $this->authorize('update', $sla);

        $validated = $this->validatePayload($request);
        $sla->fill($validated)->save();

        return redirect()->route('sla.index')->with('success', __('maintenance.sla.flash.updated'));
    }

    public function destroy(Request $request, SlaDefinition $sla): RedirectResponse
    {
        $this->authorize('delete', $sla);
        $sla->delete();

        return redirect()->route('sla.index')->with('success', __('maintenance.sla.flash.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'category' => ['nullable', 'string', 'in:issue,complaint'],
            'subcategory' => ['nullable', 'string', 'max:64'],
            'priority' => ['required', 'string', 'in:'.implode(',', array_keys(Ticket::SLA_SECONDS))],
            'response_seconds' => ['required', 'integer', 'min:60', 'max:31536000'],
            'resolution_seconds' => ['required', 'integer', 'min:60', 'max:31536000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function landlordIdFor(Request $request): int
    {
        $user = $request->user();

        return $user->role === 'landlord' ? (int) $user->id : (int) $user->landlord_id;
    }

    /**
     * @return array<int, string>
     */
    private function categoryOptions(): array
    {
        return ['issue', 'complaint'];
    }
}
