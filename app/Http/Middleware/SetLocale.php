<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase-24 I18N-INFRA-2: resolve and apply the request locale.
 *
 * Without this middleware App::getLocale() is permanently the config
 * default — __()/trans()/validation always return English regardless
 * of any preference. This is the piece that actually switches the
 * language.
 *
 * Resolution priority:
 *   1. the authenticated user's `users.locale` (if a supported value)
 *   2. session('locale') — a guest's choice, or set by the
 *      locale-switch endpoint before the user row is updated
 *   3. the request's Accept-Language header (best supported match)
 *   4. config('app.locale') — the app default
 *
 * The resolved value is always one of config('app.available_locales')
 * — an unknown value can never reach App::setLocale(). It is a
 * deliberate no-op when the resolved locale is the default.
 *
 * Registered in the web middleware group (bootstrap/app.php) AFTER
 * StartSession so $request->user() + the session are available, and
 * BEFORE HandleInertiaRequests so the Inertia share() sees the
 * resolved locale.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('app.available_locales', ['en' => 'English']));
        $locale = $this->resolve($request, $supported);

        App::setLocale($locale);
        // Carbon has its own locale, separate from the app's — set it
        // here so server-rendered dates (PDFs, emails) localise too
        // (I18N-FORMAT-3).
        Carbon::setLocale($locale);

        return $next($request);
    }

    /**
     * @param  array<int, string>  $supported
     */
    private function resolve(Request $request, array $supported): string
    {
        $user = $request->user();
        if ($user && in_array($user->locale, $supported, true)) {
            return $user->locale;
        }

        if ($request->hasSession()) {
            $session = $request->session()->get('locale');
            if (in_array($session, $supported, true)) {
                return $session;
            }
        }

        // getPreferredLanguage returns the best Accept-Language match,
        // or the first supported locale if there is no match — either
        // way it is always a supported value.
        $preferred = $request->getPreferredLanguage($supported);
        if (in_array($preferred, $supported, true)) {
            return $preferred;
        }

        // The ultimate fallback. fallback_locale (not locale) is used
        // deliberately: App::setLocale() mutates config('app.locale')
        // for the rest of the process, so reading it here would be
        // self-referential under a long-running worker — fallback_locale
        // is the stable "base language" anchor.
        return config('app.fallback_locale');
    }
}
