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
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $propertyId = $this->option('property');
        $force = $this->option('force');

        $this->info('');
        $this->info($dryRun ? 'Unit Prefix Fix Preview' : 'Unit Prefix Fix');
        $this->info('========================');
        $this->info('');

        $properties = $this->queryProperties($propertyId);

        if ($properties->isEmpty()) {
            $this->info('No properties found with wings to fix.');

            return 0;
        }

        $propertyChanges = $this->buildPropertyChanges($properties);

        $this->displayChanges($propertyChanges);

        if ($dryRun) {
            $this->warn('DRY RUN - No changes made. Remove --dry-run to execute.');

            return 0;
        }

        if (! $force && ! $this->confirm('Do you want to proceed with these changes?')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        $this->applyChanges($propertyChanges);

        return 0;
    }

    private function queryProperties(?string $propertyId): \Illuminate\Database\Eloquent\Collection
    {
        $query = Property::whereHas('buildings', function ($q) {
            $q->where('is_wing', true);
        })->with(['buildings' => function ($q) {
            $q->with('units')->orderBy('id');
        }]);

        if ($propertyId) {
            $query->where('id', $propertyId);
        }

        return $query->get();
    }

    private function buildPropertyChanges(\Illuminate\Database\Eloquent\Collection $properties): array
    {
        $propertyChanges = [];

        foreach ($properties as $property) {
            $mainBuilding = $property->buildings->where('is_wing', false)->first();

            if (! $mainBuilding) {
                continue;
            }

            $wings = $property->buildings->where('is_wing', true)->sortBy('id');
            $propertyChanges[] = $this->buildChangesForProperty($property, $mainBuilding, $wings);
        }

        return $propertyChanges;
    }

    private function buildChangesForProperty(Property $property, Building $mainBuilding, \Illuminate\Support\Collection $wings): array
    {
        $changes = [
            'property' => $property,
            'main_building' => $mainBuilding,
            'buildings' => [],
        ];

        $prefix = 'A';

        if ($mainBuilding->units->count() > 0) {
            $changes['buildings'][] = $this->buildingChangeEntry($mainBuilding, $prefix, 'Block '.$prefix, true);
            $prefix++;
        }

        foreach ($wings as $wing) {
            $newName = preg_match('/^Block\s+[A-Z]$/i', $wing->name) ? 'Block '.$prefix : $wing->name;
            $changes['buildings'][] = $this->buildingChangeEntry($wing, $prefix, $newName, false);
            $prefix++;
        }

        return $changes;
    }

    private function buildingChangeEntry(Building $building, string $newPrefix, string $newName, bool $isMain): array
    {
        $oldPrefix = $building->unit_prefix ?? '';
        $sampleRenames = $this->buildSampleRenames($building, $newPrefix);

        return [
            'building' => $building,
            'old_prefix' => $oldPrefix,
            'new_prefix' => $newPrefix,
            'old_name' => $building->name,
            'new_name' => $newName,
            'unit_count' => $building->units->count(),
            'sample_renames' => implode(', ', $sampleRenames),
            'is_main' => $isMain,
        ];
    }

    private function buildSampleRenames(Building $building, string $newPrefix): array
    {
        $sampleRenames = [];

        foreach ($building->units->take(3) as $unit) {
            $numericPart = preg_replace('/^[A-Z]+/', '', $unit->unit_number);
            $sampleRenames[] = $unit->unit_number.' -> '.$newPrefix.$numericPart;
        }

        return $sampleRenames;
    }

    private function displayChanges(array $propertyChanges): void
    {
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
    }

    private function applyChanges(array $propertyChanges): void
    {
        $this->info('Applying changes...');
        $bar = $this->output->createProgressBar(count($propertyChanges));
        $bar->start();

        foreach ($propertyChanges as $changes) {
            DB::transaction(function () use ($changes) {
                foreach ($changes['buildings'] as $buildingChange) {
                    $this->applyBuildingChange($buildingChange);
                }
            });

            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->line('');
        $this->info('Successfully fixed unit prefixes for '.count($propertyChanges).' properties.');
    }

    private function applyBuildingChange(array $buildingChange): void
    {
        $building = $buildingChange['building'];
        $newPrefix = $buildingChange['new_prefix'];

        $building->update([
            'name' => $buildingChange['new_name'],
            'unit_prefix' => $newPrefix,
        ]);

        foreach ($building->units as $unit) {
            $numericPart = preg_replace('/^[A-Z]+/', '', $unit->unit_number);
            $unit->update(['unit_number' => $newPrefix.$numericPart]);
        }
    }
}
