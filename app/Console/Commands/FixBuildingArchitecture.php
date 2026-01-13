<?php

namespace App\Console\Commands;

use App\Models\Building;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixBuildingArchitecture extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'buildings:fix-architecture
                            {--dry-run : Preview changes without executing}
                            {--property= : Only process a specific property ID}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix building architecture: move units from main building to a new wing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $propertyId = $this->option('property');
        $force = $this->option('force');

        $this->info('');
        $this->info($dryRun ? 'Building Architecture Fix Preview' : 'Building Architecture Fix');
        $this->info('===================================');
        $this->info('');

        // Find main buildings that have units directly (they should be containers only)
        $query = Building::where('is_wing', false)
            ->whereNull('parent_building_id')
            ->whereHas('units')
            ->whereHas('wings')
            ->with(['property', 'units', 'wings']);

        if ($propertyId) {
            $query->where('property_id', $propertyId);
        }

        $mainBuildings = $query->get();

        if ($mainBuildings->isEmpty()) {
            $this->info('No buildings found that need architecture fixes.');
            $this->info('(Main buildings should have no direct units when they have wings)');

            return 0;
        }

        // Show what will be changed
        foreach ($mainBuildings as $mainBuilding) {
            $property = $mainBuilding->property;

            $this->line("Property: {$property->name} (ID: {$property->id})");
            $this->line('');
            $this->line('  Current State (WRONG):');
            $this->line("  ├── Building ID={$mainBuilding->id}: \"{$mainBuilding->name}\" [MAIN with units]");
            $this->line("  │   └── Units: {$mainBuilding->units->count()} (e.g., ".$mainBuilding->units->take(3)->pluck('unit_number')->implode(', ').')');

            foreach ($mainBuilding->wings as $wing) {
                $this->line("  └── Building ID={$wing->id}: \"{$wing->name}\" [WING]");
                $this->line("      └── Units: {$wing->units->count()} (e.g., ".$wing->units->take(3)->pluck('unit_number')->implode(', ').')');
            }

            $this->line('');
            $this->line('  Target State (CORRECT):');
            $this->line("  ├── Building: \"{$property->name}\" [MAIN CONTAINER, no units]");
            $this->line('  │   ├── Wing: "Block A" [NEW] - Units moved here');

            foreach ($mainBuilding->wings as $wing) {
                $this->line("  │   └── Wing: \"{$wing->name}\" [EXISTING]");
            }

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
        $bar = $this->output->createProgressBar(count($mainBuildings));
        $bar->start();

        foreach ($mainBuildings as $mainBuilding) {
            DB::transaction(function () use ($mainBuilding) {
                $property = $mainBuilding->property;

                // 1. Rename main building back to property name
                $mainBuilding->update([
                    'name' => $property->name,
                    'unit_prefix' => null,
                ]);

                // 2. Create new wing "Block A" for the main building's units
                $blockA = Building::create([
                    'property_id' => $property->id,
                    'landlord_id' => $mainBuilding->landlord_id,
                    'parent_building_id' => $mainBuilding->id,
                    'name' => 'Block A',
                    'unit_prefix' => 'A',
                    'is_wing' => true,
                    'total_floors' => $mainBuilding->total_floors,
                    'units_per_floor' => $mainBuilding->units_per_floor,
                ]);

                // 3. Move units from main building to Block A wing
                Unit::where('building_id', $mainBuilding->id)
                    ->update(['building_id' => $blockA->id]);

                // 4. Clear main building's floor/unit counts (it's now just a container)
                $mainBuilding->update([
                    'total_floors' => 0,
                    'units_per_floor' => 0,
                ]);
            });

            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->line('');
        $this->info('Successfully fixed architecture for '.count($mainBuildings).' buildings.');

        return 0;
    }
}
