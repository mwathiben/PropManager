<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-83 GUARANTOR-1: a party (parent, employer, company) standing behind a
 * lease. Common for students / first leases / corporate guarantees. Released
 * when the lease ends (move-out completion) or manually.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_guarantors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('national_id')->nullable();
            $table->string('relationship')->nullable();
            $table->decimal('guaranteed_amount', 12, 2)->nullable();
            $table->enum('status', ['active', 'released'])->default('active');
            $table->timestamp('released_at')->nullable();
            $table->string('released_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['lease_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_guarantors');
    }
};
