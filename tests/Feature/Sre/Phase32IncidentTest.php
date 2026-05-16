<?php

declare(strict_types=1);

namespace Tests\Feature\Sre;

use App\Models\OperationalIncident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase32IncidentTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_and_advance_incident(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'super_admin';
        $admin->save();

        $this->actingAs($admin)
            ->post(route('ops.incidents.store'), [
                'title' => 'Daraja STK degraded',
                'severity' => OperationalIncident::SEV2,
                'summary' => 'Success rate dropped below 50%.',
                'affected_services' => ['payment_webhook_handlers'],
            ])
            ->assertRedirect();

        $incident = OperationalIncident::query()->latest('id')->first();
        $this->assertNotNull($incident);
        $this->assertSame(OperationalIncident::STATUS_OPEN, $incident->status);
        $this->assertSame(OperationalIncident::SEV2, $incident->severity);
        $this->assertSame($admin->id, $incident->opened_by_user_id);
    }

    public function test_status_machine_advances_through_mitigated_and_resolved(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'super_admin';
        $admin->save();

        $incident = OperationalIncident::create([
            'title' => 'Test', 'severity' => OperationalIncident::SEV3,
            'status' => OperationalIncident::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('ops.incidents.set-status', ['incident' => $incident->id]), [
                'status' => OperationalIncident::STATUS_MITIGATED,
            ])->assertRedirect();
        $incident->refresh();
        $this->assertSame(OperationalIncident::STATUS_MITIGATED, $incident->status);
        $this->assertNotNull($incident->mitigated_at);

        $this->actingAs($admin)
            ->post(route('ops.incidents.set-status', ['incident' => $incident->id]), [
                'status' => OperationalIncident::STATUS_RESOLVED,
                'root_cause' => 'Daraja outage.',
            ])->assertRedirect();
        $incident->refresh();
        $this->assertSame(OperationalIncident::STATUS_RESOLVED, $incident->status);
        $this->assertNotNull($incident->resolved_at);
        $this->assertSame('Daraja outage.', $incident->root_cause);
    }

    public function test_non_admin_cannot_open_incident(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)
            ->post(route('ops.incidents.store'), [
                'title' => 'X', 'severity' => OperationalIncident::SEV3,
            ])
            ->assertForbidden();
    }

    public function test_post_mortem_url_is_recorded(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'super_admin';
        $admin->save();

        $incident = OperationalIncident::create([
            'title' => 'X', 'severity' => OperationalIncident::SEV2,
            'status' => OperationalIncident::STATUS_RESOLVED,
            'opened_at' => now()->subHour(), 'resolved_at' => now(),
        ]);

        $this->actingAs($admin)
            ->postJson(route('ops.incidents.post-mortem', ['incident' => $incident->id]), [
                'post_mortem_url' => 'https://wiki.example.com/postmortems/daraja-2026-05-16',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $incident->refresh();
        $this->assertStringContainsString('postmortems/daraja-2026-05-16', $incident->post_mortem_url);
    }

    public function test_mttr_audit_emits_per_severity_buckets(): void
    {
        OperationalIncident::create([
            'title' => 'Incident A', 'severity' => OperationalIncident::SEV2,
            'status' => OperationalIncident::STATUS_RESOLVED,
            'opened_at' => now()->subMinutes(30),
            'resolved_at' => now(),
        ]);
        OperationalIncident::create([
            'title' => 'Incident B', 'severity' => OperationalIncident::SEV2,
            'status' => OperationalIncident::STATUS_RESOLVED,
            'opened_at' => now()->subMinutes(60),
            'resolved_at' => now(),
        ]);

        $exit = \Artisan::call('mttr:audit');
        $output = \Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('severity=sev2', $output);
        $this->assertStringContainsString('count=2', $output);
    }

    public function test_post_mortem_template_exists(): void
    {
        $this->assertFileExists(base_path('docs/runbooks/post-mortem-template.md'));
    }
}
