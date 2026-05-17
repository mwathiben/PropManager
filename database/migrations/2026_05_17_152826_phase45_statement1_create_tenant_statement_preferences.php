<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-45 STATEMENT-DEPTH-3: per-tenant statement column choices.
 *
 * Phase 28 [TENANT-PORTAL] hard-coded the 6-column statement layout
 * for all tenants. Phase 45 lets a tenant persist which columns they
 * want and the order. The columns JSON shape is a list of column keys
 * matching XlsxExportService 'key' values; the absence of a key means
 * 'don't render this column'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_statement_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('columns');
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_statement_preferences');
    }
};
