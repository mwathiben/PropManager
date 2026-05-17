<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SlaDefinition;
use Illuminate\Database\Seeder;

/**
 * Phase-49 SLA-PER-CATEGORY-3: platform-default SLA matrix.
 *
 * landlord_id NULL = applies globally; individual landlords override
 * specific rows by inserting their own (landlord_id = N, same other
 * dimensions) row with is_active=true. The SlaDefinitionService cascade
 * prefers landlord rows over global rows.
 */
class Phase49SlaSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // urgent — any category, any subcategory: 4h response / 24h resolution
            ['priority' => 'urgent', 'category' => null, 'subcategory' => null, 'response_seconds' => 14400, 'resolution_seconds' => 86400],

            // plumbing — slow resolution (waits on parts + vendor scheduling)
            ['priority' => 'high', 'category' => 'issue', 'subcategory' => 'plumbing', 'response_seconds' => 86400, 'resolution_seconds' => 172800],
            ['priority' => 'medium', 'category' => 'issue', 'subcategory' => 'plumbing', 'response_seconds' => 259200, 'resolution_seconds' => 604800],

            // electrical — faster resolution required (safety)
            ['priority' => 'high', 'category' => 'issue', 'subcategory' => 'electrical', 'response_seconds' => 14400, 'resolution_seconds' => 43200],
            ['priority' => 'medium', 'category' => 'issue', 'subcategory' => 'electrical', 'response_seconds' => 86400, 'resolution_seconds' => 259200],

            // structural — patient resolution window (engineering surveys)
            ['priority' => 'high', 'category' => 'issue', 'subcategory' => 'structural', 'response_seconds' => 86400, 'resolution_seconds' => 1209600],
            ['priority' => 'medium', 'category' => 'issue', 'subcategory' => 'structural', 'response_seconds' => 259200, 'resolution_seconds' => 1209600],

            // pest_control — moderate
            ['priority' => 'high', 'category' => 'issue', 'subcategory' => 'pest_control', 'response_seconds' => 86400, 'resolution_seconds' => 432000],
            ['priority' => 'medium', 'category' => 'issue', 'subcategory' => 'pest_control', 'response_seconds' => 172800, 'resolution_seconds' => 604800],

            // appliances — moderate
            ['priority' => 'high', 'category' => 'issue', 'subcategory' => 'appliances', 'response_seconds' => 86400, 'resolution_seconds' => 432000],
            ['priority' => 'medium', 'category' => 'issue', 'subcategory' => 'appliances', 'response_seconds' => 172800, 'resolution_seconds' => 604800],

            // complaints — generally faster response, longer resolution
            ['priority' => 'high', 'category' => 'complaint', 'subcategory' => null, 'response_seconds' => 86400, 'resolution_seconds' => 604800],
        ];

        foreach ($defaults as $row) {
            SlaDefinition::updateOrCreate(
                [
                    'landlord_id' => null,
                    'category' => $row['category'],
                    'subcategory' => $row['subcategory'],
                    'priority' => $row['priority'],
                ],
                [
                    'response_seconds' => $row['response_seconds'],
                    'resolution_seconds' => $row['resolution_seconds'],
                    'is_active' => true,
                ],
            );
        }
    }
}
