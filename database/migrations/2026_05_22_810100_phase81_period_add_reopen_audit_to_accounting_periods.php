<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-81 PERIOD-CLOSE-3: audit who reopened a closed accounting period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->timestamp('reopened_at')->nullable()->after('close_notes');
            $table->foreignId('reopened_by_user_id')->nullable()->after('reopened_at')->constrained('users')->nullOnDelete();
            $table->string('reopen_reason', 500)->nullable()->after('reopened_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reopened_by_user_id');
            $table->dropColumn(['reopened_at', 'reopen_reason']);
        });
    }
};
