<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Phase-25 API-RATELIMIT-3 watchdog: each Sanctum PAT can carry a
 * rate_limit_multiplier that scales the default API bucket. The
 * lift travels with the token, so revoke ⇒ lift is gone.
 */
class Phase25RateLimitMultiplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_access_tokens_has_rate_limit_multiplier_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('personal_access_tokens', 'rate_limit_multiplier'),
            'API-RATELIMIT-3: personal_access_tokens.rate_limit_multiplier column must exist.',
        );
    }

    public function test_default_multiplier_is_one(): void
    {
        $user = User::factory()->create();
        $issued = $user->createToken('default-bucket');
        $token = PersonalAccessToken::find($issued->accessToken->id);

        $this->assertSame(
            1.0,
            (float) $token->rate_limit_multiplier,
            'API-RATELIMIT-3: a freshly minted token must default to multiplier 1.0 (parity with the legacy bucket).',
        );
    }

    public function test_multiplier_scales_the_api_bucket(): void
    {
        config()->set('security.rate_limits.api', '10,1');

        $user = User::factory()->create();
        $issued = $user->createToken('lifted-partner');
        $token = $issued->accessToken;
        $token->forceFill(['rate_limit_multiplier' => 2.0])->save();

        // Inject the token as the request's current access token so
        // the limiter sees the lift. currentAccessToken() reads from
        // the user's withAccessToken(...) state.
        $user->withAccessToken($token);

        $request = Request::create('/api/v1/anything', 'GET');
        $request->setUserResolver(fn () => $user);

        $resolver = RateLimiterFacade::limiter('api');
        $this->assertIsCallable($resolver, 'API-RATELIMIT-3: the api rate limiter must be a callable resolver.');

        $limit = $resolver($request);
        $limits = is_array($limit) ? $limit : [$limit];
        $this->assertSame(
            20,
            $limits[0]->maxAttempts,
            'API-RATELIMIT-3: multiplier 2.0 on a 10/min base must lift the bucket to 20/min.',
        );
    }

    public function test_multiplier_below_one_throttles_the_api_bucket(): void
    {
        config()->set('security.rate_limits.api', '10,1');

        $user = User::factory()->create();
        $issued = $user->createToken('demoted');
        $token = $issued->accessToken;
        $token->forceFill(['rate_limit_multiplier' => 0.5])->save();
        $user->withAccessToken($token);

        $request = Request::create('/api/v1/anything', 'GET');
        $request->setUserResolver(fn () => $user);

        $resolver = RateLimiterFacade::limiter('api');
        $limit = $resolver($request);
        $limits = is_array($limit) ? $limit : [$limit];
        $this->assertSame(
            5,
            $limits[0]->maxAttempts,
            'API-RATELIMIT-3: multiplier 0.5 on a 10/min base must shrink the bucket to 5/min.',
        );
    }

    public function test_zero_or_negative_multiplier_falls_back_to_default(): void
    {
        // Defensive: zero must not zero out the partner (revoke is for that).
        config()->set('security.rate_limits.api', '10,1');

        $user = User::factory()->create();
        $issued = $user->createToken('zeroed');
        $token = $issued->accessToken;
        $token->forceFill(['rate_limit_multiplier' => 0.0])->save();
        $user->withAccessToken($token);

        $request = Request::create('/api/v1/anything', 'GET');
        $request->setUserResolver(fn () => $user);

        $resolver = RateLimiterFacade::limiter('api');
        $limit = $resolver($request);
        $limits = is_array($limit) ? $limit : [$limit];
        $this->assertSame(
            10,
            $limits[0]->maxAttempts,
            'API-RATELIMIT-3: a zero or negative multiplier must fall back to the configured base, not lock the partner out.',
        );
    }
}
