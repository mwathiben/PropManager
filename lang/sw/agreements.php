<?php

declare(strict_types=1);

/**
 * Slice-2 PR-2.2: management-agreement composer. Mirror en/sw/ar.
 */
return [
    'index' => [
        'title' => 'Mikataba ya usimamizi',
        'subtitle' => 'Mikataba unayosimamia kwa niaba ya wamiliki.',
        'new' => 'Mkataba mpya',
        'none' => 'Bado hakuna mikataba. Tunga wa kwanza kuweka masharti ya mmiliki.',
        'owner' => 'Mmiliki',
        'status' => 'Hali',
        'created' => 'Imeundwa',
    ],
    'compose' => [
        'title' => 'Mkataba mpya wa usimamizi',
        'owner' => 'Mmiliki wa mali',
        'owner_placeholder' => 'Chagua mmiliki...',
        'clauses' => 'Vifungu',
        'clauses_hint' => 'Chagua masharti. Kila kimoja kimeelezwa; kifungu cha ada huweka unachotoza.',
        'include' => 'Jumuisha',
        'required_clause' => 'Inahitajika',
        'fee_type' => 'Aina',
        'fee_base' => 'Msingi',
        'fee_value' => 'Thamani',
        'fee_cadence' => 'Mzunguko',
        'preview' => 'Onyesho la mkataba',
        'preview_empty' => 'Chagua vifungu kuona mkataba.',
        'submit' => 'Hifadhi rasimu',
        'cancel' => 'Ghairi',
        'fee_options' => [
            'percentage' => 'Asilimia',
            'flat' => 'Kiwango maalum',
            'collected' => 'Iliyokusanywa',
            'billed' => 'Iliyotozwa',
            'scheduled' => 'Iliyopangwa',
            'per_period' => 'Kwa kipindi',
            'per_unit' => 'Kwa kila nyumba',
        ],
    ],
    'show' => [
        'owner' => 'Mmiliki',
        'status' => 'Hali',
        'hash' => 'Alama ya hati',
        'back' => 'Rudi kwa mikataba',
        'draft_note' => 'Rasimu — bado haijatumwa kwa mmiliki kusaini.',
    ],
    'status' => [
        'draft' => 'Rasimu',
        'sent' => 'Imetumwa',
        'signed' => 'Imesainiwa',
        'active' => 'Inatumika',
        'amending' => 'Inarekebishwa',
        'terminated' => 'Imesitishwa',
    ],
    'draft_created' => 'Rasimu ya mkataba imeundwa.',
    'errors' => [
        'duplicate_binding' => 'Mkataba unaweza kujumuisha kila aina ya kifungu mara moja tu.',
        'invalid_fee' => 'Masharti ya ada ya usimamizi si sahihi — angalia aina na thamani.',
        'missing_param' => 'Jaza maelezo ya ":field" kwa kifungu hiki.',
        'invalid_option' => 'Thamani ya ":field" hairuhusiwi kwa kifungu hiki.',
    ],
];
