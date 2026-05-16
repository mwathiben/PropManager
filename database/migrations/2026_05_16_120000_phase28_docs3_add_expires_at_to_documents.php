<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-28 TENANT-DOCS-3: documents.expires_at lets the tenant dashboard
 * surface an expiry banner 30 days ahead for time-bound documents
 * (national_id, alien_id, passport, lease_addendum). Default NULL — the
 * vast majority of documents (lease agreements, receipts, payslips) do
 * not have an expiry and the column stays unset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->date('expires_at')->nullable()->after('document_type');
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
