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
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $propertyId = $this->option('property');
        $force = $this->option('force');

        $this->printHeader($dryRun);

        $properties = $this->fetchProperties($propertyId);

        if ($properties->isEmpty()) {
            $this->info('No properties found that need organization.');
            $this->info('(Properties must have 2+ standalone buildings to be organized)');

            return 0;
        }

        [$propertyChanges, $totalUnitsToRename] = $this->buildChangeManifest($properties);

        $this->displayChanges($propertyChanges);
        $this->displaySkippedProperties();

        $this->info("Total: {$properties->count()} properties, {$totalUnitsToRename} units to rename");
        $this->line('');

        if ($dryRun) {
            $this->warn('DRY RUN - No changes made. Remove --dry-run to execute.');

            return 0;
        }

        if (! $force && ! $this->confirm('Do you want to proceed with these changes?')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        $this->applyChanges($propertyChanges);

        $this->info("Successfully organized {$properties->count()} properties.");

        return 0;
    }

    /**
     * Generate a unique prefix from a building name.
     */
    protected function generateUniquePrefix(string $name, array $usedPrefixes): string
    {
        $prefix = $this->extractPrefixFromWords($name);

        if (empty($prefix)) {
            $cleanName = preg_replace('/[^a-zA-Z]/', '', $name);
            $prefix = strtoupper(substr($cleanName, 0, 1)) ?: 'A';
        }

        return $this->makeUnique($prefix, $usedPrefixes);
    }

    private function printHeader(bool $dryRun): void
    {
        $this->info('');
        $this->info($dryRun ? 'Building Auto-Organization Preview' : 'Building Auto-Organization');
        $this->info('==================================');
        $this->info('');
    }

    private function fetchProperties(?string $propertyId): \Illuminate\Database\Eloquent\Collection
    {
        $query = Property::whereHas('buildings', function ($q) {
            $q->whereNull('parent_building_id');
        }, '>', 1)->with(['buildings' => function ($q) {
            $q->whereNull('parent_building_id')->with('units');
        }]);

        if ($propertyId) {
            $query->where('id', $propertyId);
        }

        return $query->get();
    }

    private function buildChangeManifest(\Illuminate\Database\Eloquent\Collection $properties): array
    {
        $totalUnitsToRename = 0;
        $propertyChanges = [];

        foreach ($properties as $property) {
            [$changes, $unitCount] = $this->buildPropertyChanges($property);
            $totalUnitsToRename += $unitCount;
            $propertyChanges[] = $changes;
        }

        return [$propertyChanges, $totalUnitsToRename];
    }

    private function buildPropertyChanges(Property $property): array
    {
        $buildings = $property->buildings->sortBy('id');
        $mainBuilding = $buildings->first();

        $buildingPrefixes = $this->assignPrefixes($buildings);

        $changes = [
            'property' => $property,
            'main_building' => $mainBuilding,
            'rename_main' => $mainBuilding->name !== $property->name,
            'buildings' => [],
        ];

        $totalUnits = 0;

        foreach ($buildings as $building) {
            $unitCount = $building->units->count();
            $totalUnits += $unitCount;
            $prefix = $buildingPrefixes[$building->id];

            $changes['buildings'][] = $this->buildingChangeEntry($building, $prefix, $unitCount, $mainBuilding->id);
        }

        return [$changes, $totalUnits];
    }

    private function assignPrefixes(\Illuminate\Support\Collection $buildings): array
    {
        $usedPrefixes = [];
        $buildingPrefixes = [];

        foreach ($buildings as $building) {
            $prefix = $this->generateUniquePrefix($building->name, $usedPrefixes);
            $usedPrefixes[] = $prefix;
            $buildingPrefixes[$building->id] = $prefix;
        }

        return $buildingPrefixes;
    }

    private function buildingChangeEntry(Building $building, string $prefix, int $unitCount, int $mainBuildingId): array
    {
        $sampleUnits = $building->units->take(3)->pluck('unit_number')->toArray();
        $sampleRenames = array_map(function ($num) use ($prefix) {
            $numeric = preg_replace('/^[A-Z]+/', '', $num);

            return $num.'->'.$prefix.$numeric;
        }, $sampleUnits);

        return [
            'building' => $building,
            'prefix' => $prefix,
            'unit_count' => $unitCount,
            'sample_renames' => implode(', ', $sampleRenames),
            'is_main' => $building->id === $mainBuildingId,
        ];
    }

    private function displayChanges(array $propertyChanges): void
    {
        foreach ($propertyChanges as $changes) {
            $this->displayPropertyChange($changes);
        }
    }

    private function displayPropertyChange(array $changes): void
    {
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

    private function displaySkippedProperties(): void
    {
        $skippedProperties = Property::whereHas('buildings', function ($q) {
            $q->whereNull('parent_building_id');
        }, '=', 1)->get();

        if ($skippedProperties->isEmpty()) {
            return;
        }

        $this->line('Properties to skip (single building or already organized):');
        foreach ($skippedProperties as $prop) {
            $buildingCount = $prop->buildings()->whereNull('parent_building_id')->count();
            $this->line("  - {$prop->name} ({$buildingCount} building)");
        }
        $this->line('');
    }

    private function applyChanges(array $propertyChanges): void
    {
        $this->info('Applying changes...');
        $bar = $this->output->createProgressBar(count($propertyChanges));
        $bar->start();

        foreach ($propertyChanges as $changes) {
            DB::transaction(function () use ($changes) {
                $this->applyPropertyChange($changes);
            });
            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->line('');
    }

    private function applyPropertyChange(array $changes): void
    {
        $mainBuilding = $changes['main_building'];

        if ($changes['rename_main']) {
            $mainBuilding->update(['name' => $changes['property']->name]);
        }

        foreach ($changes['buildings'] as $buildingChange) {
            $this->applyBuildingChange($buildingChange, $mainBuilding);
        }
    }

    private function applyBuildingChange(array $buildingChange, Building $mainBuilding): void
    {
        $building = $buildingChange['building'];
        $prefix = $buildingChange['prefix'];

        if ($buildingChange['is_main']) {
            $this->applyMainBuildingUnits($building, $prefix);
        } else {
            $this->convertToWing($building, $prefix, $mainBuilding);
        }
    }

    private function applyMainBuildingUnits(Building $building, string $prefix): void
    {
        if ($building->units->count() === 0) {
            return;
        }

        $building->update(['unit_prefix' => $prefix]);

        foreach ($building->units as $unit) {
            $numericPart = preg_replace('/^[A-Z]+/', '', $unit->unit_number);
            $unit->update(['unit_number' => $prefix.$numericPart]);
        }
    }

    private function convertToWing(Building $building, string $prefix, Building $mainBuilding): void
    {
        $building->update([
            'parent_building_id' => $mainBuilding->id,
            'is_wing' => true,
            'unit_prefix' => $prefix,
        ]);

        foreach ($building->units as $unit) {
            $numericPart = preg_replace('/^[A-Z]+/', '', $unit->unit_number);
            $unit->update(['unit_number' => $prefix.$numericPart]);
        }
    }

    private function extractPrefixFromWords(string $name): string
    {
        $words = preg_split('/\s+/', $name);
        $prefix = '';

        foreach ($words as $word) {
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

        return $prefix;
    }

    private function makeUnique(string $prefix, array $usedPrefixes): string
    {
        $original = $prefix;
        $counter = 1;

        while (in_array($prefix, $usedPrefixes)) {
            $prefix = $this->nextCandidatePrefix($original, $counter);
            $counter++;
        }

        return $prefix;
    }

    private function nextCandidatePrefix(string $original, int $counter): string
    {
        if (strlen($original) !== 1) {
            return $original.$counter;
        }

        $nextChar = chr(ord(substr($original, 0, 1)) + $counter);

        return ctype_alpha($nextChar) ? strtoupper($nextChar) : $original.$counter;
    }
}
