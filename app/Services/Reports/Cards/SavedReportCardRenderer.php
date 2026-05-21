<?php

declare(strict_types=1);

namespace App\Services\Reports\Cards;

class SavedReportCardRenderer extends AbstractCardRenderer
{
    public function type(): string
    {
        return 'saved_report';
    }

    public function validate(int $index, array $card, int $landlordId): array
    {
        $report = $this->requireSavedReport($index, $card, $landlordId);
        $normalised = [
            'type' => 'saved_report',
            'saved_report_id' => $report->id,
            'size' => $this->validateSize($card['size'] ?? 'wide'),
        ];
        if (isset($card['title']) && is_string($card['title']) && $card['title'] !== '') {
            $normalised['title'] = mb_substr($card['title'], 0, 200);
        }

        return $normalised;
    }

    public function render(int $index, array $card, int $landlordId): array
    {
        $report = $this->requireSavedReport($index, $card, $landlordId);

        return [
            'type' => 'saved_report',
            'title' => $this->stringOr($card['title'] ?? null, $report->name),
            'size' => $this->validateSize($card['size'] ?? 'wide'),
            'saved_report_id' => $report->id,
            'rows' => $this->builder->run($report->config, $landlordId),
        ];
    }

    public function descriptor(): array
    {
        return ['key' => 'saved_report', 'label' => 'Saved report', 'needs_saved_report' => true, 'needs_metric' => false];
    }
}
