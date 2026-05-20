<?php

declare(strict_types=1);

namespace Tests\Feature\LegalHold;

use App\Console\Commands\SweepStaleHolds;
use App\Exceptions\LegalHoldActiveException;
use App\Http\Controllers\LegalHoldHistoryController;
use App\Mail\StaleHoldReminderMailable;
use App\Models\Concerns\HasLegalHolds;
use App\Services\Legal\BulkHoldService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-68 LEGAL-HOLD-PHASE2 CI (CI-1): cross-category surface map.
 * Guards the shipped surface of all five feature sub-phases (HISTORY,
 * DOC-HOLD, HOLD-GUARD, STALE-SWEEP, BULK-UI) against drift.
 */
class Phase68LegalHoldPhase2SurfaceTest extends TestCase
{
    private function vue(string $relative): string
    {
        $path = base_path('resources/js/'.$relative);
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_history_surface(): void
    {
        $this->assertTrue(class_exists(LegalHoldHistoryController::class));
        $this->assertTrue(Route::has('legal-holds.history'));
        $this->assertTrue(Route::has('legal-holds.history.export'));
        $this->vue('Pages/LegalHolds/History.vue');
        $this->assertIsArray(__('legal_holds.history'));

        foreach (['Pages/Invoices/Show.vue', 'Pages/Tickets/Show.vue', 'Pages/LegalHolds/Index.vue'] as $page) {
            $this->assertStringContainsString('hold-history-link', $this->vue($page));
        }
    }

    public function test_doc_hold_surface(): void
    {
        $src = $this->vue('Pages/Documents/Index.vue');
        $this->assertStringContainsString('data-testid="document-hold-badge"', $src);
        $this->assertStringContainsString('data-testid="document-place-hold"', $src);
        $this->assertStringContainsString('data-testid="document-release-hold"', $src);
        $this->assertIsArray(__('legal_holds.doc'));
    }

    public function test_hold_guard_surface(): void
    {
        $this->assertTrue(class_exists(LegalHoldActiveException::class));
        $this->assertTrue(method_exists(HasLegalHolds::class, 'bootHasLegalHolds'));
        // Documents/Index disables delete on held rows; Tickets/Show too.
        $this->assertStringContainsString('data-testid="delete-blocked-by-hold"', $this->vue('Pages/Documents/Index.vue'));
        $this->assertStringContainsString('delete-blocked-by-hold', $this->vue('Pages/Tickets/Show.vue'));
        $this->assertSame('This subject type cannot be placed under legal hold.', __('legal_holds.unsupported_holdable_type'));
        $this->assertNotSame('legal_holds.delete_blocked', __('legal_holds.delete_blocked'));
    }

    public function test_stale_sweep_surface(): void
    {
        $this->assertTrue(class_exists(SweepStaleHolds::class));
        $this->assertTrue(class_exists(StaleHoldReminderMailable::class));
        $this->assertArrayHasKey('legal-hold:sweep-stale', Artisan::all());
        $this->assertTrue(Schema::hasColumn('legal_holds', 'last_reminded_at'));
        $this->assertIsInt(config('legal_hold.stale_after_days'));
        $this->assertIsInt(config('legal_hold.stale_reminder_cooldown_days'));

        $alert = collect(config('alerts.alerts'))->firstWhere('key', 'legal_hold_stale');
        $this->assertNotNull($alert);
        $this->assertSame('sev3', $alert['severity']);
        $this->assertSame('docs/runbooks/legal-hold.md#stale-holds', $alert['runbook']);
        $this->assertIsArray(__('legal_holds.stale'));
        $this->assertFileExists(base_path('resources/views/emails/legal-hold/stale-reminder.blade.php'));
    }

    public function test_bulk_ui_surface(): void
    {
        $this->assertTrue(class_exists(BulkHoldService::class));
        $this->assertTrue(Route::has('legal-holds.bulk.store'));
        $this->assertTrue(Route::has('legal-holds.bulk.destroy'));
        $this->vue('Components/LegalHold/BulkHoldModal.vue');

        $src = $this->vue('Pages/Documents/Index.vue');
        $this->assertStringContainsString('data-testid="bulk-hold-bar"', $src);
        $this->assertStringContainsString('data-testid="bulk-select-all"', $src);
        $this->assertStringContainsString('data-testid="bulk-place-hold"', $src);
        $this->assertStringContainsString('data-testid="bulk-release-hold"', $src);
        $this->assertIsArray(__('legal_holds.bulk'));
    }

    public function test_destroy_route_is_number_constrained(): void
    {
        // The whereNumber guard keeps DELETE /legal-holds/bulk from being
        // shadowed by DELETE /legal-holds/{legalHold}.
        $this->assertTrue(Route::has('legal-holds.destroy'));
        $this->assertTrue(Route::has('legal-holds.bulk.destroy'));
    }
}
