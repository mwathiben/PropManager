<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Services\I18n\TranslationSuggestionService;
use App\Support\LangBundleLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * Phase-43 LANG-AUDIT-1/2/3: lang:audit cron + lang:check CI gate
 * + lang:suggest CLI.
 */
class Phase43LangAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_lang_audit_command_exits_zero_against_real_bundles(): void
    {
        $this->artisan('lang:audit')->assertExitCode(0);
    }

    public function test_lang_audit_scheduled_daily_at_04_15_africa_nairobi(): void
    {
        $entry = collect(Schedule::events())
            ->first(fn ($e) => str_contains((string) $e->command, 'lang:audit'));
        $this->assertNotNull($entry, 'lang:audit must be scheduled');
        $this->assertSame('15 4 * * *', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    public function test_lang_check_pinned_only_passes_when_bundles_in_parity(): void
    {
        $this->artisan('lang:check', ['--pinned-only' => true])->assertExitCode(0);
    }

    public function test_lang_bundle_loader_flatten_returns_dotted_keys(): void
    {
        $loader = new LangBundleLoader();
        $flat = $loader->flatten([
            'common' => ['save' => 'Save', 'cancel' => 'Cancel'],
            'auth' => ['login' => ['title' => 'Log in']],
        ]);
        $this->assertContains('common.save', $flat);
        $this->assertContains('common.cancel', $flat);
        $this->assertContains('auth.login.title', $flat);
    }

    public function test_translation_suggestion_stub_returns_placeholder(): void
    {
        $service = new TranslationSuggestionService(driver: 'stub');
        $this->assertSame('[TODO:sw] Save', $service->suggest('Save', 'en', 'sw'));
    }

    public function test_translation_suggestion_falls_back_to_stub_when_api_key_missing(): void
    {
        $service = new TranslationSuggestionService(driver: 'google', googleApiKey: null);
        $this->assertStringContainsString('[TODO:sw]', $service->suggest('Hello', 'en', 'sw'));
    }

    public function test_lang_suggest_emits_php_array_for_missing_keys(): void
    {
        $this->artisan('lang:suggest', ['namespace' => 'common', '--target' => 'fr', '--driver' => 'stub'])
            ->assertExitCode(0);
    }

    public function test_lang_suggest_no_missing_keys_short_circuits(): void
    {
        $this->artisan('lang:suggest', ['namespace' => 'common', '--target' => 'sw'])
            ->expectsOutputToContain('No missing keys')
            ->assertExitCode(0);
    }

    public function test_pinned_namespaces_config_present(): void
    {
        $pinned = config('i18n.pinned_namespaces');
        $this->assertIsArray($pinned);
        $this->assertNotEmpty($pinned);
        foreach (['auth', 'common', 'validation', 'payments'] as $expected) {
            $this->assertContains($expected, $pinned);
        }
    }
}
