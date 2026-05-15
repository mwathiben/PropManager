<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Phase-27 BI-CI-1: deterministic dataset for golden-report comparisons.
 *
 * Seeded with fixed dates + amounts so the report output is byte-stable
 * across runs. Anchors to 2026-01-01 — not Carbon::now — so the
 * dataset doesn't drift across calendar boundaries.
 */
class GoldenReportFixtureSeeder extends Seeder
{
    public function __construct(private int $landlordId = 999000) {}

    public function run(): void
    {
        $anchor = CarbonImmutable::parse('2026-01-01');

        $landlord = User::firstOrCreate(
            ['id' => $this->landlordId],
            [
                'name' => 'Golden Landlord',
                'email' => "golden+{$this->landlordId}@propmanager.test",
                'password' => Hash::make('password'),
                'role' => 'landlord',
            ],
        );

        // 2 properties × 1 building × 2 units = 4 units total.
        foreach (['Acacia', 'Baobab'] as $propIndex => $propName) {
            $property = Property::firstOrCreate(
                ['landlord_id' => $landlord->id, 'name' => $propName],
                ['type' => 'residential', 'address' => 'Nairobi'],
            );
            $building = Building::firstOrCreate(
                ['landlord_id' => $landlord->id, 'property_id' => $property->id, 'name' => $propName.' Block'],
            );
            foreach ([1, 2] as $unitIndex) {
                $unitNumber = sprintf('%s-%d', strtoupper($propName[0]), $unitIndex);
                $unit = Unit::firstOrCreate(
                    [
                        'landlord_id' => $landlord->id,
                        'building_id' => $building->id,
                        'unit_number' => $unitNumber,
                    ],
                    [
                        'floor_number' => 1,
                        'target_rent' => 25000 + (1000 * ($propIndex + $unitIndex)),
                        'status' => $unitIndex === 2 && $propIndex === 1 ? 'vacant' : 'occupied',
                    ],
                );

                if ($unit->status === 'vacant') {
                    continue;
                }

                $tenant = User::firstOrCreate(
                    ['email' => "tenant-{$unit->id}@propmanager.test"],
                    [
                        'name' => "Tenant {$unitNumber}",
                        'password' => Hash::make('password'),
                        'role' => 'tenant',
                        'landlord_id' => $landlord->id,
                    ],
                );

                $lease = Lease::firstOrCreate(
                    [
                        'unit_id' => $unit->id,
                        'tenant_id' => $tenant->id,
                    ],
                    [
                        'landlord_id' => $landlord->id,
                        'rent_amount' => $unit->target_rent,
                        'start_date' => $anchor->subMonths(6),
                        'is_active' => true,
                    ],
                );

                // 3 payments per lease — deterministic amounts.
                for ($m = 0; $m < 3; $m++) {
                    Payment::firstOrCreate(
                        [
                            'lease_id' => $lease->id,
                            'reference' => "GOLD-{$unit->id}-{$m}",
                        ],
                        [
                            'landlord_id' => $landlord->id,
                            'amount' => $unit->target_rent,
                            'currency' => 'KES',
                            'payment_method' => 'cash',
                            'payment_date' => $anchor->subMonths(5 - $m)->day(5),
                        ],
                    );
                }
            }
        }
    }
}
