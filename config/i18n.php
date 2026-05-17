<?php

declare(strict_types=1);

/**
 * Phase-43 [I18N-DEPTH] config. Centralises locale-related
 * settings so commands + tests + middleware all read from a
 * single source of truth.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Pinned namespaces
    |--------------------------------------------------------------------------
    | Translation namespaces that MUST stay parity-complete across
    | every supported locale. Missing keys here block CI (lang:check)
    | and trigger sev3 alerts (lang:audit).
    |
    | Top of the user-visible blast radius lives here:
    | - auth: login / register / password reset / 2FA flows
    | - common: shared UI verbs (save, cancel, close, etc.)
    | - validation: form error messages
    | - payments: Phase-42 surface (VAT, plan-sync, cart, etc.)
    */
    'pinned_namespaces' => [
        'auth',
        'common',
        'validation',
        'payments',
    ],

    /*
    |--------------------------------------------------------------------------
    | RTL locales
    |--------------------------------------------------------------------------
    | Locales rendered right-to-left. `App\Support\LocaleHelper::isRtl`
    | reads this list. `<html dir="rtl">` flips automatically.
    | Phase-43 ships the scaffolding; Phase-44 [I18N-RTL] is the
    | mass-migration cycle for component classes
    | (ml- -> ms-, mr- -> me-, pl- -> ps-, pr- -> pe-, etc).
    */
    'rtl_locales' => [
        'ar',
        'he',
        'fa',
        'ur',
    ],

    /*
    |--------------------------------------------------------------------------
    | Translation suggestion driver
    |--------------------------------------------------------------------------
    | `lang:suggest` interfaces with this. 'stub' (default) returns
    | [TODO:locale] placeholders for hand-translation. 'google' /
    | 'deepl' require API credentials in env.
    */
    'suggestion_driver' => env('I18N_SUGGESTION_DRIVER', 'stub'),
    'google_api_key' => env('I18N_GOOGLE_TRANSLATE_API_KEY'),
    'deepl_api_key' => env('I18N_DEEPL_API_KEY'),
];
