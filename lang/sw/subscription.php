<?php

declare(strict_types=1);

/**
 * i18n migration: subscription / billing management page. Mirror en/sw/ar.
 */
return [
    'title' => 'Usajili',
    'subtitle' => 'Dhibiti mpango wako na malipo',
    'view_plans' => 'Tazama Mipango',
    'free' => 'Bila Malipo',
    'plan_name' => 'Mpango wa {name}',
    'per_cycle' => 'kwa {cycle}',
    'your_plan' => 'Mpango Wako',
    'gateway_warning' => [
        'title' => 'Mfumo wa Malipo Haujasanidiwa',
        'body' => 'Lango la malipo bado halijasanidiwa. Unaweza kutazama mipango na matumizi yako ya sasa, lakini uboreshaji wa mipango ya kulipia haupatikani kwa muda. Tafadhali wasiliana na usaidizi ikiwa unahitaji msaada.',
    ],
    'cycle' => [
        'month' => 'mwezi',
    ],
    'details' => [
        'billing_cycle' => 'Mzunguko wa Malipo',
        'ends_on' => 'Inaisha Tarehe',
        'next_billing' => 'Tarehe ya Malipo Yanayofuata',
        'trial_ends' => 'Jaribio Linaisha',
        'na' => 'Haipo',
    ],
    'actions' => [
        'resume' => 'Endeleza Usajili',
        'cancel' => 'Ghairi Usajili',
        'upgrade' => 'Boresha Mpango',
        'change' => 'Badilisha Mpango',
    ],
    'usage' => [
        'heading' => 'Matumizi',
        'subtitle' => 'Matumizi yako ya sasa dhidi ya vikomo vya mpango',
        'at_limit' => 'Umefikia kikomo chako',
        'near_limit' => 'Unakaribia kikomo',
    ],
    'payments' => [
        'heading' => 'Historia ya Malipo',
        'line' => 'Malipo ya {plan}',
        'default_plan' => 'Usajili',
        'download' => 'Pakua Risiti',
        'empty' => 'Bado hakuna historia ya malipo.',
    ],
    'cancel_modal' => [
        'title' => 'Ghairi Usajili?',
        'intro' => 'Una uhakika unataka kughairi usajili wako? Unaweza kuchagua:',
        'at_period_end' => 'Ghairi mwishoni mwa kipindi',
        'keep_until' => 'Endelea kupata huduma hadi {date}',
        'immediately' => 'Ghairi mara moja',
        'immediately_note' => 'Poteza huduma papo hapo (hakuna marejesho)',
        'keep' => 'Endelea na Usajili',
        'cancelling' => 'Inaghairi...',
        'confirm' => 'Thibitisha Kughairi',
    ],
];
