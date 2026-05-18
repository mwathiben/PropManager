<?php

declare(strict_types=1);

/**
 * Phase-54 maintenance lang namespace. Houses operator-facing strings
 * for the maintenance workflow surface (vendor assignment + future
 * landlord notifications + parts reorder + cost notifications).
 *
 * Parity contract: keys MUST mirror across en / sw / ar exactly in
 * order + nesting (Phase 24 CI watchdog does identity comparison on
 * array_keys, not a set comparison).
 */

return [
    'vendor_assigned' => [
        'subject' => 'You have been assigned to a maintenance ticket: :ticket',
        'heading' => 'New maintenance assignment',
        'greeting' => 'Hello :name,',
        'body' => ':landlord has assigned you to ticket ":title" (priority :priority). Please review the scope below and respond at your earliest convenience.',
        'scope_label' => 'Scope of work',
        'note_label' => 'Note from the landlord',
        'contact_note' => 'Reply to this email or contact the landlord directly to confirm acceptance, provide a quote, or request additional information.',
        'signoff' => 'Thank you, the :app team',
    ],
];
