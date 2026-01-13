<?php

namespace App\Console\Commands;

use App\Models\Building;
use App\Models\Property;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoOrganizeBuildings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'buildings:auto-organize
                            {--dry-run : Preview changes without executing}
                            {--property= : Only process a specific property ID}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-organize properties with multiple standalone buildings into wing structure';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $propertyId = $this->option('property');
        $force = $this->option('force');

        $this->info('');
        $this->info($dryRun ? 'Building Auto-Organization Preview' : 'Building Auto-Organization');
        $this->info('==================================');
        $this->info('');

        // Find properties with multiple standalone buildings
        $query = Property::whereHas('buildings', function ($q) {
            $q->whereNull('parent_building_id');
        }, '>', 1)->with(['buildings' => function ($q) {
            $q->whereNull('parent_building_id')->with('units');
        }]);

        if ($propertyId) {
            $query->where('id', $propertyId);
        }

        $properties = $query->get();

        if ($properties->isEmpty()) {
            $this->info('No properties found that need organization.');
            $this->info('(Properties must have 2+ standalone buildings to be organized)');

            return 0;
        }

        // Show what will be changed
        $totalUnitsToRename = 0;
        $propertyChanges = [];

        foreach ($properties as $property) {
            $buildings = $property->buildings->sortBy('id');
            $mainBuilding = $buildings->first();

            // Generate prefixes for all buildings
            $usedPrefixes = [];
            $buildingPrefixes = [];

            foreach ($buildings as $building) {
                $prefix = $this->generateUniquePrefix($building->name, $usedPrefixes);
                $usedPrefixes[] = $prefix;
                $buildingPrefixes[$building->id] = $prefix;
            }

            $changes = [
                'property' => $property,
                'main_building' => $mainBuilding,
                'rename_main' => $mainBuilding->name !== $property->name,
                'buildings' => [],
            ];

            foreach ($buildings as $building) {
                $unitCount = $building->units->count();
                $totalUnitsToRename += $unitCount;

                $sampleUnits = $building->units->take(3)->pluck('unit_number')->toArray();
                $prefix = $buildingPrefixes[$building->id];
                $sampleRenames = array_map(function ($num) use ($prefix) {
                    $numeric = preg_replace('/^[A-Z]+/', '', $num);

                    return $num.'->'.$prefix.$numeric;
                }, $sampleUnits);

                $changes['buildings'][] = [
                    'building' => $building,
                    'prefix' => $prefix,
                    'unit_count' => $unitCount,
                    'sample_renames' => implode(', ', $sampleRenames),
                    'is_main' => $building->id === $mainBuilding->id,
                ];
            }

            $propertyChanges[] = $changes;
        }

        // Display changes
        foreach ($propertyChanges as $changes) {
            $this->line("Property: {$changes['property']->name} (ID: {$changes['property']->id})");
            $this->line("  Current: {$changes['property']->buildings->count()} standalone buildings");
            $this->line('');

            if ($changes['rename_main']) {
                $this->line("  Main Building: \"{$changes['main_building']->name}\" → \"{$changes['property']->name}\" (renamed)");
            } else {
                $this->line("  Main Building: \"{$changes['main_building']->name}\"");
            }
            $this->line('');

            $this->line('  Wings to create:');
            $tableData = [];
            foreach ($changes['buildings'] as $buildingChange) {
                $tableData[] = [
                    $buildingChange['building']->name.($buildingChange['is_main'] ? ' (main)' : ''),
                    $buildingChange['prefix'],
                    $buildingChange['unit_count'],
                    $buildingChange['sample_renames'] ?: '-',
                ];
            }
            $this->table(['Building', 'Prefix', 'Units', 'Unit Rename'], $tableData);
            $this->line('');
        }

        // Show properties being skipped
        $skippedProperties = Property::whereHas('buildings', function ($q) {
            $q->whereNull('parent_building_id');
        }, '=', 1)->get();

        if ($skippedProperties->isNotEmpty()) {
            $this->line('Properties to skip (single building or already organized):');
            foreach ($skippedProperties as $prop) {
                $buildingCount = $prop->buildings()->whereNull('parent_building_id')->count();
                $this->line("  - {$prop->name} ({$buildingCount} building)");
            }
            $this->line('');
        }

        // Summary
        $this->info("Total: {$properties->count()} properties, {$totalUnitsToRename} units to rename");
        $this->line('');

        if ($dryRun) {
            $this->warn('DRY RUN - No changes made. Remove --dry-run to execute.');

            return 0;
        }

        // Confirm before executing
        if (! $force && ! $this->confirm('Do you want to proceed with these changes?')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        // Execute the changes
        $this->info('Applying changes...');
        $bar = $this->output->createProgressBar(count($propertyChanges));
        $bar->start();

        foreach ($propertyChanges as $changes) {
            DB::transaction(function () use ($changes) {
                $mainBuilding = $changes['main_building'];

                // Rename main building to property name if needed
                if ($changes['rename_main']) {
                    $mainBuilding->update(['name' => $changes['property']->name]);
                }

                foreach ($changes['buildings'] as $buildingChange) {
                    $building = $buildingChange['building'];
                    $prefix = $buildingChange['prefix'];

                    if ($buildingChange['is_main']) {
                        // Main building keeps its units but gets a prefix
                        if ($building->units->count() > 0) {
                            $building->update(['unit_prefix' => $prefix]);

                            foreach ($building->units as $unit) {
                                $numericPart = preg_replace('/^[A-Z]+/', '', $unit->unit_number);
                                $unit->update(['unit_number' => $prefix.$numericPart]);
                            }
                        }
                    } else {
                        // Convert to wing
                        $building->update([
                            'parent_building_id' => $mainBuilding->id,
                            'is_wing' => true,
                            'unit_prefix' => $prefix,
                        ]);

                        // Rename all units
                        foreach ($building->units as $unit) {
                            $numericPart = preg_replace('/^[A-Z]+/', '', $unit->unit_number);
                            $unit->update(['unit_number' => $prefix.$numericPart]);
                        }
                    }
                }
            });

            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->line('');
        $this->info("Successfully organized {$properties->count()} properties.");

        return 0;
    }

    /**
     * Generate a unique prefix from a building name.
     */
    protected function generateUniquePrefix(string $name, array $usedPrefixes): string
    {
        // Try first letter of significant words
        $words = preg_split('/\s+/', $name);
        $prefix = '';

        foreach ($words as $word) {
            // Skip common words
            if (in_array(strtolower($word), ['the', 'a', 'an', 'of', 'and'])) {
                continue;
            }

            $letter = strtoupper(substr($word, 0, 1));
            if (ctype_alpha($letter)) {
                $prefix .= $letter;
                if (strlen($prefix) >= 2) {
                    break;
                }
            }
        }

        // Fallback to first letter only
        if (empty($prefix)) {
            $cleanName = preg_replace('/[^a-zA-Z]/', '', $name);
            $prefix = strtoupper(substr($cleanName, 0, 1)) ?: 'A';
        }

        // If only one letter, keep it
        if (strlen($prefix) === 1) {
            // That's fine
        }

        // Ensure uniqueness
        $original = $prefix;
        $counter = 1;
        while (in_array($prefix, $usedPrefixes)) {
            if (strlen($original) === 1) {
                // Try next letter in alphabet
                $nextChar = chr(ord(substr($original, 0, 1)) + $counter);
                if (ctype_alpha($nextChar)) {
                    $prefix = strtoupper($nextChar);
                } else {
                    $prefix = $original.$counter;
                }
            } else {
                $prefix = $original.$counter;
            }
            $counter++;
        }

        return $prefix;
    }
}
