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
];
