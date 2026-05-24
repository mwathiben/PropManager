<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: integrations settings tab. Mirror en/sw/ar.
 * Third-party/brand names (OCR provider names, Azure, API keys) stay literal.
 */
return [
    'title' => 'Muunganisho',
    'subtitle' => 'Unganisha huduma za nje ili kuboresha utendaji wa PropManager.',
    'ocr' => [
        'heading' => 'OCR (Utambuzi wa Maandishi kwa Macho)',
        'description' => 'Soma kiotomatiki thamani za mita ya maji kutoka kwa picha',
        'enable' => 'Wezesha OCR',
        'select_provider' => 'Chagua Mtoa Huduma wa OCR',
        'recommended' => 'Inapendekezwa',
        'setup_guide' => 'Mwongozo wa Usanidi',
        'requires' => 'Inahitaji: {requirements}',
        'none' => [
            'name' => 'Hakuna OCR (Mwongozo Pekee)',
            'description' => 'Lemaza ugunduzi wa kusoma kiotomatiki. Walezi wataingiza thamani kwa mkono pekee.',
        ],
        'api_key_required_label' => 'Ufunguo wa API Unahitajika:',
        'api_key_required_body' => 'Unahitaji kujisajili kwa {provider} na kupata ufunguo wa API.',
        'get_api_key' => 'Pata Ufunguo wa API',
        'api_key_configured' => 'Ufunguo wa API Umewekwa',
        'update_key' => 'Sasisha Ufunguo',
        'delete' => 'Futa',
        'api_key_label' => 'Ufunguo wa API',
        'api_key_placeholder' => 'Ingiza ufunguo wako wa API',
        'api_key_hint' => 'Ufunguo wako wa API utasimbwa na kuhifadhiwa kwa usalama',
        'endpoint_label' => 'URL ya Mwisho',
        'auto_verify' => 'Thibitisha usomaji kiotomatiki',
        'auto_verify_hint' => 'Thibitisha kiotomatiki ikiwa usomaji wa OCR unalingana na uingizaji wa mkono ndani ya uvumilivu',
        'saving' => 'Inahifadhi...',
        'save' => 'Hifadhi Mipangilio ya OCR',
        'test_connection' => 'Jaribu Muunganisho',
        'delete_confirm' => 'Una uhakika unataka kufuta ufunguo huu wa API? Utahitaji kuuingiza tena ili kutumia OCR.',
    ],
    'coming_soon' => [
        'title' => 'Muunganisho zaidi unakuja hivi karibuni',
        'body' => 'Lango la SMS, programu za uhasibu, na zaidi',
    ],
];
