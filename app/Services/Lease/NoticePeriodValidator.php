<?php

declare(strict_types=1);

namespace App\Services\Lease;

use App\Exceptions\ShortNoticeException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Phase-61 NOTICE-LIFECYCLE-1: centralised notice-period gate that
 * termination / transfer / pause all share. Throws
 * ShortNoticeException when the effective date is closer than the
 * configured threshold.
 */
class NoticePeriodValidator
{
    private const TRANSLATION_KEYS = [
        'termination' => 'lease.short_notice_termination',
        'transfer' => 'lease.short_notice_transfer',
        'pause' => 'lease.short_notice_pause',
    ];

    /**
     * @throws ShortNoticeException
     */
    public function validate(
        string $action,
        CarbonInterface $effectiveDate,
        ?int $landlordOverrideDays = null,
    ): void {
        $threshold = $landlordOverrideDays
            ?? (int) config("lease.notice_periods.{$action}", 0);

        if ($threshold <= 0) {
            return;
        }

        $minimum = CarbonImmutable::now()->addDays($threshold);

        if ($effectiveDate->lessThan($minimum)) {
            throw new ShortNoticeException(
                self::TRANSLATION_KEYS[$action] ?? 'lease.short_notice_generic',
                $action,
                $threshold,
            );
        }
    }
}
