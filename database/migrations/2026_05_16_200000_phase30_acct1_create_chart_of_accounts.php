<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-30 INT-ACCT-EXPORT-2: per-landlord chart of accounts. The
 * accounting export needs to map each invoice/expense category to a
 * GL account before it can write a QuickBooks IIF or Sage CSV row;
 * without a mapping table the export collapses everything into a
 * single suspense account, defeating the point. Each landlord owns
 * an isolated chart (TenantScope) with codes that match their own
 * accounting system — there is no cross-tenant "global" chart.
 *
 * The (account_type, account_code) pair is unique per landlord; the
 * (landlord_id, source_kind, source_key) triple lets the export
 * resolve "what account does invoice_type=rent map to?" or
 * "what account does expense_category=42 map to?" in one query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('account_code', 32);
            $table->string('account_name');
            $table->enum('account_type', ['asset', 'liability', 'equity', 'income', 'expense']);
            $table->string('source_kind')->nullable();
            $table->string('source_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['landlord_id', 'account_code'], 'coa_landlord_code_unq');
            $table->index(['landlord_id', 'source_kind', 'source_key'], 'coa_landlord_source_idx');
            $table->index(['landlord_id', 'account_type', 'is_active'], 'coa_landlord_type_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
