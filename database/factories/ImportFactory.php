<?php

namespace Database\Factories;

use App\Models\Import;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportFactory extends Factory
{
    protected $model = Import::class;

    public function definition(): array
    {
        $landlord = User::factory()->state(['role' => 'landlord'])->create();
        $totalRows = fake()->numberBetween(10, 500);
        $successfulRows = fake()->numberBetween(0, $totalRows);

        return [
            'landlord_id' => $landlord->id,
            'imported_by' => $landlord->id,
            'type' => fake()->randomElement(['tenants', 'units', 'payments', 'water_readings']),
            'file_name' => fake()->slug(2).'.csv',
            'file_path' => 'imports/'.fake()->uuid().'.csv',
            'status' => 'processing',
            'total_rows' => $totalRows,
            'successful_rows' => 0,
            'failed_rows' => 0,
            'errors' => [],
            'summary' => [],
            'started_at' => now(),
            'completed_at' => null,
        ];
    }

    public function processing(): static
    {
        return $this->state([
            'status' => 'processing',
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attrs) {
            $totalRows = $attrs['total_rows'] ?? 100;
            $failedRows = fake()->numberBetween(0, (int) ($totalRows * 0.1));
            $successfulRows = $totalRows - $failedRows;

            return [
                'status' => 'completed',
                'successful_rows' => $successfulRows,
                'failed_rows' => $failedRows,
                'completed_at' => now(),
                'summary' => [
                    'created' => $successfulRows,
                    'skipped' => $failedRows,
                    'duration_seconds' => fake()->numberBetween(5, 120),
                ],
            ];
        });
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'successful_rows' => 0,
            'failed_rows' => fn (array $attrs) => $attrs['total_rows'],
            'completed_at' => now(),
            'errors' => [
                ['row' => 1, 'message' => 'Invalid file format'],
                ['row' => null, 'message' => 'Processing aborted'],
            ],
        ]);
    }

    public function withErrors(): static
    {
        return $this->state(function (array $attrs) {
            $totalRows = $attrs['total_rows'] ?? 100;
            $failedRows = fake()->numberBetween((int) ($totalRows * 0.2), (int) ($totalRows * 0.5));

            $errors = [];
            for ($i = 0; $i < min($failedRows, 10); $i++) {
                $errors[] = [
                    'row' => fake()->numberBetween(1, $totalRows),
                    'message' => fake()->randomElement([
                        'Invalid email format',
                        'Phone number already exists',
                        'Required field missing',
                        'Invalid date format',
                        'Unit not found',
                    ]),
                ];
            }

            return [
                'status' => 'completed',
                'successful_rows' => $totalRows - $failedRows,
                'failed_rows' => $failedRows,
                'completed_at' => now(),
                'errors' => $errors,
            ];
        });
    }

    public function perfectImport(): static
    {
        return $this->state(function (array $attrs) {
            $totalRows = $attrs['total_rows'] ?? 100;

            return [
                'status' => 'completed',
                'successful_rows' => $totalRows,
                'failed_rows' => 0,
                'completed_at' => now(),
                'errors' => [],
                'summary' => [
                    'created' => $totalRows,
                    'skipped' => 0,
                    'duration_seconds' => fake()->numberBetween(5, 60),
                ],
            ];
        });
    }

    public function tenantsImport(): static
    {
        return $this->state([
            'type' => 'tenants',
            'file_name' => 'tenants-import-'.date('Ymd').'.csv',
        ]);
    }

    public function unitsImport(): static
    {
        return $this->state([
            'type' => 'units',
            'file_name' => 'units-import-'.date('Ymd').'.csv',
        ]);
    }

    public function paymentsImport(): static
    {
        return $this->state([
            'type' => 'payments',
            'file_name' => 'payments-import-'.date('Ymd').'.csv',
        ]);
    }

    public function waterReadingsImport(): static
    {
        return $this->state([
            'type' => 'water_readings',
            'file_name' => 'water-readings-import-'.date('Ymd').'.csv',
        ]);
    }

    public function withRowCount(int $total): static
    {
        return $this->state(['total_rows' => $total]);
    }

    public function importedBy(User $user): static
    {
        return $this->state([
            'imported_by' => $user->id,
            'landlord_id' => $user->landlord_id ?? $user->id,
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state([
            'landlord_id' => $landlord->id,
            'imported_by' => $landlord->id,
        ]);
    }
}
