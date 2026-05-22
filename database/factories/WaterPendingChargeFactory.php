<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Lease;
use App\Models\User;
use App\Models\WaterPendingCharge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaterPendingCharge>
 */
class WaterPendingChargeFactory extends Factory
{
    protected $model = WaterPendingCharge::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory(),
            'lease_id' => Lease::factory(),
            'amount' => 500,
            'type' => 'reconnection_fee',
            'description' => 'Water reconnection fee',
            'applied_invoice_id' => null,
            'applied_at' => null,
        ];
    }
}
