<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\SampleDataRun;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Phase-31 ONB-SAMPLE-1: prospect demo dataset.
 *
 * populate(landlord) writes 1 property + 1 building + 4 units +
 * 2 sample tenants (with @propmanager.demo emails — NOT deliverable
 * per ONB-SAMPLE-3) + 2 leases + 3 paid invoices + 1 unpaid + 1
 * overdue + 2 payments. All row IDs are recorded in
 * sample_data_runs.row_refs so reset() can undo cleanly without
 * touching any real rows the landlord may have created in parallel.
 *
 * Hard-cap: refuses to populate if the landlord already has any
 * active Lease — sample data is for prospects, not running estates.
 */
class SampleDataSeederService
{
    public function populate(User $landlord): ?SampleDataRun
    {
        if (Lease::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlord->id)
            ->where('is_active', true)
            ->exists()) {
            return null;
        }

        return DB::transaction(function () use ($landlord): SampleDataRun {
            $refs = [
                'properties' => [],
                'buildings' => [],
                'units' => [],
                'tenants' => [],
                'leases' => [],
                'invoices' => [],
                'payments' => [],
            ];

            $property = Property::create([
                'name' => 'Sample Apartments',
                'address' => 'Sample Street, Nairobi',
                'type' => 'apartment',
                'landlord_id' => $landlord->id,
            ]);
            $refs['properties'][] = $property->id;

            $building = Building::create([
                'property_id' => $property->id,
                'landlord_id' => $landlord->id,
                'name' => 'Block A',
                'total_floors' => 2,
                'units_per_floor' => 2,
                'building_type' => 'residential_apartment',
            ]);
            $refs['buildings'][] = $building->id;

            for ($i = 1; $i <= 4; $i++) {
                $unit = Unit::create([
                    'building_id' => $building->id,
                    'landlord_id' => $landlord->id,
                    'unit_number' => "A10{$i}",
                    'floor_number' => $i <= 2 ? 1 : 2,
                    'status' => 'vacant',
                    'target_rent' => 25_000 + ($i * 1_000),
                ]);
                $refs['units'][] = $unit->id;
            }

            for ($t = 1; $t <= 2; $t++) {
                // Phase-31 ONB-SAMPLE-3 isolation: sample tenants must NOT
                // pollute activation funnel — wrap in withoutEvents() so the
                // UserObserver milestone hooks don't write signed_up /
                // first_tenant rows for the demo dataset.
                $tenant = User::withoutEvents(fn () => User::factory()->create([
                    'name' => "Sample Tenant {$t}",
                    'email' => "sample-{$landlord->id}-{$t}@propmanager.demo",
                    'role' => 'tenant',
                    'landlord_id' => $landlord->id,
                ]));
                $refs['tenants'][] = $tenant->id;

                $unitId = $refs['units'][$t - 1];
                $unit = Unit::query()->withoutGlobalScopes()->find($unitId);
                $lease = Lease::create([
                    'unit_id' => $unit->id,
                    'tenant_id' => $tenant->id,
                    'landlord_id' => $landlord->id,
                    'rent_amount' => $unit->target_rent,
                    'deposit_amount' => $unit->target_rent,
                    'start_date' => now()->subMonths(3),
                    'is_active' => true,
                    'wallet_balance' => 0,
                ]);
                $refs['leases'][] = $lease->id;
                $unit->update(['status' => 'occupied']);

                foreach ([
                    ['ago' => 3, 'status' => 'paid', 'paid' => true],
                    ['ago' => 2, 'status' => 'paid', 'paid' => true],
                    ['ago' => 1, 'status' => $t === 1 ? 'overdue' : 'sent', 'paid' => false],
                ] as $spec) {
                    $invoice = Invoice::create([
                        'lease_id' => $lease->id,
                        'landlord_id' => $landlord->id,
                        'invoice_number' => 'SAMPLE-'.$landlord->id.'-'.$lease->id.'-'.$spec['ago'],
                        'rent_due' => $lease->rent_amount,
                        'water_due' => 0,
                        'arrears' => 0,
                        'wallet_applied' => 0,
                        'total_due' => $lease->rent_amount,
                        'amount_paid' => $spec['paid'] ? $lease->rent_amount : 0,
                        'status' => $spec['status'],
                        'due_date' => now()->subMonths($spec['ago']),
                        'billing_period_start' => now()->subMonths($spec['ago'])->startOfMonth(),
                    ]);
                    $refs['invoices'][] = $invoice->id;

                    if ($spec['paid']) {
                        $payment = Payment::create([
                            'invoice_id' => $invoice->id,
                            'landlord_id' => $landlord->id,
                            'lease_id' => $lease->id,
                            'amount' => $lease->rent_amount,
                            'payment_method' => 'mpesa',
                            'payment_date' => now()->subMonths($spec['ago']),
                            'reference' => 'SAMPLE-'.$invoice->id,
                        ]);
                        $refs['payments'][] = $payment->id;
                    }
                }
            }

            return SampleDataRun::create([
                'landlord_id' => $landlord->id,
                'status' => SampleDataRun::STATUS_POPULATED,
                'populated_at' => now(),
                'row_refs' => $refs,
            ]);
        });
    }

    public function reset(User $landlord): int
    {
        $runs = SampleDataRun::query()
            ->where('landlord_id', $landlord->id)
            ->where('status', SampleDataRun::STATUS_POPULATED)
            ->get();
        if ($runs->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($runs, $landlord): int {
            $count = 0;
            foreach ($runs as $run) {
                $refs = $run->row_refs ?? [];

                Payment::query()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $refs['payments'] ?? [])
                    ->delete();
                Invoice::query()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $refs['invoices'] ?? [])
                    ->forceDelete();
                Lease::query()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $refs['leases'] ?? [])
                    ->forceDelete();
                User::query()
                    ->whereIn('id', $refs['tenants'] ?? [])
                    ->forceDelete();
                Unit::query()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $refs['units'] ?? [])
                    ->forceDelete();
                Building::query()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $refs['buildings'] ?? [])
                    ->forceDelete();
                Property::query()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $refs['properties'] ?? [])
                    ->forceDelete();

                $run->update([
                    'status' => SampleDataRun::STATUS_RESET_DONE,
                    'reset_at' => now(),
                ]);
                $count++;
            }
            unset($landlord);

            return $count;
        });
    }
}
