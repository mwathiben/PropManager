<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: caretaker work-queue (maintenance tickets) page. Mirror en/sw/ar.
 */
return [
    'title' => 'Tiketi Zangu',
    'heading' => 'Foleni Yangu ya Kazi',
    'subtitle' => 'Dhibiti tiketi zilizokabidhiwa kwako',
    'stats' => [
        'urgent' => 'Dharura',
        'open' => 'Wazi',
        'in_progress' => 'Inaendelea',
        'resolved' => 'Imetatuliwa',
    ],
    'filter_label' => 'Chuja:',
    'all_statuses' => 'Hali Zote',
    'active_option' => 'Inayoendelea (Wazi/Inaendelea)',
    'all_priorities' => 'Vipaumbele Vyote',
    'unit_prefix' => '- Kipengele {number}',
    'reported_by' => 'Iliripotiwa na {name}',
    'unknown_reporter' => 'Haijulikani',
    'view' => 'Tazama',
    'acknowledge' => 'Thibitisha',
    'start_work' => 'Anza Kazi',
    'resolve' => 'Tatua',
    'empty' => [
        'title' => 'Umemaliza yote!',
        'description' => 'Hakuna tiketi kwenye foleni yako zinazolingana na vichujio vya sasa.',
    ],
    'pagination' => 'Inaonyesha {from} hadi {to} kati ya {total} tiketi',
    'time_ago' => [
        'days' => 'siku {count} zilizopita',
        'hours' => 'saa {count} zilizopita',
        'just_now' => 'Sasa hivi',
    ],
    'resolve_modal' => [
        'title' => 'Tatua Tiketi',
        'notes_label' => 'Maelezo ya Utatuzi',
        'notes_placeholder' => 'Eleza kilichofanyika kutatua tatizo hili...',
        'resolving' => 'Inatatua...',
        'submit' => 'Weka Alama Imetatuliwa',
        'cancel' => 'Ghairi',
    ],
];
