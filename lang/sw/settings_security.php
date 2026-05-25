<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: account-settings security tab (Settings/partials/SecurityTab).
 * Page-specific namespace, distinct from profile_security. Mirror en/sw/ar.
 */
return [
    'heading' => 'Usalama na Faragha',
    'subheading' => 'Dhibiti usalama wa akaunti yako na mipangilio ya faragha ya data.',
    'links' => [
        'two_factor' => [
            'title' => 'Uthibitishaji wa Hatua Mbili',
            'description' => 'Ongeza safu ya ziada ya usalama kwenye akaunti yako kwa 2FA',
        ],
        'password' => [
            'title' => 'Nenosiri na Wasifu',
            'description' => 'Sasisha nenosiri lako na maelezo yako binafsi',
        ],
        'privacy' => [
            'title' => 'Faragha na Data',
            'description' => 'Hamisha au futa data yako binafsi (utii wa GDPR)',
        ],
    ],
    'status' => [
        'enabled' => 'Imewashwa',
        'disabled' => 'Imezimwa',
    ],
    'recommendations' => [
        'title' => 'Mapendekezo ya Usalama',
        'enable_2fa' => 'Washa uthibitishaji wa hatua mbili',
        'done' => 'Imekamilika',
        'strong_password' => 'Tumia nenosiri imara na la kipekee',
        'review_privacy' => 'Kagua mipangilio yako ya faragha ya data mara kwa mara',
    ],
    'account_status' => [
        'title' => 'Hali ya Usalama wa Akaunti',
        'two_factor' => 'Uthibitishaji wa Hatua Mbili',
        'two_factor_protected' => 'Akaunti yako imelindwa kwa 2FA',
        'two_factor_not_enabled' => 'Haijawashwa - tunapendekeza kuwasha 2FA',
        'data_privacy' => 'Faragha ya Data',
        'data_privacy_desc' => 'Ushughulikiaji wa data unaotii GDPR',
    ],
];
