<?php

declare(strict_types=1);

namespace Tests\Feature\TenantPortal;

use App\Events\TicketSlaBreached;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-28 TENANT-MAINT-1/2/3 watchdog suite.
 */
class Phase28MaintTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private $building;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->building = $setup['building'];
        ['tenant' => $this->tenant] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );
    }

    public function test_sla_due_at_set_on_create_for_each_priority(): void
    {
        foreach (Ticket::SLA_SECONDS as $priority => $seconds) {
            $ticket = $this->makeTicket($priority);
            $expected = $ticket->created_at->copy()->addSeconds($seconds);
            $this->assertEqualsWithDelta(
                $expected->timestamp,
                $ticket->sla_due_at->timestamp,
                2,
                "{$priority} sla_due_at should be created_at + {$seconds}s",
            );
        }
    }

    public function test_first_response_at_set_only_by_landlord_or_caretaker_activity(): void
    {
        $ticket = $this->makeTicket('medium');

        // Tenant comment (action != 'created' but reporter is tenant) — must NOT count
        TicketActivity::create([
            'landlord_id' => $this->landlord->id,
            'ticket_id' => $ticket->id,
            'user_id' => $this->tenant->id,
            'action' => TicketActivity::ACTION_COMMENTED,
            'description' => 'tenant follow-up',
            'created_at' => now(),
        ]);
        $this->assertNull($ticket->fresh()->first_response_at);

        // Landlord response — must stamp
        TicketActivity::create([
            'landlord_id' => $this->landlord->id,
            'ticket_id' => $ticket->id,
            'user_id' => $this->landlord->id,
            'action' => TicketActivity::ACTION_COMMENTED,
            'description' => 'landlord reply',
            'created_at' => now(),
        ]);
        $this->assertNotNull($ticket->fresh()->first_response_at);
    }

    public function test_first_response_at_does_not_advance_on_subsequent_landlord_activity(): void
    {
        $ticket = $this->makeTicket('low');
        $first = TicketActivity::create([
            'landlord_id' => $this->landlord->id,
            'ticket_id' => $ticket->id,
            'user_id' => $this->landlord->id,
            'action' => TicketActivity::ACTION_COMMENTED,
            'description' => 'first',
            'created_at' => now()->subMinute(),
        ]);
        $original = $ticket->fresh()->first_response_at;
        $this->assertNotNull($original);

        TicketActivity::create([
            'landlord_id' => $this->landlord->id,
            'ticket_id' => $ticket->id,
            'user_id' => $this->landlord->id,
            'action' => TicketActivity::ACTION_COMMENTED,
            'description' => 'second',
            'created_at' => now(),
        ]);

        $this->assertSame(
            $original->toDateTimeString(),
            $ticket->fresh()->first_response_at->toDateTimeString(),
        );
    }

    public function test_breached_sla_scope_returns_only_overdue_unresponded(): void
    {
        $overdue = $this->makeTicket('urgent', sla_due_at: now()->subHour());
        $responded = $this->makeTicket('urgent', sla_due_at: now()->subHour());
        $responded->forceFill(['first_response_at' => now()])->save();
        $upcoming = $this->makeTicket('urgent', sla_due_at: now()->addHour());

        $ids = Ticket::breachedSla()->pluck('id')->all();
        $this->assertContains($overdue->id, $ids);
        $this->assertNotContains($responded->id, $ids);
        $this->assertNotContains($upcoming->id, $ids);
    }

    public function test_audit_command_fires_event_for_breaches_and_is_idempotent(): void
    {
        Event::fake([TicketSlaBreached::class]);
        Cache::flush();
        $ticket = $this->makeTicket('high', sla_due_at: now()->subDay());

        $this->artisan('tickets:audit-sla')->assertSuccessful();
        Event::assertDispatchedTimes(TicketSlaBreached::class, 1);

        // Second run within the cache window: should NOT re-fire.
        Event::fake([TicketSlaBreached::class]);
        $this->artisan('tickets:audit-sla')->assertSuccessful();
        Event::assertNotDispatched(TicketSlaBreached::class);
    }

    public function test_audit_command_dry_run_does_not_fire_events(): void
    {
        Event::fake([TicketSlaBreached::class]);
        $this->makeTicket('low', sla_due_at: now()->subDays(8));

        $this->artisan('tickets:audit-sla', ['--dry-run' => true])->assertSuccessful();
        Event::assertNotDispatched(TicketSlaBreached::class);
    }

    public function test_listener_uses_phase_16_backoff_array(): void
    {
        $listener = new \App\Listeners\NotifyOnTicketSlaBreach(
            $this->createMock(\App\Services\NotificationService::class),
        );
        $this->assertSame([30, 60, 300, 1800], $listener->backoff);
        $this->assertSame(4, $listener->tries);
    }

    public function test_store_ticket_validates_max_5_photos(): void
    {
        $sixPhotos = collect(range(1, 6))
            ->map(fn ($i) => UploadedFile::fake()->image("photo{$i}.jpg"))
            ->all();

        $this->actingAs($this->tenant)
            ->from(route('tickets.create'))
            ->post(route('tickets.store'), [
                'building_id' => $this->building->id,
                'category' => 'issue',
                'subcategory' => 'plumbing',
                'title' => 'leak',
                'description' => 'water everywhere',
                'priority' => 'high',
                'photos' => $sixPhotos,
            ])
            ->assertSessionHasErrors('photos');
    }

    public function test_store_ticket_rejects_non_image_mime(): void
    {
        $bad = UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream');

        $this->actingAs($this->tenant)
            ->from(route('tickets.create'))
            ->post(route('tickets.store'), [
                'building_id' => $this->building->id,
                'category' => 'issue',
                'subcategory' => 'plumbing',
                'title' => 'leak',
                'description' => 'water everywhere',
                'priority' => 'high',
                'photos' => [$bad],
            ])
            ->assertSessionHasErrors('photos.0');
    }

    public function test_schedule_includes_tickets_audit_sla_at_07_00_nairobi(): void
    {
        $events = collect(Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'tickets:audit-sla'));

        $this->assertNotNull($entry, 'tickets:audit-sla must be scheduled');
        $this->assertSame('0 7 * * *', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    private function makeTicket(string $priority, ?\Carbon\Carbon $sla_due_at = null): Ticket
    {
        $ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->tenant->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'leak '.uniqid(),
            'description' => 'desc',
            'priority' => $priority,
            'status' => 'open',
        ]);
        if ($sla_due_at) {
            $ticket->forceFill(['sla_due_at' => $sla_due_at])->save();
        }

        return $ticket->fresh();
    }
}
