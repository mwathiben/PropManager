<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-94 WATER-CLIENTS-FOUNDATION: a water connection is the "water line" a
 * landlord supplies to a non-tenant client (neighbour) — the analogue of a Lease,
 * but water-only. user_id is the water-client account (linked at onboarding,
 * Phase 95); until then a connection is an identified, rated water line the
 * landlord manages. supplies_water_clients + water_client_rate opt the landlord in
 * and set the default (different) rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('meter_id')->nullable()->constrained('water_meters')->nullOnDelete();
            $table->string('identifier');
            $table->string('client_name')->nullable();
            $table->string('billing_mode')->default('metered');
            $table->decimal('client_rate', 10, 2)->nullable();
            $table->string('status')->default('active');
            $table->date('connected_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['landlord_id', 'status']);
            $table->index('user_id');
        });

        Schema::table('payment_configurations', function (Blueprint $table) {
            $table->boolean('supplies_water_clients')->default(false);
            $table->decimal('water_client_rate', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_connections');

        Schema::table('payment_configurations', function (Blueprint $table) {
            $table->dropColumn(['supplies_water_clients', 'water_client_rate']);
        });
    }
};
