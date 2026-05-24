<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: notifications setup wizard component. Mirror en/sw/ar.
 */
return [
    'steps' => [
        'welcome' => 'Karibu',
        'channels' => 'Chagua Njia',
        'email' => 'Usanidi wa Barua Pepe',
        'sms' => 'Usanidi wa SMS',
        'whatsapp' => 'Usanidi wa WhatsApp',
        'push' => 'Usanidi wa Arifa za Kusukuma',
        'complete' => 'Yote Yamekamilika!',
    ],
    'channel_options' => [
        'email_name' => 'Barua Pepe',
        'email_desc' => 'Tuma kupitia SMTP au huduma ya barua pepe',
        'sms_name' => 'SMS',
        'sms_desc' => 'Ujumbe wa maandishi kupitia AT au Twilio',
        'whatsapp_name' => 'WhatsApp',
        'whatsapp_desc' => 'Ujumbe kupitia Twilio WhatsApp',
        'push_name' => 'Kusukuma',
        'push_desc' => 'Arifa za kusukuma za kivinjari',
    ],
    'header' => [
        'step_progress' => 'Hatua {current} kati ya {total}',
    ],
    'welcome' => [
        'heading' => 'Karibu kwenye Arifa',
        'intro' => 'Hebu tusanidi njia zako za arifa. Utaweza kutuma vikumbusho vya kodi, ilani za madeni, na zaidi kupitia Barua Pepe, SMS, WhatsApp, na arifa za Kusukuma.',
        'guide' => 'Mchawi huyu atakuongoza katika kusanidi kila njia. Unaweza kuruka njia yoyote na kuisanidi baadaye kutoka kichupo cha Mipangilio.',
    ],
    'channels' => [
        'intro' => 'Chagua njia za arifa unazotaka kusanidi. Unaweza kuongeza zaidi wakati wowote baadaye.',
    ],
    'email' => [
        'intro' => 'Sanidi mipangilio yako ya barua pepe kwa kutuma arifa.',
        'mail_driver' => 'Kiendeshi cha Barua',
        'encryption' => 'Usimbaji Fiche',
        'driver_smtp' => 'SMTP',
        'driver_mailgun' => 'Mailgun',
        'driver_postmark' => 'Postmark',
        'driver_ses' => 'Amazon SES',
        'encryption_tls' => 'TLS',
        'encryption_ssl' => 'SSL',
        'encryption_none' => 'Hakuna',
        'smtp_host' => 'Mwenyeji wa SMTP',
        'smtp_port' => 'Mlango wa SMTP',
        'username' => 'Jina la Mtumiaji',
        'password' => 'Nenosiri',
        'from_address' => 'Anwani ya Mtumaji',
        'from_name' => 'Jina la Mtumaji',
        'from_name_placeholder' => 'Property Manager',
    ],
    'sms' => [
        'intro' => 'Sanidi mtoa huduma wako wa SMS kwa kutuma ujumbe wa maandishi.',
        'provider' => 'Mtoa Huduma wa SMS',
        'provider_africastalking' => "Africa's Talking",
        'provider_twilio' => 'Twilio',
        'username' => 'Jina la Mtumiaji',
        'username_placeholder' => 'sandbox au jina lako la mtumiaji',
        'api_key' => 'Ufunguo wa API',
        'sender_id' => 'Kitambulisho cha Mtumaji (Si lazima)',
        'sender_id_placeholder' => 'Kitambulisho chako cha mtumaji kilichoidhinishwa',
        'account_sid' => 'Akaunti SID',
        'auth_token' => 'Tokeni ya Uthibitishaji',
        'from_number' => 'Namba ya Mtumaji',
    ],
    'whatsapp' => [
        'intro' => 'Sanidi Twilio WhatsApp kwa kutuma ujumbe.',
        'account_sid' => 'Akaunti SID',
        'auth_token' => 'Tokeni ya Uthibitishaji',
        'from_number' => 'Namba ya Mtumaji ya WhatsApp',
        'sandbox_hint' => 'Tumia namba yako ya sandbox ya Twilio WhatsApp kwa majaribio',
    ],
    'push' => [
        'intro' => 'Sanidi arifa za Web Push.',
        'vapid_required' => 'Funguo za VAPID Zinahitajika',
        'vapid_explainer' => 'Web Push inahitaji funguo za VAPID kwa uthibitishaji. Bofya hapa chini kuzalisha funguo kiotomatiki.',
        'generate_keys' => 'Zalisha Funguo za VAPID',
        'vapid_subject' => 'Mada ya VAPID (Barua Pepe)',
        'vapid_subject_hint' => 'Lazima iwe URL ya mailto: au URL ya https://',
    ],
    'complete' => [
        'heading' => 'Uko Tayari Kabisa!',
        'body' => 'Njia zako za arifa zimesanidiwa. Sasa unaweza kutuma vikumbusho vya kodi, ilani za madeni, na arifa zingine kwa wapangaji wako.',
        'footer' => 'Unaweza kubadilisha mipangilio hii wakati wowote kutoka kichupo cha Mipangilio.',
    ],
    'footer' => [
        'back' => 'Nyuma',
        'skip' => 'Ruka njia hii',
        'get_started' => 'Anza',
        'continue' => 'Endelea',
        'complete_setup' => 'Kamilisha Usanidi',
    ],
    'alert' => [
        'vapid_generated' => 'Funguo za VAPID zimezalishwa kwa mafanikio!',
        'vapid_failed' => 'Imeshindwa kuzalisha funguo za VAPID: {error}',
    ],
];
