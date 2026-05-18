<?php

declare(strict_types=1);

return [
    'sla' => [
        'title' => '[TODO-ar] SLA overrides',
        'description' => '[TODO-ar] Customise response and resolution targets for your portfolio. Platform defaults apply when you have no matching override.',
        'flash' => [
            'created' => '[TODO-ar] SLA override saved.',
            'updated' => '[TODO-ar] SLA override updated.',
            'deleted' => '[TODO-ar] SLA override removed.',
        ],
    ],
    'vendor_assigned' => [
        'subject' => '[TODO-ar] You have been assigned to a maintenance ticket: :ticket',
        'heading' => '[TODO-ar] New maintenance assignment',
        'greeting' => '[TODO-ar] Hello :name,',
        'body' => '[TODO-ar] :landlord has assigned you to ticket ":title" (priority :priority). Please review the scope below and respond at your earliest convenience.',
        'scope_label' => '[TODO-ar] Scope of work',
        'note_label' => '[TODO-ar] Note from the landlord',
        'contact_note' => '[TODO-ar] Reply to this email or contact the landlord directly to confirm acceptance, provide a quote, or request additional information.',
        'signoff' => '[TODO-ar] Thank you, the :app team',
    ],
];
