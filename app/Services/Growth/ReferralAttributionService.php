<?php

declare(strict_types=1);

namespace App\Services\Growth;

use App\Events\ReferralAttributed;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase-34 GROWTH-REFERRAL-2: referral redeem + attribution.
 *
 *   - generateCodeFor(User): assigns a unique 8-char code (collision-
 *     retry up to 5 times before throwing).
 *   - redeem(referredUser, code): writes a pending row if the
 *     referred user hasn't been referred before AND the code maps
 *     to a real referrer that isn't the referred user themselves.
 *   - attribute(referredUser): flips pending->attributed when the
 *     referred landlord completes a qualifying milestone (typically
 *     first_invoice via the MilestoneRecorded listener pipe).
 */
class ReferralAttributionService
{
    public function generateCodeFor(User $user): string
    {
        if ($user->referral_code) {
            return $user->referral_code;
        }

        $code = $this->generateUniqueCode();
        $user->forceFill(['referral_code' => $code])->save();

        return $code;
    }

    public function redeem(User $referred, string $code): ?Referral
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }

        $referrer = User::query()->where('referral_code', $code)->first();
        if (! $referrer || $referrer->id === $referred->id) {
            return null;
        }

        $existing = Referral::query()->where('referred_user_id', $referred->id)->first();
        if ($existing) {
            return $existing;
        }

        return Referral::create([
            'referrer_user_id' => $referrer->id,
            'referred_user_id' => $referred->id,
            'referral_code' => $code,
            'status' => Referral::STATUS_PENDING,
        ]);
    }

    public function attribute(User $referred): ?Referral
    {
        $referral = Referral::query()
            ->where('referred_user_id', $referred->id)
            ->where('status', Referral::STATUS_PENDING)
            ->first();
        if (! $referral) {
            return null;
        }

        DB::transaction(function () use ($referral) {
            $referral->update([
                'status' => Referral::STATUS_ATTRIBUTED,
                'attributed_at' => now(),
            ]);
        });

        ReferralAttributed::dispatch($referral->fresh());

        return $referral->fresh();
    }

    private function generateUniqueCode(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $code = strtoupper(Str::random(8));
            if (! User::query()->where('referral_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not generate unique referral code after 5 attempts.');
    }
}
