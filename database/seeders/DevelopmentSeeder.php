<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Notification;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyOwner;
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
 *   landlord@propmanager.test   manager      (firm — manages the owner below; main dev login, has the demo data)
 *   test@example.com            landlord     (self-managing owner — fresh, lands on onboarding)
 *   caretaker@propmanager.test  caretaker    (under the manager above)
 *   tenant@propmanager.test     tenant       (+ tenant2, tenant3)
 *   owner@propmanager.test      owner        (delegating owner portal — Phase 101-104)
 *
 * This is DEV data, distinct from LoadTestSeeder (which seeds the
 * dedicated k6 load-test landlord — keep the two separate).
 */
class DevelopmentSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $this->ensureUser('admin@propmanager.test', 'Demo Admin', 'super_admin');

        // Manages the demo owner's property for a fee — i.e. a management firm, not a
        // self-manager. Kept as $landlord locally since it is the scope owner downstream.
        $landlord = $this->ensureUser('landlord@propmanager.test', 'Demo Manager (firm)', 'manager');

        // A self-managing landlord (no owner delegation → stays `landlord`). Restores the
        // familiar test login and covers the role the manager split would otherwise leave empty.
        $this->ensureUser('test@example.com', 'Test Landlord', 'landlord');

        $this->ensureUser('caretaker@propmanager.test', 'Demo Caretaker', 'caretaker', $landlord->id);

        $tenants = [
            $this->ensureUser('tenant@propmanager.test', 'Demo Tenant One', 'tenant', $landlord->id),
            $this->ensureUser('tenant2@propmanager.test', 'Demo Tenant Two', 'tenant', $landlord->id),
            $this->ensureUser('tenant3@propmanager.test', 'Demo Tenant Three', 'tenant', $landlord->id),
        ];

        // Phase 101-104: a property OWNER the PM manages on behalf of, with a portal login.
        $ownerUser = $this->ensureUser('owner@propmanager.test', 'Demo Owner', 'owner', $landlord->id);

        // The property graph is built once. On a re-run the existing graph is reused; the
        // owner demo is (re-)ensured against it either way so the owner portal always has data.
        $property = Property::where('landlord_id', $landlord->id)->where('name', 'Demo Property')->first()
            ?? $this->buildPropertyGraph($landlord, $tenants);

        $this->ensureOwnerDemo($landlord, $ownerUser, $property);

        $this->command?->info(
            'DevelopmentSeeder: dev accounts ensured (admin/manager/self-managing landlord/caretaker/3 tenants/owner) + Demo Property '
            .'with an owner portal demo (assigned property, 10% fee, payouts, notifications). Logins use password "'.self::PASSWORD.'".'
        );
    }

    /**
     * Build the demo property graph: 6 units (3 occupied), 3 leases, 9 invoices, and a
     * payment per paid invoice (so the owner statement has real collected figures).
     *
     * @param  array<int, User>  $tenants
     */
    private function buildPropertyGraph(User $landlord, array $tenants): Property
    {
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

                // Three invoices per lease: two paid, one outstanding. Each paid invoice
                // gets a real Payment so the owner statement's "collected" is non-zero.
                foreach (['paid', 'paid', 'sent'] as $i => $status) {
                    $invoice = Invoice::create([
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

                    if ($status === 'paid') {
                        $this->ensurePayment($lease, $invoice);
                    }
                }

                $unitIndex++;
            }
        }

        return $property;
    }

    /**
     * Wire the owner portal demo: a PropertyOwner contact linked to the owner login, the
     * demo property assigned to them, a management fee, a couple of payouts, and a couple of
     * in-app notices. Idempotent + backfills payments so it also works on an older dev DB
     * whose paid invoices predate this seeder's payment rows.
     */
    private function ensureOwnerDemo(User $landlord, User $ownerUser, Property $property): void
    {
        $contact = PropertyOwner::where('landlord_id', $landlord->id)->where('name', 'Demo Owner')->first()
            ?? new PropertyOwner;

        $contact->forceFill([
            'landlord_id' => $landlord->id,
            'user_id' => $ownerUser->id,
            'name' => 'Demo Owner',
            'email' => $ownerUser->email,
            'is_active' => true,
            'management_fee_type' => 'percentage',
            'management_fee_value' => 10,
        ])->save();

        if ((int) $property->property_owner_id !== (int) $contact->id) {
            $property->update(['property_owner_id' => $contact->id]);
        }

        // Backfill a payment for any paid invoice on the property that lacks one (older DBs).
        $leaseIds = Lease::whereHas('unit.building', fn ($q) => $q->where('property_id', $property->id))->pluck('id');
        Invoice::whereIn('lease_id', $leaseIds)->where('status', 'paid')->each(function (Invoice $invoice) {
            $lease = $invoice->lease;
            if ($lease && ! Payment::where('invoice_id', $invoice->id)->exists()) {
                $this->ensurePayment($lease, $invoice);
            }
        });

        if (OwnerPayout::where('property_owner_id', $contact->id)->count() === 0) {
            foreach ([2, 1] as $monthsAgo) {
                OwnerPayout::create([
                    'landlord_id' => $landlord->id,
                    'property_owner_id' => $contact->id,
                    'amount' => 45000,
                    'currency' => 'KES',
                    'paid_on' => now()->subMonths($monthsAgo),
                    'method' => 'bank_transfer',
                    'reference' => 'DEMO-PO-'.$monthsAgo,
                    'created_by' => $landlord->id,
                ]);
            }
        }

        $hasNotices = Notification::withoutGlobalScope('landlord')
            ->where('recipient_id', $ownerUser->id)
            ->whereIn('type', [Notification::TYPE_OWNER_PAYOUT_SENT, Notification::TYPE_OWNER_STATEMENT_SENT])
            ->exists();
        if (! $hasNotices) {
            $this->ensureNotice($landlord, $ownerUser, Notification::TYPE_OWNER_PAYOUT_SENT, 'Payout sent', 'A payout of KSh 45,000.00 has been sent to you.');
            $this->ensureNotice($landlord, $ownerUser, Notification::TYPE_OWNER_STATEMENT_SENT, 'New statement available', 'A new statement has been issued for your account.');
        }
    }

    private function ensurePayment(Lease $lease, Invoice $invoice): void
    {
        Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'amount' => $lease->rent_amount,
            'payment_method' => 'mpesa',
            'reference' => 'DEMO-PAY-'.$invoice->invoice_number,
            'payment_date' => $invoice->due_date,
        ]);
    }

    private function ensureNotice(User $landlord, User $recipient, string $type, string $subject, string $message): void
    {
        Notification::create([
            'landlord_id' => $landlord->id,
            'recipient_id' => $recipient->id,
            'type' => $type,
            'urgency' => Notification::getUrgencyForType($type),
            'channel' => 'in_app',
            'subject' => $subject,
            'message' => $message,
            'data' => [],
            'status' => 'sent',
            'sent_at' => now(),
        ]);
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
