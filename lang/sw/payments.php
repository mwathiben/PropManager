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
    'methods' => [
        'saved_cards_heading' => 'Njia za malipo zilizohifadhiwa',
        'add_card_button' => 'Ongeza kadi',
        'last4_label' => 'Kadi inayoishia kwa :last4',
        'brand_label' => 'Aina ya kadi',
        'default_badge' => 'Chaguo-msingi',
        'remove_card_button' => 'Ondoa kadi',
        'setup_intent_failed' => 'Imeshindwa kuanzisha usanidi wa kadi — jaribu tena.',
        'card_attached_flash' => 'Kadi imehifadhiwa.',
        'card_removed_flash' => 'Kadi imeondolewa.',
    ],
    'cart' => [
        'heading' => 'Kikapu cha malipo',
        'total_label' => 'Jumla',
        'currency_section_heading' => 'Lipa kwa :currency',
        'confirm_payment_button' => 'Thibitisha malipo',
        'payment_succeeded_message' => 'Malipo yamefanikiwa.',
        'payment_failed_message' => 'Malipo yameshindwa. Jaribu tena.',
        'expired_session_message' => 'Kipindi hiki cha malipo kimeisha au kimeshakamilika.',
        'line_description' => 'Kikapu :session — kundi la :currency',
        'unsupported_currency_pair' => 'Hakuna lango la malipo kwa sarafu :currency.',
    ],
    'plan_sync' => [
        'drift_mode_label' => 'Hali ya kutatua mteremko',
        'manual_review_option' => 'Ukaguzi wa mwongozo',
        'always_app_wins_option' => 'Programu inashinda kila wakati',
        'always_stripe_wins_option' => 'Stripe inashinda kila wakati',
        'drift_history_heading' => 'Matukio ya hivi karibuni ya mteremko',
        'drift_resolved_badge' => 'Imetatuliwa',
        'drift_pending_badge' => 'Inasubiri',
        'drift_manual_pending_badge' => 'Inasubiri ukaguzi wa mwongozo',
        'drift_mode_updated_flash' => 'Hali ya kutatua mteremko kwa :plan imesasishwa.',
    ],
    'tax' => [
        'vat_heading' => 'VAT na usajili wa kodi',
        'vat_label' => 'VAT',
        'vat_rate_label' => 'Kiwango cha VAT',
        'vat_amount_label' => 'Kiasi cha VAT',
        'kra_pin_label' => 'Nambari ya KRA PIN',
        'kra_pin_placeholder' => 'mfano A001234567Z',
        'vat_rate_override_label' => 'Badilisha kiwango cha VAT (basis points)',
        'stripe_tax_enabled_label' => 'Wezesha kodi ya Stripe kiotomatiki kwa malipo yasiyo ya KES',
        'vat_inclusive_disclaimer' => 'Jumla ya ankara inajumuisha VAT ya 16% ya Kenya pale inapostahili.',
        'kra_pin_missing_warning' => 'Nambari ya KRA PIN haijawekwa — wenye nyumba waliosajili VAT lazima wawe nayo kabla ya kutuma ankara.',
        'kra_pin_invalid_format' => 'KRA PIN lazima ifuate muundo A### au P###. Mfano: A001234567Z.',
        'updated_flash' => 'Mipangilio ya kodi kwa :name imesasishwa.',
    ],
];
