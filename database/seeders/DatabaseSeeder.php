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
            LegalDocumentSeeder::class,
            KycRequirementSeeder::class,
            MoveOutDeductionCategorySeeder::class,
            HelpContentSeeder::class,
            SecurityFaqSeeder::class,
            PlatformBillingSettingsSeeder::class,
            PlatformFeeTierSeeder::class,
        ]);

        // DevelopmentSeeder is committed (database/seeders/DevelopmentSeeder.php)
        // and idempotent — but the class_exists guard keeps `db:seed`
        // from fataling if it is ever absent again, so reference-data
        // seeding still succeeds on its own.
        if (app()->environment('local', 'testing') && class_exists(DevelopmentSeeder::class)) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}
