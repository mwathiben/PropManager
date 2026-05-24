<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: finances invoice/receipt/credit-note templates tab. Mirror en/sw/ar.
 */
return [
    'invoices' => [
        'heading' => 'Violezo vya Ankara',
        'subtitle' => 'Badilisha jinsi ankara zako zinavyoonekana zinapotumwa kwa wapangaji',
        'empty_title' => 'Bado hakuna violezo vya ankara',
        'empty_description' => 'Tengeneza kiolezo chako cha kwanza ili kubadilisha jinsi ankara zako zinavyoonekana.',
    ],
    'receipts' => [
        'heading' => 'Violezo vya Risiti',
        'subtitle' => 'Badilisha jinsi risiti za malipo zinavyoonekana kwa wapangaji',
        'empty_title' => 'Bado hakuna violezo vya risiti',
        'empty_description' => 'Tengeneza kiolezo chako cha kwanza ili kubadilisha jinsi risiti zako za malipo zinavyoonekana.',
    ],
    'credit_notes' => [
        'heading' => 'Violezo vya Noti za Mikopo',
        'subtitle' => 'Noti za mikopo hutumia kiolezo chako cha ankara na mabadiliko',
        'inheritance' => 'Urithi wa Kiolezo',
        'using_invoice_template' => 'Kutumia Kiolezo cha Ankara',
        'inherit_body' => 'Noti za mikopo hurithi kiotomatiki mipangilio ya kiolezo chako chaguo-msingi cha ankara. Kichwa hubadilishwa kuwa "NOTI YA MKOPO" na kiasi huonyeshwa kama thamani hasi.',
        'current_default' => 'Kiolezo Chaguo-msingi cha Sasa:',
        'none_selected' => 'Hakuna kilichochaguliwa',
        'edit_invoice_template' => 'Hariri Kiolezo cha Ankara',
        'no_template_found' => 'Hakuna kiolezo cha ankara kilichopatikana. Tengeneza kiolezo cha ankara kwanza ili kukitumia na noti za mikopo.',
        'create_invoice_template' => 'Tengeneza Kiolezo cha Ankara',
    ],
    'new_template' => 'Kiolezo Kipya',
    'set_default' => 'Weka Chaguo-msingi',
    'edit' => 'Hariri',
    'create_template' => 'Tengeneza Kiolezo',
    'default_badge' => 'Chaguo-msingi',
    'design' => [
        'classic' => 'Asili',
        'modern' => 'Kisasa',
        'minimal' => 'Sahili',
        'professional' => 'Kitaalamu',
    ],
    'features' => [
        'logo' => 'Nembo',
        'bank' => 'Benki',
        'qr' => 'QR',
        'water' => 'Maji',
        'arrears' => 'Madeni',
        'receipt_number' => 'Risiti #',
        'method' => 'Mbinu',
        'tenant' => 'Mpangaji',
        'none' => 'Hakuna nyongeza',
        'separator' => ' • ',
    ],
];
