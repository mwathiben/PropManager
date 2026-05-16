<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-29 WF-RENT-REMIND-2: per-lease reminder tier selector.
 *
 * Defaults to 'standard' on new leases. Landlord can override per
 * tenant — e.g., a tenant with a chronic-late history moves to
 * 'aggressive', a long-standing reliable payer moves to 'lenient'.
 * RentRemindersDispatch resolves lease.reminder_tier →
 * RentReminderPolicy where cadence_template = tier (per-landlord),
 * falling back to is_default=true when no exact match.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->enum('reminder_tier', ['standard', 'aggressive', 'lenient', 'custom'])
                ->default('standard')
                ->after('is_active');
            $table->index(['reminder_tier']);
        });
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropIndex(['reminder_tier']);
            $table->dropColumn('reminder_tier');
        });
    }
};
