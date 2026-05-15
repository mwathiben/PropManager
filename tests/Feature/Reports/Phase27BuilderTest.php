<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\SavedReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-27 BI-BUILDER-1/2 functional watchdog: saved-report CRUD
 * lifecycle, policy scoping, builder page renders the allowlist.
 *
 * SQL-injection regression lives in Phase27BuilderInjectionTest.
 */
class Phase27BuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = User::factory()->create(['role' => 'landlord']);
    }

    public function test_saved_reports_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('saved_reports'));
        foreach (['landlord_id', 'name', 'description', 'config', 'created_at', 'updated_at'] as $col) {
            $this->assertTrue(
                Schema::hasColumn('saved_reports', $col),
                "BI-BUILDER-1: saved_reports.{$col} must exist.",
            );
        }
    }

    public function test_builder_index_page_emits_allowlist(): void
    {
        $this->actingAs($this->landlord)
            ->get(route('reports.builder.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/Builder')
                ->has('allowedTables')
                ->has('allowedFields')
                ->has('operatorMatrix.numeric')
                ->has('operatorMatrix.date')
                ->has('operatorMatrix.string')
                ->has('operatorMatrix.boolean'),
            );
    }

    public function test_landlord_can_save_a_valid_report(): void
    {
        $this->actingAs($this->landlord)
            ->post(route('reports.builder.store'), [
                'name' => 'High-value payments',
                'description' => 'Payments over 50k',
                'config' => [
                    'table' => 'payments',
                    'fields' => ['payment.amount', 'payment.payment_date'],
                    'filters' => [['field' => 'payment.amount', 'op' => '>=', 'value' => 50000]],
                ],
            ])
            ->assertRedirect(route('reports.builder.index'));

        $this->assertDatabaseHas('saved_reports', [
            'landlord_id' => $this->landlord->id,
            'name' => 'High-value payments',
        ]);
    }

    public function test_saved_report_with_invalid_config_is_rejected(): void
    {
        $this->actingAs($this->landlord)
            ->post(route('reports.builder.store'), [
                'name' => 'malicious',
                'config' => [
                    'table' => 'users', // not in allowlist
                    'fields' => ['payment.amount'],
                ],
            ])
            ->assertSessionHasErrors('table');

        $this->assertDatabaseCount('saved_reports', 0);
    }

    public function test_run_returns_rows_for_valid_config(): void
    {
        $this->actingAs($this->landlord)
            ->postJson(route('reports.builder.run'), [
                'config' => [
                    'table' => 'payments',
                    'fields' => ['payment.amount'],
                    'limit' => 10,
                ],
            ])
            ->assertOk()
            ->assertJsonStructure(['rows']);
    }

    public function test_run_rejects_invalid_config(): void
    {
        $this->actingAs($this->landlord)
            ->postJson(route('reports.builder.run'), [
                'config' => [
                    'table' => 'payments',
                    'fields' => ['users.password'], // not in allowlist
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields']);
    }

    public function test_other_landlord_cannot_delete_my_report(): void
    {
        $report = SavedReport::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'mine',
            'config' => ['table' => 'payments', 'fields' => ['payment.amount']],
        ]);

        $intruder = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($intruder)
            ->delete(route('reports.builder.destroy', $report->id))
            ->assertForbidden();

        $this->assertDatabaseHas('saved_reports', ['id' => $report->id]);
    }

    public function test_landlord_can_delete_own_report(): void
    {
        $report = SavedReport::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'mine',
            'config' => ['table' => 'payments', 'fields' => ['payment.amount']],
        ]);

        $this->actingAs($this->landlord)
            ->delete(route('reports.builder.destroy', $report->id))
            ->assertRedirect(route('reports.builder.index'));

        $this->assertDatabaseMissing('saved_reports', ['id' => $report->id]);
    }

    public function test_tenant_cannot_access_builder(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($tenant)
            ->get(route('reports.builder.index'))
            ->assertForbidden();
    }

    public function test_saved_report_policy_is_registered(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Gate::getPolicyFor(SavedReport::class) !== null,
            'BI-BUILDER-1: SavedReportPolicy must be registered in AuthServiceProvider.',
        );
    }
}
