<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business API Templates
    |--------------------------------------------------------------------------
    |
    | Meta-approved templates for WhatsApp Business API. Templates must be
    | submitted via Twilio Console for Meta approval before use.
    |
    | Once approved, add the template SID to your .env file.
    | Until approved, messages will fall back to plain text (session messages).
    |
    */

    'templates' => [

        'rent_reminder' => [
            'name' => 'propmanager_rent_reminder',
            'content' => 'Hi {{1}}, your rent of KES {{2}} is due on {{3}}. Pay via M-Pesa to avoid late fees. Questions? Reply to this message.',
            'variables' => ['tenant_name', 'amount', 'due_date'],
            'sid' => env('WHATSAPP_TEMPLATE_RENT_REMINDER_SID'),
        ],

        'payment_received' => [
            'name' => 'propmanager_payment_received',
            'content' => 'Thank you {{1}}! We received your payment of KES {{2}} (Ref: {{3}}). Your new balance is KES {{4}}.',
            'variables' => ['tenant_name', 'amount', 'reference', 'balance'],
            'sid' => env('WHATSAPP_TEMPLATE_PAYMENT_RECEIVED_SID'),
        ],

        'invoice_ready' => [
            'name' => 'propmanager_invoice_ready',
            'content' => 'Hi {{1}}, your invoice {{2}} for KES {{3}} is ready. Due date: {{4}}. View: {{5}}',
            'variables' => ['tenant_name', 'invoice_no', 'amount', 'due_date', 'link'],
            'sid' => env('WHATSAPP_TEMPLATE_INVOICE_READY_SID'),
        ],

        'arrears_notice' => [
            'name' => 'propmanager_arrears_notice',
            'content' => 'Hi {{1}}, you have an outstanding balance of KES {{2}} that is {{3}} days overdue. Please settle to avoid penalties.',
            'variables' => ['tenant_name', 'amount', 'days_overdue'],
            'sid' => env('WHATSAPP_TEMPLATE_ARREARS_NOTICE_SID'),
        ],

        'maintenance_update' => [
            'name' => 'propmanager_maintenance_update',
            'content' => 'Hi {{1}}, your maintenance request #{{2}} has been updated to: {{3}}. {{4}}',
            'variables' => ['tenant_name', 'ticket_id', 'status', 'notes'],
            'sid' => env('WHATSAPP_TEMPLATE_MAINTENANCE_UPDATE_SID'),
        ],

        'lease_renewal' => [
            'name' => 'propmanager_lease_renewal',
            'content' => 'Hi {{1}}, your lease expires on {{2}}. We\'d like to offer renewal at KES {{3}}/month. Reply YES to proceed or contact us.',
            'variables' => ['tenant_name', 'expiry_date', 'new_rent'],
            'sid' => env('WHATSAPP_TEMPLATE_LEASE_RENEWAL_SID'),
        ],

    ],

];
