<?php

declare(strict_types=1);

namespace Tests\Feature\Leases;

use App\Exceptions\ShortNoticeException;
use App\Services\Lease\NoticePeriodValidator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-61 NOTICE-LIFECYCLE-1/2/3: shared notice-period gate.
 */
class Phase61NoticeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_validator_passes_when_effective_date_is_beyond_threshold(): void
    {
        $validator = app(NoticePeriodValidator::class);

        $validator->validate('termination', CarbonImmutable::now()->addDays(45));

        $this->assertTrue(true);
    }

    public function test_validator_throws_when_termination_is_inside_30_day_window(): void
    {
        $validator = app(NoticePeriodValidator::class);

        $this->expectException(ShortNoticeException::class);
        $validator->validate('termination', CarbonImmutable::now()->addDays(10));
    }

    public function test_validator_throws_specific_action_translation_key(): void
    {
        $validator = app(NoticePeriodValidator::class);

        try {
            $validator->validate('transfer', CarbonImmutable::now()->addDays(1));
            $this->fail('Expected ShortNoticeException');
        } catch (ShortNoticeException $e) {
            $this->assertSame('lease.short_notice_transfer', $e->translationKey());
            $this->assertSame('transfer', $e->action);
            $this->assertSame(14, $e->requiredDays);
        }
    }

    public function test_validator_uses_landlord_override_when_provided(): void
    {
        $validator = app(NoticePeriodValidator::class);

        // Default termination notice is 30 days; landlord override
        // shortens to 5 days. 10 days out is fine.
        $validator->validate('termination', CarbonImmutable::now()->addDays(10), landlordOverrideDays: 5);

        $this->assertTrue(true);
    }

    public function test_validator_is_noop_for_zero_threshold_action(): void
    {
        $validator = app(NoticePeriodValidator::class);

        // Action with zero/missing config threshold is a no-op.
        $validator->validate('unknown_action', CarbonImmutable::now()->addDays(1));

        $this->assertTrue(true);
    }

    public function test_translation_keys_exist(): void
    {
        $this->assertNotSame('lease.short_notice_termination', __('lease.short_notice_termination'));
        $this->assertNotSame('lease.short_notice_transfer', __('lease.short_notice_transfer'));
        $this->assertNotSame('lease.short_notice_pause', __('lease.short_notice_pause'));
    }
}
