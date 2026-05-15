<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email Template Strings (Kiswahili)
    |--------------------------------------------------------------------------
    |
    | Phase-24 I18N-SWAHILI-1: Kiswahili translation of lang/en/emails.php.
    | Key-for-key parity; placeholder tokens preserved exactly. Natural Kenyan
    | Kiswahili — see docs/runbooks/i18n.md glossary.
    |
    */

    'payment' => [
        'title' => 'Malipo Yamepokelewa',
        'greeting' => 'Mpendwa :name',
        'success_message' => 'Tumepokea malipo yako kwa mafanikio. Asante kwa kulipa kwa wakati!',
        'details_title' => 'Maelezo ya Malipo',
        'amount_paid' => 'Kiasi Kilicholipwa',
        'payment_date' => 'Tarehe ya Malipo',
        'payment_method' => 'Njia ya Malipo',
        'receipt_number' => 'Nambari ya Risiti',
        'invoice_info_title' => 'Maelezo ya Ankara',
        'invoice_number' => 'Nambari ya Ankara',
        'billing_period' => 'Kipindi cha Malipo',
        'unit' => 'Nyumba',
        'summary_title' => 'Muhtasari wa Ankara',
        'total_due' => 'Jumla Inayodaiwa',
        'amount_paid_this' => 'Kiasi Kilicholipwa (Malipo Haya)',
        'total_paid_to_date' => 'Jumla Iliyolipwa Hadi Sasa',
        'balance_remaining' => 'Salio Lililobaki',
        'fully_paid' => 'Hongera! Ankara yako imelipwa kikamilifu. Asante!',
        'download_receipt' => 'Pakua Risiti',
        'questions' => 'Ukiwa na maswali yoyote kuhusu malipo haya, tafadhali wasiliana na msimamizi wa mali yako.',
        'thanks' => 'Asante',
        'team' => 'Timu ya :app',
    ],

    'invoice' => [
        'title' => 'Ankara :number',
        'greeting' => 'Habari :name',
        'intro' => 'Ankara yako ya :period imetengenezwa na sasa inadaiwa.',
        'property_details' => 'Maelezo ya Mali',
        'property' => 'Mali',
        'building' => 'Jengo',
        'unit' => 'Nyumba',
        'summary' => 'Muhtasari wa Ankara',
        'description' => 'Maelezo',
        'amount' => 'Kiasi',
        'rent' => 'Kodi',
        'water_charges' => 'Gharama za Maji',
        'previous_arrears' => 'Malimbikizo ya Awali',
        'total_due' => 'Jumla Inayodaiwa',
        'due_date' => 'Tarehe ya Mwisho',
        'due_warning' => 'Tafadhali hakikisha malipo yamefanyika kabla ya tarehe ya mwisho ili kuepuka faini za kuchelewa.',
        'view_pay_button' => 'Angalia Ankara na Ulipe',
        'payment_methods_title' => 'Njia za Malipo',
        'payment_methods_intro' => 'Unaweza kulipa ankara yako kwa kutumia mojawapo ya njia zifuatazo:',
        'method_mpesa' => 'M-Pesa (Pesa za Simu) - Haraka na rahisi',
        'method_bank' => 'Uhamisho wa Benki - Weka pesa moja kwa moja kwenye akaunti ya mwenye nyumba',
        'method_online' => 'Malipo ya Mtandaoni - Lipa kwa usalama kupitia kadi',
        'already_paid' => 'Ikiwa tayari umefanya malipo haya, tafadhali puuza taarifa hii.',
        'questions' => 'Ukiwa na maswali yoyote kuhusu ankara hii, tafadhali wasiliana na mwenye nyumba wako.',
        'thanks' => 'Asante',
    ],

    'caretaker_invitation' => [
        'title' => 'Umealikwa!',
        'greeting' => 'Habari',
        'intro' => ':landlord amekualika ujiunge kama msimamizi wa :property.',
        'abilities_intro' => 'Kama msimamizi, utaweza:',
        'ability_water' => 'Kurekodi usomaji wa mita za maji kwa nyumba zote',
        'ability_tenants' => 'Kuona taarifa za wapangaji',
        'ability_maintenance' => 'Kusimamia maombi ya matengenezo (inakuja hivi karibuni)',
        'ability_operations' => 'Kusaidia na shughuli za kila siku za mali',
        'get_started' => 'Anza',
        'get_started_text' => 'Bofya kitufe hapa chini kukubali mwaliko huu na kuunda akaunti yako ya msimamizi.',
        'accept_button' => 'Kubali Mwaliko',
        'expiry_notice' => 'Mwaliko huu utaisha tarehe :date.',
        'contact' => 'Ukiwa na maswali yoyote, tafadhali wasiliana na :landlord moja kwa moja.',
        'ignore' => 'Ikiwa hukutarajia mwaliko huu, unaweza kuupuuza barua pepe hii kwa usalama.',
        'thanks' => 'Asante',
        'team' => 'Timu ya :app',
    ],

    'reminder' => [
        'title_overdue' => 'Malipo Yamechelewa',
        'title_reminder' => 'Kikumbusho cha Malipo',
        'overdue_intro' => 'Hiki ni kikumbusho kwamba ankara yako :number imechelewa.',
        'upcoming_intro' => 'Hiki ni kikumbusho cha kirafiki kwamba ankara yako :number inadaiwa kulipwa.',
        'pay_immediately' => 'Tafadhali lipa salio hili mara moja ili kuepuka hatua zaidi.',
        'pay_by_due_date' => 'Tafadhali hakikisha malipo yamefanyika kabla ya tarehe ya mwisho.',
        'pay_now_button' => 'Lipa Sasa',
    ],

    'tenant_welcome' => [
        'title' => 'Karibu :property!',
        'subtitle' => 'Mkataba wako sasa unatumika!',
        'intro' => 'Tunafurahi kukuwa na wewe kama mpangaji.',
        'lease_details' => 'Maelezo ya Mkataba Wako',
        'monthly_rent' => 'Kodi ya Kila Mwezi',
        'security_deposit' => 'Amana ya Usalama',
        'lease_start' => 'Tarehe ya Kuanza Mkataba',
        'landlord' => 'Mwenye Nyumba Wako',
        'portal_title' => 'Fikia Tovuti Yako ya Mpangaji',
        'portal_intro' => 'Ingia kwenye tovuti yako ya mpangaji ili:',
        'portal_lease' => 'Kuona maelezo ya mkataba wako',
        'portal_payments' => 'Kulipa kodi na kuona historia ya malipo',
        'portal_maintenance' => 'Kuwasilisha maombi ya matengenezo',
        'portal_communicate' => 'Kuwasiliana na mwenye nyumba wako',
        'portal_button' => 'Nenda kwenye Tovuti ya Mpangaji',
        'questions' => 'Ukiwa na maswali au wasiwasi wowote, tafadhali wasiliana na mwenye nyumba wako.',
    ],

    'common' => [
        'thanks' => 'Asante',
        'regards' => 'Wako mwaminifu',
        'team' => 'Timu ya :app',
        'important' => 'Muhimu',
        'note' => 'Kumbuka',
        'currency' => 'KES',
    ],

    'subjects' => [
        'scheduled_report' => 'Ripoti iliyopangwa: :name',
        'invoice_sent' => 'Ankara :number - Malipo Yanahitajika',
        'payment_received' => 'Malipo Yamepokelewa - :number',
        'invoice_reminder' => 'Kumbusho la Malipo - Ankara :number',
        'invoice_overdue' => 'Malipo Yamepitwa na Wakati - Ankara :number',
        'credit_note_issued' => 'Hati ya Mkopo Imetolewa - :number',
        'data_export_ready' => 'Hifadhi Yako ya Data Iko Tayari - :app',
        'deposit_refunded' => 'Amana Yako ya Usalama Imerejeshwa',
        'deposit_partial_refund' => 'Amana Yako ya Usalama Imerejeshwa Sehemu',
        'deposit_forfeited' => 'Notisi ya Kupoteza Amana ya Usalama',
        'deposit_update' => 'Sasisho la Amana ya Usalama',
        'landlord_welcome' => 'Karibu :app - Hebu Tuanze!',
        'overpayment_notice' => 'Notisi ya Malipo ya Ziada ya Mpangaji - Kumbukumbu :ref',
        'payment_verification_approved' => 'Malipo Yamethibitishwa - Karibu Nyumbani Kwako Kipya',
        'payment_verification_rejected' => 'Tatizo la Uthibitisho wa Malipo - Hatua Inahitajika',
        'rent_hike_notice' => 'Notisi ya Marekebisho ya Kodi - Itaanza :date',
        'tenant_credentials' => 'Karibu :property - Maelezo ya Akaunti Yako',
        'tenant_welcome' => 'Karibu :property - Mkataba Wako Umeanza',
        'tenant_invitation_existing' => 'Mwaliko wa Mkataba Mpya - :property',
        'tenant_invitation_new' => 'Umealikwa Kujiunga na :property',
        'caretaker_invitation' => 'Mwaliko wa Kujiunga kama Mlinzi wa Jengo',
    ],

];
