<?php

declare(strict_types=1);

return [
    'sla' => [
        'title' => 'Mbadala wa SLA',
        'description' => 'Badilisha malengo ya kujibu na kutatua kwa mali zako. Chaguomsingi za jukwaa zinatumika usipowa na mbadala unaolingana.',
        'flash' => [
            'created' => 'Mbadala wa SLA umehifadhiwa.',
            'updated' => 'Mbadala wa SLA umesasishwa.',
            'deleted' => 'Mbadala wa SLA umeondolewa.',
        ],
    ],
    'vendor_onboarding' => [
        'subject' => ':landlord amekuongeza kama muuzaji — tafadhali kamilisha wasifu wako',
        'heading' => 'Karibu — kamilisha wasifu wako wa muuzaji',
        'greeting' => 'Habari :name,',
        'body' => ':landlord amekuongeza kwenye orodha yake ya wauzaji kwenye PropManager. Tafadhali thibitisha simu yako na eneo la huduma ili kazi za matengenezo zikufikie.',
        'cta' => 'Kamilisha wasifu',
        'expiry_note' => 'Kiungo hiki kitaisha baada ya siku 7. Wasiliana na mwenye nyumba moja kwa moja kiungo kikiisha.',
        'signoff' => 'Asante, timu ya :app',
        'saved' => 'Wasifu umesasishwa. Asante.',
        'form' => [
            'title' => 'Kamilisha wasifu wako wa muuzaji',
            'intro' => 'Sasisha mawasiliano yako na eneo la huduma ili mwenye nyumba aweze kukufikia kwa kazi za matengenezo.',
            'contact_person' => 'Mtu wa mawasiliano',
            'phone' => 'Simu',
            'address' => 'Anwani',
            'notes' => 'Utaalamu / eneo la huduma',
            'submit' => 'Hifadhi mabadiliko',
            'expired' => 'Kiungo hiki kimeisha. Tafadhali muulize mwenye nyumba akutumie mwaliko mpya.',
        ],
    ],
    'vendor_assigned' => [
        'subject' => 'Umepewa tiketi ya matengenezo: :ticket',
        'heading' => 'Mgawo mpya wa matengenezo',
        'greeting' => 'Habari :name,',
        'body' => ':landlord amekupa tiketi ":title" (kipaumbele :priority). Tafadhali angalia kazi inayohitajika hapa chini na ujibu mapema iwezekanavyo.',
        'scope_label' => 'Kazi inayohitajika',
        'note_label' => 'Maelezo kutoka kwa mwenye nyumba',
        'contact_note' => 'Jibu barua pepe hii au wasiliana na mwenye nyumba moja kwa moja kuthibitisha kukubali, kutoa bei, au kuomba taarifa zaidi.',
        'signoff' => 'Asante, timu ya :app',
    ],
    'photos' => [
        'title' => 'Picha za matengenezo',
        'subtitle' => 'Kila picha ya tiketi katika mali zako',
        'filter_building' => 'Jengo',
        'filter_category' => 'Aina',
        'filter_from' => 'Kuanzia',
        'filter_to' => 'Hadi',
        'filter_all' => 'Zote',
        'apply' => 'Tumia',
        'reset' => 'Weka upya',
        'export_pdf' => 'Hamisha PDF',
        'empty' => 'Hakuna picha zinazolingana na vichujio hivi.',
        'annotated' => 'Imefafanuliwa',
        'view_ticket' => 'Angalia tiketi',
    ],
];
