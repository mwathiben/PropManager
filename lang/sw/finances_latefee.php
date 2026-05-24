<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: late-fee settings tab (Finances). Mirror en/sw/ar.
 */
return [
    'apply_now' => 'Weka ada za kuchelewa sasa',
    'stats' => [
        'active_policies' => 'Sera Zinazotumika',
        'fees_this_month' => 'Ada za Mwezi Huu',
        'total_applied' => 'Jumla Iliyowekwa',
        'total_waived' => 'Jumla Iliyosamehewa',
    ],
    'policies' => [
        'title' => 'Sera za Ada za Kuchelewa',
        'subtitle' => 'Sanidi kanuni za ada za kuchelewa kiotomatiki kwa ankara zilizochelewa',
        'add' => 'Ongeza Sera',
    ],
    'form' => [
        'name' => 'Jina la Sera *',
        'name_placeholder' => 'mf., Ada ya Kuchelewa ya Kawaida',
        'property' => 'Mali (Si Lazima)',
        'property_all' => 'Mali Zote (Chaguo-msingi)',
        'building' => 'Jengo (Si Lazima)',
        'building_all' => 'Majengo Yote',
        'grace_period' => 'Kipindi cha Neema (siku) *',
        'grace_period_hint' => 'Siku baada ya tarehe ya mwisho kabla ya ada kutumika',
        'fee_type' => 'Aina ya Ada *',
        'fee_type_percentage' => 'Asilimia (%)',
        'fee_type_fixed' => 'Kiasi Kisichobadilika ({currency})',
        'fee_percentage' => 'Asilimia ya Ada *',
        'fee_amount' => 'Kiasi cha Ada *',
        'max_fee_cap' => 'Kikomo cha Juu cha Ada (Si Lazima)',
        'max_fee_cap_placeholder' => 'Hakuna kikomo',
        'compounding' => 'Mlundikano (weka ada mara nyingi)',
        'frequency' => 'Marudio:',
        'frequency_daily' => 'Kila Siku',
        'frequency_weekly' => 'Kila Wiki',
        'frequency_monthly' => 'Kila Mwezi',
        'active' => 'Inatumika',
        'cancel' => 'Ghairi',
        'saving' => 'Inahifadhi...',
        'update' => 'Sasisha Sera',
        'create' => 'Unda Sera',
    ],
    'empty' => [
        'title' => 'Hakuna sera za ada za kuchelewa',
        'subtitle' => 'Anza kwa kuunda sera ya ada ya kuchelewa.',
        'add_first' => 'Ongeza Sera Yako ya Kwanza',
    ],
    'list' => [
        'status_active' => 'Inatumika',
        'status_inactive' => 'Haitumiki',
        'grace_period' => 'kipindi cha neema cha siku {days}',
        'compounds' => '| Hulundikana {frequency}',
        'max' => '| Kikomo {amount}',
        'deactivate' => 'Zima',
        'activate' => 'Washa',
        'edit' => 'Hariri',
        'delete' => 'Futa',
    ],
    'delete' => [
        'title' => 'Futa Sera',
        'confirm' => 'Una uhakika unataka kufuta "{name}"? Kitendo hiki hakiwezi kutenduliwa.',
        'cancel' => 'Ghairi',
        'confirm_btn' => 'Futa',
    ],
];
