<?php

declare(strict_types=1);

namespace Tests\Feature\Pwa;

use App\Exceptions\WriteConflictException;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\WaterReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-62 CONFLICT-RESOLUTION-1/2/3 watchdog: version column + RowVersion
 * trait + WriteConflictException + 409 renderer + ConflictDialog.vue.
 */
class Phase62ConflictResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_column_exists_on_tickets_ticket_comments_water_readings(): void
    {
        foreach (['tickets', 'ticket_comments', 'water_readings'] as $table) {
            $this->assertTrue(
                Schema::hasColumn($table, 'version'),
                "CONFLICT-RESOLUTION-1: {$table} must have a version column.",
            );
        }
    }

    public function test_models_use_row_version_trait(): void
    {
        foreach ([Ticket::class, TicketComment::class, WaterReading::class] as $modelClass) {
            $traits = class_uses_recursive($modelClass);
            $this->assertContains(
                \App\Models\Concerns\RowVersion::class,
                $traits,
                "CONFLICT-RESOLUTION-1: {$modelClass} must use the RowVersion trait.",
            );
        }
    }

    public function test_row_version_trait_bumps_on_save(): void
    {
        $landlord = \App\Models\User::factory()->create(['role' => 'landlord']);
        $building = \App\Models\Building::factory()->create(['landlord_id' => $landlord->id]);
        $ticket = Ticket::factory()->create([
            'landlord_id' => $landlord->id,
            'building_id' => $building->id,
            'reporter_id' => $landlord->id,
        ]);

        $this->assertSame(1, (int) $ticket->version);

        $ticket->title = 'Updated title';
        $ticket->save();
        $ticket->refresh();

        $this->assertSame(2, (int) $ticket->version);
    }

    public function test_assert_if_match_throws_on_version_mismatch(): void
    {
        $landlord = \App\Models\User::factory()->create(['role' => 'landlord']);
        $building = \App\Models\Building::factory()->create(['landlord_id' => $landlord->id]);
        $ticket = Ticket::factory()->create([
            'landlord_id' => $landlord->id,
            'building_id' => $building->id,
            'reporter_id' => $landlord->id,
            'title' => 'Original',
        ]);

        $incoming = ['title' => 'Stale write'];
        $this->expectException(WriteConflictException::class);
        $ticket->assertIfMatch(99, $incoming);
    }

    public function test_assert_if_match_passes_when_version_matches(): void
    {
        $landlord = \App\Models\User::factory()->create(['role' => 'landlord']);
        $building = \App\Models\Building::factory()->create(['landlord_id' => $landlord->id]);
        $ticket = Ticket::factory()->create([
            'landlord_id' => $landlord->id,
            'building_id' => $building->id,
            'reporter_id' => $landlord->id,
        ]);

        // No exception expected.
        $ticket->assertIfMatch((int) $ticket->version, []);
        $this->expectNotToPerformAssertions();
    }

    public function test_assert_if_match_skipped_when_null(): void
    {
        $landlord = \App\Models\User::factory()->create(['role' => 'landlord']);
        $building = \App\Models\Building::factory()->create(['landlord_id' => $landlord->id]);
        $ticket = Ticket::factory()->create([
            'landlord_id' => $landlord->id,
            'building_id' => $building->id,
            'reporter_id' => $landlord->id,
        ]);

        // Null version skips check (backwards compat for callers
        // that haven't opted in yet).
        $ticket->assertIfMatch(null, []);
        $this->expectNotToPerformAssertions();
    }

    public function test_write_conflict_exception_diff_surfaces_changed_fields_only(): void
    {
        $landlord = \App\Models\User::factory()->create(['role' => 'landlord']);
        $building = \App\Models\Building::factory()->create(['landlord_id' => $landlord->id]);
        $ticket = Ticket::factory()->create([
            'landlord_id' => $landlord->id,
            'building_id' => $building->id,
            'reporter_id' => $landlord->id,
            'title' => 'Server title',
            'description' => 'Server description',
        ]);

        $incoming = [
            'title' => 'Client title',
            'description' => 'Server description', // unchanged
        ];
        $e = new WriteConflictException($ticket, 1, $incoming);
        $diff = $e->diff();

        $this->assertArrayHasKey('title', $diff);
        $this->assertArrayNotHasKey('description', $diff, 'Unchanged fields must not surface in diff.');
        $this->assertSame('Server title', $diff['title']['current']);
        $this->assertSame('Client title', $diff['title']['incoming']);
    }

    public function test_bootstrap_renders_write_conflict_as_409(): void
    {
        $bootstrap = (string) file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringContainsString(
            'WriteConflictException',
            $bootstrap,
            'CONFLICT-RESOLUTION-2: bootstrap/app.php must register a renderer for WriteConflictException.',
        );
        $this->assertStringContainsString(
            "'error' => 'write_conflict'",
            $bootstrap,
            'CONFLICT-RESOLUTION-2: 409 payload must use error code write_conflict so the client can detect it.',
        );
        $this->assertStringContainsString(
            '409',
            $bootstrap,
            'CONFLICT-RESOLUTION-2: renderer must produce a 409 status code.',
        );
    }

    public function test_conflict_dialog_component_exists(): void
    {
        $path = resource_path('js/Components/Offline/ConflictDialog.vue');
        $this->assertFileExists($path, 'CONFLICT-RESOLUTION-3: ConflictDialog.vue must exist.');

        $src = (string) file_get_contents($path);
        foreach (['overwrite', 'discard', 'merge'] as $action) {
            $this->assertStringContainsString(
                "'{$action}'",
                $src,
                "CONFLICT-RESOLUTION-3: ConflictDialog must surface the {$action} action.",
            );
        }
        $this->assertStringContainsString(
            'aria-modal="true"',
            $src,
            'CONFLICT-RESOLUTION-3: ConflictDialog must be aria-modal for accessibility.',
        );
        $this->assertStringContainsString(
            'current_version',
            $src,
            'CONFLICT-RESOLUTION-3: ConflictDialog must show the current server version so the user knows what they are reconciling with.',
        );
    }
}
