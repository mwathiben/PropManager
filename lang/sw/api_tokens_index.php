<?php

declare(strict_types=1);

return [
    'head_title' => 'Tokeni za API',
    'header' => 'Tokeni za API',

    'plaintext' => [
        'title' => 'Hifadhi tokeni hii sasa — hutaweza kuiona tena',
        'body' => 'PropManager haihifadhi tokeni kwa maandishi wazi. Inakili kwa hifadhi ya siri ya muunganisho wako kabla ya kufunga ukurasa huu.',
        'copy' => 'Nakili',
        'copied' => 'Imenakiliwa',
        'hide' => 'Nimeihifadhi — ficha tangazo hili',
    ],

    'create' => [
        'title' => 'Tengeneza tokeni mpya',
        'description' => 'Tengeneza tokeni ya ufikiaji ya kibinafsi kwa muunganisho. Chagua mipaka kwa uangalifu — uhuru mdogo unashinda urahisi.',
        'name_label' => 'Jina la tokeni',
        'name_placeholder' => 'mfano QuickBooks Sync',
        'scopes_label' => 'Mipaka',
        'submit' => 'Tengeneza tokeni',
    ],

    'scope_descriptions' => [
        'landlord_manage' => 'Soma + simamia mali yako (mali, majengo, vyumba, wapangaji, ankara, malipo, ripoti).',
        'integration_webhook' => 'Jiunge + simamia webhooks zinazotoka; soma ripoti za jumla.',
    ],

    'active' => [
        'title' => 'Tokeni zinazotumika',
        'description' => 'Futa tokeni yoyote ambayo chanzo chake hutambui. Tokeni zilizofutwa zinarejesha 401 ndani ya ombi moja — hakuna TTL ya cache.',
        'empty' => 'Hakuna tokeni zinazotumika bado.',
        'created' => 'Imeundwa:',
        'last_used' => 'Ilitumika mwisho:',
        'expires' => 'Itaisha:',
        'never' => 'Kamwe',
        'revoke' => 'Futa',
    ],

    'confirm_revoke' => 'Futa "{name}"? Maombi yanayotumia tokeni hii yataanza kurejesha 401 mara moja.',
];
