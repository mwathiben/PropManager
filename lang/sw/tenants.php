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

    'index' => [
        'head_title' => 'Wapangaji',
        'heading' => 'Wapangaji',
        'subtitle' => 'Simamia wapangaji na mialiko yako',
        'invite_tenant' => 'Alika Mpangaji',
        'view' => 'Tazama',
        'pending' => 'Inasubiri',
        'viewed' => 'Imetazamwa',
        'per_month' => '/mwezi',
        'no_unit_assigned' => 'Hakuna kipengele kilichopangwa',
        'unit_prefix' => 'Kipengele {number}',
        'unit_label' => 'Kipengele',
        'deposit_label' => 'Amana:',
        'start_label' => 'Anza:',
        'expires_label' => 'Inaisha:',

        'tabs' => [
            'active' => 'Wapangaji Hai',
            'pending' => 'Mialiko Inayosubiri',
            'past' => 'Wapangaji wa Zamani',
        ],

        'stats' => [
            'active_tenants' => 'Wapangaji Hai',
            'pending_invites' => 'Mialiko Inayosubiri',
            'monthly_rent' => 'Kodi ya Mwezi',
            'total_arrears' => 'Jumla ya Malimbikizo',
        ],

        'search' => [
            'placeholder' => 'Tafuta wapangaji...',
            'pending_placeholder' => 'Tafuta kwa jina, barua pepe, au simu...',
        ],

        'table' => [
            'tenant' => 'Mpangaji',
            'contact' => 'Mawasiliano',
            'unit' => 'Kipengele',
            'payment' => 'Malipo',
            'rent' => 'Kodi',
            'actions' => 'Vitendo',
            'tenant_info' => 'Taarifa za Mpangaji',
            'lease_terms' => 'Masharti ya Mkataba',
            'status' => 'Hali',
            'last_unit' => 'Kipengele cha Mwisho',
            'end_date' => 'Tarehe ya Mwisho',
        ],

        'lease_status' => [
            'no_lease' => 'Hakuna Mkataba',
            'active' => 'Unaoendelea',
            'inactive' => 'Haufanyi Kazi',
        ],

        'payment_status' => [
            'na' => 'Haipo',
            'arrears' => 'Malimbikizo',
            'up_to_date' => 'Amelipa Kikamilifu',
        ],

        'empty_active' => [
            'title' => 'Hakuna wapangaji hai',
            'description' => 'Alika wapangaji ili kuanza.',
            'search' => 'Jaribu neno tofauti la utafutaji.',
        ],

        'empty_pending' => [
            'title' => 'Hakuna mialiko inayosubiri',
            'description' => 'Mialiko yote imekubaliwa au imeisha muda.',
            'search' => 'Jaribu neno tofauti la utafutaji.',
        ],

        'empty_past' => [
            'title' => 'Hakuna wapangaji wa zamani',
            'description' => 'Wapangaji wa zamani wataonekana hapa baada ya mkataba wao kuisha.',
            'search' => 'Jaribu neno tofauti la utafutaji.',
        ],

        'pagination' => [
            'page_of' => 'Ukurasa {current} kati ya {total}',
            'previous' => 'Iliyotangulia',
            'next' => 'Inayofuata',
        ],

        'actions' => [
            'copy' => 'Nakili kiungo',
            'resend' => 'Tuma tena',
            'edit' => 'Hariri',
            'cancel' => 'Ghairi',
        ],

        'confirm' => [
            'resend' => 'Tuma mwaliko huu tena?',
            'cancel' => 'Una uhakika unataka kughairi mwaliko huu?',
        ],

        'alert' => [
            'copied' => 'Kiungo cha mwaliko kimenakiliwa!',
        ],
    ],

    'history' => [
        'head_title' => 'Historia ya Wapangaji',
        'heading' => 'Historia ya Wapangaji',
        'subtitle' => 'Tazama wapangaji wa zamani waliohama',
        'total_past_tenants' => 'Jumla ya Wapangaji wa Zamani',
        'search_label' => 'Tafuta',
        'search_placeholder' => 'Tafuta kwa jina au barua pepe...',
        'building_label' => 'Jengo',
        'all_buildings' => 'Majengo Yote',
        'clear' => 'Futa',
        'na' => 'Haipo',
        'duration_months' => 'miezi {count}',
        'not_specified' => 'Haijabainishwa',
        'view_profile' => 'Tazama Wasifu',

        'table' => [
            'tenant' => 'Mpangaji',
            'last_unit' => 'Kipengele cha Mwisho',
            'lease_period' => 'Kipindi cha Mkataba',
            'duration' => 'Muda',
            'move_out_reason' => 'Sababu ya Kuhama',
            'actions' => 'Vitendo',
        ],

        'empty' => [
            'title' => 'Hakuna wapangaji wa zamani waliopatikana',
            'description' => 'Rekodi za wapangaji wa zamani zitaonekana hapa baada ya kuhama',
        ],
    ],
];
