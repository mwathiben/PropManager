<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: lease-creation (tenant invitation) page. Mirror en/sw/ar.
 */
return [
    'create' => [
        'title' => 'Alika Mpangaji',
        'heading' => 'Alika Mpangaji: Kipengele {unit}',
        'subheading' => 'Tuma mwaliko wa kukodisha kwa Ghorofa {floor}',
        'success' => [
            'title' => 'Mwaliko Umetumwa!',
            'sent_to' => 'Mwaliko umetumwa kwa',
            'via' => 'kupitia {channels}.',
            'follow_up' => 'Mpangaji atapokea arifa yenye kiungo cha kukagua masharti ya ukodishaji na kufungua akaunti yake.',
            'send_another' => 'Tuma Mwaliko Mwingine',
            'return_dashboard' => 'Rudi kwenye Dashibodi',
        ],
        'how_it_works' => [
            'title' => 'Jinsi inavyofanya kazi:',
            'step1' => 'Weka barua pepe ya mpangaji na masharti ya ukodishaji hapa chini',
            'step2' => 'Mpangaji hupokea barua pepe yenye kiungo cha kukagua na kukubali',
            'step3' => 'Mpangaji hufungua akaunti yake na ukodishaji huanzishwa',
        ],
        'tenant_info' => [
            'title' => 'Taarifa za Mpangaji',
            'subtitle' => 'Weka maelezo ya mawasiliano ya mpangaji tarajiwa',
        ],
        'fields' => [
            'email' => 'Anwani ya Barua Pepe',
            'email_placeholder' => 'tenant@example.com',
            'email_help' => 'Mwaliko utatumwa kwa barua pepe hii',
            'name' => 'Jina Kamili (Si Lazima)',
            'name_placeholder' => 'John Doe',
            'name_help' => 'Mpangaji anaweza kusasisha hili wakati wa kukubali',
            'phone' => 'Nambari ya Simu',
            'phone_optional' => '(Si Lazima)',
            'phone_placeholder' => '+254 7XX XXX XXX',
            'phone_required_help' => 'Inahitajika kwa utumaji wa SMS/WhatsApp',
            'monthly_rent' => 'Kodi ya Mwezi ({currency})',
            'service_charge' => 'Ada ya Huduma ({currency})',
            'service_charge_help' => 'Taka, Usalama, Taa',
            'security_deposit' => 'Amana ya Usalama ({currency})',
            'amount_placeholder' => '0.00',
            'start_date' => 'Tarehe ya Kuanza Ukodishaji',
            'end_date' => 'Tarehe ya Kumaliza Ukodishaji (Si Lazima)',
            'end_date_help' => 'Acha wazi kwa ukodishaji wa mwezi hadi mwezi',
        ],
        'lease_terms' => [
            'title' => 'Masharti ya Ukodishaji',
            'subtitle' => 'Weka kiasi cha kodi na amana kwa ukodishaji huu',
        ],
        'totals' => [
            'move_in' => 'Jumla Inayodaiwa Kuingia:',
        ],
        'lease_period' => [
            'title' => 'Kipindi cha Ukodishaji',
        ],
        'channels' => [
            'title' => 'Tuma Mwaliko Kupitia',
            'subtitle' => 'Chagua jinsi ya kumjulisha mpangaji kuhusu mwaliko huu',
            'email' => 'Barua Pepe',
            'sms' => 'SMS',
            'whatsapp' => 'WhatsApp',
            'not_configured' => 'Haijawekwa - sanidi katika Mipangilio',
            'enter_phone' => 'Weka nambari ya simu hapo juu',
            'cost_warning' => 'Ujumbe wa SMS na WhatsApp unaweza kusababisha gharama kulingana na mipangilio ya mtoa huduma wako.',
        ],
        'required' => '*',
        'cancel' => 'Ghairi',
        'sending' => 'Inatuma...',
        'send' => 'Tuma Mwaliko',
    ],
];
