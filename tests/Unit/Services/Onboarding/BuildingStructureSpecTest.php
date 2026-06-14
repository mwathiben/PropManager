<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Onboarding;

use App\Models\Property;
use App\Services\Onboarding\BuildingStructureSpec;
use Tests\TestCase;

/**
 * Pure-logic characterization of the structure-step parameter object. These
 * lock the two onboarding data shapes (canonical snake_case step-4 data and the
 * legacy camelCase /onboarding/store payload) onto a single normalized spec the
 * BuildingStructureBuilder consumes, so the extraction cannot drift from the
 * behaviour OnboardingService::processStructure() / storeLegacy() had inline.
 */
class BuildingStructureSpecTest extends TestCase
{
    private function property(string $name = 'Acacia Court'): Property
    {
        $property = new Property;
        $property->name = $name;

        return $property;
    }

    public function test_canonical_single_building_maps_top_level_grid(): void
    {
        $spec = BuildingStructureSpec::fromCanonicalStep(
            ['has_wings' => false, 'floors' => 2, 'units_per_floor' => 3],
            $this->property(),
            12000.0,
        );

        $this->assertSame(12000.0, $spec->baseRent);
        $this->assertSame([], $spec->wings);
        $this->assertSame('Acacia Court', $spec->mainBlock->name);
        $this->assertSame('', $spec->mainBlock->prefix);
        $this->assertSame(2, $spec->mainBlock->floors);
        $this->assertSame(3, $spec->mainBlock->unitsPerFloor);
    }

    public function test_canonical_winged_building_uppercases_prefix_and_zeroes_container(): void
    {
        $spec = BuildingStructureSpec::fromCanonicalStep(
            [
                'has_wings' => true,
                'wings' => [
                    ['name' => 'Block A', 'prefix' => 'a', 'floors' => 1, 'units_per_floor' => 2],
                    ['name' => 'Block B', 'prefix' => 'b', 'floors' => 3, 'units_per_floor' => 4],
                ],
            ],
            $this->property(),
            9000.0,
        );

        $this->assertSame(0, $spec->mainBlock->floors);
        $this->assertSame(0, $spec->mainBlock->unitsPerFloor);
        $this->assertCount(2, $spec->wings);
        $this->assertSame('Block A', $spec->wings[0]->name);
        $this->assertSame('A', $spec->wings[0]->prefix);
        $this->assertSame(1, $spec->wings[0]->floors);
        $this->assertSame(2, $spec->wings[0]->unitsPerFloor);
        $this->assertSame('B', $spec->wings[1]->prefix);
        $this->assertSame(3, $spec->wings[1]->floors);
        $this->assertSame(4, $spec->wings[1]->unitsPerFloor);
    }

    public function test_legacy_single_building_reads_camel_case_keys_and_base_rent(): void
    {
        $spec = BuildingStructureSpec::fromLegacyPayload(
            ['hasWings' => false, 'floors' => 2, 'unitsPerFloor' => 3, 'baseRent' => 15000],
            $this->property('Riverside'),
        );

        $this->assertSame(15000.0, $spec->baseRent);
        $this->assertSame([], $spec->wings);
        $this->assertSame('Riverside', $spec->mainBlock->name);
        $this->assertSame(2, $spec->mainBlock->floors);
        $this->assertSame(3, $spec->mainBlock->unitsPerFloor);
    }

    public function test_legacy_winged_building_uppercases_prefix_and_maps_camel_case_wings(): void
    {
        $spec = BuildingStructureSpec::fromLegacyPayload(
            [
                'hasWings' => true,
                'baseRent' => 20000,
                'wings' => [
                    ['name' => 'Block A', 'prefix' => 'a', 'floors' => 1, 'unitsPerFloor' => 2],
                ],
            ],
            $this->property('Riverside'),
        );

        $this->assertSame(20000.0, $spec->baseRent);
        $this->assertSame(0, $spec->mainBlock->floors);
        $this->assertCount(1, $spec->wings);
        $this->assertSame('A', $spec->wings[0]->prefix);
        $this->assertSame(1, $spec->wings[0]->floors);
        $this->assertSame(2, $spec->wings[0]->unitsPerFloor);
    }

    public function test_canonical_treats_has_wings_true_with_empty_wings_as_single(): void
    {
        $spec = BuildingStructureSpec::fromCanonicalStep(
            ['has_wings' => true, 'wings' => [], 'floors' => 4, 'units_per_floor' => 5],
            $this->property(),
            10000.0,
        );

        $this->assertSame([], $spec->wings);
        $this->assertSame(4, $spec->mainBlock->floors);
        $this->assertSame(5, $spec->mainBlock->unitsPerFloor);
    }
}
