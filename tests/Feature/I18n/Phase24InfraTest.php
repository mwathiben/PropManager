<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * Phase-24 I18N-INFRA-1/2/3 watchdogs — the localization spine:
 * users.locale persistence, the supported-locale source of truth,
 * the SetLocale resolver middleware, and the Inertia locale share.
 */
class Phase24InfraTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_locale_column(): void
    {
        $user = User::factory()->create(['locale' => 'sw']);

        $this->assertSame('sw', $user->fresh()->locale);
        $this->assertContains('locale', (new User)->getFillable());
    }

    public function test_supported_locales_config_is_the_source_of_truth(): void
    {
        $available = config('app.available_locales');

        $this->assertIsArray($available);
        $this->assertArrayHasKey('en', $available, 'I18N-INFRA-1: en must be a supported locale.');
        $this->assertArrayHasKey('sw', $available, 'I18N-INFRA-1: sw must be a supported locale.');
    }

    public function test_effective_locale_falls_back_for_null_or_unsupported(): void
    {
        $default = config('app.fallback_locale');

        $this->assertSame('sw', User::factory()->create(['locale' => 'sw'])->effectiveLocale());
        $this->assertSame($default, User::factory()->create(['locale' => null])->effectiveLocale());
        $this->assertSame($default, User::factory()->create(['locale' => 'zz'])->effectiveLocale());
    }

    public function test_setlocale_middleware_is_registered(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringContainsString(
            'App\Http\Middleware\SetLocale::class',
            $bootstrap,
            'I18N-INFRA-2: SetLocale must be registered in the web middleware group.',
        );
        // It must run before HandleInertiaRequests so the share sees the locale.
        $this->assertLessThan(
            strpos($bootstrap, 'HandleInertiaRequests::class'),
            strpos($bootstrap, 'SetLocale::class'),
            'I18N-INFRA-2: SetLocale must be registered BEFORE HandleInertiaRequests.',
        );
    }

    public function test_locale_resolution_priority_order(): void
    {
        // Capture the base locale up front — App::setLocale() (called
        // by the middleware) mutates config('app.locale') for the rest
        // of the process, so it cannot be read mid-test as "the default".
        $default = config('app.fallback_locale');

        $middleware = new SetLocale;
        $resolve = function (Request $request) use ($middleware): string {
            $captured = 'unset';
            $middleware->handle($request, function () use (&$captured) {
                $captured = App::getLocale();

                return response('');
            });

            return $captured;
        };

        // 1. authenticated user preference wins.
        $swUser = User::factory()->create(['locale' => 'sw']);
        $req = Request::create('/');
        $req->setUserResolver(fn () => $swUser);
        $this->assertSame('sw', $resolve($req), 'user locale must be honoured first');

        // 2. user preference beats a conflicting session value.
        $enUser = User::factory()->create(['locale' => 'en']);
        $req = Request::create('/');
        $req->setUserResolver(fn () => $enUser);
        $req->setLaravelSession($this->app['session']->driver());
        $req->session()->put('locale', 'sw');
        $this->assertSame('en', $resolve($req), 'user locale must beat session');

        // 3. session beats Accept-Language for a guest.
        $req = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'en']);
        $req->setLaravelSession($this->app['session']->driver());
        $req->session()->put('locale', 'sw');
        $this->assertSame('sw', $resolve($req), 'session must beat Accept-Language');

        // 4. Accept-Language is used when there is no user + no session pref.
        $req = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'sw']);
        $req->setLaravelSession($this->app['session']->driver());
        $this->assertSame('sw', $resolve($req), 'Accept-Language must be used as the fallback signal');

        // 5. an unsupported value never escapes — falls to the app default.
        $bogusUser = User::factory()->create(['locale' => 'zz']);
        $req = Request::create('/');
        $req->setUserResolver(fn () => $bogusUser);
        $this->assertSame($default, $resolve($req), 'unsupported locale must fall back to default');
    }

    public function test_inertia_shares_locale_and_bundle(): void
    {
        $middleware = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));

        $this->assertStringContainsString("'locale'", $middleware, 'I18N-INFRA-3: share() must expose locale.');
        $this->assertStringContainsString("'availableLocales'", $middleware, 'I18N-INFRA-3: share() must expose availableLocales.');
        $this->assertStringContainsString("'i18n'", $middleware, 'I18N-INFRA-3: share() must expose the i18n bundle.');
        $this->assertStringContainsString('getI18nBundle', $middleware, 'I18N-INFRA-3: the i18n bundle must be sourced from getI18nBundle().');
    }
}
