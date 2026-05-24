<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: integrations settings tab. Mirror en/sw/ar.
 * Third-party/brand names (OCR provider names, Azure, API keys) stay literal.
 */
return [
    'title' => 'Integrations',
    'subtitle' => 'Connect external services to enhance PropManager functionality.',
    'ocr' => [
        'heading' => 'OCR (Optical Character Recognition)',
        'description' => 'Automatically read water meter values from photos',
        'enable' => 'Enable OCR',
        'select_provider' => 'Select OCR Provider',
        'recommended' => 'Recommended',
        'setup_guide' => 'Setup Guide',
        'requires' => 'Requires: {requirements}',
        'none' => [
            'name' => 'No OCR (Manual Only)',
            'description' => 'Disable automatic reading detection. Caretakers will only enter values manually.',
        ],
        'api_key_required_label' => 'API Key Required:',
        'api_key_required_body' => 'You need to sign up for {provider} and get an API key.',
        'get_api_key' => 'Get API Key',
        'api_key_configured' => 'API Key Configured',
        'update_key' => 'Update Key',
        'delete' => 'Delete',
        'api_key_label' => 'API Key',
        'api_key_placeholder' => 'Enter your API key',
        'api_key_hint' => 'Your API key will be encrypted and stored securely',
        'endpoint_label' => 'Endpoint URL',
        'auto_verify' => 'Auto-verify readings',
        'auto_verify_hint' => 'Automatically verify if OCR reading matches manual input within tolerance',
        'saving' => 'Saving...',
        'save' => 'Save OCR Settings',
        'test_connection' => 'Test Connection',
        'delete_confirm' => 'Are you sure you want to delete this API key? You will need to re-enter it to use OCR.',
    ],
    'coming_soon' => [
        'title' => 'More integrations coming soon',
        'body' => 'SMS gateways, accounting software, and more',
    ],
];
