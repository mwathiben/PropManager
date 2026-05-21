<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\LandlordDashboard;
use App\Models\SavedReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-73 DASHBOARD-EDITOR: landlord dashboards CRUD + card-layout ownership
 * validation + preview isolation.
 */
class Phase73DashboardEditorTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $otherLandlord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = $this->createLandlordWithFullSetup()['landlord'];
        $this->otherLandlord = $this->createLandlordWithFullSetup()['landlord'];
    }

    private function reportFor(User $owner, string $name = 'Rent report'): SavedReport
    {
        $this->actingAs($owner);

        return SavedReport::create([
            'name' => $name,
            'config' => ['table' => 'payments', 'fields' => ['payment.amount'], 'filters' => [], 'group_by' => [], 'sort_by' => [], 'limit' => 50],
        ]);
    }

    public function test_index_lists_only_the_landlords_dashboards(): void
    {
        $this->actingAs($this->landlord);
        LandlordDashboard::create(['slug' => 'mine', 'name' => 'Mine', 'layout' => []]);
        $this->actingAs($this->otherLandlord);
        LandlordDashboard::create(['slug' => 'theirs', 'name' => 'Theirs', 'layout' => []]);

        $this->actingAs($this->landlord)
            ->get(route('dashboards.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Dashboards/Index')
                ->has('dashboards', 1)
                ->where('dashboards.0.name', 'Mine'),
            );
    }

    public function test_store_creates_a_dashboard_with_valid_cards(): void
    {
        $report = $this->reportFor($this->landlord);

        $this->actingAs($this->landlord)
            ->post(route('dashboards.store'), [
                'name' => 'Ops board',
                'layout' => [['type' => 'saved_report', 'saved_report_id' => $report->id, 'size' => 'wide']],
            ])
            ->assertRedirect();

        $dashboard = LandlordDashboard::where('landlord_id', $this->landlord->id)->first();
        $this->assertNotNull($dashboard);
        $this->assertSame($report->id, $dashboard->layout[0]['saved_report_id']);
    }

    public function test_store_rejects_a_cross_tenant_card(): void
    {
        $foreign = $this->reportFor($this->otherLandlord, 'Theirs');

        $this->actingAs($this->landlord)
            ->post(route('dashboards.store'), [
                'name' => 'Sneaky',
                'layout' => [['type' => 'saved_report', 'saved_report_id' => $foreign->id, 'size' => 'wide']],
            ])
            ->assertSessionHasErrors('layout.0.saved_report_id');

        $this->assertSame(0, LandlordDashboard::withoutGlobalScopes()->where('landlord_id', $this->landlord->id)->count());
    }

    public function test_update_relayouts_and_set_default_is_sole(): void
    {
        $report = $this->reportFor($this->landlord);
        $this->actingAs($this->landlord);
        $a = LandlordDashboard::create(['slug' => 'a', 'name' => 'A', 'layout' => [], 'is_default' => true]);
        $b = LandlordDashboard::create(['slug' => 'b', 'name' => 'B', 'layout' => []]);

        $this->actingAs($this->landlord)
            ->put(route('dashboards.update', $b->id), [
                'name' => 'B renamed',
                'layout' => [['type' => 'saved_report', 'saved_report_id' => $report->id, 'size' => 'narrow']],
                'is_default' => true,
            ])
            ->assertRedirect();

        $this->assertSame('B renamed', $b->fresh()->name);
        $this->assertTrue($b->fresh()->is_default);
        $this->assertFalse($a->fresh()->is_default);
    }

    public function test_destroy_soft_deletes(): void
    {
        $this->actingAs($this->landlord);
        $d = LandlordDashboard::create(['slug' => 'd', 'name' => 'D', 'layout' => []]);

        $this->actingAs($this->landlord)->delete(route('dashboards.destroy', $d->id))->assertRedirect();
        $this->assertSoftDeleted('landlord_dashboards', ['id' => $d->id]);
    }

    public function test_preview_returns_cards_and_rejects_foreign(): void
    {
        $report = $this->reportFor($this->landlord);
        $foreign = $this->reportFor($this->otherLandlord, 'Theirs');

        $this->actingAs($this->landlord)
            ->postJson(route('dashboards.preview'), [
                'layout' => [['type' => 'saved_report', 'saved_report_id' => $report->id, 'size' => 'wide']],
            ])
            ->assertOk()
            ->assertJsonPath('cards.0.type', 'saved_report');

        $this->actingAs($this->landlord)
            ->postJson(route('dashboards.preview'), [
                'layout' => [['type' => 'saved_report', 'saved_report_id' => $foreign->id, 'size' => 'wide']],
            ])
            ->assertStatus(422);
    }

    public function test_long_name_and_soft_deleted_slug_do_not_500(): void
    {
        $this->actingAs($this->landlord);

        // A 200-char name must not overflow the 64-char slug column.
        $this->post(route('dashboards.store'), ['name' => str_repeat('Very Long Dashboard ', 12), 'layout' => []])
            ->assertRedirect();

        // Re-using a soft-deleted dashboard's name must not collide on the unique index.
        $this->post(route('dashboards.store'), ['name' => 'Recurring', 'layout' => []])->assertRedirect();
        $first = LandlordDashboard::where('landlord_id', $this->landlord->id)->where('name', 'Recurring')->firstOrFail();
        $this->delete(route('dashboards.destroy', $first->id));
        $this->post(route('dashboards.store'), ['name' => 'Recurring', 'layout' => []])->assertRedirect();

        $this->assertSame(2, LandlordDashboard::withTrashed()->withoutGlobalScopes()->where('landlord_id', $this->landlord->id)->where('name', 'Recurring')->count());
    }

    public function test_cannot_edit_another_landlords_dashboard(): void
    {
        $this->actingAs($this->otherLandlord);
        $foreign = LandlordDashboard::create(['slug' => 'theirs', 'name' => 'Theirs', 'layout' => []]);

        $this->actingAs($this->landlord)->get(route('dashboards.edit', $foreign->id))->assertNotFound();
        $this->actingAs($this->landlord)->delete(route('dashboards.destroy', $foreign->id))->assertNotFound();
    }
}
