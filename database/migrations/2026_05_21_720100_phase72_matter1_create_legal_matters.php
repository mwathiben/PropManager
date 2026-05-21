<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-72 MATTER-GROUPING: a "case" that groups many legal holds so the
 * landlord sees "Case CV/2026/0123 — 12 records held", not 12 flat rows.
 * Landlord-scoped (TenantScope); closing a matter is audited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_matters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('matter_reference')->nullable();
            $table->string('situation_type')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->date('review_by')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['landlord_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_matters');
    }
};
