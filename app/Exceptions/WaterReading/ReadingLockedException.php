<?php

namespace App\Exceptions\WaterReading;

class ReadingLockedException extends WaterReadingException
{
    public const INVOICED = 'invoiced';

    public const APPROVED = 'approved';

    public function __construct(int $readingId, string $lockReason)
    {
        $messages = [
            self::INVOICED => 'Cannot modify readings that have been invoiced. Please void the invoice first.',
            self::APPROVED => 'Cannot modify approved readings. Please reject the reading first if changes are needed.',
        ];

        $message = $messages[$lockReason] ?? "Reading is locked due to: {$lockReason}";

        parent::__construct(
            message: $message,
            errorCode: 'WATER_READING_LOCKED',
            context: [
                'reading_id' => $readingId,
                'lock_reason' => $lockReason,
            ],
            statusCode: 409
        );
    }
}
