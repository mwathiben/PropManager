<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-45 TICKET-PHOTOS-2: persist annotated maintenance-ticket photos
 * as sibling Document rows linked to the original via annotates_document_id.
 * annotation_data carries the canvas scene JSON so the annotation can be
 * re-opened in the editor for further edits.
 *
 * Storing the annotated copy as a sibling Document row (rather than
 * replacing the original) keeps both available for the contractor + the
 * landlord, and the existing Document SoftDelete + retention policy
 * applies to the annotated copy automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->foreignId('annotates_document_id')
                ->nullable()
                ->after('documentable_type')
                ->constrained('documents')
                ->nullOnDelete()
                ->comment('Phase-45 TICKET-PHOTOS-2: original Document for annotated copies.');

            $table->json('annotation_data')
                ->nullable()
                ->after('annotates_document_id')
                ->comment('Phase-45 TICKET-PHOTOS-2: canvas scene JSON for re-edit.');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('annotates_document_id');
            $table->dropColumn('annotation_data');
        });
    }
};
