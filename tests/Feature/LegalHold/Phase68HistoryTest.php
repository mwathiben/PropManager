<?php

declare(strict_types=1);

namespace Tests\Feature\LegalHold;

use App\Models\Invoice;
use App\Models\User;
use App\Services\Legal\LegalHoldAuditExportService;
use App\Support\LegalHoldRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-68 HISTORY: per-subject hold/release timeline + CSV, strictly
 * gated to the owning landlord (cross-tenant subjects are rejected,
 * never returned).
 */
class Phase68HistoryTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $otherLandlord;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');

        $this->landlord = $this->createLandlordWithFullSetup()['landlord'];
        $this->otherLandlord = $this->createLandlordWithFullSetup()['landlord'];
    }

    private function heldThenReleasedInvoice(): Invoice
    {
        $invoice = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);

        // One full lifecycle (held -> released) + one active hold = 2 rows.
        LegalHoldRegistry::hold($invoice, $this->landlord, 'first preservation order CV/2026/0001');
        LegalHoldRegistry::release($invoice, $this->landlord);
        LegalHoldRegistry::hold($invoice, $this->landlord, 'second preservation order CV/2026/0002');

        return $invoice;
    }

    public function test_owner_sees_full_timeline(): void
    {
        $invoice = $this->heldThenReleasedInvoice();

        $response = $this->actingAs($this->landlord)->get(route('legal-holds.history', [
            'subject_type' => Invoice::class,
            'subject_id' => $invoice->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('LegalHolds/History')
            ->where('subject.short_type', 'Invoice')
            ->where('subject.id', $invoice->id)
            ->has('holds', 2)
            ->where('holds.0.is_active', true)
            ->where('holds.1.is_active', false)
        );
    }

    public function test_cross_tenant_subject_is_rejected(): void
    {
        $theirInvoice = Invoice::factory()->create(['landlord_id' => $this->otherLandlord->id]);
        LegalHoldRegistry::hold($theirInvoice, $this->otherLandlord, 'their preservation order');

        $this->actingAs($this->landlord)->get(route('legal-holds.history', [
            'subject_type' => Invoice::class,
            'subject_id' => $theirInvoice->id,
        ]))->assertForbidden();
    }

    public function test_unsupported_subject_type_is_rejected(): void
    {
        $this->actingAs($this->landlord)->getJson(route('legal-holds.history', [
            'subject_type' => User::class,
            'subject_id' => 1,
        ]))->assertStatus(422);
    }

    public function test_export_redirects_for_owner_and_writes_csv(): void
    {
        $invoice = $this->heldThenReleasedInvoice();

        $this->actingAs($this->landlord)->get(route('legal-holds.history.export', [
            'subject_type' => Invoice::class,
            'subject_id' => $invoice->id,
        ]))->assertRedirect();

        // The service writes a chain-of-custody CSV with both lifecycle rows.
        $path = app(LegalHoldAuditExportService::class)
            ->exportSubjectHistoryToCsv($this->landlord, Invoice::class, $invoice->id);

        $csv = Storage::disk('local')->get($path);
        $this->assertStringContainsString('first preservation order CV/2026/0001', $csv);
        $this->assertStringContainsString('second preservation order CV/2026/0002', $csv);
        $this->assertStringContainsString('released', $csv);
        $this->assertStringContainsString('active', $csv);
    }

    public function test_export_is_cross_tenant_gated(): void
    {
        $theirInvoice = Invoice::factory()->create(['landlord_id' => $this->otherLandlord->id]);
        LegalHoldRegistry::hold($theirInvoice, $this->otherLandlord, 'their preservation order');

        $this->actingAs($this->landlord)->get(route('legal-holds.history.export', [
            'subject_type' => Invoice::class,
            'subject_id' => $theirInvoice->id,
        ]))->assertForbidden();
    }
}
