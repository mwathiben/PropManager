<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: offline-sync conflict-resolution dialog. Mirror en/sw/ar.
 */
return [
    'title' => 'Conflict — this record changed while you were offline',
    'body' => 'Someone else updated this record. Your queued change conflicts with the latest version (server version {version}).',
    'server_value' => 'Server: {value}',
    'your_value' => 'Your change: {value}',
    'discard' => 'Discard my change',
    'merge' => 'Merge selected fields',
    'overwrite' => 'Overwrite server version',
];
