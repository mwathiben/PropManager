<?php

namespace App\Console\Commands;

use App\Models\Building;
use App\Models\Unit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConvertBuildingsToWings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'buildings:convert-to-wings
                            {--parent= : ID of the building to use as parent}
                            {--child= : ID of the building to convert to a wing}
                            {--prefix= : Unit prefix for the wing (1-3 chars, e.g., A, B, BL)}
                            {--dry-run : Preview changes without executing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert a standalone building to a wing under a parent building';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $parentId = $this->option('parent');
        $childId = $this->option('child');
        $prefix = strtoupper($this->option('prefix') ?? '');
        $dryRun = $this->option('dry-run');

        if ($this->validateOptions($parentId, $childId, $prefix) !== 0) {
            return 1;
        }

        $buildings = $this->validateBuildings($parentId, $childId, $prefix);
        if ($buildings === null) {
            return 1;
        }

        [$parent, $child] = $buildings;
        $units = Unit::where('building_id', $child->id)->get();

        $this->displayPreview($parent, $child, $prefix, $units);

        if ($dryRun) {
            $this->warn('');
            $this->warn('DRY RUN - No changes made. Remove --dry-run to execute.');

            return 0;
        }

        if (! $this->confirm('Do you want to proceed with this conversion?')) {
            $this->info('Conversion cancelled.');

            return 0;
        }

        $this->executeConversion($parent, $child, $prefix, $units);

        $this->info('');
        $this->info("Successfully converted '{$child->name}' to a wing under '{$parent->name}'.");
        $this->info("Renamed {$units->count()} units with prefix '{$prefix}'.");

        return 0;
    }

    /**
     * Validate required options and prefix format.
     */
    private function validateOptions(?string $parentId, ?string $childId, string $prefix): int
    {
        if (! $parentId || ! $childId || ! $prefix) {
            $this->error('All options are required: --parent, --child, --prefix');
            $this->line('');
            $this->line('Usage: php artisan buildings:convert-to-wings --parent=1 --child=2 --prefix=A');
            $this->line('');
            $this->showAvailableBuildings();

            return 1;
        }

        if (strlen($prefix) < 1 || strlen($prefix) > 3) {
            $this->error('Prefix must be 1-3 characters.');

            return 1;
        }

        return 0;
    }

    /**
     * Find and validate parent and child buildings.
     *
     * @return array{0: Building, 1: Building}|null
     */
    private function validateBuildings(string $parentId, string $childId, string $prefix): ?array
    {
        $parent = $this->resolveParentBuilding($parentId);
        if ($parent === null) {
            return null;
        }

        $child = $this->resolveChildBuilding($childId);
        if ($child === null) {
            return null;
        }

        if ($parent->property_id !== $child->property_id) {
            $this->error('Parent and child buildings must belong to the same property.');

            return null;
        }

        $existingPrefixes = $parent->wings()->pluck('unit_prefix')->toArray();
        if (in_array($prefix, $existingPrefixes)) {
            $this->error("Prefix '{$prefix}' is already in use by another wing.");

            return null;
        }

        return [$parent, $child];
    }

    /**
     * Find and validate the parent building.
     */
    private function resolveParentBuilding(string $parentId): ?Building
    {
        $parent = Building::find($parentId);
        if (! $parent) {
            $this->error("Parent building with ID {$parentId} not found.");

            return null;
        }

        if ($parent->is_wing) {
            $this->error('Parent building cannot be a wing itself.');

            return null;
        }

        return $parent;
    }

    /**
     * Find and validate the child building.
     */
    private function resolveChildBuilding(string $childId): ?Building
    {
        $child = Building::find($childId);
        if (! $child) {
            $this->error("Child building with ID {$childId} not found.");

            return null;
        }

        if ($child->is_wing) {
            $this->error('This building is already a wing.');

            return null;
        }

        return $child;
    }

    /**
     * Display the conversion preview table and unit renaming samples.
     */
    private function displayPreview(Building $parent, Building $child, string $prefix, $units): void
    {
        $this->info('Conversion Preview:');
        $this->line('');
        $this->table(['Property', 'Value'], [
            ['Parent Building', "{$parent->name} (ID: {$parent->id})"],
            ['Child Building', "{$child->name} (ID: {$child->id})"],
            ['Wing Prefix', $prefix],
            ['Units to Rename', $units->count()],
        ]);

        $this->line('');
        $this->info('Unit Renaming:');

        $samples = $units->take(5);
        $sampleRenames = [];
        foreach ($samples as $unit) {
            $oldNumber = $unit->unit_number;
            $numericPart = preg_replace('/^[A-Z]+/', '', $oldNumber);
            $newNumber = $prefix.$numericPart;
            $sampleRenames[] = [$oldNumber, $newNumber];
        }
        if ($units->count() > 5) {
            $sampleRenames[] = ['...', '...'];
        }
        $this->table(['Current', 'New'], $sampleRenames);
    }

    /**
     * Execute the building conversion in a transaction.
     */
    private function executeConversion(Building $parent, Building $child, string $prefix, $units): void
    {
        DB::transaction(function () use ($parent, $child, $prefix, $units) {
            $child->update([
                'parent_building_id' => $parent->id,
                'is_wing' => true,
                'unit_prefix' => $prefix,
            ]);

            foreach ($units as $unit) {
                $numericPart = preg_replace('/^[A-Z]+/', '', $unit->unit_number);
                $unit->update([
                    'unit_number' => $prefix.$numericPart,
                ]);
            }
        });
    }

    /**
     * Show available buildings for selection.
     */
    protected function showAvailableBuildings(): void
    {
        $buildings = Building::with('property')
            ->whereNull('parent_building_id')
            ->get()
            ->groupBy('property_id');

        if ($buildings->isEmpty()) {
            $this->warn('No buildings found in the system.');

            return;
        }

        $this->info('Available Buildings:');
        $this->line('');

        foreach ($buildings as $propertyId => $propertyBuildings) {
            $property = $propertyBuildings->first()->property;
            $this->line("Property: {$property->name} (ID: {$propertyId})");

            $tableData = [];
            foreach ($propertyBuildings as $building) {
                $wingCount = $building->wings()->count();
                $unitCount = $building->units()->count();
                $tableData[] = [
                    $building->id,
                    $building->name,
                    $unitCount,
                    $wingCount > 0 ? "{$wingCount} wings" : '-',
                ];
            }

            $this->table(['ID', 'Name', 'Units', 'Wings'], $tableData);
            $this->line('');
        }
    }
}
