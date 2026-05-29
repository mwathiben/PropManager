<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: settings payout accounts page (Settings/PayoutAccounts).
 * Tenant/landlord payout destinations (M-Pesa, bank, etc.). Mirror en/sw/ar.
 */
return [
    'title' => 'Akaunti za Malipo',
    'header' => 'Akaunti za Malipo',
    'add_account' => 'Ongeza Akaunti',
    'fee_banner' => [
        'heading' => 'Taarifa ya Ada ya Mfumo',
        'billing_model_label' => 'Mtindo wa sasa wa bili:',
        'fee_label' => 'Ada ya mfumo:',
        'fee_per_transaction_suffix' => 'kwa kila muamala',
        'description' => 'Unganisha akaunti yako ya benki ili upokee malipo moja kwa moja. Ada ya mfumo itakatwa kiotomatiki.',
    ],
    'billing_models' => [
        'transaction_fee' => 'Ada ya Muamala',
        'subscription' => 'Usajili',
        'hybrid' => 'Mchanganyiko',
    ],
    'alert' => [
        'heading' => 'Akaunti ya Malipo Inahitajika',
        'description' => 'Unahitaji kuunganisha akaunti ya malipo iliyothibitishwa kabla ya wapangaji kufanya malipo mtandaoni.',
    ],
    'badge' => [
        'primary' => 'Msingi',
    ],
    'actions' => [
        'set_primary' => 'Weka kama akaunti kuu ya malipo',
        'sync_status' => 'Sawazisha hali ya akaunti',
        'deactivate' => 'Zima akaunti ya malipo',
    ],
    'empty' => [
        'title' => 'Hakuna akaunti za malipo',
        'description' => 'Anza kwa kuunganisha akaunti yako ya benki.',
        'action' => 'Ongeza Akaunti',
    ],
    'modal' => [
        'title' => 'Ongeza Akaunti ya Malipo',
        'business_name' => 'Jina la Biashara',
        'business_name_placeholder' => 'Jina la biashara au mali yako',
        'bank' => 'Benki',
        'select_bank' => 'Chagua benki',
        'loading_banks' => 'Inapakia benki...',
        'account_number' => 'Nambari ya Akaunti',
        'account_number_placeholder' => 'Ingiza nambari ya akaunti',
        'verify' => 'Thibitisha',
        'verifying' => 'Inathibitisha...',
        'verified_heading' => 'Akaunti Imethibitishwa',
        'cancel' => 'Ghairi',
        'adding' => 'Inaongeza...',
        'submit' => 'Ongeza Akaunti',
    ],
    'confirm' => [
        'deactivate' => 'Una uhakika unataka kuzima akaunti hii ya malipo?',
    ],
    'errors' => [
        'verify_failed' => 'Imeshindwa kuthibitisha akaunti',
        'verify_exception' => 'Uthibitishaji wa akaunti umeshindwa',
    ],
];
