<?php

declare(strict_types=1);

namespace Tests\Feature\TenantPortal;

use App\Enums\KycSubmissionStatus;
use App\Models\Document;
use App\Models\KycRequirement;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\TenantKycSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-28 TENANT-DOCS-1/2/3 watchdog suite.
 */
class Phase28DocsTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Lease $lease;

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
    }

    public function test_documents_index_groups_lease_receipts_and_kyc(): void
    {
        $leaseDoc = $this->createDocument(Lease::class, $this->lease->id, 'Lease Agreement', 'lease_agreement');
        $receipt = $this->createReceipt();
        // KYC requirement is OPTIONAL so the kyc.complete middleware
        // does not redirect — required + pending would block the page.
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->optional()
            ->create();
        $kycSubmission = TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $requirement->id,
            'status' => KycSubmissionStatus::Approved,
        ]);
        $kycDoc = $this->createDocument(TenantKycSubmission::class, $kycSubmission->id, 'National ID', 'tenant_id');

        $response = $this->actingAs($this->tenant)->get(route('tenant.documents.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tenant/Documents')
            ->has('leaseDocuments', 1, fn ($doc) => $doc->where('id', $leaseDoc->id)->etc())
            ->has('kycDocuments', 1, fn ($doc) => $doc->where('id', $kycDoc->id)->etc())
            ->has('receipts', 1, fn ($r) => $r->where('id', $receipt->id)->etc())
        );
    }

    public function test_tenant_can_download_own_lease_document(): void
    {
        $doc = $this->createDocument(Lease::class, $this->lease->id, 'Lease.pdf', 'lease_agreement');

        $response = $this->actingAs($this->tenant)
            ->get(route('tenant.documents.download', ['document' => $doc->id]));

        $response->assertOk();
        $this->assertSame('attachment; filename='.$doc->file_name, $response->headers->get('Content-Disposition'));
    }

    public function test_tenant_cannot_download_other_tenants_document(): void
    {
        $otherSetup = $this->createLandlordWithFullSetup();
        $otherLandlord = $otherSetup['landlord'];
        ['lease' => $otherLease] = $this->createTenantWithActiveLease(
            $otherLandlord,
            $otherSetup['units']->first(),
        );
        $otherDoc = Document::create([
            'landlord_id' => $otherLandlord->id,
            'documentable_id' => $otherLease->id,
            'documentable_type' => Lease::class,
            'title' => 'Other lease.pdf',
            'file_name' => 'other.pdf',
            'file_path' => 'documents/other.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'document_type' => 'lease_agreement',
            'uploaded_by' => $otherLandlord->id,
        ]);
        Storage::disk('local')->put('documents/other.pdf', 'other');

        $response = $this->actingAs($this->tenant)
            ->get(route('tenant.documents.download', ['document' => $otherDoc->id]));

        $response->assertForbidden();
    }

    public function test_expires_at_scope_returns_documents_within_window(): void
    {
        $expiringSoon = $this->createDocument(Lease::class, $this->lease->id, 'ID expiring', 'tenant_id', now()->addDays(10));
        $expiringFar = $this->createDocument(Lease::class, $this->lease->id, 'ID far', 'tenant_id', now()->addDays(120));
        $noExpiry = $this->createDocument(Lease::class, $this->lease->id, 'Lease', 'lease_agreement');

        $ids = Document::expiringSoon(30)->pluck('id')->all();

        $this->assertContains($expiringSoon->id, $ids);
        $this->assertNotContains($expiringFar->id, $ids);
        $this->assertNotContains($noExpiry->id, $ids);
    }

    public function test_expiring_docs_shared_to_inertia_for_tenant_only(): void
    {
        $this->createDocument(Lease::class, $this->lease->id, 'ID', 'tenant_id', now()->addDays(15));

        // Same /dashboard route serves both roles; the shared prop is
        // gated by isTenant() so landlords get an empty array.
        $tenantResponse = $this->actingAs($this->tenant)->get(route('dashboard'));
        $tenantResponse->assertInertia(fn ($page) => $page
            ->has('tenantExpiringDocs', 1)
            ->where('tenantExpiringDocs.0.title', 'ID')
        );

        $landlordResponse = $this->actingAs($this->landlord)->get(route('dashboard'));
        $landlordResponse->assertInertia(fn ($page) => $page->where('tenantExpiringDocs', []));
    }

    public function test_landlord_cannot_reach_tenant_documents_index(): void
    {
        $this->actingAs($this->landlord)
            ->get(route('tenant.documents.index'))
            ->assertForbidden();
    }

    private function createDocument(string $type, int $id, string $title, string $docType, ?\Carbon\Carbon $expiresAt = null): Document
    {
        $path = 'documents/'.uniqid().'.pdf';
        Storage::disk('local')->put($path, 'pdf');

        return Document::create([
            'landlord_id' => $this->landlord->id,
            'documentable_id' => $id,
            'documentable_type' => $type,
            'title' => $title,
            'file_name' => basename($path),
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'document_type' => $docType,
            'expires_at' => $expiresAt?->toDateString(),
            'uploaded_by' => $this->landlord->id,
        ]);
    }

    private function createReceipt(): Receipt
    {
        $payment = Payment::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 25000,
            'payment_method' => 'mpesa',
            'payment_date' => now()->toDateString(),
            'reference' => 'MPESA-DOC-1',
        ]);

        return Receipt::create([
            'payment_id' => $payment->id,
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'receipt_number' => 'RCT-2026-0001',
            'amount' => 25000,
            'payment_method' => 'mpesa',
            'reference' => 'MPESA-DOC-1',
            'is_partial' => false,
            'issued_at' => now(),
        ]);
    }
}
