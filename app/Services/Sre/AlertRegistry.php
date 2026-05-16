<?php

declare(strict_types=1);

namespace App\Services\Sre;

/**
 * Phase-32 SRE-RUNBOOK-1: typed accessor for the alerts registry.
 * Single place to mutate if we ever migrate the registry from
 * config/alerts.php to a DB table; everything else (audits, recorder
 * lookups, the watchdog test) reads through this service.
 */
class AlertRegistry
{
    /**
     * @return list<array{
     *     key: string,
     *     severity: string,
     *     threshold: float|int,
     *     window: string,
     *     gauge: string,
     *     runbook: string,
     *     paging: string,
     *     description: string,
     * }>
     */
    public function all(): array
    {
        return (array) config('alerts.alerts', []);
    }

    public function find(string $key): ?array
    {
        foreach ($this->all() as $alert) {
            if ($alert['key'] === $key) {
                return $alert;
            }
        }

        return null;
    }
}
