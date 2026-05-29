<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Models\User;
use App\Support\LocaleHelper;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-43 LOCALE-SWITCHER-1/2/3: sticky preference verification +
 * HasLocalePreference adoption + html[lang]+hreflang + a11y.
 */
class Phase43LocaleSwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_model_implements_has_locale_preference(): void
    {
        $reflection = new \ReflectionClass(User::class);
        $this->assertTrue(
            $reflection->implementsInterface(HasLocalePreference::class),
            'User must implement HasLocalePreference so Mail::to($user) auto-swaps locale at queue time.',
        );
    }

    public function test_user_preferred_locale_returns_users_locale_column(): void
    {
        $user = User::factory()->create(['locale' => 'sw']);
        $this->assertSame('sw', $user->preferredLocale());
    }

    public function test_locale_helper_dir_is_ltr_for_default_locales(): void
    {
        $helper = new LocaleHelper;
        $this->assertSame('ltr', $helper->dir('en'));
        $this->assertSame('ltr', $helper->dir('sw'));
    }

    public function test_locale_helper_dir_is_rtl_for_configured_rtl_locales(): void
    {
        $helper = new LocaleHelper;
        $this->assertSame('rtl', $helper->dir('ar'));
        $this->assertSame('rtl', $helper->dir('he'));
        $this->assertSame('rtl', $helper->dir('ar-KE'));
    }

    public function test_locale_helper_is_rtl_handles_compound_locale(): void
    {
        $helper = new LocaleHelper;
        $this->assertTrue($helper->isRtl('ar_KE'));
        $this->assertTrue($helper->isRtl('ar-EG'));
        $this->assertFalse($helper->isRtl('en-KE'));
    }

    public function test_locale_helper_alternates_emit_one_row_per_locale(): void
    {
        config(['app.available_locales' => ['en' => 'English', 'sw' => 'Kiswahili']]);
        $helper = new LocaleHelper;
        $rows = $helper->alternates('https://example.test/dashboard');

        $this->assertCount(2, $rows);
        $codes = array_column($rows, 'locale');
        $this->assertContains('en', $codes);
        $this->assertContains('sw', $codes);
        foreach ($rows as $row) {
            $this->assertStringContainsString('locale='.$row['locale'], $row['url']);
        }
    }

    public function test_locale_helper_alternates_append_to_existing_query(): void
    {
        config(['app.available_locales' => ['en' => 'English', 'sw' => 'Kiswahili']]);
        $helper = new LocaleHelper;
        $rows = $helper->alternates('https://example.test/dashboard?ref=banner');

        foreach ($rows as $row) {
            $this->assertStringContainsString('ref=banner&locale=', $row['url']);
        }
    }

    public function test_pinned_locale_change_persists_in_users_locale_column(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        config(['app.available_locales' => ['en' => 'English', 'sw' => 'Kiswahili']]);

        $response = $this->actingAs($user)->patch('/locale', ['locale' => 'sw']);
        $response->assertStatus(302);

        $user->refresh();
        $this->assertSame('sw', $user->locale);
    }
}
