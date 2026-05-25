<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: notification message-templates tab. Mirror en/sw/ar.
 */
return [
    'heading' => 'Notification Templates',
    'subheading' => 'Create reusable templates for different notification types',
    'create' => 'Create Template',
    'empty' => [
        'title' => 'No Templates Yet',
        'body' => 'Create your first notification template to get started',
    ],
    'default_badge' => 'Default',
    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],
    'actions' => [
        'preview' => 'Preview',
        'edit' => 'Edit',
        'duplicate' => 'Duplicate',
        'delete' => 'Delete',
        'cancel' => 'Cancel',
    ],
    'modal' => [
        'edit_title' => 'Edit Template',
        'create_title' => 'Create Template',
        'name_label' => 'Template Name',
        'name_placeholder' => 'e.g., Monthly Rent Reminder',
        'type_label' => 'Type',
        'placeholders_title' => 'Available Placeholders',
        'placeholders_hint' => 'Click to insert into subject or body',
        'subject_label' => 'Subject',
        'subject_placeholder' => "e.g., Rent Due Reminder for {'{{unit_name}}'}",
        'body_label' => 'Message Body',
        'body_placeholder' => "Dear {'{{tenant_name}}'},\n\nThis is a reminder that your rent of {'{{rent_amount}}'} is due on {'{{due_date}}'}.\n\nBest regards,\n{'{{landlord_name}}'}",
        'is_active' => 'Template is active',
        'update_submit' => 'Update Template',
        'create_submit' => 'Create Template',
    ],
    'preview' => [
        'title' => 'Template Preview',
        'subject' => 'Subject',
        'message' => 'Message',
        'note' => 'Preview uses sample data. Actual values will be replaced when sending.',
    ],
    'types' => [
        'rent_reminder' => 'Rent Reminder',
        'arrears_notice' => 'Arrears Notice',
        'invoice' => 'Invoice',
        'receipt' => 'Receipt',
        'rent_hike' => 'Rent Hike',
        'lease_expiry' => 'Lease Expiry',
        'general' => 'General',
    ],
    'confirm_delete' => 'Are you sure you want to delete "{name}"?',
    'copy_suffix' => ' (Copy)',
    'sample' => [
        'tenant_name' => 'John Doe',
        'unit_name' => 'Unit A1',
        'payment_method' => 'M-Pesa',
        'landlord_name' => 'Property Manager',
        'property_name' => 'Sunrise Apartments',
    ],
];
