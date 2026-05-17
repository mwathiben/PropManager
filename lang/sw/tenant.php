<?php

declare(strict_types=1);

/*
 * Phase-28 [TENANT-PORTAL] i18n keys for tenant-facing flash messages.
 * Swahili parity is enforced by Phase24CiTest.
 */

return [
    'profile' => [
        'updated' => 'Wasifu umesasishwa.',
        'password_updated' => 'Nenosiri limesasishwa.',
        'notifications_updated' => 'Mapendeleo ya arifa yamehifadhiwa.',
    ],
    'ticket_sla' => [
        'subject' => 'SLA imevuka: :title',
        'body' => 'Tiketi ":title" (:priority) imevuka SLA saa :breached_at na bado haijapata jibu la kwanza.',
    ],
    'payment_plan' => [
        'submitted' => 'Ombi la mpango wa malipo limewasilishwa (:count awamu). Linasubiri idhini ya mwenye nyumba.',
    ],
    'deposit_refund' => [
        'submitted' => 'Ombi la kurudisha amana limewasilishwa. Mwenye nyumba atalipitia hivi karibuni.',
    ],
    'statement' => [
        'title' => 'Taarifa Yangu',
        'period_label' => 'Kipindi :from hadi :to',
        'opening_balance' => 'Salio la kuanza',
        'closing_balance' => 'Salio la mwisho',
        'invoice_description' => 'Ankara :number',
        'payment_description' => 'Malipo yamepokelewa',
        'col_date' => 'Tarehe',
        'col_description' => 'Maelezo',
        'col_reference' => 'Kumbukumbu',
        'col_charge' => 'Malipo Yanayodaiwa',
        'col_payment' => 'Malipo Yaliyolipwa',
        'col_balance' => 'Salio',
        'col_month' => 'Mwezi',
        'col_charges' => 'Malipo yanayodaiwa',
        'col_payments' => 'Malipo yaliyolipwa',
        'col_net' => 'Jumla halisi',
        'col_closing_balance' => 'Salio la mwisho',
        'monthly_summary_title' => 'Muhtasari wa Mwezi',
        'preferences_saved' => 'Safu wima za taarifa zimesasishwa.',
        'emailed' => 'Taarifa yako imetumwa kwa barua pepe.',
        'email_subject' => 'Taarifa yako :from hadi :to',
        'email_heading' => 'Taarifa ya akaunti yako',
        'email_intro' => 'Habari :name, taarifa yako iko tayari.',
        'email_footer' => 'Tafadhali wasiliana na mwenye nyumba ikiwa kuna kosa lolote.',
    ],
];
