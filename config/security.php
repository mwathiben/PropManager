<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | These headers are added to all responses to protect against common
    | web vulnerabilities like clickjacking, MIME sniffing, and XSS attacks.
    |
    */

    'headers' => [
        'x_frame_options' => env('SECURITY_X_FRAME_OPTIONS', 'DENY'),
        'x_content_type_options' => 'nosniff',
        'x_xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'permissions_policy' => 'camera=(), microphone=(), geolocation=()',

        // HSTS - Only enable in production with HTTPS
        'hsts_enabled' => env('SECURITY_HSTS_ENABLED', false),
        'hsts_max_age' => env('SECURITY_HSTS_MAX_AGE', 31536000), // 1 year
        'hsts_include_subdomains' => env('SECURITY_HSTS_SUBDOMAINS', true),
        'hsts_preload' => env('SECURITY_HSTS_PRELOAD', false),

        // Content Security Policy
        // CSP is dynamically built in SecurityHeaders middleware with nonce support
        // This allows Vite dev server to work alongside CSP in all environments
        'csp_enabled' => env('SECURITY_CSP_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for various endpoints to prevent brute force
    | attacks and abuse. Format: 'attempts,minutes'
    |
    */

    'rate_limits' => [
        'login' => env('RATE_LIMIT_LOGIN', '5,1'), // 5 attempts per minute
        'register' => env('RATE_LIMIT_REGISTER', '3,1'), // 3 attempts per minute
        'password_reset' => env('RATE_LIMIT_PASSWORD_RESET', '3,1'),
        'password_confirm' => env('RATE_LIMIT_PASSWORD_CONFIRM', '5,1'),
        'verification_email' => env('RATE_LIMIT_VERIFICATION', '3,1'),
        'two_factor' => env('RATE_LIMIT_TWO_FACTOR', '5,1'),
        'api' => env('RATE_LIMIT_API', '60,1'), // 60 requests per minute
        'file_upload' => env('RATE_LIMIT_FILE_UPLOAD', '10,1'),
        'export' => env('RATE_LIMIT_EXPORT', '5,1'), // 5 exports per minute (resource intensive)
        'search' => env('RATE_LIMIT_SEARCH', '30,1'), // 30 searches per minute (autocomplete UX)
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    |
    | Strong password requirements following NIST 800-63B guidelines.
    |
    */

    'password' => [
        'min_length' => env('PASSWORD_MIN_LENGTH', 12),
        'require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
        'require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
        'require_numbers' => env('PASSWORD_REQUIRE_NUMBERS', true),
        'require_symbols' => env('PASSWORD_REQUIRE_SYMBOLS', true),
        'history_count' => env('PASSWORD_HISTORY_COUNT', 5), // Prevent reuse of last 5 passwords
        'expiry_days' => env('PASSWORD_EXPIRY_DAYS', 0), // 0 = disabled, 90 = recommended
        'check_breached' => env('PASSWORD_CHECK_BREACHED', true), // Check against Have I Been Pwned
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security
    |--------------------------------------------------------------------------
    |
    | Session timeout and security settings.
    |
    */

    'session' => [
        'inactivity_timeout' => env('SESSION_INACTIVITY_TIMEOUT', 30), // minutes
        'absolute_timeout' => env('SESSION_ABSOLUTE_TIMEOUT', 480), // 8 hours max
        'regenerate_on_login' => true,
        'invalidate_on_password_change' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication
    |--------------------------------------------------------------------------
    |
    | TOTP-based two-factor authentication settings.
    |
    */

    'two_factor' => [
        'enabled' => env('TWO_FACTOR_ENABLED', true),
        'method' => 'totp', // TOTP only (Google Authenticator, Authy)
        'enforced_roles' => ['landlord', 'caretaker', 'super_admin'],
        'backup_codes_count' => 8,
        'issuer' => env('TWO_FACTOR_ISSUER', env('APP_NAME', 'PropManager')),
        'window' => 1, // Allow 1 period before/after for clock drift
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Configure which events are logged for security audit purposes.
    |
    */

    'audit' => [
        'enabled' => env('AUDIT_ENABLED', true),
        'retention_days' => env('AUDIT_RETENTION_DAYS', 365),
        'logged_events' => [
            'login',
            'logout',
            'login_failed',
            'password_change',
            'password_reset',
            'role_change',
            'data_export',
            'data_delete',
            'two_factor_enabled',
            'two_factor_disabled',
            'impersonation_start',
            'impersonation_end',
            'sensitive_data_access',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Logging
    |--------------------------------------------------------------------------
    |
    | Security event logging configuration.
    |
    */

    'logging' => [
        'enabled' => env('SECURITY_LOGGING_ENABLED', true),
        'channel' => env('SECURITY_LOG_CHANNEL', 'security'),
        'retention_days' => env('SECURITY_LOG_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compliance Settings (GDPR & Kenya DPA)
    |--------------------------------------------------------------------------
    |
    | Data protection and privacy compliance settings.
    |
    */

    'compliance' => [
        'gdpr_enabled' => env('GDPR_ENABLED', true),
        'kenya_dpa_enabled' => env('KENYA_DPA_ENABLED', true),
        'deletion_grace_days' => (int) env('DELETION_GRACE_DAYS', 30),
        'data_retention_years' => (int) env('DATA_RETENTION_YEARS', 7),
        'consent_required' => ['terms', 'privacy'],
        'breach_notification_hours' => 72, // GDPR & Kenya DPA requirement
        'log_in_console' => env('AUDIT_LOG_IN_CONSOLE', false), // Log audit events in console/CLI
    ],

    /*
    |--------------------------------------------------------------------------
    | Kenya DPA Specific Settings
    |--------------------------------------------------------------------------
    |
    | Settings specific to Kenya Data Protection Act 2019 compliance.
    |
    */

    'kenya_dpa' => [
        'odpc_email' => env('KENYA_DPA_ODPC_EMAIL', 'info@odpc.go.ke'),
        'data_controller_registration' => env('KENYA_DPA_REGISTRATION', null), // Registration number
        'cross_border_requires_consent' => env('KENYA_DPA_CROSS_BORDER_CONSENT', true),
        'sensitive_data_encryption_required' => true,
        'breach_notification_email' => env('KENYA_DPA_BREACH_EMAIL', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth Providers
    |--------------------------------------------------------------------------
    |
    | Social login configuration.
    |
    */

    'oauth' => [
        'enabled' => env('OAUTH_ENABLED', true),
        'providers' => ['google', 'microsoft', 'github', 'facebook'],
        'allow_registration' => env('OAUTH_ALLOW_REGISTRATION', true),
        'auto_link_existing' => env('OAUTH_AUTO_LINK', true), // Link if email matches
    ],

    /*
    |--------------------------------------------------------------------------
    | SAML SSO (Enterprise)
    |--------------------------------------------------------------------------
    |
    | Enterprise Single Sign-On settings.
    |
    */

    'saml' => [
        'enabled' => env('SAML_ENABLED', false),
        'sp_entity_id' => env('SAML_SP_ENTITY_ID'),
        'sp_acs_url' => env('SAML_SP_ACS_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Security
    |--------------------------------------------------------------------------
    |
    | Secure file upload settings.
    |
    */

    'uploads' => [
        'max_size_mb' => env('UPLOAD_MAX_SIZE_MB', 10),
        'allowed_extensions' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'webp'],
        'allowed_mimes' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ],
        'scan_for_malware' => env('UPLOAD_SCAN_MALWARE', false), // Requires ClamAV
    ],

    /*
    |--------------------------------------------------------------------------
    | Intrusion Detection
    |--------------------------------------------------------------------------
    |
    | Settings for detecting and responding to suspicious activity.
    |
    */

    'intrusion_detection' => [
        'enabled' => env('INTRUSION_DETECTION_ENABLED', true),
        'failed_login_threshold' => env('FAILED_LOGIN_THRESHOLD', 5),
        'lockout_duration_minutes' => env('LOCKOUT_DURATION', 15),
        'suspicious_ip_threshold' => env('SUSPICIOUS_IP_THRESHOLD', 10),
        'alert_admins' => env('INTRUSION_ALERT_ADMINS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Breach Detection Seed Rules (Phase-13 BREACH-2)
    |--------------------------------------------------------------------------
    |
    | Thresholds consumed by App\Services\IncidentDetector. Each rule
    | has a threshold + window + debounce window so the same burst
    | does not create one SecurityIncident per triggering event.
    | Tuning these is preferable to disabling — set window very high
    | rather than disabling the rule entirely, so a regulator audit
    | still sees evidence of detection.
    |
    */

    'detection' => [
        'failed_login_burst' => [
            'threshold' => (int) env('DETECTION_FAILED_LOGIN_BURST_THRESHOLD', 50),
            'window_minutes' => (int) env('DETECTION_FAILED_LOGIN_BURST_WINDOW', 60),
            'debounce_minutes' => (int) env('DETECTION_FAILED_LOGIN_BURST_DEBOUNCE', 60),
        ],
        'large_export' => [
            'threshold' => (int) env('DETECTION_LARGE_EXPORT_THRESHOLD', 10000),
            'debounce_minutes' => (int) env('DETECTION_LARGE_EXPORT_DEBOUNCE', 60),
        ],
        'webhook_signature' => [
            'threshold' => (int) env('DETECTION_WEBHOOK_SIGNATURE_THRESHOLD', 10),
            'window_minutes' => (int) env('DETECTION_WEBHOOK_SIGNATURE_WINDOW', 1),
            'debounce_minutes' => (int) env('DETECTION_WEBHOOK_SIGNATURE_DEBOUNCE', 30),
        ],
    ],

];
