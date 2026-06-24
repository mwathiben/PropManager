<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice-2 PR-2.1: curated, explained, parameterised legal clauses — the building
 * blocks of management (and later tenancy) agreements. Platform reference data,
 * NOT tenant-scoped: clauses are shared across all managers; each agreement
 * snapshots its own rendered text. needs_legal_review flags DRAFT content pending
 * advocate sign-off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clauses', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('type')->default('management');
            $table->string('binding');
            $table->string('title');
            $table->text('explanation');
            $table->text('body_template');
            $table->json('params_schema')->nullable();
            $table->boolean('is_exclusive')->default(true);
            $table->string('jurisdiction', 8)->default('KE');
            $table->string('version')->default('draft-2026-06');
            $table->boolean('is_active')->default(true);
            $table->boolean('needs_legal_review')->default(true);
            $table->timestamps();

            $table->unique(['key', 'version']);
            $table->index(['type', 'binding', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clauses');
    }
};
