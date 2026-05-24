<?php

declare(strict_types=1);

/**
 * Phase-101 OWNER-FOUNDATION. Mirror en / sw / ar exactly (parity-checked).
 */
return [
    'title' => 'Wamiliki wa Mali',
    'subtitle' => 'Wamiliki unaowasimamia mali zao',
    'add' => 'Ongeza mmiliki',
    'edit' => 'Hariri mmiliki',
    'none' => 'Hakuna wamiliki bado. Ongeza mmiliki wa kwanza unayemsimamia.',
    'fields' => [
        'name' => 'Jina',
        'email' => 'Barua pepe',
        'phone' => 'Simu',
        'id_number' => 'Nambari ya kitambulisho / usajili',
        'notes' => 'Maelezo',
        'active' => 'Hai',
        'properties' => 'Mali',
    ],
    'actions' => [
        'save' => 'Hifadhi',
        'cancel' => 'Ghairi',
        'delete' => 'Futa',
        'assign' => 'Kabidhi',
        'unassign' => 'Ondoa',
        'email_statement' => 'Tuma taarifa kwa barua pepe',
        'download_statement' => 'Pakua taarifa',
        'invite' => 'Alika kwenye portal',
    ],
    'assign' => [
        'title' => 'Mali',
        'owner' => 'Mmiliki',
        'unassigned' => 'Haijakabidhiwa',
    ],
    'delete_confirm' => 'Futa mmiliki huyu? Mali zao zitabaki, ila bila kukabidhiwa.',
    'messages' => [
        'created' => 'Mmiliki ameongezwa.',
        'updated' => 'Mmiliki amesasishwa.',
        'deleted' => 'Mmiliki amefutwa; mali zao zimeondolewa ukabidhi.',
        'assigned' => 'Mali imekabidhiwa kwa mmiliki.',
        'unassigned' => 'Mmiliki wa mali ameondolewa.',
        'statement_sent' => 'Taarifa inatumwa kwa :email.',
        'statement_no_email' => 'Mmiliki huyu hana anwani ya barua pepe.',
    ],
    'invite' => [
        'sent' => 'Mwaliko umetumwa kwa mmiliki.',
        'no_email' => 'Ongeza anwani ya barua pepe kabla ya kumwalika mmiliki huyu.',
        'email_taken' => 'Mtumiaji mwenye barua pepe hiyo tayari yupo.',
        'already_pending' => 'Tayari kuna mwaliko unaosubiri kwa mmiliki huyu.',
        'used' => 'Mwaliko huu tayari umetumika.',
        'expired' => 'Mwaliko huu umeisha muda.',
        'revoked' => 'Mwaliko huu si halali tena.',
        'failed' => 'Imeshindwa kukamilisha mwaliko. Tafadhali jaribu tena.',
        'welcome' => 'Karibu! Hizi ni mali zinazosimamiwa kwa niaba yako.',
    ],
    'accept' => [
        'title' => 'Anzisha akaunti yako ya mmiliki',
        'invited_by' => 'Umealikwa na',
        'name' => 'Jina lako',
        'mobile' => 'Nambari ya simu',
        'password' => 'Nenosiri',
        'password_confirm' => 'Thibitisha nenosiri',
        'submit' => 'Tengeneza akaunti yangu',
    ],
    'portal' => [
        'dashboard_title' => 'Mali Zangu',
        'dashboard_subtitle' => 'Mali zinazosimamiwa kwa niaba yako',
        'statements_title' => 'Taarifa Zangu',
        'no_properties' => 'Hakuna mali zilizokabidhiwa kwako bado.',
        'occupancy' => 'Ukaaji',
        'units' => 'vyumba',
        'rent_roll' => 'Kodi ya kila mwezi',
        'arrears' => 'Malimbikizo yanayodaiwa',
        'collected' => 'Imekusanywa',
        'expenses' => 'Gharama',
        'net' => 'Halisi kwako',
        'download' => 'Pakua taarifa (PDF)',
        'period' => 'Kipindi',
    ],
];
