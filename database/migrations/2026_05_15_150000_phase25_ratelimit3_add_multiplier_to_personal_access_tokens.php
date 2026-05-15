<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-25 API-RATELIMIT-3: per-token rate-limit multiplier. Default
 * 1.0 = the bucket size configured in security.rate_limits.api. A
 * trusted partner can be lifted off the default ad-hoc by an operator
 * raising the value (2.0 = 2x the bucket, 0.5 = half) without
 * burning route attributes or middleware customisation per integrator.
 *
 * Stored on the token (not the user) so revoking the token also
 * revokes the lift.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('personal_access_tokens', 'rate_limit_multiplier')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->decimal('rate_limit_multiplier', 5, 2)->default(1.00)->after('abilities');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('personal_access_tokens', 'rate_limit_multiplier')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->dropColumn('rate_limit_multiplier');
            });
        }
    }
};
