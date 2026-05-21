<?php

declare(strict_types=1);

return [
    'resume_banner' => [
        'title' => 'Hatua ya {current} kati ya {total}',
        'subtitle' => 'Umefikia {pct}% ya usanidi — endelea kutoka ulikoachia.',
        'continue' => 'Endelea',
        'dismiss' => 'Funga',
    ],
    'tour' => [
        'aria_label' => 'Ziara ya bidhaa',
        'step_of' => 'Hatua ya {current} kati ya {total}',
        'nav' => [
            'back' => 'Nyuma',
            'next' => 'Endelea',
            'skip' => 'Ruka ziara',
            'done' => 'Maliza',
        ],
        'landlord-dashboard' => [
            'welcome' => [
                'title' => 'Karibu PropManager 👋',
                'body' => 'Hebu tuandae mali zako kwa hatua chache haraka. Unaweza kuruka ziara hii wakati wowote.',
            ],
            'add_building' => [
                'title' => 'Ongeza jengo lako la kwanza',
                'body' => 'Anza hapa kusajili jengo na vyumba vyake — msingi wa kila kitu kingine.',
            ],
            'add_unit' => [
                'title' => 'Ongeza vyumba vyako',
                'body' => 'Ndani ya jengo, ongeza vyumba unavyokodisha ili uweze kuweka wapangaji.',
            ],
            'invite_tenant' => [
                'title' => 'Alika mpangaji',
                'body' => 'Alika wapangaji kwa barua pepe au simu — kila mmoja anapata tovuti ya kulipa kodi na kuibua masuala.',
            ],
            'create_invoice' => [
                'title' => 'Tuma ankara kwa wapangaji',
                'body' => 'Tengeneza ankara za kodi hapa, au acha PropManager izitengeneze kiotomatiki kila mzunguko.',
            ],
            'record_payment' => [
                'title' => 'Rekodi malipo',
                'body' => 'Kodi inapoingia, irekodi hapa nasi tutaweka vitabu na taarifa sawa.',
            ],
        ],
        'caretaker-intro' => [
            'welcome' => [
                'title' => 'Karibu 👋',
                'body' => 'Hii ni ziara fupi ya eneo lako la kazi la ulinzi.',
            ],
            'tickets' => [
                'title' => 'Tiketi zako',
                'body' => 'Maombi ya matengenezo uliyopangiwa yako hapa — kubali, sasisha, na kamilisha.',
            ],
            'finish' => [
                'title' => 'Umemaliza',
                'body' => 'Nenda kwenye dashibodi yako wakati wowote kupata muhtasari wa kazi yako.',
            ],
        ],
        'tenant-intro' => [
            'welcome' => [
                'title' => 'Karibu nyumbani 👋',
                'body' => 'Hii ni ziara fupi ya tovuti yako ya mpangaji.',
            ],
            'finances' => [
                'title' => 'Fedha zako',
                'body' => 'Ona ankara zako za kodi na historia ya malipo, na ulipe mtandaoni hapa hapa.',
            ],
            'inbox' => [
                'title' => 'Ujumbe',
                'body' => 'Wasiliana moja kwa moja na mwenye nyumba au mlinzi — maswali, maombi, masasisho.',
            ],
        ],
    ],
    'wizard' => [
        'skip_button' => 'Ruka kwa sasa',
        'resume_cta' => 'Endelea usanidi',
    ],
    'sample' => [
        'populated_success' => 'Data ya mfano imepakiwa. Unaweza kuiweka upya wakati wowote.',
        'reset_success' => 'Mizunguko :count ya data ya mfano imefutwa.',
        'refused_real_data' => 'Haiwezekani kupakia data ya mfano wakati una upangaji halisi unaoendelea.',
        'populate_button' => 'Pakia data ya mfano',
        'reset_button' => 'Weka upya data ya mfano',
    ],
    'help' => [
        'drawer_title' => 'Msaada',
        'search_placeholder' => 'Tafuta makala za msaada',
        'no_results' => 'Hakuna makala inayolingana na utafutaji wako.',
    ],
    'checklist' => [
        'heading' => 'Maliza kusanidi',
        'dismiss' => 'Funga',
        'steps' => [
            'first_property' => 'Ongeza mali yako ya kwanza',
            'first_unit' => 'Ongeza nyumba yako ya kwanza',
            'first_tenant' => 'Alika mpangaji wako wa kwanza',
            'first_invoice' => 'Tengeneza ankara yako ya kwanza',
            'first_payment' => 'Rekodi malipo yako ya kwanza',
        ],
    ],
    'video' => [
        'label' => 'Video ya muongozo',
    ],
    'nudge' => [
        'subject' => 'Endelea kutoka ulikoachia',
        'heading' => 'Karibu tena PropManager',
        'greeting' => 'Habari :name,',
        'body' => 'Bado unaendelea na usanidi — hatua yako inayofuata ni **:step**. Endelea kutoka pale ulikoachia kwa kutumia kiungo hapo chini.',
        'cta' => 'Endelea usanidi',
        'expiry_note' => 'Kiungo hiki kinaisha baada ya siku 7. Iwapo kinaisha, barua-pepe inayofuata ya kukumbusha itakuwa na kingine kipya.',
        'signoff' => 'Asante — timu ya :app',
    ],
    'caretaker' => [
        'title' => 'Usanidi wa mlinzi',
        'welcome_title' => 'Karibu',
        'welcome_body' => 'Hatua chache za haraka kuthibitisha maelezo yako, majengo utakayosimamia, na jinsi unavyotaka kuarifiwa — kisha tutakupeleka moja kwa moja kwenye kazi yako ya kwanza.',
        'welcome_cta' => 'Anza',
        'orientation_title' => 'Uko tayari',
        'orientation_body' => 'Haya ni majengo unayosimamia sasa. Maliza ili kwenda kwenye kazi yako ya kwanza iliyo wazi.',
        'orientation_empty' => 'Hakuna majengo uliyopewa bado — mwenye nyumba atayapanga hivi karibuni.',
        'orientation_cta' => 'Nenda kwenye kazi yangu ya kwanza',
    ],
];
