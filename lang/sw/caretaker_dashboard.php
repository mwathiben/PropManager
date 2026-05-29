<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: caretaker home dashboard. Mirror en/sw/ar.
 */
return [
    'page_title' => 'Dashibodi ya Mlinzi',
    'header' => [
        'property_fallback' => 'Mali',
        'property_operations' => 'Shughuli za {name}',
        'buildings_assigned' => 'Majengo {count} Yaliyokabidhiwa',
        'record_readings' => 'Rekodi Visomo',
    ],
    'action_items' => [
        'urgent_tickets_title' => 'Tiketi za Dharura',
        'urgent_tickets_description' => 'Zinahitaji uangalifu wa haraka',
        'no_urgent_title' => 'Hakuna Masuala ya Dharura',
        'no_urgent_description' => 'Tiketi zote za dharura zimetatuliwa',
        'open_tickets_title' => 'Tiketi Zilizo Wazi',
        'open_tickets_description' => 'Zinasubiri kutatuliwa',
        'no_open_title' => 'Hakuna Tiketi Zilizo Wazi',
        'no_open_description' => 'Tiketi zote zimetatuliwa',
        'pending_readings_title' => 'Visomo Vinavyosubiri',
        'pending_readings_description' => 'Vinasubiri kuingizwa',
        'total_units_title' => 'Jumla ya Vyumba',
        'total_units_subtitle' => '{count} vinavyokaliwa',
        'action_view' => 'Tazama',
        'action_view_all' => 'Tazama Zote',
        'action_input' => 'Ingiza',
    ],
    'tasks' => [
        'heading' => 'Kazi za Leo',
        'subtitle' => 'Tiketi ulizopangiwa zilizopangwa kwa kipaumbele',
        'view_all' => 'Tazama Zote',
        'empty_title' => 'Umemaliza yote!',
        'empty_subtitle' => 'Hakuna kazi ulizopangiwa',
        'unit_label' => 'Chumba {number} •',
    ],
    'quick_actions' => [
        'heading' => 'Vitendo vya Haraka',
        'input_readings_title' => 'Ingiza Visomo vya Maji',
        'input_readings_subtitle' => 'Rekodi visomo vya mita kila mwezi',
        'view_tickets_title' => 'Tazama Tiketi Zangu',
        'view_tickets_subtitle' => 'tiketi {count} zilizo wazi',
        'report_issue_title' => 'Ripoti Suala Jipya',
        'report_issue_subtitle' => 'Tengeneza tiketi ya matengenezo',
    ],
    'unit_status' => [
        'heading' => 'Muhtasari wa Hali ya Vyumba',
        'occupied' => 'Vinavyokaliwa',
        'vacant' => 'Vitupu',
        'maintenance' => 'Matengenezo',
        'total_units' => 'Jumla ya Vyumba',
    ],
    'ticket_summary' => [
        'heading' => 'Muhtasari wa Tiketi Zangu',
        'resolved' => 'Zilizotatuliwa',
        'open' => 'Zilizo Wazi',
        'total_assigned' => 'Jumla Niliyopangiwa',
    ],
    'landlord_contact' => [
        'heading' => 'Mawasiliano ya Mwenye Nyumba',
    ],
];
