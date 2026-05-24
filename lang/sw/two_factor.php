<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: two-factor authentication settings page. Mirror en/sw/ar.
 */
return [
    'title' => 'Uthibitishaji wa Hatua Mbili',
    'back_to_settings' => 'Rudi kwenye Mipangilio',
    'subtitle' => 'Ongeza usalama zaidi kwa akaunti yako kwa kutumia uthibitishaji wa hatua mbili.',
    'enabled' => [
        'heading' => 'Uthibitishaji wa hatua mbili umewashwa',
        'body' => 'Akaunti yako inalindwa kwa programu ya uthibitishaji.',
    ],
    'recovery' => [
        'heading' => 'Misimbo ya Urejeshaji',
        'remaining' => 'Una misimbo {count} ya urejeshaji iliyobaki.',
        'store_safely' => 'Hifadhi misimbo hii salama - inaweza kutumika ukipoteza ufikiaji wa programu yako ya uthibitishaji.',
        'view' => 'Tazama Misimbo ya Urejeshaji',
    ],
    'disable' => 'Zima Uthibitishaji wa Hatua Mbili',
    'required_notice' => 'Uthibitishaji wa hatua mbili unahitajika kwa akaunti yako.',
    'disabled' => [
        'heading' => 'Uthibitishaji wa hatua mbili haujawashwa',
        'body' => 'Uthibitishaji wa hatua mbili unapowashwa, utaombwa tokeni salama na ya nasibu wakati wa uthibitishaji.',
    ],
    'required_warning' => [
        'heading' => 'Hatua Inahitajika',
        'body' => 'Uthibitishaji wa hatua mbili unahitajika kwa akaunti yako. Tafadhali uwashe ili kuendelea kutumia programu.',
    ],
    'enable' => 'Washa Uthibitishaji wa Hatua Mbili',
    'how' => [
        'heading' => 'Jinsi inavyofanya kazi',
        'step1_title' => 'Sakinisha programu ya uthibitishaji',
        'step1_body' => 'Pakua Google Authenticator, Authy, au Microsoft Authenticator kwenye simu yako.',
        'step2_title' => 'Changanua msimbo wa QR',
        'step2_body' => 'Tumia programu yako ya uthibitishaji kuchanganua msimbo wa QR tunaotoa.',
        'step3_title' => 'Weka msimbo wa tarakimu 6',
        'step3_body' => 'Weka msimbo kutoka kwa programu yako ili kuthibitisha na kukamilisha usanidi.',
    ],
    'password_modal' => [
        'title' => 'Thibitisha Nenosiri',
        'body' => 'Tafadhali thibitisha nenosiri lako ili kuendelea.',
        'placeholder' => 'Nenosiri',
        'confirming' => 'Inathibitisha...',
        'confirm' => 'Thibitisha',
    ],
    'disable_modal' => [
        'title' => 'Zima Uthibitishaji wa Hatua Mbili',
        'body' => 'Weka nenosiri lako na msimbo kutoka kwa programu yako ya uthibitishaji ili kuzima uthibitishaji wa hatua mbili.',
        'password_label' => 'Nenosiri',
        'code_label' => 'Msimbo wa Uthibitishaji',
        'code_placeholder' => 'Weka msimbo wa tarakimu 6 au msimbo wa urejeshaji',
        'disabling' => 'Inazima...',
        'disable' => 'Zima',
    ],
    'cancel' => 'Ghairi',
];
