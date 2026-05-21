<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-83 CO-TENANT-1: additional tenants on a joint tenancy. Leases were
 * strictly single tenant_id; couples / flatmates / company-plus-signatory could
 * not be represented. The primary tenant stays on leases.tenant_id; co-tenants
 * are recorded here (optionally linked to a user account).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_co_tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('national_id')->nullable();
            $table->string('relationship')->nullable();
            $table->boolean('is_responsible_for_rent')->default(false);
            $table->decimal('liability_share', 5, 2)->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['lease_id', 'removed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_co_tenants');
    }
};
