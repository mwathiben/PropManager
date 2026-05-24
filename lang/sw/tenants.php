<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant detail page. Mirror en/sw/ar.
 */
return [
    'show' => [
        'head_title' => 'Mpangaji: {name}',
        'back_to_tenants' => 'Rudi kwa Wapangaji',
        'message' => 'Tuma Ujumbe',
        'edit_profile' => 'Hariri Wasifu',

        'sections' => [
            'overview' => 'Muhtasari',
            'lease' => 'Maelezo ya Mkataba',
            'payments' => 'Malipo',
            'documents' => 'Hati',
            'notes' => 'Maelezo',
            'contacts' => 'Anwani za Dharura',
            'activity' => 'Shughuli',
        ],

        'status' => [
            'no_active_lease' => 'Hakuna Mkataba Unaoendelea',
            'in_arrears' => 'Ana Malimbikizo',
            'up_to_date' => 'Amelipa Kikamilifu',
            'active' => 'Unaoendelea',
            'inactive' => 'Haufanyi Kazi',
        ],

        'contact_info' => [
            'title' => 'Maelezo ya Mawasiliano',
            'email' => 'Barua Pepe',
            'phone' => 'Simu',
            'id_number' => 'Namba ya Kitambulisho',
            'tenant_since' => 'Mpangaji Tangu',
        ],

        'stats' => [
            'unit' => 'Kipengele',
            'monthly_rent' => 'Kodi ya Mwezi',
            'deposit' => 'Amana',
            'arrears' => 'Malimbikizo',
            'credit_balance' => 'Salio la Mkopo',
            'adjust' => 'Rekebisha',
        ],

        'primary_contact' => [
            'title' => 'Anwani Kuu ya Dharura',
            'none' => 'Hakuna anwani kuu iliyowekwa',
        ],

        'lease' => [
            'current_title' => 'Mkataba wa Sasa',
            'property_building_unit' => 'Mali / Jengo / Kipengele',
            'property_fallback' => 'Mali',
            'building_fallback' => 'Jengo',
            'unit_prefix' => 'Kipengele',
            'lease_period' => 'Kipindi cha Mkataba',
            'ongoing' => 'Unaoendelea',
            'monthly_rent' => 'Kodi ya Mwezi',
            'deposit_paid' => 'Amana Iliyolipwa',
            'service_charge' => 'Ada ya Huduma',
            'status_label' => 'Hali',
            'rent_history' => 'Historia ya Kodi',
            'no_active_title' => 'Hakuna Mkataba Unaoendelea',
            'no_active_body' => 'Mpangaji huyu hana mkataba unaoendelea.',
            'past_leases' => 'Mikataba ya Zamani',
            'per_month_suffix' => '/mwezi',
        ],

        'payments' => [
            'recent_invoices' => 'Ankara za Hivi Karibuni',
            'invoice_number' => 'Ankara #',
            'date' => 'Tarehe',
            'amount' => 'Kiasi',
            'status' => 'Hali',
            'no_invoices' => 'Hakuna ankara zilizopatikana',
            'recent_payments' => 'Malipo ya Hivi Karibuni',
            'no_payments' => 'Hakuna malipo yaliyorekodiwa',
        ],

        'documents' => [
            'title' => 'Hati',
            'files_count' => 'faili {count}',
            'type_fallback' => 'Nyingine',
            'view' => 'Tazama',
            'download' => 'Pakua',
            'none' => 'Hakuna hati zilizopakiwa',
        ],

        'notes' => [
            'title' => 'Maelezo ya Faragha',
            'add' => 'Ongeza Maelezo',
            'author_unknown' => 'Haijulikani',
            'edit_aria' => 'Hariri maelezo',
            'delete_aria' => 'Futa maelezo',
            'none' => 'Bado hakuna maelezo. Ongeza maelezo yako ya kwanza kuhusu mpangaji huyu.',
        ],

        'contacts' => [
            'title' => 'Anwani za Dharura',
            'add' => 'Ongeza Anwani',
            'primary_badge' => 'Kuu',
            'edit_aria' => 'Hariri anwani ya dharura',
            'delete_aria' => 'Futa anwani ya dharura',
            'none' => 'Hakuna anwani za dharura. Ongeza moja kwa mpangaji huyu.',
        ],

        'activity' => [
            'title' => 'Ratiba ya Shughuli',
            'by' => 'na {name}',
            'system' => 'Mfumo',
            'none' => 'Bado hakuna shughuli iliyorekodiwa.',
        ],

        'edit_modal' => [
            'title' => 'Hariri Wasifu wa Mpangaji',
            'name' => 'Jina',
            'email' => 'Barua Pepe',
            'phone' => 'Simu',
            'id_number' => 'Namba ya Kitambulisho',
            'dob' => 'Tarehe ya Kuzaliwa',
            'dob_hint' => '(hiari — inahitajika kwa mtiririko wa idhini ya mtoto)',
            'minor_title' => 'Mtoto — Idhini ya Mzazi Inahitajika',
            'minor_body' => 'Sheria ya DPA ya Kenya Kifungu cha 8 / Sehemu ya 33 inahitaji idhini ya mzazi inayoweza kuthibitishwa kabla ya kuchakata data ya wapangaji walio chini ya umri wa miaka 18.',
            'consent_url' => 'Kiungo cha Hati ya Idhini ya Mzazi',
            'consent_url_placeholder' => 'https://drive.example.com/consent.pdf',
            'consent_at' => 'Idhini Ilitolewa Lini',
            'consent_required_note' => 'Kiungo cha hati na muhuri wa muda lazima vitolewe kabla ya kuhifadhi.',
            'cancel' => 'Ghairi',
            'save' => 'Hifadhi Mabadiliko',
        ],

        'note_modal' => [
            'edit_title' => 'Hariri Maelezo',
            'add_title' => 'Ongeza Maelezo',
            'label' => 'Maelezo',
            'placeholder' => 'Andika maelezo yako hapa...',
            'pin' => 'Bandika maelezo haya',
            'cancel' => 'Ghairi',
            'save' => 'Hifadhi',
            'add' => 'Ongeza Maelezo',
        ],

        'contact_modal' => [
            'edit_title' => 'Hariri Anwani',
            'add_title' => 'Ongeza Anwani ya Dharura',
            'name' => 'Jina',
            'name_placeholder' => 'John Doe',
            'relationship' => 'Uhusiano',
            'relationship_placeholder' => 'Mwenzi, Mzazi, Ndugu, n.k.',
            'phone' => 'Simu',
            'phone_placeholder' => '+254 712 345 678',
            'email' => 'Barua Pepe (Hiari)',
            'email_placeholder' => 'contact@example.com',
            'set_primary' => 'Weka kama anwani kuu',
            'cancel' => 'Ghairi',
            'save' => 'Hifadhi',
            'add' => 'Ongeza Anwani',
        ],

        'wallet_modal' => [
            'title' => 'Rekebisha Salio la Pochi',
            'current_balance' => 'Salio la Sasa',
            'adjustment_type' => 'Aina ya Marekebisho',
            'credit' => '+ Mkopo (Ongeza)',
            'debit' => '− Deni (Ondoa)',
            'amount' => 'Kiasi ({currency})',
            'amount_placeholder' => 'Weka kiasi',
            'reason' => 'Sababu',
            'reason_placeholder' => 'k.m., Marejesho ya malipo ya ziada, Mkopo wa nia njema',
            'warning_label' => 'Onyo:',
            'warning_body' => 'Kiasi cha deni kinazidi salio la sasa. Hii itasababisha salio hasi.',
            'cancel' => 'Ghairi',
            'add_credit' => 'Ongeza Mkopo',
            'remove_credit' => 'Ondoa Mkopo',
        ],

        'confirm' => [
            'delete_note' => 'Futa maelezo haya?',
            'delete_contact' => 'Futa anwani hii ya dharura?',
        ],
    ],
];
