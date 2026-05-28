<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: standalone PaymentVerifications/Index page. Mirror en/sw/ar.
 */
return [
    'head_title' => 'Uthibitishaji wa Malipo',
    'header' => [
        'title' => 'Uthibitishaji wa Malipo',
        'subtitle' => 'Kagua na uidhinishe malipo mapya ya wapangaji',
        'awaiting_review_badge' => '{count} yanasubiri ukaguzi',
    ],
    'filters' => [
        'search_placeholder' => 'Tafuta kwa jina la mpangaji...',
    ],
    'status_options' => [
        'all' => 'Hali Zote',
        'awaiting_review' => 'Inasubiri Ukaguzi',
        'pending_payment' => 'Malipo Yanayosubiri',
        'verified' => 'Imethibitishwa',
        'rejected' => 'Imekataliwa',
    ],
    'table' => [
        'tenant' => 'Mpangaji',
        'unit' => 'Kitengo',
        'total_required' => 'Jumla Inayohitajika',
        'status' => 'Hali',
        'submitted' => 'Iliwasilishwa',
        'documents' => 'Nyaraka',
        'actions' => 'Vitendo',
    ],
    'unknown_tenant' => 'Haijulikani',
    'actions' => [
        'view' => 'Tazama',
    ],
    'empty' => 'Hakuna uthibitishaji wa malipo uliopatikana',
];
