<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: finance hub reports tab. Mirror en/sw/ar.
 */
return [
    'heading' => 'Ripoti za Kifedha',
    'subheading' => 'Changanua mapato, gharama, na utendaji wa ukusanyaji',
    'filters_button' => 'Vichujio',
    'export_formats' => [
        'xlsx' => 'Excel (.xlsx)',
        'pdf' => 'PDF',
        'csv' => 'CSV',
    ],
    'export_buttons' => [
        'rent_roll' => 'Orodha ya Kodi',
        'property_pnl' => 'Faida na Hasara ya Mali',
    ],
    'periods' => [
        'this_month' => 'Mwezi Huu',
        'last_month' => 'Mwezi Uliopita',
        'this_quarter' => 'Robo Hii',
        'last_quarter' => 'Robo Iliyopita',
        'ytd' => 'Mwaka Hadi Sasa',
        'this_fy' => 'Mwaka Huu wa Fedha',
        'last_fy' => 'Mwaka wa Fedha Uliopita',
        '12' => 'Miezi 12 Iliyopita',
        '6' => 'Miezi 6 Iliyopita',
        '3' => 'Miezi 3 Iliyopita',
        'custom' => 'Kipindi Maalum',
    ],
    'tools' => [
        'builder' => [
            'name' => 'Mjenzi wa Ripoti',
            'desc' => 'Tunga ripoti maalum kutoka kwa data yako',
        ],
        'dashboards' => [
            'name' => 'Dashibodi',
            'desc' => 'Jenga dashibodi kutoka ripoti zilizohifadhiwa + vipimo',
        ],
        'templates' => [
            'name' => 'Violezo',
            'desc' => 'Nakili ripoti iliyochaguliwa ili kuanza',
        ],
        'scheduled' => [
            'name' => 'Zilizopangwa',
            'desc' => 'Tuma ripoti kwa barua pepe kwa ratiba ya mara kwa mara',
        ],
        'shares' => [
            'name' => 'Viungo Vilivyoshirikiwa',
            'desc' => 'Viungo vya kusoma tu kwa ripoti iliyohifadhiwa',
        ],
        'metrics' => [
            'name' => 'Vipimo Maalum',
            'desc' => 'Tunga fomula kama safu zinazotokana',
        ],
    ],
    'filters' => [
        'building' => 'Jengo',
        'all_buildings' => 'Majengo Yote',
        'from' => 'Kuanzia',
        'to' => 'Hadi',
        'compare' => 'Linganisha na kipindi kilichopita',
        'apply' => 'Tumia',
        'clear' => 'Futa',
    ],
    'metrics' => [
        'total_invoiced' => 'Jumla Iliyokatiwa Ankara',
        'total_collected' => 'Jumla Iliyokusanywa',
        'total_expenses' => 'Jumla ya Gharama',
        'avg_collection_rate' => 'Wastani wa Kiwango cha Ukusanyaji',
    ],
    'revenue' => [
        'title' => 'Mapato dhidi ya Gharama',
        'net' => 'Halisi: {amount}',
        'invoiced' => 'Iliyokatiwa Ankara',
        'collected' => 'Iliyokusanywa',
        'expenses' => 'Gharama',
        'invoiced_tooltip' => 'Iliyokatiwa Ankara: {amount}',
        'collected_tooltip' => 'Iliyokusanywa: {amount}',
        'expenses_tooltip' => 'Gharama: {amount}',
        'empty_title' => 'Hakuna data ya mapato',
        'empty_body' => 'Jaribu kurekebisha vichujio au kipindi cha tarehe',
    ],
    'collection' => [
        'title' => 'Mwelekeo wa Kiwango cha Ukusanyaji',
        'target' => 'Lengo 85%',
        'empty_title' => 'Hakuna data ya ukusanyaji',
        'empty_body' => 'Data itaonekana ankara zinapotengenezwa',
    ],
    'occupancy' => [
        'title' => 'Ukaaji kwa Jengo',
        'building' => 'Jengo',
        'units' => 'Vyumba',
        'occupied' => 'Vilivyokaliwa',
        'vacant' => 'Tupu',
        'rate' => 'Kiwango',
        'total' => 'Jumla',
        'empty_title' => 'Hakuna majengo yaliyopatikana',
        'empty_body' => 'Ongeza mali ili kuona data ya ukaaji',
    ],
    'arrears' => [
        'title' => 'Umri wa Madeni',
        'total_outstanding' => 'Jumla ya Madeni',
        'buckets' => [
            'current' => 'Ya Sasa',
            '1-30' => 'Siku 1-30',
            '31-60' => 'Siku 31-60',
            '61-90' => 'Siku 61-90',
            '90+' => 'Siku 90+',
        ],
        'empty_title' => 'Hakuna Madeni Yaliyobaki',
        'empty_body' => 'Ankara zote zimelipwa kwa wakati',
    ],
    'expenses_by_category' => [
        'title' => 'Gharama kwa Kategoria',
        'total' => 'Jumla',
        'expense_count' => 'gharama {count} | gharama {count}',
        'empty_title' => 'Hakuna gharama zilizorekodiwa',
        'empty_body' => 'Gharama zitaonekana hapa zinapoongezwa',
    ],
    'water' => [
        'title' => 'Matumizi ya Maji',
        'units' => 'vipimo',
        'total_cost' => 'gharama jumla {amount}',
        'top_consumers' => 'Watumiaji Wakuu',
        'consumer_units' => 'vipimo {count}',
        'empty' => 'Hakuna data ya matumizi ya maji',
    ],
    'top_units' => [
        'title' => 'Vyumba Vinavyofanya Vizuri Zaidi',
        'on_time' => '{onTime}/{total} kwa wakati',
        'empty_title' => 'Hakuna data ya utendaji',
        'empty_body' => 'Data huonekana ankara zinapotengenezwa',
    ],
];
