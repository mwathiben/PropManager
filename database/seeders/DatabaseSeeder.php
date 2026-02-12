<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            SubscriptionPlanSeeder::class,
            KycRequirementSeeder::class,
            MoveOutDeductionCategorySeeder::class,
            HelpContentSeeder::class,
            SecurityFaqSeeder::class,
            PlatformBillingSettingsSeeder::class,
            PlatformFeeTierSeeder::class,
        ]);

        if (app()->environment('local', 'testing')) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}
