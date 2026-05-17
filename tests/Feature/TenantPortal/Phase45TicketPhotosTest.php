<?php

declare(strict_types=1);

namespace Tests\Feature\TenantPortal;

use App\Models\Document;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketAnnotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-45 TICKET-PHOTOS-1/2/3 watchdog suite:
 *  - service stores a sibling Document with annotates_document_id pointing
 *    back to the original + annotation_data JSON.
 *  - the tenant-owned-ticket / landlord-owned-ticket authorisation gates
 *    fire correctly + non-image attachments + already-annotated copies
 *    are refused.
 *  - the relationship + isAnnotation() helper returns the right shape.
 */
class Phase45TicketPhotosTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private $lease;

    private Ticket $ticket;

    private Document $original;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['tenant' => $this->tenant, 'lease' => $this->lease] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );

        $unit = $this->lease->unit;
        $this->ticket = Ticket::create([
            'landlord_id' => $this->landlord->id,
            'tenant_id' => $this->tenant->id,
            'reporter_id' => $this->tenant->id,
            'lease_id' => $this->lease->id,
            'unit_id' => $unit->id,
            'building_id' => $unit->building_id,
            'title' => 'Leaky pipe',
            'description' => 'There is a leak under the sink.',
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'priority' => 'high',
            'status' => 'open',
        ]);

        Storage::disk('local')->put('tickets/'.$this->ticket->id.'/original.jpg', 'BINARYIMAGEPAYLOAD');
        $this->original = $this->ticket->attachments()->create([
            'landlord_id' => $this->landlord->id,
            'title' => 'leak.jpg',
            'file_name' => 'leak.jpg',
            'file_path' => 'tickets/'.$this->ticket->id.'/original.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 18,
            'document_type' => 'other',
            'uploaded_by' => $this->tenant->id,
        ]);
    }

    public function test_service_persists_annotated_copy_with_annotates_document_id(): void
    {
        $service = app(TicketAnnotationService::class);
        $base64 = base64_encode($this->fakePngBytes());

        $annotated = $service->storeAnnotation(
            $this->ticket,
            $this->original,
            $base64,
            [['kind' => 'pen', 'color' => '#ef4444', 'width' => 4, 'points' => [['x' => 1, 'y' => 2]]]],
            $this->tenant,
        );

        $this->assertSame($this->original->id, $annotated->annotates_document_id);
        $this->assertSame('image/png', $annotated->mime_type);
        $this->assertSame($this->ticket->id, $annotated->documentable_id);
        $this->assertTrue($annotated->isAnnotation());
        $this->assertNotEmpty($annotated->annotation_data);
        $this->assertTrue(Storage::disk('local')->exists($annotated->file_path));
    }

    public function test_service_decodes_data_url_form(): void
    {
        $service = app(TicketAnnotationService::class);
        $dataUrl = 'data:image/png;base64,'.base64_encode($this->fakePngBytes());

        $annotated = $service->storeAnnotation(
            $this->ticket,
            $this->original,
            $dataUrl,
            [],
            $this->tenant,
        );

        $this->assertNotNull($annotated);
        $this->assertTrue(Storage::disk('local')->exists($annotated->file_path));
    }

    public function test_endpoint_authorizes_ticket_owner_and_persists(): void
    {
        $response = $this->actingAs($this->tenant)
            ->post(route('tickets.attachments.annotation', [
                'ticket' => $this->ticket->id,
                'document' => $this->original->id,
            ]), [
                'image' => 'data:image/png;base64,'.base64_encode($this->fakePngBytes()),
                'annotation_data' => [['kind' => 'rect', 'color' => '#ef4444', 'width' => 2, 'x' => 0, 'y' => 0, 'w' => 50, 'h' => 50]],
            ]);

        $response->assertRedirect();
        $this->assertSame(1, $this->original->annotations()->count());
    }

    public function test_endpoint_rejects_user_from_a_different_landlord(): void
    {
        $stranger = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($stranger)
            ->post(route('tickets.attachments.annotation', [
                'ticket' => $this->ticket->id,
                'document' => $this->original->id,
            ]), [
                'image' => 'data:image/png;base64,'.base64_encode($this->fakePngBytes()),
                'annotation_data' => [],
            ]);

        $response->assertForbidden();
        $this->assertSame(0, $this->original->annotations()->count());
    }

    public function test_endpoint_rejects_non_image_attachments(): void
    {
        Storage::disk('local')->put('tickets/'.$this->ticket->id.'/notes.pdf', '%PDF-1.4 fake');
        $pdf = $this->ticket->attachments()->create([
            'landlord_id' => $this->landlord->id,
            'title' => 'notes.pdf',
            'file_name' => 'notes.pdf',
            'file_path' => 'tickets/'.$this->ticket->id.'/notes.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 12,
            'document_type' => 'other',
            'uploaded_by' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->tenant)
            ->post(route('tickets.attachments.annotation', [
                'ticket' => $this->ticket->id,
                'document' => $pdf->id,
            ]), [
                'image' => 'data:image/png;base64,'.base64_encode($this->fakePngBytes()),
                'annotation_data' => [],
            ]);

        $response->assertForbidden();
    }

    public function test_endpoint_rejects_annotating_an_existing_annotation(): void
    {
        $service = app(TicketAnnotationService::class);
        $annotated = $service->storeAnnotation(
            $this->ticket,
            $this->original,
            base64_encode($this->fakePngBytes()),
            [],
            $this->tenant,
        );

        $response = $this->actingAs($this->tenant)
            ->post(route('tickets.attachments.annotation', [
                'ticket' => $this->ticket->id,
                'document' => $annotated->id,
            ]), [
                'image' => 'data:image/png;base64,'.base64_encode($this->fakePngBytes()),
                'annotation_data' => [],
            ]);

        $response->assertForbidden();
    }

    public function test_document_relationships_link_annotation_to_original(): void
    {
        $service = app(TicketAnnotationService::class);
        $annotated = $service->storeAnnotation(
            $this->ticket,
            $this->original,
            base64_encode($this->fakePngBytes()),
            [],
            $this->tenant,
        );

        $this->assertSame($this->original->id, $annotated->annotates->id);
        $this->assertSame($annotated->id, $this->original->annotations()->first()->id);
        $this->assertFalse($this->original->isAnnotation());
        $this->assertTrue($annotated->isAnnotation());
    }

    private function fakePngBytes(): string
    {
        // 1x1 transparent PNG (minimum-valid).
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
            true,
        );
    }
}
