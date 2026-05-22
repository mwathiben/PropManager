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
    'sla' => [
        'title' => 'SLA overrides',
        'description' => 'Customise response and resolution targets for your portfolio. Platform defaults apply when you have no matching override.',
        'flash' => [
            'created' => 'SLA override saved.',
            'updated' => 'SLA override updated.',
            'deleted' => 'SLA override removed.',
        ],
    ],
    'vendor_onboarding' => [
        'subject' => ':landlord has added you as a vendor — please complete your profile',
        'heading' => 'Welcome — finish your vendor profile',
        'greeting' => 'Hello :name,',
        'body' => ':landlord has added you to their PropManager vendor list. Please confirm your phone and service area so they can route maintenance jobs to you.',
        'cta' => 'Complete profile',
        'expiry_note' => 'This link expires in 7 days. Contact the landlord directly if it lapses.',
        'signoff' => 'Thank you, the :app team',
        'saved' => 'Profile updated. Thank you.',
        'form' => [
            'title' => 'Complete your vendor profile',
            'intro' => 'Update your contact details and service area so the landlord can reach you for maintenance jobs.',
            'contact_person' => 'Contact person',
            'phone' => 'Phone',
            'address' => 'Address',
            'notes' => 'Specialties / service area',
            'submit' => 'Save changes',
            'expired' => 'This link has expired. Please ask the landlord to send a new invitation.',
        ],
    ],
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
    'photos' => [
        'title' => 'Maintenance photos',
        'subtitle' => 'Every ticket photo across your properties',
        'filter_building' => 'Building',
        'filter_category' => 'Category',
        'filter_from' => 'From',
        'filter_to' => 'To',
        'filter_all' => 'All',
        'apply' => 'Apply',
        'reset' => 'Reset',
        'export_pdf' => 'Export PDF',
        'empty' => 'No photos match these filters.',
        'annotated' => 'Annotated',
        'view_ticket' => 'View ticket',
    ],
    'escalation' => [
        'not_open' => 'This ticket can no longer be escalated.',
        'raised' => 'Escalated to the landlord.',
        'acknowledged' => 'Escalation acknowledged.',
        'notify_subject' => 'A ticket was escalated to you',
        'notify_body' => 'A caretaker escalated ":title" — review and reassign or acknowledge.',
        'sla_breach_reason' => 'Auto-escalated: resolution SLA breached.',
        'banner_title' => 'Escalated by the caretaker',
        'acknowledge' => 'Acknowledge',
    ],
    'task_board' => [
        'title' => 'My tasks',
        'subtitle' => 'Your open jobs',
        'invalid_transition' => 'That status change is not allowed.',
        'updated' => 'Task updated.',
        'empty' => 'All caught up — no open tasks.',
        'overdue' => 'Overdue',
        'escalated' => 'Escalated',
        'group_overdue' => 'Overdue',
        'group_urgent' => 'Urgent',
        'group_today' => 'To do',
        'acknowledge' => 'Acknowledge',
        'start' => 'Start',
        'resolve' => 'Resolve',
        'escalate' => 'Escalate',
        'record_water' => 'Record water readings',
        'report_issue' => 'Report an issue',
        'view_all' => 'View all tickets',
        'escalate_title' => 'Escalate to the landlord',
        'escalate_reason' => 'What is blocking you?',
        'submit' => 'Submit',
        'cancel' => 'Cancel',
    ],
    'caretaker_perf' => [
        'title' => 'Caretaker performance',
        'subtitle' => 'How your caretakers compare on responsiveness and resolution',
        'window' => 'Window',
        'days' => '{count} days',
        'empty' => 'No caretakers to compare yet.',
        'col_caretaker' => 'Caretaker',
        'col_within_sla' => 'Within SLA',
        'col_first_response' => 'Avg first response',
        'col_avg_resolution' => 'Avg resolution',
        'col_resolved' => 'Resolved',
        'col_overdue' => 'Overdue',
        'col_water' => 'Water readings',
        'col_escalations' => 'Escalations',
    ],
    'overview' => [
        'open_issues' => 'Open issues',
        'open_complaints' => 'Open complaints',
        'urgent' => 'Urgent',
        'escalated' => 'Escalated',
    ],
];
