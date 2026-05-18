<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Phase-61 NOTICE-LIFECYCLE-1: thrown by NoticePeriodValidator when
 * a termination/transfer/pause is requested with less than the
 * required notice period. Message is the translation key so
 * controllers can render a localised flash.
 */
class ShortNoticeException extends Exception
{
    public function __construct(
        string $translationKey,
        public readonly string $action,
        public readonly int $requiredDays,
    ) {
        parent::__construct($translationKey);
    }

    public function translationKey(): string
    {
        return $this->getMessage();
    }
}
