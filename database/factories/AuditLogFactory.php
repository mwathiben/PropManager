<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'landlord']),
            'landlord_id' => fn (array $attrs) => $attrs['user_id'],
            'event_type' => fake()->randomElement([
                AuditLog::EVENT_CREATED,
                AuditLog::EVENT_UPDATED,
                AuditLog::EVENT_DELETED,
            ]),
            'auditable_type' => Invoice::class,
            'auditable_id' => fake()->numberBetween(1, 1000),
            'old_values' => null,
            'new_values' => ['status' => 'sent'],
            'changed_fields' => ['status'],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'url' => fake()->url(),
            'metadata' => [],
        ];
    }

    public function created(): static
    {
        return $this->state([
            'event_type' => AuditLog::EVENT_CREATED,
            'old_values' => null,
            'new_values' => [
                'id' => fake()->numberBetween(1, 1000),
                'status' => 'draft',
            ],
            'changed_fields' => [],
        ]);
    }

    public function updated(): static
    {
        return $this->state([
            'event_type' => AuditLog::EVENT_UPDATED,
            'old_values' => ['status' => 'draft'],
            'new_values' => ['status' => 'sent'],
            'changed_fields' => ['status'],
        ]);
    }

    public function deleted(): static
    {
        return $this->state([
            'event_type' => AuditLog::EVENT_DELETED,
            'old_values' => ['id' => fake()->numberBetween(1, 1000)],
            'new_values' => null,
            'changed_fields' => [],
        ]);
    }

    public function exported(): static
    {
        return $this->state([
            'event_type' => AuditLog::EVENT_EXPORTED,
            'old_values' => null,
            'new_values' => null,
            'changed_fields' => [],
            'metadata' => [
                'export_type' => 'csv',
                'records_count' => fake()->numberBetween(10, 500),
            ],
        ]);
    }

    public function imported(): static
    {
        return $this->state(function () {
            $recordsCount = fake()->numberBetween(10, 500);
            $successCount = fake()->numberBetween(0, $recordsCount);
            $errorCount = $recordsCount - $successCount;

            return [
                'event_type' => AuditLog::EVENT_IMPORTED,
                'old_values' => null,
                'new_values' => null,
                'changed_fields' => [],
                'metadata' => [
                    'import_type' => 'csv',
                    'records_count' => $recordsCount,
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                ],
            ];
        });
    }

    public function statusChanged(): static
    {
        return $this->state([
            'event_type' => AuditLog::EVENT_STATUS_CHANGED,
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'approved'],
            'changed_fields' => ['status'],
        ]);
    }

    public function bulkUpdate(): static
    {
        return $this->state([
            'event_type' => AuditLog::EVENT_BULK_UPDATE,
            'metadata' => [
                'affected_count' => fake()->numberBetween(5, 50),
                'operation' => 'status_update',
            ],
        ]);
    }

    public function forInvoice(): static
    {
        return $this->state([
            'auditable_type' => Invoice::class,
            'auditable_id' => fake()->numberBetween(1, 1000),
        ]);
    }

    public function forPayment(): static
    {
        return $this->state([
            'auditable_type' => Payment::class,
            'auditable_id' => fake()->numberBetween(1, 1000),
        ]);
    }

    public function forLease(): static
    {
        return $this->state([
            'auditable_type' => Lease::class,
            'auditable_id' => fake()->numberBetween(1, 1000),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }

    public function withMetadata(array $metadata): static
    {
        return $this->state(['metadata' => $metadata]);
    }

    public function recent(): static
    {
        return $this->state(fn () => [
            'created_at' => now()->subHours(fake()->numberBetween(1, 24)),
        ]);
    }

    public function historical(): static
    {
        return $this->state(fn () => [
            'created_at' => now()->subDays(fake()->numberBetween(30, 90)),
        ]);
    }
}
