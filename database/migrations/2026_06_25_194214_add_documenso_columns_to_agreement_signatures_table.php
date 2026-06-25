<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agreement_signatures', function (Blueprint $table) {
            // Documenso integrity layer (Slice 2, PR 2.4b). All nullable: the
            // in-house OTP click-sign (PR 2.3c) remains a complete signing path
            // and never populates these. Populated only on the Documenso path.
            $table->string('signing_method', 20)->default('in_house')->after('status');
            $table->unsignedBigInteger('documenso_document_id')->nullable()->after('signing_method');
            $table->string('documenso_envelope_id')->nullable()->after('documenso_document_id');
            $table->string('documenso_recipient_token', 100)->nullable()->after('documenso_envelope_id');
            $table->string('documenso_status', 30)->nullable()->after('documenso_recipient_token');
            $table->timestamp('documenso_completed_at')->nullable()->after('documenso_status');
            $table->string('signed_pdf_path')->nullable()->after('documenso_completed_at');
            $table->string('certificate_path')->nullable()->after('signed_pdf_path');
            $table->string('sealed_pdf_sha256', 64)->nullable()->after('certificate_path');

            // The webhook matches the inbound document id back to its signature,
            // and it is the SOLE correlation key for an unauthenticated, money-
            // activating callback — so it must be unique. Nullable-unique in MySQL
            // permits many NULLs, so the in-house path (never sets it) is unaffected.
            $table->unique('documenso_document_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agreement_signatures', function (Blueprint $table) {
            $table->dropIndex(['documenso_document_id']);
            $table->dropColumn([
                'signing_method',
                'documenso_document_id',
                'documenso_envelope_id',
                'documenso_recipient_token',
                'documenso_status',
                'documenso_completed_at',
                'signed_pdf_path',
                'certificate_path',
                'sealed_pdf_sha256',
            ]);
        });
    }
};
