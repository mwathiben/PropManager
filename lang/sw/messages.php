<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Flash Messages (Kiswahili)
    |--------------------------------------------------------------------------
    |
    | Phase-24 I18N-SWAHILI-1: Kiswahili translation of lang/en/messages.php.
    | Key-for-key parity with the English source; placeholder tokens are
    | preserved exactly. Natural Kenyan Kiswahili — see docs/runbooks/i18n.md.
    |
    */

    'invoice' => [
        'generated' => 'Zimetengenezwa ankara :count.',
        'status_updated' => 'Hali ya ankara imesasishwa.',
        'deleted' => 'Ankara imefutwa.',
        'voided' => 'Ankara imebatilishwa.',
        'reissued' => 'Ankara imetolewa upya kama rasimu.',
        'reminder_sent' => 'Kikumbusho cha malipo kimetumwa.',
        'cannot_delete_paid' => 'Huwezi kufuta ankara zilizolipwa.',
        'cannot_remind_paid' => 'Huwezi kutuma kikumbusho kwa ankara zilizolipwa.',
        'cannot_void_status' => 'Ni ankara za rasimu au zilizotumwa pekee zinazoweza kubatilishwa.',
        'cannot_void_with_payments' => 'Huwezi kubatilisha ankara yenye malipo. Rejesha malipo kwanza.',
        'cannot_reissue' => 'Ni ankara zilizobatilishwa pekee zinazoweza kutolewa upya.',
    ],

    'payment' => [
        'recorded' => 'Malipo ya KES :amount yamerekodiwa.',
        'wallet_credited' => 'KES :amount yameongezwa kwenye pochi.',
        'voided' => 'Malipo yamebatilishwa.',
        'receipt_sent' => 'Risiti imetumwa.',
        'verification_failed' => 'Uthibitishaji wa malipo umeshindwa.',
        'not_successful' => 'Malipo hayakufanikiwa.',
        'reference_not_found' => 'Kumbukumbu ya malipo haijapatikana.',
    ],

    'bulk' => [
        'rent_adjusted' => 'Kodi imerekebishwa kwa nyumba :count.',
        'status_updated' => 'Hali imesasishwa kwa nyumba :count.',
        'leases_terminated' => 'Mikataba :count imesitishwa.',
        'leases_extended' => 'Mikataba :count imeongezwa muda.',
        'deposits_adjusted' => 'Amana imerekebishwa kwa nyumba :count.',
        'target_rent_updated' => 'Kodi lengwa imesasishwa kwa nyumba :count.',
        'meters_updated' => 'Nambari za mita zimesasishwa kwa nyumba :count.',
    ],

    'building' => [
        'created' => 'Jengo limeundwa.',
        'updated' => 'Jengo limesasishwa.',
        'deleted' => 'Jengo limefutwa.',
    ],

    'unit' => [
        'created' => 'Nyumba imeundwa.',
        'updated' => 'Nyumba imesasishwa.',
        'deleted' => 'Nyumba imefutwa.',
    ],

    'lease' => [
        'created' => 'Mkataba umeundwa.',
        'updated' => 'Mkataba umesasishwa.',
        'terminated' => 'Mkataba umesitishwa.',
        'extended' => 'Mkataba umeongezwa muda.',
    ],

    'tenant' => [
        'invited' => 'Mwaliko wa mpangaji umetumwa.',
        'updated' => 'Mpangaji amesasishwa.',
    ],

    'document' => [
        'uploaded' => 'Hati imepakiwa.',
        'deleted' => 'Hati imefutwa.',
    ],

    'notification' => [
        'sent' => 'Arifa imetumwa.',
        'scheduled' => 'Arifa imepangwa.',
    ],

];
