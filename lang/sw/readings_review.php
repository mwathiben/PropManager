<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: ukurasa wa kukagua visomo vya maji. Mirror en/sw/ar.
 */
return [
    'title' => 'Kagua Visomo vya Maji',
    'pending_count' => 'Visomo {count} vinasubiri kuidhinishwa',
    'filters' => [
        'building' => 'Jengo',
        'all_buildings' => 'Majengo Yote',
        'date_from' => 'Tarehe Kuanzia',
        'date_to' => 'Tarehe Hadi',
        'apply' => 'Tumia',
        'reset' => 'Weka Upya',
    ],
    'empty' => [
        'title' => 'Hakuna visomo vinavyosubiri kukaguliwa',
        'body' => 'Visomo vyote vimeidhinishwa au kukataliwa',
    ],
    'card' => [
        'meter_photo' => 'Picha ya Mita',
        'meter_photo_alt' => 'Picha ya Usomaji wa Mita',
        'no_photo' => 'Hakuna picha inayopatikana',
        'reading_details' => 'Maelezo ya Usomaji',
        'unit' => 'Nyumba:',
        'building' => 'Jengo:',
        'reading_date' => 'Tarehe ya Usomaji:',
        'recorded_by' => 'Imerekodiwa na:',
        'consumption_cost' => 'Matumizi na Gharama',
        'previous_reading' => 'Usomaji wa Awali:',
        'manual_reading' => 'Usomaji wa Mkono:',
        'ocr_reading' => 'Usomaji wa OCR:',
        'verified' => 'Imethibitishwa',
        'diff' => 'Tofauti: {value}',
        'consumption' => 'Matumizi:',
        'consumption_value' => 'vipimo {units}',
        'cost' => 'Gharama:',
    ],
    'actions' => [
        'approve' => 'Idhinisha',
        'reject' => 'Kataa',
    ],
    'pagination' => [
        'showing' => 'Inaonyesha {from} hadi {to} kati ya visomo {total}',
    ],
    'modal' => [
        'unit' => 'Nyumba:',
        'reading' => 'Usomaji:',
        'cost' => 'Gharama:',
        'cancel' => 'Ghairi',
    ],
    'approve' => [
        'title' => 'Idhinisha Usomaji wa Maji',
        'notes_label' => 'Maelezo (Si Lazima)',
        'notes_placeholder' => 'Ongeza maelezo yoyote kuhusu uidhinishaji huu...',
        'processing' => 'Inaidhinisha...',
        'confirm' => 'Thibitisha Uidhinishaji',
    ],
    'reject' => [
        'title' => 'Kataa Usomaji wa Maji',
        'reason_label' => 'Sababu ya Kukataa',
        'reason_placeholder' => 'Eleza kwa nini usomaji huu unakataliwa...',
        'reason_required' => 'Tafadhali toa sababu ya kukataa',
        'processing' => 'Inakataa...',
        'confirm' => 'Thibitisha Kukataa',
    ],
];
