<?php

declare(strict_types=1);

use App\Models\LandlordProfile;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase-46 CANONICAL-FIX-2: backfill users.profile_photo_path from the
 * canonical LandlordProfile row for every landlord/caretaker user with
 * a LandlordProfile.profile_photo_path set. Closes existing drift
 * before the LandlordProfile::saved listener takes over.
 *
 * No schema changes — pure data migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $count = 0;

        LandlordProfile::query()
            ->whereNotNull('profile_photo_path')
            ->chunkById(200, function ($profiles) use (&$count): void {
                foreach ($profiles as $profile) {
                    $updated = User::query()
                        ->where('id', $profile->user_id)
                        ->where(function ($q) use ($profile) {
                            $q->whereNull('profile_photo_path')
                                ->orWhereRaw('profile_photo_path <> ?', [$profile->profile_photo_path]);
                        })
                        ->update(['profile_photo_path' => $profile->profile_photo_path]);

                    $count += $updated;
                }
            });

        if ($count > 0) {
            DB::table('migrations')->select(); // touch — no-op
            \Illuminate\Support\Facades\Log::info(
                "[Phase-46 CANONICAL-FIX-2] backfilled users.profile_photo_path from {$count} LandlordProfile row(s)."
            );
        }
    }

    public function down(): void
    {
        // Reversible no-op — the data is harmless to leave. The
        // canonical row in landlord_profiles is unchanged.
    }
};
