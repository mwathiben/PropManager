<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case MobileMoney = 'mobile_money';
    case Paystack = 'paystack';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank Transfer',
            self::MobileMoney => 'M-Pesa',
            self::Paystack => 'Paystack (Online)',
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

    public static function normalize(string $method): string
    {
        return $method === 'mpesa' ? 'mobile_money' : $method;
    }

    public static function tryFromNormalized(string $method): ?self
    {
        return self::tryFrom(self::normalize($method));
    }
}
