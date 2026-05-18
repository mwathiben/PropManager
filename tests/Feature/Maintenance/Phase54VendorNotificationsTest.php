<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Events\TicketAssignedToVendor;
use App\Listeners\NotifyVendorOnAssignment;
use App\Mail\VendorAssignmentMailable;
use App\Models\Building;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase-54 VENDOR-NOTIFICATIONS-1/2/3 watchdog.
 */
class Phase54VendorNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_is_queued_via_should_queue(): void
    {
        $this->assertContains(
            'Illuminate\Contracts\Queue\ShouldQueue',
            class_implements(NotifyVendorOnAssignment::class) ?: [],
            'NotifyVendorOnAssignment must implement ShouldQueue (Phase-16 RESIL pattern).',
        );

        $listener = new NotifyVendorOnAssignment;
        $this->assertSame(4, $listener->tries);
        $this->assertSame([30, 60, 300, 1800], $listener->backoff);
    }

    public function test_listener_subscribes_to_ticket_assigned_to_vendor(): void
    {
        Event::fake();

        [$landlord, $vendor, $ticket] = $this->makeFixture();

        TicketAssignedToVendor::dispatch($ticket, $vendor, 'urgent — pipe burst');

        Event::assertListening(TicketAssignedToVendor::class, NotifyVendorOnAssignment::class);
    }

    public function test_handle_queues_mailable_to_vendor_email(): void
    {
        Mail::fake();

        [$landlord, $vendor, $ticket] = $this->makeFixture();

        (new NotifyVendorOnAssignment)->handle(
            new TicketAssignedToVendor($ticket, $vendor, 'urgent — pipe burst'),
        );

        Mail::assertQueued(VendorAssignmentMailable::class, function ($mail) use ($vendor) {
            return $mail->hasTo($vendor->email);
        });
    }

    public function test_handle_skips_when_vendor_email_is_null(): void
    {
        Mail::fake();

        [$landlord, $vendor, $ticket] = $this->makeFixture(['email' => null]);

        (new NotifyVendorOnAssignment)->handle(
            new TicketAssignedToVendor($ticket, $vendor, null),
        );

        Mail::assertNothingQueued();
    }

    public function test_mailable_uses_after_commit_and_should_queue(): void
    {
        [$landlord, $vendor, $ticket] = $this->makeFixture();
        $mail = new VendorAssignmentMailable($ticket, $vendor, null);

        $this->assertTrue($mail->afterCommit, 'Mailable must wait for the DB transaction (Phase-16 afterCommit).');
        $this->assertContains(
            'Illuminate\Contracts\Queue\ShouldQueue',
            class_implements($mail) ?: [],
        );
    }

    public function test_lang_maintenance_namespace_exists_in_all_locales(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            $path = base_path("lang/{$locale}/maintenance.php");
            $this->assertFileExists($path, "lang/{$locale}/maintenance.php missing");
            $payload = require $path;
            $this->assertArrayHasKey('vendor_assigned', $payload);
            foreach (['subject', 'heading', 'greeting', 'body', 'scope_label', 'note_label', 'contact_note', 'signoff'] as $key) {
                $this->assertArrayHasKey($key, $payload['vendor_assigned'], "{$locale}.maintenance.vendor_assigned.{$key} missing");
            }
        }
    }

    public function test_lang_maintenance_keys_have_identity_parity(): void
    {
        $en = require base_path('lang/en/maintenance.php');
        $sw = require base_path('lang/sw/maintenance.php');
        $ar = require base_path('lang/ar/maintenance.php');

        // Identity comparison on array_keys at every nesting level so a
        // future key addition can't silently drift one locale.
        $this->assertSame(array_keys($en), array_keys($sw));
        $this->assertSame(array_keys($en), array_keys($ar));
        $this->assertSame(array_keys($en['vendor_assigned']), array_keys($sw['vendor_assigned']));
        $this->assertSame(array_keys($en['vendor_assigned']), array_keys($ar['vendor_assigned']));
    }

    public function test_mailable_subject_interpolates_ticket_title_from_lang(): void
    {
        [$landlord, $vendor, $ticket] = $this->makeFixture();
        $ticket->title = 'Leaky kitchen tap';

        $envelope = (new VendorAssignmentMailable($ticket, $vendor, null))->envelope();
        $this->assertStringContainsString('Leaky kitchen tap', $envelope->subject);
    }

    /**
     * @param  array<string, mixed>  $vendorOverrides
     * @return array{User, Vendor, Ticket}
     */
    private function makeFixture(array $vendorOverrides = []): array
    {
        $landlord = User::factory()->create([
            'role' => 'landlord',
            'locale' => 'en',
        ]);

        $property = Property::factory()->create(['landlord_id' => $landlord->id]);
        $building = Building::factory()->create([
            'landlord_id' => $landlord->id,
            'property_id' => $property->id,
        ]);

        $vendor = Vendor::create(array_merge([
            'landlord_id' => $landlord->id,
            'name' => 'Acme Plumbing',
            'contact_person' => 'Joe Acme',
            'email' => 'joe@acme.test',
            'phone' => '0712345678',
            'is_active' => true,
        ], $vendorOverrides));

        $ticket = Ticket::create([
            'landlord_id' => $landlord->id,
            'building_id' => $building->id,
            'reporter_id' => $landlord->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'priority' => 'high',
            'status' => 'open',
            'title' => 'Burst pipe',
            'description' => 'Water everywhere.',
        ]);

        return [$landlord, $vendor, $ticket];
    }
}
