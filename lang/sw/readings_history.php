<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: water-meter reading history page. Mirror en/sw/ar.
 */
return [
    'title' => 'Historia ya Usomaji wa Maji',
    'heading' => 'Historia ya Usomaji wa Maji',
    'add_readings' => 'Ongeza Usomaji',
    'filters' => [
        'title' => 'Vichujio',
        'building' => 'Jengo',
        'all_buildings' => 'Majengo Yote',
        'unit' => 'Kitengo',
        'all_units' => 'Vitengo Vyote',
        'from_date' => 'Kuanzia Tarehe',
        'to_date' => 'Hadi Tarehe',
        'status' => 'Hali',
        'all' => 'Zote',
        'not_invoiced' => 'Haijatolewa Ankara',
        'invoiced' => 'Imetolewa Ankara',
        'apply' => 'Tumia Vichujio',
        'clear' => 'Futa',
    ],
    'table' => [
        'date' => 'Tarehe',
        'unit' => 'Kitengo',
        'previous' => 'Iliyopita',
        'current' => 'Ya Sasa',
        'consumption' => 'Matumizi',
        'cost' => 'Gharama',
        'status' => 'Hali',
        'actions' => 'Vitendo',
    ],
    'cost_na' => 'Haipo',
    'status' => [
        'invoiced' => 'Imetolewa Ankara',
        'pending' => 'Inasubiri',
    ],
    'actions' => [
        'save' => 'Hifadhi',
        'cancel' => 'Ghairi',
        'edit' => 'Hariri',
        'delete' => 'Futa',
        'locked' => 'Imefungwa',
    ],
    'pagination' => [
        'showing' => 'Inaonyesha {from} hadi {to} kati ya {total} usomaji',
    ],
    'empty' => [
        'title' => 'Hakuna usomaji uliopatikana',
        'description' => 'Jaribu kurekebisha vichujio vyako au ongeza usomaji mpya.',
    ],
    'confirm' => [
        'delete' => 'Una uhakika unataka kufuta usomaji huu?',
    ],
];
