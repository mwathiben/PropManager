<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-72 MATTER-GROUPING: link a hold to its matter. Nullable — existing
 * holds (and ad-hoc single-subject holds) stay matter-less; nullOnDelete so
 * deleting a matter unlinks its holds rather than cascading them away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_holds', function (Blueprint $table) {
            $table->foreignId('legal_matter_id')
                ->nullable()
                ->after('id')
                ->constrained('legal_matters')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('legal_holds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('legal_matter_id');
        });
    }
};
