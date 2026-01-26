<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KycRequirementSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'requirement_type' => 'selfie',
                'label' => 'Profile Photo / Selfie',
                'description' => 'A clear photo of your face for identification purposes. This helps us verify your identity.',
                'is_required' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'requirement_type' => 'national_id',
                'label' => 'National ID',
                'description' => 'Upload a clear photo of both sides of your National ID or Passport.',
                'is_required' => true,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'requirement_type' => 'signed_lease',
                'label' => 'Signed Lease Agreement',
                'description' => 'Upload the signed lease agreement document provided by your landlord.',
                'is_required' => true,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($defaults as $requirement) {
            DB::table('kyc_requirements')->updateOrInsert(
                [
                    'landlord_id' => null,
                    'building_id' => null,
                    'requirement_type' => $requirement['requirement_type'],
                ],
                array_merge($requirement, [
                    'landlord_id' => null,
                    'building_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
