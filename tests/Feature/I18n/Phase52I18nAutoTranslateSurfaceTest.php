<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Console\Commands\LangReview;
use App\Services\I18n\Contracts\TranslationDriverInterface;
use App\Services\I18n\CostAwareDriver;
use App\Services\I18n\Drivers\DeepLTranslationDriver;
use App\Services\I18n\Drivers\GoogleTranslationDriver;
use App\Services\I18n\Drivers\StubTranslationDriver;
use App\Services\I18n\LangFileWriter;
use App\Services\I18n\TranslationCostTracker;
use App\Services\I18n\TranslationDriverFactory;
use App\Services\I18n\TranslationSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Phase-52 CI-1: consolidated I18N-AUTO-TRANSLATE surface watchdog.
 *
 * Http::fake() mocks Google + DeepL responses — no real API calls.
 * Cache assertions verify the cost-tracker increments correctly.
 * LangFileWriter tested against a tmp file with the .bak invariant.
 */
class Phase52I18nAutoTranslateSurfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // -- DRIVER-INTERFACE --------------------------------------------------

    public function test_translation_driver_interface_exists(): void
    {
        $this->assertTrue(interface_exists(TranslationDriverInterface::class));
    }

    public function test_stub_driver_implements_interface_with_marker(): void
    {
        $stub = new StubTranslationDriver();
        $this->assertInstanceOf(TranslationDriverInterface::class, $stub);
        $this->assertSame('[TODO:ar] Hello', $stub->translate('Hello', 'en', 'ar'));
        $this->assertSame(0.0, $stub->costEstimateUsd(100));
        $this->assertSame('stub', $stub->name());
    }

    public function test_factory_make_returns_correct_driver_per_key(): void
    {
        $factory = new TranslationDriverFactory();
        $this->assertInstanceOf(StubTranslationDriver::class, $factory->make('stub'));

        config(['i18n.google_api_key' => 'fake-google-key']);
        $g = $factory->make('google');
        $this->assertInstanceOf(GoogleTranslationDriver::class, $g);

        config(['i18n.deepl_api_key' => 'fake-deepl-key']);
        $d = $factory->make('deepl');
        $this->assertInstanceOf(DeepLTranslationDriver::class, $d);
    }

    public function test_factory_make_throws_on_unknown_driver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new TranslationDriverFactory())->make('quaerendo');
    }

    public function test_factory_wraps_non_stub_drivers_with_cost_aware_decorator(): void
    {
        config(['i18n.google_api_key' => 'fake-key']);
        $factory = new TranslationDriverFactory(new TranslationCostTracker());
        $driver = $factory->make('google');
        $this->assertInstanceOf(CostAwareDriver::class, $driver);
    }

    public function test_service_delegates_to_injected_driver(): void
    {
        $fake = new class implements TranslationDriverInterface {
            public int $calls = 0;
            public function translate(string $text, string $from, string $to): string {
                $this->calls++;
                return "fake({$to}):{$text}";
            }
            public function costEstimateUsd(int $c): float { return 0.0; }
            public function name(): string { return 'fake'; }
        };
        $svc = new TranslationSuggestionService($fake);
        $this->assertSame('fake(sw):Hello', $svc->suggest('Hello', 'en', 'sw'));
        $this->assertSame(1, $fake->calls);
    }

    // -- GOOGLE-DRIVER -----------------------------------------------------

    public function test_google_driver_parses_translated_text_from_response(): void
    {
        Http::fake([
            'translation.googleapis.com/*' => Http::response([
                'data' => ['translations' => [['translatedText' => 'Habari']]],
            ]),
        ]);
        $driver = new GoogleTranslationDriver('fake-key');
        $this->assertSame('Habari', $driver->translate('Hello', 'en', 'sw'));
        $this->assertSame(20e-6 * 5, $driver->costEstimateUsd(5));
    }

    public function test_google_driver_falls_back_to_stub_on_4xx(): void
    {
        Http::fake([
            'translation.googleapis.com/*' => Http::response(['error' => 'bad'], 400),
        ]);
        $driver = new GoogleTranslationDriver('fake-key');
        $this->assertStringStartsWith('[TODO:sw]', $driver->translate('Hello', 'en', 'sw'));
    }

    public function test_google_driver_falls_back_when_api_key_missing(): void
    {
        $driver = new GoogleTranslationDriver('');
        $this->assertStringStartsWith('[TODO:sw]', $driver->translate('Hello', 'en', 'sw'));
    }

    public function test_google_driver_falls_back_when_rate_limit_exceeded(): void
    {
        Http::fake([
            'translation.googleapis.com/*' => Http::response([
                'data' => ['translations' => [['translatedText' => 'X']]],
            ]),
        ]);
        $driver = new GoogleTranslationDriver('fake-key', maxPerMinute: 2);
        $this->assertSame('X', $driver->translate('a', 'en', 'sw'));
        $this->assertSame('X', $driver->translate('a', 'en', 'sw'));
        // Third call exceeds the per-minute cap.
        $this->assertStringStartsWith('[TODO:sw]', $driver->translate('a', 'en', 'sw'));
    }

    // -- DEEPL-DRIVER ------------------------------------------------------

    public function test_deepl_driver_uses_pro_endpoint_when_key_has_no_fx_suffix(): void
    {
        $driver = new DeepLTranslationDriver('pro-key');
        $this->assertSame('https://api.deepl.com', $driver->endpointBase());
    }

    public function test_deepl_driver_uses_free_endpoint_when_key_ends_in_fx(): void
    {
        $driver = new DeepLTranslationDriver('free-key:fx');
        $this->assertSame('https://api-free.deepl.com', $driver->endpointBase());
    }

    public function test_deepl_driver_parses_translation_from_response(): void
    {
        Http::fake([
            'api.deepl.com/*' => Http::response([
                'translations' => [['text' => 'Hallo']],
            ]),
        ]);
        $driver = new DeepLTranslationDriver('fake-pro-key');
        $this->assertSame('Hallo', $driver->translate('Hello', 'en', 'de'));
    }

    public function test_deepl_driver_falls_back_on_5xx(): void
    {
        Http::fake([
            'api.deepl.com/*' => Http::response('', 503),
        ]);
        $driver = new DeepLTranslationDriver('fake-pro-key');
        $this->assertStringStartsWith('[TODO:de]', $driver->translate('Hello', 'en', 'de'));
    }

    // -- APPLY-WORKFLOW ----------------------------------------------------

    public function test_lang_file_writer_deep_merges_without_overwriting(): void
    {
        $writer = new LangFileWriter();
        $existing = ['a' => 'keep me', 'nested' => ['x' => 'also keep']];
        $suggest = ['a' => 'NEW', 'b' => 'add me', 'nested' => ['x' => 'CHANGE', 'y' => 'add me too']];
        $merged = $writer->merge($existing, $suggest);
        $this->assertSame('keep me', $merged['a']);
        $this->assertSame('also keep', $merged['nested']['x']);
        $this->assertSame('add me', $merged['b']);
        $this->assertSame('add me too', $merged['nested']['y']);
    }

    public function test_lang_file_writer_creates_backup_before_overwrite(): void
    {
        $writer = new LangFileWriter();
        $path = storage_path('framework/testing/phase52-langwriter-test.php');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, "<?php\nreturn ['original' => 'value'];\n");

        $writer->write($path, ['original' => 'value', 'new' => 'fresh']);

        $backups = glob($path . '.bak.*') ?: [];
        $this->assertNotEmpty($backups, 'Backup file should be created');
        foreach ($backups as $backup) {
            File::delete($backup);
        }
        File::delete($path);
    }

    public function test_lang_review_command_registered(): void
    {
        $this->assertTrue(class_exists(LangReview::class));
        $registered = collect($this->app[\Illuminate\Contracts\Console\Kernel::class]->all())
            ->keys()
            ->toArray();
        $this->assertContains('lang:review', $registered);
    }

    public function test_lang_suggest_command_accepts_apply_flag(): void
    {
        $signature = (new \ReflectionClass(\App\Console\Commands\LangSuggest::class))
            ->getProperty('signature')
            ->getDefaultValue();
        $this->assertStringContainsString('--apply', $signature);
    }

    // -- COST-GUARD --------------------------------------------------------

    public function test_cost_tracker_record_increments_daily_total(): void
    {
        $tracker = new TranslationCostTracker();
        $this->assertSame(0.0, $tracker->currentDailySpend());

        $tracker->record('google', 'sw', 100, 0.002);

        $this->assertEqualsWithDelta(0.002, $tracker->currentDailySpend(), 1e-9);
    }

    public function test_cost_tracker_can_spend_returns_false_past_budget(): void
    {
        config(['i18n.daily_budget_usd' => 1.0]);
        $tracker = new TranslationCostTracker();
        $tracker->record('google', 'sw', 100, 0.95);
        $this->assertTrue($tracker->canSpend(0.04));
        $this->assertFalse($tracker->canSpend(0.10));
    }

    public function test_cost_aware_driver_routes_to_fallback_past_budget(): void
    {
        config(['i18n.daily_budget_usd' => 0.0]);
        $tracker = new TranslationCostTracker();
        $inner = new GoogleTranslationDriver('fake-key');
        $driver = new CostAwareDriver($inner, $tracker, new StubTranslationDriver());
        $result = $driver->translate('Hello', 'en', 'sw');
        $this->assertStringStartsWith('[TODO:sw]', $result);
    }

    public function test_alert_thresholds_carries_translation_spend_row(): void
    {
        $md = file_get_contents(base_path('docs/runbooks/alert-thresholds.md'));
        $this->assertStringContainsString('i18n_translation_spend_usd_24h', $md);
    }

    public function test_i18n_runbook_has_auto_translate_section(): void
    {
        $md = file_get_contents(base_path('docs/runbooks/i18n.md'));
        $this->assertStringContainsString('Auto-translate', $md);
    }
}
