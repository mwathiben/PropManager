<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: bank reconciliation tab. Mirror en/sw/ar.
 */
return [
    'heading' => 'Ulinganishaji wa Benki',
    'subtitle' => 'Ingiza taarifa za benki na ulinganishe miamala na ankara',
    'auto_match_all' => 'Linganisha Vyote Kiotomatiki',
    'import_statement' => 'Ingiza Taarifa',
    'paystack' => [
        'heading' => 'Ulinganishaji wa Paystack',
        'last_run_failed' => 'Mwendo wa mwisho umeshindwa: {message}',
        'status' => [
            'failed' => 'Imeshindwa',
            'discrepancies' => 'Tofauti {count}',
            'clean' => 'Safi',
        ],
        'matched' => 'Zilizolinganishwa',
        'local' => 'Za Hapa',
        'remote' => 'Za Mbali',
        'discrepancies' => 'Tofauti',
    ],
    'import' => [
        'heading' => 'Ingiza Taarifa ya Benki',
        'bank_label' => 'Benki',
        'bank_placeholder' => 'Chagua benki...',
        'file_label' => 'Faili la CSV/Excel',
        'file_hint' => 'Upeo 5MB. Zinazokubalika: CSV, XLSX, XLS',
        'column_mapping_toggle' => 'Uunganishaji wa Safu (Si Lazima)',
        'column_mapping_hint' => 'Bainisha majina ya safu ikiwa yanatofautiana na chaguo-msingi (reference, amount, date, description)',
        'reference_column' => 'Safu ya Kumbukumbu',
        'amount_column' => 'Safu ya Kiasi',
        'date_column' => 'Safu ya Tarehe',
        'description_column' => 'Safu ya Maelezo',
        'cancel' => 'Ghairi',
        'importing' => 'Inaingiza...',
        'submit' => 'Ingiza',
    ],
    'banks' => [
        'equity' => 'Benki ya Equity',
        'kcb' => 'Benki ya KCB',
        'coop' => 'Benki ya Co-operative',
        'stanbic' => 'Benki ya Stanbic',
        'absa' => 'Benki ya Absa',
        'ncba' => 'Benki ya NCBA',
        'dtb' => 'Benki ya DTB',
        'i_and_m' => 'Benki ya I&M',
        'family' => 'Benki ya Family',
        'other' => 'Benki Nyingine',
    ],
    'stats' => [
        'pending' => 'Inasubiri',
        'unmatched' => 'Hazijalinganishwa',
        'matched' => 'Zimelinganishwa',
        'unmatched_amount' => 'Kiasi Kisicholinganishwa',
    ],
    'pending' => [
        'heading' => 'Ulinganishaji Unaosubiri',
        'body' => 'Una malipo {count} yanayohitaji kulinganishwa na ankara.',
    ],
    'reconciled' => [
        'heading' => 'Vyote Vimelinganishwa',
        'body' => 'Malipo yote yamelinganishwa na ankara. Ingiza taarifa ya benki ili kulinganisha miamala mipya.',
    ],
    'table' => [
        'reference' => 'Kumbukumbu',
        'tenant' => 'Mpangaji',
        'amount' => 'Kiasi',
        'method' => 'Njia',
        'date' => 'Tarehe',
        'empty_title' => 'Hakuna malipo yasiyolinganishwa',
        'empty_description' => 'Ingiza taarifa ya benki ili kuanza kulinganisha miamala',
        'match' => 'Linganisha',
    ],
    'placeholders' => [
        'reference' => 'kumbukumbu',
        'amount' => 'kiasi',
        'date' => 'tarehe',
        'description' => 'maelezo',
    ],
    'fallback' => [
        'unknown_tenant' => 'Haijulikani',
        'no_unit' => 'Haipo',
    ],
];
