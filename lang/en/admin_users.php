<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: admin users-list page. Mirror en/sw/ar.
 */
return [
    'title' => 'Manage Users',
    'heading' => 'All Users',
    'search_placeholder' => 'Search by name or email...',
    'all_roles' => 'All Roles',
    'filter' => 'Filter',
    'empty' => 'No users found.',
    'table' => [
        'user' => 'User',
        'role' => 'Role',
        'status' => 'Status',
        'joined' => 'Joined',
        'actions' => 'Actions',
    ],
    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],
    'actions' => [
        'activate' => 'Activate',
        'deactivate' => 'Deactivate',
        'login_as' => 'Login As',
    ],
    'pagination' => [
        'showing' => 'Showing {from} to {to} of {total} results',
        'previous' => 'Previous',
        'next' => 'Next',
    ],
    'confirm' => [
        'impersonate' => 'This will log you in as this user. Continue?',
        'toggle_status' => "Are you sure you want to toggle this user's status?",
    ],
];
