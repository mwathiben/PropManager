<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Models\ProductEvent;
use App\Models\User;
use App\Services\Growth\ChurnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-56 COHORT-BY-SOURCE-3 watchdog. Three landlords (one each of
 * organic / referral / paid) signed up in the same cohort month with
 * progressively-longer engagement curves; cohortsBySource partitions
 * them correctly into 3 separate keys.
 */
class Phase56CohortBySourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_acquisition_source_column_exists_with_expected_default(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'acquisition_source'));

        $user = User::factory()->create();
        $this->assertSame('unknown', $user->fresh()->acquisition_source);
    }

    public function test_cohorts_partition_by_acquisition_source(): void
    {
        $cohortMonth = now()->subMonthsNoOverflow(2)->startOfMonth();

        $makeUser = function (string $source) use ($cohortMonth): User {
            return User::factory()->create([
                'acquisition_source' => $source,
                'created_at' => $cohortMonth->copy()->addDays(5),
            ]);
        };

        $organic = $makeUser('organic');
        $referral = $makeUser('referral');
        $paid = $makeUser('paid');

        // organic: active in cohort month only.
        ProductEvent::create([
            'user_id' => $organic->id,
            'landlord_id' => $organic->id,
            'event_name' => 'landlord.touched',
            'created_at' => $cohortMonth->copy()->addDays(10),
        ]);
        // referral: active in months 0 and 1.
        foreach ([0, 1] as $monthOffset) {
            ProductEvent::create([
                'user_id' => $referral->id,
                'landlord_id' => $referral->id,
                'event_name' => 'landlord.touched',
                'created_at' => $cohortMonth->copy()->addMonthsNoOverflow($monthOffset)->addDays(10),
            ]);
        }
        // paid: active in months 0, 1, 2.
        foreach ([0, 1, 2] as $monthOffset) {
            ProductEvent::create([
                'user_id' => $paid->id,
                'landlord_id' => $paid->id,
                'event_name' => 'landlord.touched',
                'created_at' => $cohortMonth->copy()->addMonthsNoOverflow($monthOffset)->addDays(10),
            ]);
        }

        $matrix = app(ChurnService::class)->cohortsBySource(12);
        $byKey = collect($matrix)->keyBy(fn ($r) => $r['cohort_month'].'|'.$r['source']);
        $monthKey = $cohortMonth->format('Y-m');

        $this->assertTrue($byKey->has("{$monthKey}|organic"));
        $this->assertTrue($byKey->has("{$monthKey}|referral"));
        $this->assertTrue($byKey->has("{$monthKey}|paid"));

        $this->assertSame(1, $byKey["{$monthKey}|organic"]['size']);
        $this->assertSame(1, $byKey["{$monthKey}|referral"]['size']);
        $this->assertSame(1, $byKey["{$monthKey}|paid"]['size']);

        $this->assertSame(1.0, $byKey["{$monthKey}|organic"]['retention'][0]);
        $this->assertSame(1.0, $byKey["{$monthKey}|referral"]['retention'][0]);
        $this->assertSame(1.0, $byKey["{$monthKey}|referral"]['retention'][1]);
        $this->assertSame(1.0, $byKey["{$monthKey}|paid"]['retention'][2]);
    }
}
