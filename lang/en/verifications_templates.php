<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: verification-templates management page. Mirror en/sw/ar.
 */
return [
    'title' => 'Verification Templates',
    'subtitle' => 'Create checklists for verifying new tenants',
    'new_template' => 'New Template',
    'stats' => [
        'total' => 'Total Templates',
        'default' => 'Default Template',
        'items_in_default' => 'Items in Default',
    ],
    'none' => 'None',
    'default_badge' => 'Default',
    'property_label' => 'Property: {name}',
    'items_count' => '{count} verification items',
    'more' => '+{count} more',
    'actions' => [
        'edit' => 'Edit',
        'delete' => 'Delete',
        'required' => 'Required',
        'add_item' => 'Add Item',
        'cancel' => 'Cancel',
    ],
    'empty' => [
        'title' => 'No templates',
        'description' => 'Create a verification template to get started.',
    ],
    'create' => [
        'title' => 'Create Verification Template',
        'creating' => 'Creating...',
        'submit' => 'Create Template',
    ],
    'edit' => [
        'title' => 'Edit Template: {name}',
        'saving' => 'Saving...',
        'submit' => 'Save Changes',
    ],
    'form' => [
        'name' => 'Template Name *',
        'name_placeholder' => 'e.g., Standard Verification',
        'property' => 'Property (Optional)',
        'all_properties' => 'All Properties',
        'set_default' => 'Set as default template',
        'items_heading' => 'Verification Items',
        'item_name_placeholder' => 'Item name *',
        'document_type' => 'Document type',
        'description_placeholder' => 'Description (optional)',
    ],
    'confirm' => [
        'delete' => 'Are you sure you want to delete this template? This cannot be undone.',
    ],
];
