<?php

declare(strict_types=1);

/**
 * i18n migration: profile notifications (push-notification preferences) tab. Mirror en/sw/ar.
 */
return [
    'card' => [
        'title' => 'Arifa za Kusukuma',
        'subtitle' => 'Pokea masasisho ya papo hapo kwenye kifaa chako',
    ],
    'not_supported' => [
        'title' => 'Haitumiki',
        'body' => 'Arifa za kusukuma hazitumiki katika kivinjari hiki. Tafadhali tumia Chrome, Firefox, Edge, au Safari kwa arifa za kusukuma.',
    ],
    'blocked' => [
        'title' => 'Arifa Zimezuiwa',
        'body' => 'Arifa za kusukuma zimezuiwa katika kivinjari chako. Ili kuziwasha:',
        'step_lock' => 'Bofya aikoni ya kufuli kwenye upau wa anwani wa kivinjari chako',
        'step_find' => 'Tafuta "Notifications" katika mipangilio ya tovuti',
        'step_change' => 'Badilisha kutoka "Block" hadi "Allow"',
        'step_refresh' => 'Onyesha upya ukurasa huu',
    ],
    'status' => [
        'enabled' => 'Arifa zimewashwa',
        'disabled' => 'Arifa zimezimwa',
        'active' => 'Inatumika',
        'inactive' => 'Haitumiki',
    ],
    'alerts' => [
        'intro' => 'Washa arifa za kusukuma ili kupokea taarifa za papo hapo kuhusu:',
        'invoices' => 'Ankara mpya na uthibitisho wa malipo',
        'rent' => 'Vikumbusho vya kodi na arifa za tarehe ya mwisho',
        'messages' => 'Ujumbe muhimu kutoka kwa mwenye nyumba wako',
        'maintenance' => 'Masasisho ya matengenezo na matangazo',
    ],
    'button' => [
        'enabling' => 'Inawasha...',
        'enable' => 'Washa Arifa za Kusukuma',
        'send_test' => 'Tuma Jaribio',
        'disabling' => 'Inazima...',
        'disable' => 'Zima',
    ],
    'no_vapid' => 'Arifa za kusukuma bado hazijasanidiwa. Tafadhali wasiliana na meneja wako wa mali.',
    'devices' => [
        'title' => 'Vifaa Vingi',
        'body' => 'Unaweza kuwasha arifa za kusukuma kwenye vifaa vingi. Kila kifaa kinahitaji usanidi tofauti kwa kuingia na kuwasha arifa kwenye kifaa hicho.',
    ],
    'test' => [
        'title' => 'Arifa ya Jaribio',
        'body' => 'Arifa za kusukuma zinafanya kazi vizuri!',
    ],
    'script' => [
        'permission_denied' => 'Ruhusa ya arifa za kusukuma imekataliwa. Tafadhali iwashe katika mipangilio ya kivinjari chako.',
        'not_configured' => 'Arifa za kusukuma hazijasanidiwa. Tafadhali wasiliana na mwenye nyumba wako.',
        'enable_success' => 'Arifa za kusukuma zimewashwa kwa mafanikio!',
        'enable_failed' => 'Imeshindwa kuwasha arifa za kusukuma. Tafadhali jaribu tena.',
        'disable_success' => 'Arifa za kusukuma zimezimwa.',
        'disable_failed' => 'Imeshindwa kuzima arifa za kusukuma.',
    ],
];
