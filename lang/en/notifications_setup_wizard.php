<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: notifications setup wizard component. Mirror en/sw/ar.
 */
return [
    'steps' => [
        'welcome' => 'Welcome',
        'channels' => 'Choose Channels',
        'email' => 'Email Setup',
        'sms' => 'SMS Setup',
        'whatsapp' => 'WhatsApp Setup',
        'push' => 'Push Setup',
        'complete' => 'All Done!',
    ],
    'channel_options' => [
        'email_name' => 'Email',
        'email_desc' => 'Send via SMTP or mail service',
        'sms_name' => 'SMS',
        'sms_desc' => 'Text messages via AT or Twilio',
        'whatsapp_name' => 'WhatsApp',
        'whatsapp_desc' => 'Messages via Twilio WhatsApp',
        'push_name' => 'Push',
        'push_desc' => 'Browser push notifications',
    ],
    'header' => [
        'step_progress' => 'Step {current} of {total}',
    ],
    'welcome' => [
        'heading' => 'Welcome to Notifications',
        'intro' => "Let's set up your notification channels. You'll be able to send rent reminders, arrears notices, and more via Email, SMS, WhatsApp, and Push notifications.",
        'guide' => 'This wizard will guide you through configuring each channel. You can skip any channel and configure it later from the Settings tab.',
    ],
    'channels' => [
        'intro' => 'Select which notification channels you want to configure. You can always add more later.',
    ],
    'email' => [
        'intro' => 'Configure your email settings for sending notifications.',
        'mail_driver' => 'Mail Driver',
        'encryption' => 'Encryption',
        'driver_smtp' => 'SMTP',
        'driver_mailgun' => 'Mailgun',
        'driver_postmark' => 'Postmark',
        'driver_ses' => 'Amazon SES',
        'encryption_tls' => 'TLS',
        'encryption_ssl' => 'SSL',
        'encryption_none' => 'None',
        'smtp_host' => 'SMTP Host',
        'smtp_port' => 'SMTP Port',
        'username' => 'Username',
        'password' => 'Password',
        'from_address' => 'From Address',
        'from_name' => 'From Name',
        'from_name_placeholder' => 'Property Manager',
    ],
    'sms' => [
        'intro' => 'Configure your SMS provider for sending text messages.',
        'provider' => 'SMS Provider',
        'provider_africastalking' => "Africa's Talking",
        'provider_twilio' => 'Twilio',
        'username' => 'Username',
        'username_placeholder' => 'sandbox or your username',
        'api_key' => 'API Key',
        'sender_id' => 'Sender ID (Optional)',
        'sender_id_placeholder' => 'Your approved sender ID',
        'account_sid' => 'Account SID',
        'auth_token' => 'Auth Token',
        'from_number' => 'From Number',
    ],
    'whatsapp' => [
        'intro' => 'Configure Twilio WhatsApp for sending messages.',
        'account_sid' => 'Account SID',
        'auth_token' => 'Auth Token',
        'from_number' => 'WhatsApp From Number',
        'sandbox_hint' => 'Use your Twilio WhatsApp sandbox number for testing',
    ],
    'push' => [
        'intro' => 'Configure Web Push notifications.',
        'vapid_required' => 'VAPID Keys Required',
        'vapid_explainer' => 'Web Push requires VAPID keys for authentication. Click below to generate keys automatically.',
        'generate_keys' => 'Generate VAPID Keys',
        'vapid_subject' => 'VAPID Subject (Email)',
        'vapid_subject_hint' => 'Must be a mailto: URL or https:// URL',
    ],
    'complete' => [
        'heading' => "You're All Set!",
        'body' => 'Your notification channels have been configured. You can now send rent reminders, arrears notices, and other notifications to your tenants.',
        'footer' => 'You can modify these settings anytime from the Settings tab.',
    ],
    'footer' => [
        'back' => 'Back',
        'skip' => 'Skip this channel',
        'get_started' => 'Get Started',
        'continue' => 'Continue',
        'complete_setup' => 'Complete Setup',
    ],
    'alert' => [
        'vapid_generated' => 'VAPID keys generated successfully!',
        'vapid_failed' => 'Failed to generate VAPID keys: {error}',
    ],
];
