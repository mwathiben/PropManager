<?php

namespace Database\Seeders;

use App\Models\PlatformBillingSetting;
use Illuminate\Database\Seeder;

class PlatformBillingSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only create if no settings exist
        if (PlatformBillingSetting::count() === 0) {
            PlatformBillingSetting::create([
                'active_billing_model' => 'transaction_fee',
                'transaction_fee_percentage' => env('DEFAULT_TRANSACTION_FEE_PERCENTAGE', 2.50),
                'minimum_fee' => env('DEFAULT_MINIMUM_FEE', 50.00),
                'maximum_fee' => null,
                'fee_bearer' => 'landlord',
                'hybrid_subscription_discount' => 100.00, // 100% = zero fees for subscribers
                'is_active' => true,
            ]);

            $this->command->info('Platform billing settings created successfully.');
        } else {
            $this->command->info('Platform billing settings already exist, skipping.');
        }
    }
}
