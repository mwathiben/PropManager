<?php

declare(strict_types=1);

namespace Tests\Feature\Plans;

use App\Exceptions\CouponInvalidException;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\User;
use App\Services\Subscriptions\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-60 COUPONS-1/2/3: schema + redemption service + apply-coupon
 * route.
 */
class Phase60CouponsTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupons_and_redemptions_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('coupons'));
        $this->assertTrue(Schema::hasTable('coupon_redemptions'));
        $this->assertTrue(Schema::hasColumns('coupons', [
            'code', 'stripe_coupon_id', 'discount_type', 'discount_value',
            'max_redemptions', 'expires_at', 'is_active', 'deleted_at',
        ]));
    }

    public function test_active_scope_excludes_expired_and_inactive(): void
    {
        Coupon::create(['code' => 'ACTIVE', 'discount_type' => 'percent', 'discount_value' => 10, 'is_active' => true]);
        Coupon::create(['code' => 'INACTIVE', 'discount_type' => 'percent', 'discount_value' => 10, 'is_active' => false]);
        Coupon::create(['code' => 'EXPIRED', 'discount_type' => 'percent', 'discount_value' => 10, 'is_active' => true, 'expires_at' => now()->subDay()]);

        $codes = Coupon::active()->pluck('code')->all();

        $this->assertSame(['ACTIVE'], $codes);
    }

    public function test_service_redeems_valid_coupon_and_writes_row(): void
    {
        $coupon = Coupon::create(['code' => 'SAVE10', 'discount_type' => 'percent', 'discount_value' => 10, 'is_active' => true]);
        $user = User::factory()->create();

        $redemption = app(CouponService::class)->redeem('SAVE10', $user);

        $this->assertInstanceOf(CouponRedemption::class, $redemption);
        $this->assertSame($coupon->id, $redemption->coupon_id);
        $this->assertSame($user->id, $redemption->user_id);
        $this->assertNotNull($redemption->redeemed_at);
    }

    public function test_service_throws_on_invalid_code(): void
    {
        $user = User::factory()->create();

        $this->expectException(CouponInvalidException::class);
        app(CouponService::class)->redeem('NONEXISTENT', $user);
    }

    public function test_service_throws_on_expired_coupon(): void
    {
        Coupon::create(['code' => 'EXPIRED', 'discount_type' => 'percent', 'discount_value' => 10, 'is_active' => true, 'expires_at' => now()->subDay()]);
        $user = User::factory()->create();

        $this->expectException(CouponInvalidException::class);
        app(CouponService::class)->redeem('EXPIRED', $user);
    }

    public function test_service_throws_on_max_redemptions_reached(): void
    {
        $coupon = Coupon::create([
            'code' => 'LIMITED',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'is_active' => true,
            'max_redemptions' => 1,
        ]);
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        app(CouponService::class)->redeem('LIMITED', $firstUser);

        $this->expectException(CouponInvalidException::class);
        app(CouponService::class)->redeem('LIMITED', $secondUser);
    }

    public function test_service_throws_when_user_already_redeemed(): void
    {
        Coupon::create(['code' => 'ONCE', 'discount_type' => 'percent', 'discount_value' => 10, 'is_active' => true]);
        $user = User::factory()->create();
        app(CouponService::class)->redeem('ONCE', $user);

        $this->expectException(CouponInvalidException::class);
        app(CouponService::class)->redeem('ONCE', $user);
    }

    public function test_apply_coupon_route_writes_redemption_on_success(): void
    {
        Coupon::create(['code' => 'ROUTE10', 'discount_type' => 'percent', 'discount_value' => 10, 'is_active' => true]);
        $user = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($user)
            ->from('/subscription/plans')
            ->post('/subscription/apply-coupon', ['code' => 'ROUTE10']);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('coupon_redemptions', [
            'user_id' => $user->id,
        ]);
    }

    public function test_apply_coupon_route_flashes_error_on_invalid_code(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($user)
            ->from('/subscription/plans')
            ->post('/subscription/apply-coupon', ['code' => 'NOPE']);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
