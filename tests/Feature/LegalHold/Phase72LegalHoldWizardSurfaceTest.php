<?php

declare(strict_types=1);

namespace Tests\Feature\LegalHold;

use App\Models\LegalMatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-72 CI: cross-category surface map for LEGAL-HOLD-WIZARD — schema,
 * classes, routes, Vue files + tokens, config, lang — plus the command-center
 * render. Behaviour is covered by Phase72{Matter,Subject,Wizard,HoldSettings,
 * AutoHold}Test.
 */
class Phase72LegalHoldWizardSurfaceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private function vue(string $relative): string
    {
        $path = base_path('resources/js/'.$relative);
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_schema_present(): void
    {
        $this->assertTrue(Schema::hasTable('legal_matters'));
        $this->assertTrue(Schema::hasTable('landlord_hold_settings'));
        $this->assertTrue(Schema::hasColumn('legal_holds', 'legal_matter_id'));
    }

    public function test_classes_exist(): void
    {
        foreach ([
            \App\Models\LegalMatter::class,
            \App\Models\LandlordHoldSettings::class,
            \App\Services\Legal\LegalMatterService::class,
            \App\Services\Legal\TenantSubjectResolver::class,
            \App\Services\Legal\HoldSettingsResolver::class,
            \App\Http\Controllers\LegalMatterController::class,
            \App\Http\Controllers\LegalHoldSubjectController::class,
            \App\Http\Controllers\LegalHoldWizardController::class,
            \App\Http\Controllers\LegalHoldSettingsController::class,
            \App\Listeners\HoldOnLeaseTermination::class,
            \App\Policies\LegalMatterPolicy::class,
        ] as $class) {
            $this->assertTrue(class_exists($class), "{$class} must exist");
        }
    }

    public function test_routes_registered(): void
    {
        foreach ([
            'legal-holds.list',
            'legal-holds.wizard',
            'legal-holds.wizard.store',
            'legal-holds.subjects.suggest',
            'legal-holds.settings',
            'legal-holds.settings.update',
            'legal-matters.index',
            'legal-matters.show',
            'legal-matters.release',
            'legal-matters.close',
            'legal-matters.reopen',
            'legal-matters.audit-export',
        ] as $name) {
            $this->assertTrue(Route::has($name), "route {$name} must be registered");
        }
    }

    public function test_vue_surface_tokens(): void
    {
        $this->assertStringContainsString('data-testid="hold-wizard"', $this->vue('Pages/LegalHolds/Wizard.vue'));
        $this->assertStringContainsString('data-testid="hold-command-center"', $this->vue('Pages/LegalHolds/Home.vue'));
        $this->assertStringContainsString('data-testid="hold-settings"', $this->vue('Pages/LegalHolds/Settings.vue'));
        $this->assertStringContainsString('data-testid="subject-picker"', $this->vue('Components/LegalHold/SubjectPicker.vue'));
        $this->assertStringContainsString('data-testid="legal-hold-help"', $this->vue('Components/LegalHold/LegalHoldHelpPanel.vue'));
        $this->vue('Pages/LegalHolds/Matters/Index.vue');
        $this->vue('Pages/LegalHolds/Matters/Show.vue');
    }

    public function test_config_situations(): void
    {
        $this->assertIsArray(config('legal_hold.situations'));
        $this->assertArrayHasKey('litigation', config('legal_hold.situations'));
    }

    public function test_lang_parity(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            foreach (['wizard', 'settings', 'help', 'matters', 'home', 'auto_hold'] as $block) {
                $this->assertIsArray(__("legal_holds.{$block}", [], $locale), "legal_holds.{$block} missing for {$locale}");
            }
        }
    }

    public function test_command_center_renders_for_the_landlord(): void
    {
        $landlord = $this->createLandlordWithFullSetup()['landlord'];
        $this->actingAs($landlord);
        LegalMatter::create(['title' => 'Open case', 'status' => LegalMatter::STATUS_OPEN]);

        $this->actingAs($landlord)
            ->get(route('legal-holds.index'))
            ->assertInertia(fn ($page) => $page
                ->component('LegalHolds/Home')
                ->where('summary.active_matters', 1)
                ->has('summary.active_holds')
                ->has('matters'),
            );
    }
}
