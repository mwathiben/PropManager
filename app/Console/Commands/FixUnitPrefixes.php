<?php

namespace App\Console\Commands;

use App\Models\Building;
use App\Models\Property;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixUnitPrefixes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'buildings:fix-prefixes
                            {--dry-run : Preview changes without executing}
                            {--property= : Only process a specific property ID}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix unit prefixes to use sequential single letters (A, B, C) instead of building name initials';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $propertyId = $this->option('property');
        $force = $this->option('force');

        $this->info('');
        $this->info($dryRun ? 'Unit Prefix Fix Preview' : 'Unit Prefix Fix');
        $this->info('========================');
        $this->info('');

        // Find properties with wings (already organized)
        $query = Property::whereHas('buildings', function ($q) {
            $q->where('is_wing', true);
        })->with(['buildings' => function ($q) {
            $q->with('units')->orderBy('id');
        }]);

        if ($propertyId) {
            $query->where('id', $propertyId);
        }

        $properties = $query->get();

        if ($properties->isEmpty()) {
            $this->info('No properties found with wings to fix.');

            return 0;
        }

        // Show what will be changed
        $propertyChanges = [];

        foreach ($properties as $property) {
            // Get main building and wings
            $mainBuilding = $property->buildings->where('is_wing', false)->first();
            $wings = $property->buildings->where('is_wing', true)->sortBy('id');

            if (! $mainBuilding) {
                continue;
            }

            $changes = [
                'property' => $property,
                'main_building' => $mainBuilding,
                'buildings' => [],
            ];

            // Main building gets prefix "A" if it has units
            $prefix = 'A';

            // Handle main building's units
            if ($mainBuilding->units->count() > 0) {
                $oldPrefix = $mainBuilding->unit_prefix ?? '';
                $sampleUnits = $mainBuilding->units->take(3);
                $sampleRenames = [];

                foreach ($sampleUnits as $unit) {
                    $numericPart = preg_replace('/^[A-Z]+/', '', $unit->unit_number);
                    $sampleRenames[] = $unit->unit_number.' -> '.$prefix.$numericPart;
                }

                $changes['buildings'][] = [
                    'building' => $mainBuilding,
                    'old_prefix' => $oldPrefix,
                    'new_prefix' => $prefix,
                    'old_name' => $mainBuilding->name,
                    'new_name' => 'Block '.$prefix,
                    'unit_count' => $mainBuilding->units->count(),
                    'sample_renames' => implode(', ', $sampleRenames),
                    'is_main' => true,
                ];

                $prefix++;
            }

            // Handle wings
            foreach ($wings as $wing) {
                $oldPrefix = $wing->unit_prefix ?? '';
                $sampleUnits = $wing->units->take(3);
                $sampleRenames = [];

                foreach ($sampleUnits as $unit) {
                    $numericPart = preg_replace('/^[A-Z]+/', '', $unit->unit_number);
                    $sampleRenames[] = $unit->unit_number.' -> '.$prefix.$numericPart;
                }

                // Determine new name - if it's like "Block B" keep the letter, otherwise use new prefix
                $newName = $wing->name;
                if (preg_match('/^Block\s+[A-Z]$/i', $wing->name)) {
                    $newName = 'Block '.$prefix;
                }

                $changes['buildings'][] = [
                    'building' => $wing,
                    'old_prefix' => $oldPrefix,
                    'new_prefix' => $prefix,
                    'old_name' => $wing->name,
                    'new_name' => $newName,
                    'unit_count' => $wing->units->count(),
                    'sample_renames' => implode(', ', $sampleRenames),
                    'is_main' => false,
                ];

                $prefix++;
            }

            $propertyChanges[] = $changes;
        }

        // Display changes
        foreach ($propertyChanges as $changes) {
            $this->line("Property: {$changes['property']->name} (ID: {$changes['property']->id})");
            $this->line('');

            $tableData = [];
            foreach ($changes['buildings'] as $buildingChange) {
                $tableData[] = [
                    $buildingChange['old_name'].($buildingChange['is_main'] ? ' (main)' : ' (wing)'),
                    $buildingChange['new_name'],
                    $buildingChange['old_prefix'].' -> '.$buildingChange['new_prefix'],
                    $buildingChange['unit_count'],
                    $buildingChange['sample_renames'] ?: '-',
                ];
            }
            $this->table(['Current Name', 'New Name', 'Prefix Change', 'Units', 'Sample Renames'], $tableData);
            $this->line('');
        }

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
                foreach ($changes['buildings'] as $buildingChange) {
                    $building = $buildingChange['building'];
                    $newPrefix = $buildingChange['new_prefix'];
                    $newName = $buildingChange['new_name'];

                    // Update building name and prefix
                    $building->update([
                        'name' => $newName,
                        'unit_prefix' => $newPrefix,
                    ]);

                    // Rename all units
                    foreach ($building->units as $unit) {
                        $numericPart = preg_replace('/^[A-Z]+/', '', $unit->unit_number);
                        $unit->update(['unit_number' => $newPrefix.$numericPart]);
                    }
                }
            });

            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->line('');
        $this->info('Successfully fixed unit prefixes for '.count($propertyChanges).' properties.');

        return 0;
    }
}
