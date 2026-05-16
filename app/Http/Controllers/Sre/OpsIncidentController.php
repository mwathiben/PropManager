<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sre;

use App\Http\Controllers\Controller;
use App\Models\OperationalIncident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-32 SRE-INCIDENT-2: admin-only CRUD for OperationalIncident.
 * Index returns the rolling 90-day list; store opens a new incident;
 * setStatus advances the status machine; setPostMortem attaches the
 * post-mortem URL.
 */
class OpsIncidentController extends Controller
{
    public function index(Request $request): Response
    {
        $incidents = OperationalIncident::query()
            ->orderByDesc('opened_at')
            ->limit(100)
            ->get();

        return Inertia::render('Sre/Incidents/Index', [
            'incidents' => $incidents,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'severity' => ['required', 'in:'.implode(',', OperationalIncident::SEVERITIES)],
            'summary' => ['nullable', 'string', 'max:5000'],
            'affected_services' => ['nullable', 'array'],
        ]);

        OperationalIncident::create([
            'title' => $data['title'],
            'severity' => $data['severity'],
            'status' => OperationalIncident::STATUS_OPEN,
            'opened_at' => now(),
            'opened_by_user_id' => (int) $request->user()->id,
            'summary' => $data['summary'] ?? null,
            'affected_services' => $data['affected_services'] ?? null,
        ]);

        return Redirect::back()->with('success', __('sre.incident.opened'));
    }

    public function setStatus(Request $request, OperationalIncident $incident): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', OperationalIncident::STATUSES)],
            'root_cause' => ['nullable', 'string', 'max:5000'],
        ]);
        $status = $data['status'];

        if ($status === OperationalIncident::STATUS_MITIGATED) {
            abort_unless($incident->canMitigate(), 422, 'Incident is past mitigation.');
            $incident->mitigated_at = now();
        }
        if ($status === OperationalIncident::STATUS_RESOLVED) {
            abort_unless($incident->canResolve(), 422, 'Incident already resolved.');
            $incident->resolved_at = now();
            $incident->resolved_by_user_id = (int) $request->user()->id;
            if (isset($data['root_cause'])) {
                $incident->root_cause = $data['root_cause'];
            }
        }
        $incident->status = $status;
        $incident->save();

        return Redirect::back()->with('success', __('sre.incident.status_updated'));
    }

    public function setPostMortem(Request $request, OperationalIncident $incident): JsonResponse
    {
        $data = $request->validate([
            'post_mortem_url' => ['required', 'url', 'max:500'],
        ]);
        $incident->update(['post_mortem_url' => $data['post_mortem_url']]);

        return response()->json(['ok' => true]);
    }
}
