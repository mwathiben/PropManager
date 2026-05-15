<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Phase-24 I18N-INFRA-4: the locale-switch endpoint. The selector UI
 * (LocaleSelector.vue) POSTs here; the SetLocale middleware then
 * picks up the new value on the very next request and the whole UI
 * re-renders translated.
 */
class LocaleController extends Controller
{
    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = $request->validated('locale');

        // Always set the session so the choice survives the request
        // (and covers the guest case); persist to the user row too
        // when authenticated so it follows them across devices.
        $request->session()->put('locale', $locale);

        if ($user = $request->user()) {
            $user->update(['locale' => $locale]);
        }

        return back();
    }
}
