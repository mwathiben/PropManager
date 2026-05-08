<?php

namespace Database\Seeders;

use App\Models\MoveOutDeductionCategory;
use Illuminate\Database\Seeder;

class MoveOutDeductionCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Cleaning Fee',
                'description' => 'Professional deep cleaning of the unit before new tenant.',
                'default_amount' => 3000,
                'always_apply' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Paint Works',
                'description' => 'Repainting walls and ceilings to restore original condition.',
                'default_amount' => 5000,
                'always_apply' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Key Replacement',
                'description' => 'Replacement of lost or unreturned keys.',
                'default_amount' => 500,
                'always_apply' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Wall Damage Repair',
                'description' => 'Repair of holes, cracks, or other damage to walls.',
                'default_amount' => 8000,
                'always_apply' => false,
                'sort_order' => 4,
            ],
            [
                'name' => 'Floor Repair',
                'description' => 'Repair or replacement of damaged flooring.',
                'default_amount' => 10000,
                'always_apply' => false,
                'sort_order' => 5,
            ],
            [
                'name' => 'Window Replacement',
                'description' => 'Replacement of broken or damaged windows.',
                'default_amount' => 12000,
                'always_apply' => false,
                'sort_order' => 6,
            ],
            [
                'name' => 'Door Repair',
                'description' => 'Repair or replacement of damaged doors.',
                'default_amount' => 6000,
                'always_apply' => false,
                'sort_order' => 7,
            ],
            [
                'name' => 'Electrical Repairs',
                'description' => 'Repair of damaged electrical outlets, switches, or fixtures.',
                'default_amount' => 4000,
                'always_apply' => false,
                'sort_order' => 8,
            ],
            [
                'name' => 'Plumbing Repairs',
                'description' => 'Repair of damaged pipes, taps, or plumbing fixtures.',
                'default_amount' => 5000,
                'always_apply' => false,
                'sort_order' => 9,
            ],
            [
                'name' => 'Kitchen Repairs',
                'description' => 'Repair of kitchen cabinets, countertops, or fixtures.',
                'default_amount' => 15000,
                'always_apply' => false,
                'sort_order' => 10,
            ],
            [
                'name' => 'Bathroom Repairs',
                'description' => 'Repair of bathroom fixtures, tiles, or fittings.',
                'default_amount' => 8000,
                'always_apply' => false,
                'sort_order' => 11,
            ],
            [
                'name' => 'Pest Control',
                'description' => 'Professional pest control treatment.',
                'default_amount' => 2500,
                'always_apply' => false,
                'sort_order' => 12,
            ],
            [
                'name' => 'Garbage Removal',
                'description' => 'Removal of items left behind by tenant.',
                'default_amount' => 2000,
                'always_apply' => false,
                'sort_order' => 13,
            ],
            [
                'name' => 'Curtain Rail Repair',
                'description' => 'Repair or replacement of curtain rails and fittings.',
                'default_amount' => 1500,
                'always_apply' => false,
                'sort_order' => 14,
            ],
            [
                'name' => 'Security Deposit Penalty',
                'description' => 'Penalty for early lease termination or contract breach.',
                'default_amount' => 5000,
                'always_apply' => false,
                'sort_order' => 15,
            ],
            [
                'name' => 'Water Heater Repair',
                'description' => 'Repair or replacement of damaged water heater.',
                'default_amount' => 8000,
                'always_apply' => false,
                'sort_order' => 16,
            ],
            [
                'name' => 'Ceiling Fan Repair',
                'description' => 'Repair or replacement of ceiling fans.',
                'default_amount' => 3000,
                'always_apply' => false,
                'sort_order' => 17,
            ],
            [
                'name' => 'Light Fixture Replacement',
                'description' => 'Replacement of broken or missing light fixtures.',
                'default_amount' => 2000,
                'always_apply' => false,
                'sort_order' => 18,
            ],
            [
                'name' => 'Smoke Detector Replacement',
                'description' => 'Replacement of missing or non-functional smoke detectors.',
                'default_amount' => 1000,
                'always_apply' => false,
                'sort_order' => 19,
            ],
            [
                'name' => 'Carpet Cleaning',
                'description' => 'Professional carpet cleaning or stain removal.',
                'default_amount' => 4000,
                'always_apply' => false,
                'sort_order' => 20,
            ],
            [
                'name' => 'Tile Repair',
                'description' => 'Repair or replacement of cracked or broken tiles.',
                'default_amount' => 6000,
                'always_apply' => false,
                'sort_order' => 21,
            ],
            [
                'name' => 'Wardrobe Repair',
                'description' => 'Repair of built-in wardrobes, closets, or shelving.',
                'default_amount' => 5000,
                'always_apply' => false,
                'sort_order' => 22,
            ],
            [
                'name' => 'Balcony Repair',
                'description' => 'Repair of balcony railing, floor, or fixtures.',
                'default_amount' => 7000,
                'always_apply' => false,
                'sort_order' => 23,
            ],
            [
                'name' => 'Ceiling Repair',
                'description' => 'Repair of ceiling damage, leaks, or peeling.',
                'default_amount' => 6000,
                'always_apply' => false,
                'sort_order' => 24,
            ],
            [
                'name' => 'Unpaid Utility Bills',
                'description' => 'Outstanding water, electricity, or other utility charges.',
                'default_amount' => 0,
                'always_apply' => false,
                'sort_order' => 25,
            ],
            [
                'name' => 'Notice Period Penalty',
                'description' => 'Penalty for failure to serve required notice period.',
                'default_amount' => 0,
                'always_apply' => false,
                'sort_order' => 26,
            ],
            [
                'name' => 'Property Inspection Fee',
                'description' => 'Professional inspection and condition assessment fee.',
                'default_amount' => 3000,
                'always_apply' => false,
                'sort_order' => 27,
            ],
            [
                'name' => 'Document Processing Fee',
                'description' => 'Administrative processing for move-out documentation.',
                'default_amount' => 1000,
                'always_apply' => false,
                'sort_order' => 28,
            ],
            [
                'name' => 'Meter Tampering Fee',
                'description' => 'Penalty for tampering with water or electricity meters.',
                'default_amount' => 10000,
                'always_apply' => false,
                'sort_order' => 29,
            ],
        ];

        foreach ($categories as $category) {
            MoveOutDeductionCategory::updateOrCreate(
                [
                    'landlord_id' => null,
                    'building_id' => null,
                    'name' => $category['name'],
                ],
                [
                    'description' => $category['description'],
                    'default_amount' => $category['default_amount'],
                    'always_apply' => $category['always_apply'],
                    'is_active' => true,
                    'sort_order' => $category['sort_order'],
                ]
            );
        }
    }
}
