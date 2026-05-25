<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: finances hub deposits tab. Mirror en/sw/ar.
 */
return [
    'metric' => [
        'total' => 'إجمالي الودائع',
        'held' => 'المحتجزة حاليًا',
        'refunded' => 'المستردة',
        'forfeited' => 'المصادرة',
    ],
    'search_placeholder' => 'البحث في الودائع...',
    'empty' => [
        'title' => 'لا توجد ودائع',
        'description' => 'ستظهر ودائع الضمان هنا',
    ],
    'column' => [
        'tenant' => 'المستأجر',
        'unit' => 'الوحدة',
        'amount' => 'المبلغ',
        'status' => 'الحالة',
        'collected' => 'تاريخ التحصيل',
    ],
    'status' => [
        'held' => 'محتجزة',
        'refunded' => 'مستردة',
        'forfeited' => 'مصادرة',
        'partial_refund' => 'استرداد جزئي',
    ],
    'status_label' => [
        'held' => 'محتجزة',
        'refunded' => 'مستردة',
        'forfeited' => 'مصادرة',
        'partial' => 'جزئي',
    ],
    'refunded_amount' => 'المستردة: {amount}',
    'processed' => 'تمت المعالجة: {date}',
    'action' => [
        'refund' => 'استرداد الوديعة',
        'forfeit' => 'مصادرة الوديعة',
    ],
    'transaction_history' => 'سجل المعاملات',
    'transaction_history_for' => 'سجل المعاملات - {tenant} ({unit})',
    'by' => 'بواسطة {name}',
];
