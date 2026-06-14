<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\Property;

/**
 * Normalized description of the building structure to create for a property:
 * the main building (or zeroed wing container) plus zero or more wings, with the
 * base rent every generated unit inherits. Both onboarding entry points map
 * their raw payloads here via the factories so BuildingStructureBuilder has a
 * single shape to build from. The owning landlord is read from the property.
 */
final class BuildingStructureSpec
{
    /**
     * @param  array<int, UnitBlockSpec>  $wings
     */
    public function __construct(
        public readonly Property $property,
        public readonly float $baseRent,
        public readonly UnitBlockSpec $mainBlock,
        public readonly array $wings = [],
    ) {}

    /**
     * Canonical step-4 wizard data (snake_case, integer-validated).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromCanonicalStep(array $data, Property $property, float $baseRent): self
    {
        $wings = $data['wings'] ?? [];

        if (($data['has_wings'] ?? false) && ! empty($wings)) {
            return self::winged($property, $baseRent, $wings, 'units_per_floor');
        }

        return self::single($property, $baseRent, (int) $data['floors'], (int) $data['units_per_floor']);
    }

    /**
     * Legacy /onboarding/store payload (camelCase, carries its own baseRent).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromLegacyPayload(array $data, Property $property): self
    {
        $baseRent = (float) $data['baseRent'];
        $wings = $data['wings'] ?? [];

        if (($data['hasWings'] ?? false) && ! empty($wings)) {
            return self::winged($property, $baseRent, $wings, 'unitsPerFloor');
        }

        return self::single($property, $baseRent, (int) $data['floors'], (int) $data['unitsPerFloor']);
    }

    private static function single(Property $property, float $baseRent, int $floors, int $unitsPerFloor): self
    {
        return new self(
            $property,
            $baseRent,
            new UnitBlockSpec($property->name, '', $floors, $unitsPerFloor),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $wings
     */
    private static function winged(Property $property, float $baseRent, array $wings, string $unitsKey): self
    {
        return new self(
            $property,
            $baseRent,
            new UnitBlockSpec($property->name, '', 0, 0),
            array_values(array_map(
                fn (array $wing): UnitBlockSpec => new UnitBlockSpec(
                    $wing['name'],
                    strtoupper($wing['prefix']),
                    (int) $wing['floors'],
                    (int) $wing[$unitsKey],
                ),
                $wings,
            )),
        );
    }
}
