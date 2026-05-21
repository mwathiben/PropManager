<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-82 DOC-META-1: document lifecycle columns — issue date, supersede link
 * (for renewals), per-document reminder window, and a renewable flag. Also widens
 * document_type from a drifting ENUM to a string so the Document model
 * (DOCUMENT_TYPES + Rule::in) is the single source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE documents MODIFY document_type VARCHAR(40) NOT NULL DEFAULT 'other'");

        Schema::table('documents', function (Blueprint $table) {
            $table->date('issue_date')->nullable()->after('document_type');
            $table->foreignId('superseded_by_document_id')->nullable()->after('expires_at')
                ->constrained('documents')->nullOnDelete();
            $table->unsignedSmallInteger('reminder_days')->nullable()->after('superseded_by_document_id');
            $table->boolean('is_renewable')->default(false)->after('reminder_days');
            // Expiring-document queries filter on renewable + not-superseded + expires_at.
            $table->index(['landlord_id', 'is_renewable', 'expires_at'], 'documents_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_expiry_idx');
            $table->dropConstrainedForeignId('superseded_by_document_id');
            $table->dropColumn(['issue_date', 'reminder_days', 'is_renewable']);
        });

        DB::statement(
            'ALTER TABLE documents MODIFY document_type ENUM('
            ."'lease_agreement','tenant_id','tenant_passport','bank_statement',"
            ."'payslip','reference_letter','utility_bill','other'"
            .") NOT NULL DEFAULT 'other'",
        );
    }
};
