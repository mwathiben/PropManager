<?php

declare(strict_types=1);

namespace App\Services\Reports\Cards;

use Illuminate\Validation\ValidationException;

/**
 * Phase-74 CARD-TYPES: a static text/note card for headings + explanatory copy
 * between data cards. No data source. Body is length-capped at validate time;
 * the Vue renders it as plain text ({{ }} auto-escapes), so no HTML injection.
 */
class TextCardRenderer extends AbstractCardRenderer
{
    private const MAX_BODY = 2000;

    public function type(): string
    {
        return 'text';
    }

    public function validate(int $index, array $card, int $landlordId): array
    {
        $body = $this->requireValidBody($index, $card);

        $normalised = [
            'type' => 'text',
            'body' => $body,
            'size' => $this->validateSize($card['size'] ?? 'wide'),
        ];

        $this->applyOptionalTitle($normalised, $card);

        return $normalised;
    }

    private function requireValidBody(int $index, array $card): string
    {
        $body = $card['body'] ?? null;
        if (! is_string($body) || trim($body) === '') {
            throw ValidationException::withMessages([
                "layout.{$index}.body" => 'Text card requires a body.',
            ]);
        }
        if (mb_strlen($body) > self::MAX_BODY) {
            throw ValidationException::withMessages([
                "layout.{$index}.body" => 'Text card body exceeds '.self::MAX_BODY.' characters.',
            ]);
        }

        return $body;
    }

    private function applyOptionalTitle(array &$normalised, array $card): void
    {
        if (isset($card['title']) && is_string($card['title']) && $card['title'] !== '') {
            $normalised['title'] = mb_substr($card['title'], 0, 200);
        }
    }

    public function render(int $index, array $card, int $landlordId): array
    {
        // Re-validate (fail-closed on a tampered layout) and reuse the result.
        $normalised = $this->validate($index, $card, $landlordId);

        return [
            'type' => 'text',
            'title' => $this->stringOr($card['title'] ?? null, ''),
            'size' => $normalised['size'],
            'body' => $normalised['body'],
        ];
    }

    public function descriptor(): array
    {
        return ['key' => 'text', 'label' => 'Text / note', 'needs_saved_report' => false, 'needs_metric' => false];
    }
}
