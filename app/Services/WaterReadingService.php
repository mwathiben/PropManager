<?php

namespace App\Services;

use App\Models\Property;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterReading;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
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

        return $buildings->map(function ($building) {
            return [
                'id' => $building->id,
                'name' => $building->name,
                'units' => $building->units->map(function ($unit) {
                    $lastReading = WaterReading::where('unit_id', $unit->id)
                        ->orderBy('reading_date', 'desc')
                        ->first();

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

            $previousReading = WaterReading::where('unit_id', $unit->id)
                ->orderBy('reading_date', 'desc')
                ->first();

            if ($previousReading && $readingData['current_reading'] < $previousReading->current_reading) {
                return [
                    'success' => false,
                    'error' => [
                        'unit' => $unit->unit_number,
                        'message' => "Current reading ({$readingData['current_reading']}) cannot be less than previous reading ({$previousReading->current_reading})",
                    ],
                ];
            }

            $duplicate = WaterReading::where('unit_id', $unit->id)
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
                'previous_reading' => $previousReading ? $previousReading->current_reading : 0,
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

        $ocrEnabled = Setting::get('ocr_enabled', 'false', $landlordId) === 'true';

        if (! $ocrEnabled || ! $photoPath || ! $photo) {
            return ['reading' => $ocrReading, 'verified' => $ocrVerified];
        }

        try {
            $ocrService = new \App\Services\OcrService;
            $ocrResult = $ocrService->extractMeterReading($photo, $landlordId);

            if ($ocrResult && $ocrResult['success'] && $ocrResult['reading']) {
                $ocrReading = $ocrResult['reading'];

                $tolerance = 0.5;
                $autoVerify = Setting::get('ocr_auto_verify', 'false', $landlordId) === 'true';

                if ($autoVerify && abs($ocrReading - $manualReading) <= $tolerance) {
                    $ocrVerified = true;
                }
            }
        } catch (\Exception $e) {
            Log::warning('OCR processing failed', ['error' => $e->getMessage()]);
        }

        return ['reading' => $ocrReading, 'verified' => $ocrVerified];
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
