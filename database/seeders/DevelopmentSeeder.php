<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local development dataset — a known, stable set of logins plus a
 * small property graph so the app is usable immediately after a
 * fresh database.
 *
 * WHY THIS FILE EXISTS (and is committed): DatabaseSeeder has called
 * DevelopmentSeeder::class for local/testing since Feb 2026, but the
 * file itself was never committed — it only ever lived as an
 * untracked local file, so it kept disappearing and taking the dev
 * accounts with it. This version is tracked in git and IDEMPOTENT
 * (every row is firstOrCreate / existence-guarded keyed on a stable
 * natural key), so running it is always safe and never needs a fresh
 * database.
 *
 * Restore the dev accounts at any time with:
 *   php artisan db:seed --class=Database\\Seeders\\DevelopmentSeeder
 * or the full set (reference data + dev data):
 *   php artisan db:seed
 *
 * --- Dev logins (all password: "password") ---
 *   admin@propmanager.test      super_admin
 *   landlord@propmanager.test   landlord     (the main dev login)
 *   caretaker@propmanager.test  caretaker    (under the landlord above)
 *   tenant@propmanager.test     tenant       (+ tenant2, tenant3)
 *
 * This is DEV data, distinct from LoadTestSeeder (which seeds the
 * dedicated k6 load-test landlord — keep the two separate).
 */
class DevelopmentSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $admin = $this->ensureUser('admin@propmanager.test', 'Demo Admin', 'super_admin');

        $landlord = $this->ensureUser('landlord@propmanager.test', 'Demo Landlord', 'landlord');

        $this->ensureUser('caretaker@propmanager.test', 'Demo Caretaker', 'caretaker', $landlord->id);

        $tenants = [
            $this->ensureUser('tenant@propmanager.test', 'Demo Tenant One', 'tenant', $landlord->id),
            $this->ensureUser('tenant2@propmanager.test', 'Demo Tenant Two', 'tenant', $landlord->id),
            $this->ensureUser('tenant3@propmanager.test', 'Demo Tenant Three', 'tenant', $landlord->id),
        ];

        // The property graph is built once. If it already exists for
        // this landlord, the users above are still re-ensured but the
        // graph is left untouched — keeps the seeder fully re-runnable.
        if (Property::where('landlord_id', $landlord->id)->where('name', 'Demo Property')->exists()) {
            $this->command?->info('DevelopmentSeeder: dev accounts ensured; property graph already present — skipping graph.');

            return;
        }

        $property = Property::create([
            'name' => 'Demo Property',
            'address' => '12 Demo Lane, Nairobi',
            'type' => 'apartment',
            'landlord_id' => $landlord->id,
        ]);

        $building = Building::create([
            'property_id' => $property->id,
            'name' => 'Demo Block',
            'total_floors' => 2,
            'units_per_floor' => 3,
            'landlord_id' => $landlord->id,
            'building_type' => 'residential_apartment',
        ]);

        // 6 units: the first 3 occupied by the demo tenants, the rest
        // vacant — enough to exercise occupied + vacant code paths.
        $unitIndex = 0;
        for ($floor = 1; $floor <= 2; $floor++) {
            for ($num = 1; $num <= 3; $num++) {
                $occupied = $unitIndex < count($tenants);

                $unit = Unit::create([
                    'building_id' => $building->id,
                    'unit_number' => "D{$floor}0{$num}",
                    'floor_number' => $floor,
                    'status' => $occupied ? 'occupied' : 'vacant',
                    'target_rent' => 30000,
                    'landlord_id' => $landlord->id,
                ]);

                if (! $occupied) {
                    $unitIndex++;

                    continue;
                }

                $tenant = $tenants[$unitIndex];

                $lease = Lease::create([
                    'unit_id' => $unit->id,
                    'tenant_id' => $tenant->id,
                    'landlord_id' => $landlord->id,
                    'rent_amount' => $unit->target_rent,
                    'deposit_amount' => $unit->target_rent,
                    'start_date' => now()->subMonths(4),
                    'is_active' => true,
                    'wallet_balance' => 0,
                ]);

                // Three invoices per lease: two paid, one outstanding —
                // a realistic ledger for the dashboard + finances pages.
                foreach (['paid', 'paid', 'sent'] as $i => $status) {
                    Invoice::create([
                        'lease_id' => $lease->id,
                        'landlord_id' => $landlord->id,
                        'invoice_number' => 'DEMO-'.$unit->unit_number.'-'.$i,
                        'rent_due' => $lease->rent_amount,
                        'water_due' => 0,
                        'arrears' => 0,
                        'wallet_applied' => 0,
                        'total_due' => $lease->rent_amount,
                        'amount_paid' => $status === 'paid' ? $lease->rent_amount : 0,
                        'status' => $status,
                        'due_date' => now()->subMonths(2 - $i)->addDays(7),
                        'billing_period_start' => now()->subMonths(2 - $i)->startOfMonth(),
                    ]);
                }

                $unitIndex++;
            }
        }

        $this->command?->info(
            'DevelopmentSeeder: seeded dev accounts (admin/landlord/caretaker/3 tenants) + Demo Property '
            .'(6 units, 3 leases, 9 invoices). Logins use password "'.self::PASSWORD.'".'
        );
    }

    /**
     * Idempotently ensure a user exists. `role` and `landlord_id` are
     * not on User::$fillable, so a freshly created user is populated
     * via forceFill (the same pattern LoadTestSeeder uses).
     */
    private function ensureUser(string $email, string $name, string $role, ?int $landlordId = null): User
    {
        $existing = User::where('email', $email)->first();
        if ($existing) {
            return $existing;
        }

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
            'role' => $role,
            'landlord_id' => $landlordId,
        ])->save();

        return $user;
    }
}
