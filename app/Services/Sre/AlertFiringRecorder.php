<?php

declare(strict_types=1);

namespace App\Services\Sre;

use App\Events\AlertFiringRecorded;
use App\Models\AlertFiring;

/**
 * Phase-32 SRE-ALERT-1: record every alert firing for later
 * signal-to-noise scoring + post-mortem retrospectives. The recorder
 * is idempotent on the (alert_key, open) tuple — if an open firing
 * already exists for the key, we update its value/threshold/metadata
 * instead of inserting a duplicate.
 *
 * resolve() flips resolved_at on the most recent open firing; the
 * SRE-ALERT-2 alert:quality cron compares (fired_at, resolved_at)
 * deltas to bucket transient blips vs sustained outages.
 */
class AlertFiringRecorder
{
    public function __construct(
        private readonly AlertRegistry $registry,
    ) {}

    public function record(string $alertKey, float $value, ?float $threshold = null, array $metadata = []): AlertFiring
    {
        $entry = $this->registry->find($alertKey);
        $severity = (string) ($entry['severity'] ?? 'sev3');
        $thresholdEff = $threshold ?? (float) ($entry['threshold'] ?? 0.0);

        $open = AlertFiring::query()
            ->where('alert_key', $alertKey)
            ->whereNull('resolved_at')
            ->latest('fired_at')
            ->first();

        if ($open !== null) {
            $open->update([
                'value' => $value,
                'threshold' => $thresholdEff,
                'metadata' => $metadata,
            ]);

            return $open->refresh();
        }

        $row = AlertFiring::create([
            'alert_key' => $alertKey,
            'severity' => $severity,
            'value' => $value,
            'threshold' => $thresholdEff,
            'fired_at' => now(),
            'metadata' => $metadata,
        ]);

        AlertFiringRecorded::dispatch($row);

        return $row;
    }

    public function resolve(string $alertKey): ?AlertFiring
    {
        $open = AlertFiring::query()
            ->where('alert_key', $alertKey)
            ->whereNull('resolved_at')
            ->latest('fired_at')
            ->first();
        if ($open === null) {
            return null;
        }
        $open->update(['resolved_at' => now()]);

        return $open->refresh();
    }

    public function acknowledge(string $alertKey, int $userId, ?string $note = null): ?AlertFiring
    {
        $open = AlertFiring::query()
            ->where('alert_key', $alertKey)
            ->whereNull('resolved_at')
            ->latest('fired_at')
            ->first();
        if ($open === null) {
            return null;
        }
        $open->update([
            'acknowledged_by_user_id' => $userId,
            'acknowledged_at' => now(),
            'acknowledgement_note' => $note,
        ]);

        return $open->refresh();
    }
}
