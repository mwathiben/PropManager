<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Phase-22 PERF-LOAD-2: seeds the dedicated load-test landlord the k6
 * scripts (tests/load/) authenticate as. Idempotent — safe to run
 * repeatedly; it no-ops if the landlord already exists.
 *
 * This is a LOAD-TEST fixture, not production data. It is wired into
 * the CI load-smoke job and can be run locally before a baseline run:
 *   php artisan db:seed --class=Database\\Seeders\\LoadTestSeeder
 *
 * The k6 scenarios only ever READ this landlord's data (or hit
 * data-safe rejection paths), so a load run never mutates real tenants.
 */
class LoadTestSeeder extends Seeder
{
    public const EMAIL = 'loadtest@propmanager.test';

    public function run(): void
    {
        if (User::where('email', self::EMAIL)->exists()) {
            $this->command?->info('LoadTestSeeder: load-test landlord already exists — skipping.');

            return;
        }

        // Fixed reference instant — NOT now(). The RTL visual-snapshot suite
        // (tests/a11y/rtl) renders this landlord's invoices and diffs the result
        // against committed baselines. now()-relative dates drift day-to-day, so
        // the rendered due dates would never match a committed baseline. Anchoring
        // to a constant keeps the fixture deterministic across runs.
        $reference = CarbonImmutable::create(2026, 3, 15, 9, 0, 0);

        $landlord = new User;
        $landlord->forceFill([
            'name' => 'Load Test Landlord',
            'email' => self::EMAIL,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'role' => 'landlord',
        ])->save();

        $property = Property::create([
            'name' => 'Load Test Property',
            'address' => '1 Load Test Avenue, Nairobi',
            'type' => 'apartment',
            'landlord_id' => $landlord->id,
        ]);

        $building = Building::create([
            'property_id' => $property->id,
            'name' => 'Load Test Block',
            'total_floors' => 3,
            'units_per_floor' => 6,
            'landlord_id' => $landlord->id,
            'building_type' => 'residential_apartment',
        ]);

        // 18 units, half occupied with tenants + invoices — enough rows
        // that the hot read paths return realistic-shaped result sets.
        for ($floor = 1; $floor <= 3; $floor++) {
            for ($num = 1; $num <= 6; $num++) {
                $occupied = $num <= 3;
                $unit = Unit::create([
                    'building_id' => $building->id,
                    'unit_number' => "L{$floor}0{$num}",
                    'floor_number' => $floor,
                    'status' => $occupied ? 'occupied' : 'vacant',
                    'target_rent' => 25000,
                    'landlord_id' => $landlord->id,
                ]);

                if (! $occupied) {
                    continue;
                }

                $tenant = new User;
                $tenant->forceFill([
                    'name' => "Load Test Tenant {$floor}{$num}",
                    'email' => "loadtest.tenant.{$floor}{$num}@propmanager.test",
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'role' => 'tenant',
                    'landlord_id' => $landlord->id,
                ])->save();

                $lease = Lease::create([
                    'unit_id' => $unit->id,
                    'tenant_id' => $tenant->id,
                    'landlord_id' => $landlord->id,
                    'rent_amount' => $unit->target_rent,
                    'deposit_amount' => $unit->target_rent,
                    'start_date' => $reference->subMonths(3),
                    'is_active' => true,
                    'wallet_balance' => 0,
                ]);

                foreach (['paid', 'paid', 'sent'] as $i => $status) {
                    Invoice::create([
                        'lease_id' => $lease->id,
                        'landlord_id' => $landlord->id,
                        'invoice_number' => 'LOAD-'.$unit->unit_number.'-'.$i,
                        'rent_due' => $lease->rent_amount,
                        'water_due' => 0,
                        'arrears' => 0,
                        'wallet_applied' => 0,
                        'total_due' => $lease->rent_amount,
                        'amount_paid' => $status === 'paid' ? $lease->rent_amount : 0,
                        'status' => $status,
                        'due_date' => $reference->subMonths(2 - $i)->addDays(7),
                        'billing_period_start' => $reference->subMonths(2 - $i)->startOfMonth(),
                    ]);
                }
            }
        }

        $this->command?->info('LoadTestSeeder: seeded load-test landlord '.self::EMAIL.' with 9 tenants + 27 invoices.');
    }
}
