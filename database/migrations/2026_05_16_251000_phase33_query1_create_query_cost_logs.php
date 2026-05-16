<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-33 COST-QUERY-1: per-request query cost samples. Sampled — only
 * requests with query_count > 10 get a row written, so the table stays
 * O(landlords*100/day) rather than O(rps*100/day). query:cost-audit
 * aggregates rolling 24h into per-route-class p50/p90 scan-to-return
 * gauges.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('query_cost_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('route_class', 64);
            $table->unsignedInteger('query_count');
            $table->unsignedBigInteger('rows_scanned');
            $table->unsignedBigInteger('rows_returned');
            $table->timestamp('request_at');
            $table->timestamps();

            $table->index(['route_class', 'request_at'], 'qcl_route_at_idx');
            $table->index('request_at', 'qcl_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_cost_logs');
    }
};
