<?php

declare(strict_types=1);

return [
    'filters' => [
        'all_buildings' => 'Majengo Yote',
        'all_status' => 'Hali Zote',
        'pending' => 'Inasubiri',
        'approved' => 'Imeidhinishwa',
        'invoiced' => 'Imetolewa Ankara',
        'clear' => 'Futa vichujio',
    ],
    'table' => [
        'unit' => 'Nyumba',
        'reading' => 'Usomaji',
        'date' => 'Tarehe',
        'status' => 'Hali',
    ],
    'unit_prefix' => 'Nyumba {number}',
    'status' => [
        'pending' => 'Inasubiri',
        'approved' => 'Imeidhinishwa',
        'invoiced' => 'Imetolewa Ankara',
    ],
    'empty' => [
        'title' => 'Hakuna usomaji uliopatikana',
        'description_filtered' => 'Jaribu kurekebisha vichujio vyako.',
        'description_default' => 'Usomaji wa mita utaonekana hapa baada ya kurekodiwa.',
    ],
    'pagination' => [
        'showing' => 'Inaonyesha {from} hadi {to} kati ya matokeo {total}',
    ],
];
