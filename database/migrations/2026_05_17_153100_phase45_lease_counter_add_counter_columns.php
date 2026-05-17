<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-45 LEASE-COUNTER-1/3: extend lease_renewals to support tenant
 * counter-offers + create lease_renewal_counter_history audit table.
 *
 * Phase 29 shipped proposed → accepted | rejected → confirmed. Phase 45
 * adds counter_proposed as a sibling of accepted/rejected — tenant
 * proposes alternative rent + end_date, landlord accepts/rejects/re-proposes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_renewals', function (Blueprint $table): void {
            $table->unsignedBigInteger('counter_rent_amount_cents')
                ->nullable()
                ->after('rejection_reason');
            $table->date('counter_end_date')
                ->nullable()
                ->after('counter_rent_amount_cents');
            $table->text('counter_message')
                ->nullable()
                ->after('counter_end_date');
            $table->timestamp('counter_submitted_at')
                ->nullable()
                ->after('counter_message');
        });

        // Extend the status enum to include 'counter_proposed'. MySQL
        // 5.7 doesn't support ALTER ENUM; redefine the column.
        DB::statement(
            "ALTER TABLE lease_renewals MODIFY COLUMN status ENUM('proposed','counter_proposed','accepted','rejected','confirmed','expired') NOT NULL DEFAULT 'proposed'"
        );

        Schema::create('lease_renewal_counter_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lease_renewal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('action', ['proposed', 'countered', 're_proposed', 'accepted', 'rejected', 'expired']);
            $table->unsignedBigInteger('rent_amount_cents')->nullable();
            $table->date('end_date')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['lease_renewal_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_renewal_counter_history');

        DB::statement(
            "ALTER TABLE lease_renewals MODIFY COLUMN status ENUM('proposed','accepted','rejected','confirmed','expired') NOT NULL DEFAULT 'proposed'"
        );

        Schema::table('lease_renewals', function (Blueprint $table): void {
            $table->dropColumn(['counter_rent_amount_cents', 'counter_end_date', 'counter_message', 'counter_submitted_at']);
        });
    }
};
