<?php

declare(strict_types=1);

return [
    'page_title' => 'KYC Requirements',
    'back_to_settings' => 'Back to Settings',
    'add_requirement' => 'Add Requirement',
    'edit_requirement' => 'Edit Requirement',
    'intro' => 'Configure KYC document requirements for your tenants. Platform defaults are read-only. You can add custom requirements for all buildings or specific buildings.',
    'columns' => [
        'label' => 'Label',
        'type' => 'Type',
        'scope' => 'Scope',
        'required' => 'Required',
        'active' => 'Active',
        'actions' => 'Actions',
    ],
    'scope' => [
        'platform_default' => 'Platform Default',
        'building_prefix' => 'Building: {name}',
        'all_buildings' => 'All Buildings',
    ],
    'aria' => [
        'mark_not_required' => 'Mark as not required',
        'mark_required' => 'Mark as required',
        'deactivate' => 'Deactivate requirement',
        'activate' => 'Activate requirement',
    ],
    'actions' => [
        'edit' => 'Edit',
        'delete' => 'Delete',
        'read_only' => 'Read-only',
    ],
    'empty' => [
        'title' => 'No requirements',
        'subtitle' => 'Get started by adding a KYC requirement.',
    ],
    'confirm_delete' => 'Are you sure you want to delete "{label}"? This cannot be undone.',
    'form' => [
        'requirement_type' => 'Requirement Type *',
        'requirement_type_placeholder' => 'e.g., proof_of_income',
        'label' => 'Label *',
        'label_placeholder' => 'e.g., Proof of Income',
        'description' => 'Description',
        'description_placeholder' => 'Instructions for the tenant...',
        'building' => 'Building (Optional)',
        'building_help' => 'Leave empty to apply to all buildings',
        'all_buildings' => 'All Buildings',
        'required' => 'Required',
        'active' => 'Active',
        'cancel' => 'Cancel',
        'saving' => 'Saving...',
        'update' => 'Update',
        'create' => 'Create',
    ],
];
