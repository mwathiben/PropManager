<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Enums\TicketStatus;
use App\Models\Building;
use App\Models\Document;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-75 PHOTO-ROLLUP: landlord-wide maintenance photo gallery (only the
 * landlord's ticket photos, filterable, annotation siblings grouped) + PDF
 * export.
 */
class Phase75PhotoRollupTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->building = $setup['building'];
        $this->actingAs($this->landlord);
    }

    private function ticket(User $owner, Building $building, string $subcategory = 'plumbing'): Ticket
    {
        return Model::withoutEvents(fn () => Ticket::create([
            'landlord_id' => $owner->id,
            'building_id' => $building->id,
            'reporter_id' => $owner->id,
            'category' => 'issue',
            'subcategory' => $subcategory,
            'title' => 'Leaky tap',
            'description' => 'X',
            'priority' => 'high',
            'status' => TicketStatus::Open->value,
        ]));
    }

    private function photo(Ticket $ticket, User $owner, ?int $annotates = null): Document
    {
        return Document::factory()->create([
            'landlord_id' => $owner->id,
            'documentable_type' => Ticket::class,
            'documentable_id' => $ticket->id,
            'uploaded_by' => $owner->id,
            'mime_type' => 'image/jpeg',
            'annotates_document_id' => $annotates,
        ]);
    }

    public function test_gallery_returns_only_the_landlords_ticket_photos(): void
    {
        $mine = $this->photo($this->ticket($this->landlord, $this->building), $this->landlord);

        $other = Model::withoutEvents(fn () => $this->createLandlordWithFullSetup()['landlord']);
        $otherBuilding = Building::where('landlord_id', $other->id)->first();
        $this->actingAs($other);
        $theirs = $this->photo($this->ticket($other, $otherBuilding), $other);
        $this->actingAs($this->landlord);

        $response = $this->get(route('maintenance.photos'));
        $response->assertOk();

        $ids = collect($response->viewData('page')['props']['photos']['data'])->pluck('id');
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_gallery_filters_by_building_and_category(): void
    {
        $second = Building::create([
            'property_id' => $this->building->property_id,
            'name' => 'Block B',
            'total_floors' => 1,
            'units_per_floor' => 1,
            'landlord_id' => $this->landlord->id,
            'building_type' => 'residential_apartment',
        ]);

        $plumbing = $this->photo($this->ticket($this->landlord, $this->building, 'plumbing'), $this->landlord);
        $electricalOtherBuilding = $this->photo($this->ticket($this->landlord, $second, 'electrical'), $this->landlord);

        $response = $this->get(route('maintenance.photos', [
            'building_id' => $this->building->id,
            'category' => 'plumbing',
        ]));

        $ids = collect($response->viewData('page')['props']['photos']['data'])->pluck('id');
        $this->assertContains($plumbing->id, $ids);
        $this->assertNotContains($electricalOtherBuilding->id, $ids);
    }

    public function test_annotation_siblings_are_grouped_not_listed_as_originals(): void
    {
        $ticket = $this->ticket($this->landlord, $this->building);
        $original = $this->photo($ticket, $this->landlord);
        $annotation = $this->photo($ticket, $this->landlord, annotates: $original->id);

        $response = $this->get(route('maintenance.photos'));
        $photos = collect($response->viewData('page')['props']['photos']['data']);

        $this->assertNotContains($annotation->id, $photos->pluck('id'));
        $row = $photos->firstWhere('id', $original->id);
        $this->assertNotNull($row);
        $this->assertSame($annotation->id, $row['annotations'][0]['id']);
    }

    public function test_gallery_filters_by_date_range(): void
    {
        $ticket = $this->ticket($this->landlord, $this->building);

        $recent = $this->photo($ticket, $this->landlord);
        $old = $this->photo($ticket, $this->landlord);
        $old->forceFill(['created_at' => now()->subDays(60)])->saveQuietly();

        $response = $this->get(route('maintenance.photos', ['from' => now()->subDays(7)->toDateString()]));

        $ids = collect($response->viewData('page')['props']['photos']['data'])->pluck('id');
        $this->assertContains($recent->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }

    public function test_export_pdf_returns_a_pdf_for_the_owner(): void
    {
        $this->photo($this->ticket($this->landlord, $this->building), $this->landlord);

        $response = $this->get(route('maintenance.photos.export-pdf'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_cross_tenant_building_filter_yields_no_photos(): void
    {
        $this->photo($this->ticket($this->landlord, $this->building), $this->landlord);

        $other = Model::withoutEvents(fn () => $this->createLandlordWithFullSetup()['landlord']);
        $foreignBuilding = Building::where('landlord_id', $other->id)->first();

        $response = $this->get(route('maintenance.photos', ['building_id' => $foreignBuilding->id]));

        $this->assertCount(0, $response->viewData('page')['props']['photos']['data']);
    }
}
