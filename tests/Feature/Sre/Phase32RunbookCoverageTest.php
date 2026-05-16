<?php

declare(strict_types=1);

namespace Tests\Feature\Sre;

use App\Services\Sre\AlertRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase32RunbookCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_registry_loads_with_required_fields(): void
    {
        $alerts = app(AlertRegistry::class)->all();
        $this->assertNotEmpty($alerts);

        $required = ['key', 'severity', 'threshold', 'window', 'gauge', 'runbook', 'paging', 'description'];
        foreach ($alerts as $alert) {
            foreach ($required as $field) {
                $this->assertArrayHasKey($field, $alert, "Alert missing field: {$field}");
            }
            $this->assertContains($alert['severity'], ['sev1', 'sev2', 'sev3', 'sev4']);
            $this->assertContains($alert['paging'], ['email', 'page', 'both']);
        }
    }

    public function test_alert_registry_keys_are_unique(): void
    {
        $alerts = app(AlertRegistry::class)->all();
        $keys = array_column($alerts, 'key');
        $this->assertSame(count($keys), count(array_unique($keys)), 'Alert keys must be unique.');
    }

    public function test_find_returns_matching_entry_or_null(): void
    {
        $registry = app(AlertRegistry::class);
        $first = $registry->all()[0]['key'];

        $this->assertSame($first, $registry->find($first)['key']);
        $this->assertNull($registry->find('this-alert-does-not-exist'));
    }

    public function test_runbook_coverage_audit_passes_for_seeded_registry(): void
    {
        // The seeded registry must be fully consistent with the
        // shipped runbooks — this is the CI gate.
        $this->artisan('runbook:coverage-audit')
            ->expectsOutputToContain('alert runbook references resolve')
            ->assertSuccessful();
    }

    public function test_runbook_coverage_audit_flags_broken_links(): void
    {
        config(['alerts.alerts' => [
            ['key' => 'broken', 'severity' => 'sev3', 'threshold' => 1, 'window' => '1h',
                'gauge' => 'x', 'runbook' => 'docs/runbooks/does-not-exist.md', 'paging' => 'email', 'description' => 'x'],
        ]]);

        $this->artisan('runbook:coverage-audit')
            ->expectsOutputToContain('Broken runbook references:')
            ->assertSuccessful();
    }

    public function test_runbook_coverage_audit_anchor_mismatch_is_flagged(): void
    {
        config(['alerts.alerts' => [
            ['key' => 'bad-anchor', 'severity' => 'sev3', 'threshold' => 1, 'window' => '1h',
                'gauge' => 'x', 'runbook' => 'docs/runbooks/onboarding.md#absolutely-no-such-heading-here',
                'paging' => 'email', 'description' => 'x'],
        ]]);

        $this->artisan('runbook:coverage-audit')
            ->expectsOutputToContain('anchor #absolutely-no-such-heading-here not in')
            ->assertSuccessful();
    }

    public function test_runbook_coverage_audit_fail_on_broken_flag(): void
    {
        config(['alerts.alerts' => [
            ['key' => 'broken', 'severity' => 'sev3', 'threshold' => 1, 'window' => '1h',
                'gauge' => 'x', 'runbook' => 'docs/runbooks/missing.md', 'paging' => 'email', 'description' => 'x'],
        ]]);

        $this->artisan('runbook:coverage-audit --fail-on-broken')->assertFailed();
    }

    public function test_runbook_staleness_audit_runs_and_emits_summary(): void
    {
        $this->artisan('runbook:staleness-audit')
            ->expectsOutputToContain('Audited ')
            ->assertSuccessful();
    }
}
