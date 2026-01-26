<?php

namespace Database\Factories;

use App\Models\SecurityIncident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SecurityIncidentFactory extends Factory
{
    protected $model = SecurityIncident::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement([
                SecurityIncident::TYPE_DATA_BREACH,
                SecurityIncident::TYPE_UNAUTHORIZED_ACCESS,
                SecurityIncident::TYPE_MALWARE,
                SecurityIncident::TYPE_PHISHING,
            ]),
            'severity' => fake()->randomElement([
                SecurityIncident::SEVERITY_LOW,
                SecurityIncident::SEVERITY_MEDIUM,
                SecurityIncident::SEVERITY_HIGH,
            ]),
            'description' => fake()->paragraph(),
            'affected_data_types' => fake()->randomElements(['email', 'phone', 'national_id', 'bank_details', 'address'], 2),
            'estimated_affected_users' => fake()->numberBetween(1, 100),
            'mitigation_measures' => fake()->sentence(),
            'reported_by' => User::factory()->state(['role' => 'super_admin']),
            'reported_at' => now(),
            'notification_deadline' => now()->addHours(72),
            'odpc_notified_at' => null,
            'users_notified_at' => null,
            'resolved_at' => null,
            'status' => SecurityIncident::STATUS_REPORTED,
            'resolution_notes' => null,
            'compliance_references' => ['GDPR Art. 33', 'DPA 2019'],
        ];
    }

    public function dataBreach(): static
    {
        return $this->state([
            'type' => SecurityIncident::TYPE_DATA_BREACH,
            'severity' => SecurityIncident::SEVERITY_CRITICAL,
            'affected_data_types' => ['email', 'phone', 'national_id', 'bank_details'],
        ]);
    }

    public function unauthorizedAccess(): static
    {
        return $this->state([
            'type' => SecurityIncident::TYPE_UNAUTHORIZED_ACCESS,
            'severity' => SecurityIncident::SEVERITY_HIGH,
        ]);
    }

    public function malware(): static
    {
        return $this->state([
            'type' => SecurityIncident::TYPE_MALWARE,
            'severity' => SecurityIncident::SEVERITY_HIGH,
        ]);
    }

    public function phishing(): static
    {
        return $this->state([
            'type' => SecurityIncident::TYPE_PHISHING,
            'severity' => SecurityIncident::SEVERITY_MEDIUM,
        ]);
    }

    public function reported(): static
    {
        return $this->state([
            'status' => SecurityIncident::STATUS_REPORTED,
            'resolved_at' => null,
        ]);
    }

    public function investigating(): static
    {
        return $this->state([
            'status' => SecurityIncident::STATUS_INVESTIGATING,
            'odpc_notified_at' => now()->subHours(24),
        ]);
    }

    public function contained(): static
    {
        return $this->state([
            'status' => SecurityIncident::STATUS_CONTAINED,
            'odpc_notified_at' => now()->subDays(2),
            'users_notified_at' => now()->subDay(),
        ]);
    }

    public function resolved(): static
    {
        return $this->state([
            'status' => SecurityIncident::STATUS_RESOLVED,
            'odpc_notified_at' => now()->subDays(5),
            'users_notified_at' => now()->subDays(4),
            'resolved_at' => now(),
            'resolution_notes' => 'Incident fully resolved. '.fake()->paragraph(),
        ]);
    }

    public function closed(): static
    {
        return $this->state([
            'status' => SecurityIncident::STATUS_CLOSED,
            'odpc_notified_at' => now()->subWeeks(2),
            'users_notified_at' => now()->subWeeks(2),
            'resolved_at' => now()->subWeek(),
            'resolution_notes' => 'Incident closed after review. '.fake()->paragraph(),
        ]);
    }

    public function lowSeverity(): static
    {
        return $this->state(['severity' => SecurityIncident::SEVERITY_LOW]);
    }

    public function mediumSeverity(): static
    {
        return $this->state(['severity' => SecurityIncident::SEVERITY_MEDIUM]);
    }

    public function highSeverity(): static
    {
        return $this->state(['severity' => SecurityIncident::SEVERITY_HIGH]);
    }

    public function criticalSeverity(): static
    {
        return $this->state(['severity' => SecurityIncident::SEVERITY_CRITICAL]);
    }

    public function odpcNotified(): static
    {
        return $this->state([
            'odpc_notified_at' => now()->subHours(fake()->numberBetween(1, 48)),
        ]);
    }

    public function usersNotified(): static
    {
        return $this->state([
            'users_notified_at' => now()->subHours(fake()->numberBetween(1, 48)),
        ]);
    }

    public function overdue(): static
    {
        return $this->state([
            'reported_at' => now()->subDays(5),
            'notification_deadline' => now()->subDays(2),
            'odpc_notified_at' => null,
        ]);
    }

    public function reportedBy(User $user): static
    {
        return $this->state(['reported_by' => $user->id]);
    }

    public function affectingUsers(int $count): static
    {
        return $this->state(['estimated_affected_users' => $count]);
    }
}
