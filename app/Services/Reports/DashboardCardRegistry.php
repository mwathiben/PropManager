<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Services\Reports\Cards\DashboardCardRenderer;
use Illuminate\Validation\ValidationException;

/**
 * Phase-74 CARD-REGISTRY: the single registry of dashboard card renderers,
 * keyed by type(). DashboardService resolves a card's renderer here instead of
 * a hard-coded if/else, so a new card type is added by registering a renderer
 * (see AppServiceProvider) — never by editing the security-sensitive render
 * path. Bound as a singleton.
 */
class DashboardCardRegistry
{
    /** @var array<string, DashboardCardRenderer> */
    private array $renderers = [];

    /**
     * @param  iterable<DashboardCardRenderer>  $renderers
     */
    public function __construct(iterable $renderers = [])
    {
        foreach ($renderers as $renderer) {
            $this->register($renderer);
        }
    }

    public function register(DashboardCardRenderer $renderer): void
    {
        $this->renderers[$renderer->type()] = $renderer;
    }

    public function has(string $type): bool
    {
        return isset($this->renderers[$type]);
    }

    public function get(int $index, string $type): DashboardCardRenderer
    {
        if (! $this->has($type)) {
            throw ValidationException::withMessages([
                "layout.{$index}.type" => "Card type '{$type}' is not registered.",
            ]);
        }

        return $this->renderers[$type];
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->renderers);
    }

    /**
     * Editor descriptors for the registered card types.
     *
     * @return list<array{key: string, label: string, needs_saved_report: bool, needs_metric: bool}>
     */
    public function descriptors(): array
    {
        return array_values(array_map(fn (DashboardCardRenderer $r) => $r->descriptor(), $this->renderers));
    }
}
