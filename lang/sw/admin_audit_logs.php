<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: admin (super-admin) audit-log viewer page.
 * Distinct from the landlord-facing `activity_logs` namespace.
 * Mirror en/sw/ar.
 */
return [
    'title' => 'Kumbukumbu za Ukaguzi',
    'export_csv' => 'Hamisha CSV',
    'filters' => [
        'search' => 'Tafuta',
        'search_placeholder' => 'Tafuta...',
        'event_type' => 'Aina ya Tukio',
        'all_events' => 'Matukio Yote',
        'model_type' => 'Aina ya Modeli',
        'all_models' => 'Modeli Zote',
        'from_date' => 'Kuanzia Tarehe',
        'to_date' => 'Hadi Tarehe',
        'clear' => 'Futa Vichujio',
        'apply' => 'Tumia Vichujio',
    ],
    'columns' => [
        'datetime' => 'Tarehe/Saa',
        'user' => 'Mtumiaji',
        'event' => 'Tukio',
        'model' => 'Modeli',
        'changes' => 'Mabadiliko',
        'ip' => 'IP',
        'actions' => 'Vitendo',
    ],
    'system_user' => 'Mfumo',
    'view_details' => 'Tazama Maelezo',
    'empty' => [
        'title' => 'Hakuna kumbukumbu za ukaguzi zilizopatikana',
        'body' => 'Rekebisha vichujio vyako hapo juu. Kumbukumbu za ukaguzi huzalishwa kiotomatiki wakati watumiaji wanapotenda kwenye rekodi.',
    ],
];
