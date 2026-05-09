<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Document;
use App\Models\Lease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Regression tests for SCOPE-S3: DocumentController previously used
 * auth()->id() throughout, which returns the caretaker's user ID rather
 * than the landlord ID — caretakers saw zero documents and their uploads
 * were orphaned to a non-landlord user ID.
 */
class DocumentControllerCaretakerTest extends TestCase
{
    use RefreshDatabase;

    private function makeDocument(int $landlordId, int $uploaderId): Document
    {
        // The Document model doesn't use HasFactory; build directly via create().
        // documentable_type/id reference Lease so the eager-load doesn't error.
        $lease = Lease::factory()->create(['landlord_id' => $landlordId]);

        return Document::create([
            'landlord_id' => $landlordId,
            'documentable_type' => Lease::class,
            'documentable_id' => $lease->id,
            'title' => 'Sample doc',
            'file_name' => 'sample.pdf',
            'file_path' => "documents/{$landlordId}/Lease/sample.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'document_type' => 'lease_agreement',
            'uploaded_by' => $uploaderId,
        ]);
    }

    public function test_caretaker_sees_their_landlords_documents_in_index(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $landlord->id,
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->makeDocument($landlord->id, $landlord->id);
        }

        $response = $this->actingAs($caretaker)->get(route('documents.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('documents.data', 3)
        );
    }

    public function test_caretaker_does_not_see_other_landlords_documents(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $landlord->id,
        ]);

        $otherLandlord = User::factory()->create(['role' => 'landlord']);
        $this->makeDocument($otherLandlord->id, $otherLandlord->id);
        $this->makeDocument($otherLandlord->id, $otherLandlord->id);

        $response = $this->actingAs($caretaker)->get(route('documents.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('documents.data', 0)
        );
    }
}
