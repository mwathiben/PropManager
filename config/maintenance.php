<?php

declare(strict_types=1);

return [
    /*
     | Phase-75 VENDOR-ROUTING-3: opt-in auto-routing. When true, a newly
     | created ticket with no vendor is auto-assigned the top-ranked vendor in
     | its suggested pool (matching specialty, best SLA performance). Off by
     | default — assignment stays manual unless a landlord opts in.
     */
    'auto_route_vendors' => (bool) env('MAINTENANCE_AUTO_ROUTE_VENDORS', false),

    /*
     | Default supplier lead time (days) used by the lead-time-aware reorder
     | trigger when a part has no supplier with an explicit lead_time_days.
     */
    'default_lead_time_days' => (int) env('MAINTENANCE_DEFAULT_LEAD_TIME_DAYS', 7),

    /*
     | Phase-80 ESCALATION-3: structured escalation reason presets a caretaker
     | can pick when escalating a stuck ticket to the landlord (plus free text).
     | Key => human label.
     */
    'escalation_reasons' => [
        'parts_needed' => 'Parts / materials needed',
        'access_denied' => 'Cannot access the unit',
        'beyond_scope' => 'Beyond my scope',
        'vendor_needed' => 'Needs an external vendor',
        'tenant_dispute' => 'Tenant dispute',
        'other' => 'Other',
    ],

    /*
     | Phase-80 ESCALATION-4: opt-in. When true, a resolution-SLA breach on a
     | caretaker-assigned ticket auto-escalates to the landlord so they have a
     | single escalation queue. Off by default — breaches only notify.
     */
    'auto_escalate_on_sla_breach' => (bool) env('MAINTENANCE_AUTO_ESCALATE_ON_SLA_BREACH', false),
];
