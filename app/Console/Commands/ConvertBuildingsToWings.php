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

        // Validate required options
        if (! $parentId || ! $childId || ! $prefix) {
            $this->error('All options are required: --parent, --child, --prefix');
            $this->line('');
            $this->line('Usage: php artisan buildings:convert-to-wings --parent=1 --child=2 --prefix=A');
            $this->line('');
            $this->showAvailableBuildings();

            return 1;
        }

        // Validate prefix length
        if (strlen($prefix) < 1 || strlen($prefix) > 3) {
            $this->error('Prefix must be 1-3 characters.');

            return 1;
        }

        // Find parent building
        $parent = Building::find($parentId);
        if (! $parent) {
            $this->error("Parent building with ID {$parentId} not found.");

            return 1;
        }

        // Parent should not be a wing itself
        if ($parent->is_wing) {
            $this->error('Parent building cannot be a wing itself.');

            return 1;
        }

        // Find child building
        $child = Building::find($childId);
        if (! $child) {
            $this->error("Child building with ID {$childId} not found.");

            return 1;
        }

        // Child should not already be a wing
        if ($child->is_wing) {
            $this->error('This building is already a wing.');

            return 1;
        }

        // Child should be in the same property
        if ($parent->property_id !== $child->property_id) {
            $this->error('Parent and child buildings must belong to the same property.');

            return 1;
        }

        // Check prefix uniqueness among existing wings
        $existingPrefixes = $parent->wings()->pluck('unit_prefix')->toArray();
        if (in_array($prefix, $existingPrefixes)) {
            $this->error("Prefix '{$prefix}' is already in use by another wing.");

            return 1;
        }

        // Get units to be renamed
        $units = Unit::where('building_id', $child->id)->get();

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

        // Show sample renames
        $samples = $units->take(5);
        $sampleRenames = [];
        foreach ($samples as $unit) {
            $oldNumber = $unit->unit_number;
            // Strip any existing prefix (letters at the start)
            $numericPart = preg_replace('/^[A-Z]+/', '', $oldNumber);
            $newNumber = $prefix.$numericPart;
            $sampleRenames[] = [$oldNumber, $newNumber];
        }
        if ($units->count() > 5) {
            $sampleRenames[] = ['...', '...'];
        }
        $this->table(['Current', 'New'], $sampleRenames);

        if ($dryRun) {
            $this->warn('');
            $this->warn('DRY RUN - No changes made. Remove --dry-run to execute.');

            return 0;
        }

        // Confirm execution
        if (! $this->confirm('Do you want to proceed with this conversion?')) {
            $this->info('Conversion cancelled.');

            return 0;
        }

        // Execute conversion
        DB::transaction(function () use ($parent, $child, $prefix, $units) {
            // 1. Update child building to be a wing
            $child->update([
                'parent_building_id' => $parent->id,
                'is_wing' => true,
                'unit_prefix' => $prefix,
            ]);

            // 2. Rename all units with the prefix
            foreach ($units as $unit) {
                $numericPart = preg_replace('/^[A-Z]+/', '', $unit->unit_number);
                $unit->update([
                    'unit_number' => $prefix.$numericPart,
                ]);
            }
        });

        $this->info('');
        $this->info("Successfully converted '{$child->name}' to a wing under '{$parent->name}'.");
        $this->info("Renamed {$units->count()} units with prefix '{$prefix}'.");

        return 0;
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
