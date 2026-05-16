<?php

declare(strict_types=1);

/*
 * Phase-29 [WORKFLOW-AUTOMATION] i18n keys for landlord-driven
 * workflow notifications. Swahili parity enforced by Phase24CiTest.
 */

return [
    'rent_reminder' => [
        'subject' => 'Ukumbusho wa kodi kwa ankara :number',
        'body_before' => 'Malipo yako ya kodi ya KES :amount kwenye ankara :number yanadaiwa baada ya siku :days.',
        'body_due_today' => 'Malipo yako ya kodi ya KES :amount kwenye ankara :number yanadaiwa leo.',
        'body_after' => 'Malipo yako ya kodi ya KES :amount kwenye ankara :number yamechelewa kwa siku :days. Tafadhali lipa haraka iwezekanavyo ili kuepuka adhabu.',
    ],
    'lease_renewal' => [
        'subject' => 'Kuongezewa kwa mkataba — siku :days zinabaki',
        'body' => 'Mkataba wako unakwisha tarehe :end_date — siku :days kutoka leo. Tafadhali angalia masharti mapya.',
        'proposed' => 'Masharti ya kuongezewa yamependekezwa. Mpangaji atataarifiwa.',
        'confirmed' => 'Kuongezewa kumethibitishwa. Tarehe ya mwisho na kodi zimesasishwa.',
        'tenant_accepted' => 'Umekubali masharti ya kuongezewa. Inasubiri uthibitisho wa mwenye nyumba.',
        'tenant_rejected' => 'Umekataa masharti ya kuongezewa. Mwenye nyumba atataarifiwa.',
    ],
];
