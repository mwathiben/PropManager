<?php

declare(strict_types=1);

return [
    'gateways' => [
        'paystack_label' => 'Paystack (KES nchini)',
        'stripe_label' => 'Stripe (USD/EUR/GBP)',
        'mpesa_label' => 'M-Pesa',
        'auto_label' => 'otomatiki (chagua kwa sarafu)',
    ],
    'preference' => [
        'heading' => 'Mapendeleo ya lango la malipo',
        'helper' => 'Chagua lango maalum, au "otomatiki" ili mfumo uchague (Paystack kwa KES, Stripe vinginevyo).',
        'updated_flash' => 'Mapendeleo yamesasishwa.',
        'invalid_value' => 'Chagua paystack, stripe, au otomatiki.',
    ],
    'reconcile' => [
        'drift_alert_heading' => 'Mteremko wa rekodi za lango',
        'drift_alert_helper' => 'Endesha payments:gateway-reconcile na ukague hesabu ya mteremko kwa kila mwenye nyumba.',
    ],
    'webhook' => [
        'invalid_signature' => 'Webhook imekataliwa — sahihi haifanani.',
        'not_configured' => 'Webhook imekataliwa — siri haijasanidiwa.',
        'duplicate_event' => 'Tukio dupli — tayari limeshughulikiwa.',
    ],
    'currency' => [
        'kes_to_paystack_hint' => 'Malipo ya KES yanaenda Paystack.',
        'usd_to_stripe_hint' => 'Malipo ya USD/EUR/GBP yanaenda Stripe.',
    ],
];
