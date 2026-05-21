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
    'tickets' => [
        'annotation_saved' => 'Maelezo yameokolewa.',
    ],
    'emergency_contact' => [
        'otp_sent' => 'Msimbo wa uthibitisho umetumwa. Angalia SMS kwenye simu ya mtu wa dharura.',
        'otp_message' => 'PropManager — msimbo wako wa uthibitisho ni :code. Utaisha kwa dakika 10.',
        'rate_limited' => 'Umejaribu mara nyingi katika masaa 24 yaliyopita. Tafadhali subiri kabla ya kujaribu tena.',
        'verified' => 'Mtu wa dharura amethibitishwa.',
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
        'credit_note_description' => 'Hati ya mkopo :number',
        'wallet_credit' => 'Salio la pochi',
        'wallet_debit' => 'Pochi imetumika',
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
    'wallet' => [
        'title' => 'Pochi yangu',
        'subtitle' => 'Salio lako la krediti na jinsi limebadilika',
        'no_lease' => 'Hakuna mkataba unaotumika.',
        'balance_heading' => 'Salio',
        'no_balance' => 'Hakuna salio la pochi bado.',
        'ledger_heading' => 'Shughuli za hivi karibuni',
        'no_ledger' => 'Hakuna shughuli za pochi bado.',
        'col_date' => 'Tarehe',
        'col_type' => 'Aina',
        'col_amount' => 'Kiasi',
        'col_reason' => 'Sababu',
        'col_balance' => 'Salio baada ya',
        'type_credit' => 'Krediti',
        'type_debit' => 'Imetumika',
        'apply_heading' => 'Tumia salio kwa ankara',
        'no_invoices' => 'Hakuna ankara zinazodaiwa.',
        'apply_amount' => 'Kiasi (hiari)',
        'apply_button' => 'Tumia salio',
        'applied' => 'Salio la :amount limetumika kwa ankara :invoice.',
        'nothing_applied' => 'Hakuna salio lililoweza kutumika kwa ankara hiyo.',
    ],
];
