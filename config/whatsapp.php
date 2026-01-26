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
    | Template SIDs are configured per-landlord via the Notifications Settings
    | UI and stored in the database. This file contains only the static
    | template definitions (content and variable mappings).
    |
    | IMPORTANT: rent_reminder and arrears_notice templates include {{payment_link}}.
    | These require Meta re-approval with the payment_link variable before
    | the feature can be enabled. See COM-021 in dashboard-communication-prd.json.
    |
    | Until approved:
    | - Set WHATSAPP_PAYMENT_LINKS_ENABLED=false in .env
    | - WhatsApp will use plain text fallback (payment links still work)
    | - SMS fallback chain will deliver payment links via SMS after 1 hour
    |
    */

    'templates' => [

        'rent_reminder' => [
            'name' => 'propmanager_rent_reminder',
            'label' => 'Rent Reminder',
            'content' => 'Hi {{1}}, your rent of KES {{2}} is due on {{3}}. Pay now: {{4}} Questions? Reply to this message.',
            'variables' => ['tenant_name', 'amount', 'due_date', 'payment_link'],
        ],

        'payment_received' => [
            'name' => 'propmanager_payment_received',
            'label' => 'Payment Received',
            'content' => 'Thank you {{1}}! We received your payment of KES {{2}} (Ref: {{3}}). Your new balance is KES {{4}}.',
            'variables' => ['tenant_name', 'amount', 'reference', 'balance'],
        ],

        'invoice_ready' => [
            'name' => 'propmanager_invoice_ready',
            'label' => 'Invoice Ready',
            'content' => 'Hi {{1}}, your invoice {{2}} for KES {{3}} is ready. Due date: {{4}}. View: {{5}}',
            'variables' => ['tenant_name', 'invoice_no', 'amount', 'due_date', 'link'],
        ],

        'arrears_notice' => [
            'name' => 'propmanager_arrears_notice',
            'label' => 'Arrears Notice',
            'content' => 'Hi {{1}}, you have an outstanding balance of KES {{2}} that is {{3}} days overdue. Pay now: {{4}}',
            'variables' => ['tenant_name', 'amount', 'days_overdue', 'payment_link'],
        ],

        'maintenance_update' => [
            'name' => 'propmanager_maintenance_update',
            'label' => 'Maintenance Update',
            'content' => 'Hi {{1}}, your maintenance request #{{2}} has been updated to: {{3}}. {{4}}',
            'variables' => ['tenant_name', 'ticket_id', 'status', 'notes'],
        ],

        'lease_renewal' => [
            'name' => 'propmanager_lease_renewal',
            'label' => 'Lease Renewal',
            'content' => 'Hi {{1}}, your lease expires on {{2}}. We\'d like to offer renewal at KES {{3}}/month. Reply YES to proceed or contact us.',
            'variables' => ['tenant_name', 'expiry_date', 'new_rent'],
        ],

        'ticket_created' => [
            'name' => 'propmanager_ticket_created',
            'label' => 'Issue Logged',
            'content' => 'Hi {{1}}, your issue has been logged as Ticket #{{2}}. We\'ll update you on progress. Reference: {{3}}',
            'variables' => ['tenant_name', 'ticket_id', 'issue_summary'],
        ],

    ],

];
