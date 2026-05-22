<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterReading;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WaterReadingService
{
    public function getPropertyForUser(User $user): ?Property
    {
        if ($user->role === 'caretaker') {
            return Property::where('landlord_id', $user->landlord_id)->first();
        }

        return $user->properties()->first();
    }

    public function getBuildingsWithUnits(Property $property): Collection
    {
        $buildings = $property->buildings()->with(['units' => function ($query) {
            $query->orderBy('floor_number', 'asc')->orderBy('unit_number', 'asc');
        }])->get();

        $allUnitIds = $buildings->flatMap(fn ($b) => $b->units->pluck('id'));

        $latestReadingIds = WaterReading::select('unit_id', DB::raw('MAX(id) as latest_id'))
            ->whereIn('unit_id', $allUnitIds)
            ->groupBy('unit_id')
            ->pluck('latest_id');

        $latestReadings = WaterReading::findMany($latestReadingIds)->keyBy('unit_id');

        return $buildings->map(function ($building) use ($latestReadings) {
            return [
                'id' => $building->id,
                'name' => $building->name,
                'units' => $building->units->map(function ($unit) use ($latestReadings) {
                    $lastReading = $latestReadings->get($unit->id);

                    return [
                        'id' => $unit->id,
                        'unit_number' => $unit->unit_number,
                        'meter_number' => $unit->meter_number,
                        'previous_reading' => $lastReading ? $lastReading->current_reading : 0,
                        'last_reading_date' => $lastReading ? $lastReading->reading_date->format('Y-m-d') : 'N/A',
                        'status' => $unit->status,
                    ];
                }),
            ];
        });
    }

    public function storeReadings(array $readings, int $landlordId): array
    {
        $errors = [];
        $successCount = 0;

        foreach ($readings as $readingData) {
            $result = $this->processReading($readingData, $landlordId);

            if ($result['success']) {
                $successCount++;
            } else {
                $errors[] = $result['error'];
            }
        }

        return [
            'successCount' => $successCount,
            'errors' => $errors,
        ];
    }

    public function processReading(array $readingData, int $landlordId): array
    {
        try {
            $unit = Unit::findOrFail($readingData['unit_id']);

            // Phase-86 (review C1): the FormRequest validates exists:units only,
            // not ownership. Reject a unit belonging to another landlord before
            // resolveActiveForUnit would write a cross-tenant meter row.
            if ((int) $unit->landlord_id !== (int) $landlordId) {
                return [
                    'success' => false,
                    'error' => ['unit' => $readingData['unit_id'], 'message' => 'Unit not found.'],
                ];
            }

            // Phase-86 METER-MODEL: readings key off a physical meter. The
            // previous value is the meter's last reading or — for the meter's
            // first read — its (possibly non-zero) install baseline, not 0.
            $meter = Meter::resolveActiveForUnit($unit);

            $previousReading = WaterReading::where('meter_id', $meter->id)
                ->orderBy('reading_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $previousValue = $previousReading
                ? (float) $previousReading->current_reading
                : (float) $meter->initial_reading;

            if ($readingData['current_reading'] < $previousValue) {
                return [
                    'success' => false,
                    'error' => [
                        'unit' => $unit->unit_number,
                        'message' => "Current reading ({$readingData['current_reading']}) cannot be less than previous reading ({$previousValue})",
                    ],
                ];
            }

            $duplicate = WaterReading::where('meter_id', $meter->id)
                ->whereDate('reading_date', $readingData['reading_date'])
                ->exists();

            if ($duplicate) {
                return [
                    'success' => false,
                    'error' => [
                        'unit' => $unit->unit_number,
                        'message' => 'Reading already exists for this date',
                    ],
                ];
            }

            $photoPath = $this->handlePhotoUpload($readingData['photo'] ?? null, $unit->id, $landlordId);
            $ocrData = $this->processOcr($readingData['photo'] ?? null, $photoPath, $readingData['current_reading'], $landlordId);

            WaterReading::create([
                'unit_id' => $unit->id,
                'meter_id' => $meter->id,
                'previous_reading' => $previousValue,
                'current_reading' => $readingData['current_reading'],
                'reading_date' => $readingData['reading_date'],
                'photo_path' => $photoPath,
                'status' => 'pending',
                'ocr_reading' => $ocrData['reading'],
                'ocr_verified' => $ocrData['verified'],
            ]);

            return ['success' => true];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => [
                    'unit' => $readingData['unit_id'],
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    public function handlePhotoUpload(?UploadedFile $photo, int $unitId, int $landlordId): ?string
    {
        if (! $photo) {
            return null;
        }

        $fileName = time().'_unit_'.$unitId.'_'.Str::random(8).'.'.$photo->getClientOriginalExtension();

        return $photo->storeAs(
            'water_readings/'.$landlordId,
            $fileName,
            'local'
        );
    }

    public function processOcr(?UploadedFile $photo, ?string $photoPath, float $manualReading, int $landlordId): array
    {
        $ocrReading = null;
        $ocrVerified = false;
        $ocrStatus = 'skipped';
        $ocrError = null;

        $ocrEnabled = Setting::get('ocr_enabled', 'false', $landlordId) === 'true';

        if (! $ocrEnabled || ! $photoPath || ! $photo) {
            return [
                'reading' => $ocrReading,
                'verified' => $ocrVerified,
                'ocr_status' => $ocrStatus,
                'ocr_error' => $ocrError,
            ];
        }

        try {
            $ocrService = new \App\Services\OcrService;
            $ocrResult = $ocrService->extractMeterReading($photo, $landlordId);

            if ($ocrResult && $ocrResult['success'] && $ocrResult['reading']) {
                $ocrReading = $ocrResult['reading'];
                $ocrStatus = 'matched';

                $tolerance = 0.5;
                $autoVerify = Setting::get('ocr_auto_verify', 'false', $landlordId) === 'true';

                if ($autoVerify && abs($ocrReading - $manualReading) <= $tolerance) {
                    $ocrVerified = true;
                }
            } else {
                $ocrStatus = 'no_reading';
            }
        } catch (\Throwable $e) {
            // HANDLE-13: surface the error state to callers instead of just
            // logging. The controller persists ocr_status on the WaterReading
            // row so an operator can review failed extractions later.
            Log::warning('OCR processing failed', ['error' => $e->getMessage()]);
            $ocrStatus = 'errored';
            $ocrError = $e->getMessage();
        }

        return [
            'reading' => $ocrReading,
            'verified' => $ocrVerified,
            'ocr_status' => $ocrStatus,
            'ocr_error' => $ocrError,
        ];
    }

    public function getFilteredHistory(Collection $unitIds, array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = WaterReading::with('unit')
            ->whereIn('unit_id', $unitIds)
            ->orderBy('reading_date', 'desc');

        if (! empty($filters['building_id'])) {
            $buildingUnitIds = Unit::where('building_id', $filters['building_id'])->pluck('id');
            $query->whereIn('unit_id', $buildingUnitIds);
        }

        if (! empty($filters['unit_id'])) {
            $query->where('unit_id', $filters['unit_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('reading_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('reading_date', '<=', $filters['date_to']);
        }

        if (isset($filters['invoiced'])) {
            $query->where('is_invoiced', $filters['invoiced'] === 'true');
        }

        return $query->paginate(50);
    }

    public function getPendingReadings(Collection $unitIds, array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = WaterReading::with(['unit.building', 'recorder'])
            ->whereIn('unit_id', $unitIds)
            ->where('status', 'pending')
            ->orderBy('reading_date', 'desc');

        if (! empty($filters['building_id'])) {
            $buildingUnitIds = Unit::where('building_id', $filters['building_id'])->pluck('id');
            $query->whereIn('unit_id', $buildingUnitIds);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('reading_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('reading_date', '<=', $filters['date_to']);
        }

        return $query->paginate(50);
    }

    public function validateReadingUpdate(WaterReading $reading, float $newReading): ?string
    {
        if ($reading->is_invoiced) {
            return 'Cannot update reading that has been invoiced.';
        }

        if ($newReading < $reading->previous_reading) {
            return "Current reading cannot be less than previous reading ({$reading->previous_reading})";
        }

        return null;
    }

    public function canDeleteReading(WaterReading $reading): bool
    {
        return ! $reading->is_invoiced;
    }

    public function getLandlordId(User $user): int
    {
        return $user->role === 'landlord' ? $user->id : $user->landlord_id;
    }
}
