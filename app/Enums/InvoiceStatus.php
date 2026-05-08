<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Voided = 'voided';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Viewed => 'Viewed',
            self::Partial => 'Partially Paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Voided => 'Voided',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'blue',
            self::Viewed => 'indigo',
            self::Partial => 'yellow',
            self::Paid => 'green',
            self::Overdue => 'red',
            self::Voided => 'gray',
            self::Cancelled => 'red',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->all();
    }

    public static function labelsMap(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [
            $case->value => $case->label(),
        ])->all();
    }

    public static function activeStatuses(): array
    {
        return [
            self::Draft->value,
            self::Sent->value,
            self::Viewed->value,
            self::Partial->value,
            self::Overdue->value,
        ];
    }

    public static function closedStatuses(): array
    {
        return [
            self::Paid->value,
            self::Voided->value,
            self::Cancelled->value,
        ];
    }

    public function isActive(): bool
    {
        return in_array($this->value, self::activeStatuses());
    }

    public function isClosed(): bool
    {
        return in_array($this->value, self::closedStatuses());
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::Draft => in_array($newStatus, [self::Sent, self::Voided]),
            self::Sent => in_array($newStatus, [self::Viewed, self::Partial, self::Paid, self::Overdue, self::Voided]),
            self::Viewed => in_array($newStatus, [self::Partial, self::Paid, self::Overdue, self::Voided]),
            self::Partial => in_array($newStatus, [self::Paid, self::Overdue, self::Voided]),
            self::Overdue => in_array($newStatus, [self::Partial, self::Paid, self::Voided]),
            self::Paid => false,
            self::Voided => false,
            self::Cancelled => false,
        };
    }
}
