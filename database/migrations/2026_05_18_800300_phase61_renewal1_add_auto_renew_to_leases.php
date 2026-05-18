<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-61 RENEWAL-AUTO-1: opt-out flag + parent-lease audit link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->boolean('auto_renew')->default(true)->after('is_active');
            $table->foreignId('renewed_from_lease_id')->nullable()->after('auto_renew')
                ->constrained('leases')->nullOnDelete();
            $table->index(['auto_renew', 'end_date'], 'leases_auto_renew_scan');
        });
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropIndex('leases_auto_renew_scan');
            $table->dropForeign(['renewed_from_lease_id']);
            $table->dropColumn(['auto_renew', 'renewed_from_lease_id']);
        });
    }
};
