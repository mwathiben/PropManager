<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: move-out deduction categories settings page. Mirror en/sw/ar.
 */
return [
    'head_title' => 'Deduction Categories',
    'title' => 'Deduction Categories',
    'subtitle' => 'Configure deduction categories for move-out inspections.',
    'add_category' => 'Add Category',

    'breadcrumbs' => [
        'move_outs' => 'Move-Outs',
        'deduction_categories' => 'Deduction Categories',
    ],

    'stats' => [
        'total' => 'Total',
        'active' => 'Active',
        'auto_applied' => 'Auto-Applied',
        'custom' => 'Custom',
    ],

    'search' => [
        'placeholder' => 'Search categories...',
    ],

    'scope_filter' => [
        'all' => 'All Scopes',
        'platform' => 'Platform Defaults',
        'custom' => 'Your Categories',
        'building' => 'Building-Specific',
    ],

    'empty' => [
        'title' => 'No categories found',
        'try_different_search' => 'Try a different search term.',
        'add_first' => 'Add your first deduction category to get started.',
    ],

    'sections' => [
        'platform_defaults' => 'Platform Defaults',
        'your_categories' => 'Your Categories',
        'building_specific' => 'Building-Specific',
    ],

    'badges' => [
        'platform' => 'Platform',
        'read_only' => 'Read-only',
        'all_buildings' => 'All Buildings',
    ],

    'card' => [
        'auto_apply' => 'Auto-apply',
        'active' => 'Active',
    ],

    'no_custom' => [
        'message' => 'No custom categories yet.',
        'create_first' => 'Create your first category',
    ],

    'modal' => [
        'title_new' => 'New Category',
        'title_edit' => 'Edit Category',
        'name_label' => 'Category Name',
        'name_placeholder' => 'e.g., Cleaning Fee',
        'description_label' => 'Description',
        'description_placeholder' => 'Brief description of this deduction',
        'default_amount_label' => 'Default Amount ({currency})',
        'scope_label' => 'Scope',
        'all_buildings' => 'All Buildings',
        'always_apply_label' => 'Always Apply',
        'always_apply_help' => 'Auto-added when inspection starts',
        'active_label' => 'Active',
        'active_help' => 'Available for selection',
        'cancel' => 'Cancel',
        'saving' => 'Saving...',
        'update' => 'Update',
        'create' => 'Create',
    ],

    'delete_modal' => [
        'title' => 'Delete Category?',
        'message' => 'This will permanently delete "{name}". Existing deductions using this category will not be affected.',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
    ],
];
