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
];
