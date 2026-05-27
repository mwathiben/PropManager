<?php

declare(strict_types=1);

return [
    'head_title' => 'API Tokens',
    'header' => 'API Tokens',

    'plaintext' => [
        'title' => 'Save this token now — you will not see it again',
        'body' => "PropManager does not store the plaintext token. Copy it to your integration's secrets store before closing this page.",
        'copy' => 'Copy',
        'copied' => 'Copied',
        'hide' => "I've saved it — hide this banner",
    ],

    'create' => [
        'title' => 'Create a new token',
        'description' => 'Mint a personal access token for an integration. Pick scopes narrowly — least-privilege beats convenience.',
        'name_label' => 'Token name',
        'name_placeholder' => 'e.g. QuickBooks Sync',
        'scopes_label' => 'Scopes',
        'submit' => 'Generate token',
    ],

    'scope_descriptions' => [
        'landlord_manage' => 'Read + manage your portfolio (properties, buildings, units, tenants, invoices, payments, reports).',
        'integration_webhook' => 'Subscribe + manage outbound webhooks; read aggregate reports.',
    ],

    'active' => [
        'title' => 'Active tokens',
        'description' => "Revoke any token whose source you don't recognise. Revoked tokens return 401 within one request — no cache TTL.",
        'empty' => 'No active tokens yet.',
        'created' => 'Created:',
        'last_used' => 'Last used:',
        'expires' => 'Expires:',
        'never' => 'Never',
        'revoke' => 'Revoke',
    ],

    'confirm_revoke' => 'Revoke "{name}"? Requests using this token will start returning 401 immediately.',
];
