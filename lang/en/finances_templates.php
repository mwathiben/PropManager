<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: finances invoice/receipt/credit-note templates tab. Mirror en/sw/ar.
 */
return [
    'invoices' => [
        'heading' => 'Invoice Templates',
        'subtitle' => 'Customize how your invoices look when sent to tenants',
        'empty_title' => 'No invoice templates yet',
        'empty_description' => 'Create your first template to customize how your invoices look.',
    ],
    'receipts' => [
        'heading' => 'Receipt Templates',
        'subtitle' => 'Customize how payment receipts appear to tenants',
        'empty_title' => 'No receipt templates yet',
        'empty_description' => 'Create your first template to customize how your payment receipts look.',
    ],
    'credit_notes' => [
        'heading' => 'Credit Note Templates',
        'subtitle' => 'Credit notes use your invoice template with modifications',
        'inheritance' => 'Template Inheritance',
        'using_invoice_template' => 'Using Invoice Template',
        'inherit_body' => 'Credit notes automatically inherit your default invoice template settings. The title is changed to "CREDIT NOTE" and amounts are displayed as negative values.',
        'current_default' => 'Current Default Template:',
        'none_selected' => 'None selected',
        'edit_invoice_template' => 'Edit Invoice Template',
        'no_template_found' => 'No invoice template found. Create an invoice template first to use with credit notes.',
        'create_invoice_template' => 'Create Invoice Template',
    ],
    'new_template' => 'New Template',
    'set_default' => 'Set Default',
    'edit' => 'Edit',
    'create_template' => 'Create Template',
    'default_badge' => 'Default',
    'design' => [
        'classic' => 'Classic',
        'modern' => 'Modern',
        'minimal' => 'Minimal',
        'professional' => 'Professional',
    ],
    'features' => [
        'logo' => 'Logo',
        'bank' => 'Bank',
        'qr' => 'QR',
        'water' => 'Water',
        'arrears' => 'Arrears',
        'receipt_number' => 'Receipt #',
        'method' => 'Method',
        'tenant' => 'Tenant',
        'none' => 'No extras',
        'separator' => ' • ',
    ],
];
