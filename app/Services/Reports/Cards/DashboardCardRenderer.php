<?php

declare(strict_types=1);

namespace App\Services\Reports\Cards;

/**
 * Phase-74 CARD-REGISTRY: contract for a dashboard card type. The registry
 * keys renderers by type(); DashboardService delegates layout validation +
 * rendering to them. Every renderer MUST re-validate landlord ownership of any
 * referenced saved_report / metric (fail-closed) — the layout JSON is opaque
 * storage and is never trusted.
 */
interface DashboardCardRenderer
{
    /** The card.type token this renderer handles (e.g. 'saved_report'). */
    public function type(): string;

    /**
     * Validate + normalise a card WITHOUT running any report (used by the
     * editor save path). Throws ValidationException on a bad/foreign card.
     *
     * @param  array<string, mixed>  $card
     * @return array<string, mixed> normalised card to persist
     */
    public function validate(int $index, array $card, int $landlordId): array;

    /**
     * Render a card to its display payload (runs the report / evaluates the
     * metric). Throws ValidationException on a bad/foreign card.
     *
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    public function render(int $index, array $card, int $landlordId): array;

    /**
     * Editor descriptor so the add-card UI is data-driven, not hard-coded.
     *
     * @return array{key: string, label: string, needs_saved_report: bool, needs_metric: bool}
     */
    public function descriptor(): array;
}
