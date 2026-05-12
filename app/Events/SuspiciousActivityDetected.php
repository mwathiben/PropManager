<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\SecurityIncident;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-13 BREACH-2: dispatched when IncidentDetector turns a
 * threshold-crossing pattern into a SecurityIncident. Listeners
 * (BREACH-5) can attach to surface the incident in real-time
 * (e.g. a Reverb broadcast to ops dashboards, or a one-off
 * BreachReportedAlert mail when the detector picks up something
 * mid-deploy).
 */
class SuspiciousActivityDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SecurityIncident $incident,
        public string $rule,
        public array $context = [],
    ) {}
}
