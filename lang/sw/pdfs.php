<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PDF Template Strings (Kiswahili)
    |--------------------------------------------------------------------------
    |
    | Phase-24 I18N-SWAHILI-1: Kiswahili translation of lang/en/pdfs.php.
    | Key-for-key parity; placeholder tokens preserved exactly.
    |
    */

    'invoice' => [
        'title' => 'ANKARA',
        'from' => 'Kutoka',
        'bill_to' => 'Ankara Kwa',
        'unit' => 'Nyumba :number',
        'invoice_date' => 'Tarehe ya Ankara:',
        'due_date' => 'Tarehe ya Mwisho:',
        'billing_period' => 'Kipindi cha Malipo:',
        'status' => 'Hali:',
        'description' => 'Maelezo',
        'amount' => 'Kiasi (KES)',
        'monthly_rent' => 'Kodi ya Kila Mwezi',
        'water_charges' => 'Gharama za Maji',
        'previous_arrears' => 'Malimbikizo ya Awali',
        'subtotal' => 'Jumla Ndogo:',
        'amount_paid' => 'Kiasi Kilicholipwa:',
        'balance_due' => 'Salio Linalodaiwa:',
        'amount_due' => 'Kiasi Kinachodaiwa',
        'payment_instructions' => 'Maelekezo ya Malipo',
        'mpesa_instruction' => 'Lipa kupitia Lipa Na M-Pesa ukitumia nambari ya Paybill/Till uliyopewa na mwenye nyumba wako',
        'bank_instruction' => 'Hamisha kwenye akaunti ya benki uliyopewa na mwenye nyumba wako',
        'online_instruction' => 'Lipa kwa usalama kupitia tovuti ya mpangaji',
        'generated_on' => 'Imetengenezwa tarehe :date',
        'footer_generated' => 'Ankara hii ilitengenezwa na PropManager',
        'footer_contact' => 'Kwa maswali, tafadhali wasiliana na msimamizi wa mali yako',
    ],

    'credit_note' => [
        'title' => 'HATI YA MKOPO',
        'credit_adjustment' => 'Marekebisho ya Mkopo',
        'credit_amount' => 'Kiasi cha Mkopo',
        'applied' => 'Kimetumika',
        'remaining' => 'Kilichobaki',
        'credit_issued_to' => 'Mkopo Umetolewa Kwa',
        'tenant' => 'Mpangaji',
        'property' => 'Mali',
        'reason_for_credit' => 'Sababu ya Mkopo',
        'original_invoice_reference' => 'Kumbukumbu ya Ankara Asili',
        'invoice_number' => 'Nambari ya Ankara',
        'invoice_date' => 'Tarehe ya Ankara',
        'invoice_amount' => 'Kiasi cha Ankara',
        'approval_details' => 'Maelezo ya Idhini',
        'approved_by' => 'Imeidhinishwa Na',
        'approved_date' => 'Tarehe ya Idhini',
        'application_details' => 'Maelezo ya Matumizi',
        'applied_to_invoice' => 'Kimetumika Kwa Ankara',
        'amount_applied' => 'Kiasi Kilichotumika',
        'applied_date' => 'Tarehe ya Matumizi',
        'balance_summary' => 'Muhtasari wa Salio',
        'credit_note_amount' => 'Kiasi cha Hati ya Mkopo',
        'available_credit' => 'Mkopo Uliopo',
        'terms_title' => 'Sheria na Masharti',
        'term_valid' => 'Hati hii ya mkopo ni halali kwa matumizi dhidi ya ankara zijazo.',
        'term_no_cash' => 'Hati za mkopo haziwezi kubadilishwa kuwa pesa taslimu.',
        'term_tenancy' => 'Hati hii ya mkopo lazima itumike ndani ya kipindi cha upangaji.',
        'term_contact' => 'Wasiliana na msimamizi wa mali yako kwa maswali yoyote kuhusu hati hii ya mkopo.',
        'footer' => 'Hii ni hati rasmi ya mkopo iliyotolewa na :business',
        'generated_on' => 'Imetengenezwa tarehe :date',
    ],

    'receipt' => [
        'title' => 'RISITI',
        'payment_receipt' => 'Risiti ya Malipo',
        'receipt_number' => 'Nambari ya Risiti',
        'payment_date' => 'Tarehe ya Malipo',
        'received_from' => 'Imepokelewa Kutoka',
        'payment_for' => 'Malipo Kwa Ajili Ya',
        'amount_received' => 'Kiasi Kilichopokelewa',
        'payment_method' => 'Njia ya Malipo',
        'reference' => 'Kumbukumbu',
        'invoice_details' => 'Maelezo ya Ankara',
        'balance_after_payment' => 'Salio Baada ya Malipo',
        'full_payment' => 'Malipo Kamili',
        'partial_payment' => 'Malipo ya Sehemu',
        'thank_you' => 'Asante kwa malipo yako!',
        'footer' => 'Hii ni risiti rasmi',
        'generated_on' => 'Imetengenezwa tarehe :date',
    ],

    'ledger' => [
        'title' => 'DAFTARI LA MPANGAJI',
        'statement' => 'Taarifa ya Akaunti',
        'account_summary' => 'Muhtasari wa Akaunti',
        'tenant_details' => 'Maelezo ya Mpangaji',
        'period' => 'Kipindi',
        'opening_balance' => 'Salio la Kuanzia',
        'closing_balance' => 'Salio la Kufunga',
        'transactions' => 'Miamala',
        'date' => 'Tarehe',
        'description' => 'Maelezo',
        'debit' => 'Deni',
        'credit' => 'Mkopo',
        'balance' => 'Salio',
        'total_debits' => 'Jumla ya Madeni',
        'total_credits' => 'Jumla ya Mikopo',
        'footer' => 'Taarifa hii ni kwa madhumuni ya habari pekee',
    ],

    'common' => [
        'currency' => 'KES',
        'page' => 'Ukurasa :current kati ya :total',
        'generated_by' => 'Imetengenezwa na PropManager',
        'confidential' => 'Siri',
    ],

];
