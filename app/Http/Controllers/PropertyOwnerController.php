<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Http\Requests\Finance\StorePropertyOwnerRequest;
use App\Http\Requests\Finance\UpdatePropertyOwnerRequest;
use App\Http\Traits\WithLandlordScope;
use App\Mail\OwnerStatementMail;
use App\Models\PaymentConfiguration;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Services\FinanceReportService;
use App\Services\OwnerStatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-101 OWNER-FOUNDATION: the landlord/PM manages the owners they look after and
 * which property belongs to whom. Landlord-scoped CRUD + property assignment. (The
 * owner has no login here — a later OWNER-PORTAL phase adds that.)
 *
 * Every query is EXPLICITLY landlord-scoped via getLandlordId() (not just TenantScope's
 * boot-conditional global scope) — the Finance-module convention, belt-and-suspenders
 * against a model that boots before auth resolves.
 */
class PropertyOwnerController extends Controller
{
    use WithLandlordScope;

    public function index(): Response
    {
        $this->authorize('viewAny', PropertyOwner::class);
        $landlordId = $this->getLandlordId();

        $owners = PropertyOwner::query()
            ->where('landlord_id', $landlordId)
            ->withCount(['properties' => fn ($q) => $q->where('landlord_id', $landlordId)])
            ->orderBy('name')
            ->get()
            ->map(fn (PropertyOwner $o) => [
                'id' => $o->id,
                'name' => $o->name,
                'email' => $o->email,
                'phone' => $o->phone,
                'id_number' => $o->id_number,
                'notes' => $o->notes,
                'is_active' => $o->is_active,
                'properties_count' => $o->properties_count,
                'has_login' => $o->user_id !== null,
            ]);

        $properties = Property::query()
            ->where('landlord_id', $landlordId)
            ->with('owner:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'property_owner_id'])
            ->map(fn (Property $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'owner_id' => $p->property_owner_id,
                'owner_name' => $p->owner?->name,
            ]);

        return Inertia::render('Owners/Index', [
            'owners' => $owners->values(),
            'properties' => $properties->values(),
        ]);
    }

    public function store(StorePropertyOwnerRequest $request): RedirectResponse
    {
        PropertyOwner::create($request->validated());

        return back()->with('success', __('owners.messages.created'));
    }

    public function update(UpdatePropertyOwnerRequest $request, PropertyOwner $owner): RedirectResponse
    {
        $owner->update($request->validated());

        return back()->with('success', __('owners.messages.updated'));
    }

    public function destroy(PropertyOwner $owner): RedirectResponse
    {
        $this->authorize('delete', $owner);

        // Properties keep existing — the FK nulls out (nullOnDelete), they just become
        // unassigned. No cascade onto the managed portfolio.
        $owner->delete();

        return back()->with('success', __('owners.messages.deleted'));
    }

    public function assign(Property $property, PropertyOwner $owner): RedirectResponse
    {
        $this->authorize('view', $property);
        $this->authorize('update', $owner);

        // Same-tenant invariant: a property and the owner it's assigned to must both
        // belong to the acting landlord (guards the cross-tenant boot-order window).
        $landlordId = $this->getLandlordId();
        abort_unless((int) $property->landlord_id === $landlordId, 404);
        abort_unless((int) $owner->landlord_id === $landlordId, 404);

        $property->update(['property_owner_id' => $owner->id]);

        return back()->with('success', __('owners.messages.assigned'));
    }

    public function unassign(Property $property): RedirectResponse
    {
        $this->authorize('view', $property);
        abort_unless((int) $property->landlord_id === $this->getLandlordId(), 404);

        $property->update(['property_owner_id' => null]);

        return back()->with('success', __('owners.messages.unassigned'));
    }

    public function statement(Request $request, PropertyOwner $owner, OwnerStatementService $statements, FinanceReportService $reports): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('view', $owner);

        // Derive the landlord from auth, never from the bound model, and confirm the
        // owner belongs to them (404 in the boot-order window where binding doesn't scope).
        $landlordId = $this->getLandlordId();
        abort_unless((int) $owner->landlord_id === $landlordId, 404);

        [$start, $end] = $this->resolveRange($request, $reports, $landlordId);
        $data = $statements->forOwner($landlordId, $owner->id, $start, $end);
        abort_if($data === null, 404);

        $currency = PaymentConfiguration::where('landlord_id', $landlordId)->first()?->default_currency
            ?? Currency::default();

        return Pdf::loadView('reports.owner-statement-multi', [
            'data' => $data,
            'landlord' => (object) ['name' => $owner->landlord?->name ?? config('app.name')],
            'generated_at' => $data['generated_at'],
            'currency_symbol' => $currency->symbol(),
            'currency_code' => $currency->value,
        ])->download('owner_statement_'.Str::slug($owner->name).'_'.$start->format('Y_m_d').'.pdf');
    }

    public function emailStatement(Request $request, PropertyOwner $owner, OwnerStatementService $statements, FinanceReportService $reports): RedirectResponse
    {
        $this->authorize('view', $owner);

        $landlordId = $this->getLandlordId();
        abort_unless((int) $owner->landlord_id === $landlordId, 404);

        if (blank($owner->email)) {
            return back()->with('error', __('owners.messages.statement_no_email'));
        }

        [$start, $end] = $this->resolveRange($request, $reports, $landlordId);
        $data = $statements->forOwner($landlordId, $owner->id, $start, $end);
        abort_if($data === null, 404);

        $currency = PaymentConfiguration::where('landlord_id', $landlordId)->first()?->default_currency
            ?? Currency::default();

        Mail::to($owner->email)->queue(new OwnerStatementMail(
            $data,
            $currency->symbol(),
            $currency->value,
            $owner->landlord?->name ?? config('app.name'),
        ));

        return back()->with('success', __('owners.messages.statement_sent', ['email' => $owner->email]));
    }

    /**
     * Resolve the period to a [start, end] range. An unrecognised period silently
     * collapsing to "this month so far" would understate a statement, so reject anything
     * outside the known tokens / positive-integer months.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request, FinanceReportService $reports, int $landlordId): array
    {
        $period = (string) $request->query('period', '12');
        $named = ['this_month', 'last_month', 'this_quarter', 'last_quarter', 'ytd', 'this_fy', 'last_fy', 'custom'];
        if (! in_array($period, $named, true) && ! ctype_digit($period)) {
            $period = '12';
        }

        $range = $reports->getReportDateRange(
            $period,
            $request->query('date_from'),
            $request->query('date_to'),
            $landlordId,
        );

        return [Carbon::parse($range['start']), Carbon::parse($range['end'])];
    }
}
