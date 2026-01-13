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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');

            // Polymorphic relationship - can attach to any model
            $table->morphs('documentable'); // Creates documentable_id and documentable_type

            // Document details
            $table->string('title'); // User-friendly name
            $table->string('file_name'); // Original filename
            $table->string('file_path'); // Storage path
            $table->string('mime_type'); // File MIME type
            $table->unsignedBigInteger('file_size'); // Size in bytes
            $table->enum('document_type', [
                'lease_agreement',
                'tenant_id',
                'tenant_passport',
                'bank_statement',
                'payslip',
                'reference_letter',
                'utility_bill',
                'other',
            ])->default('other');

            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->constrained('users'); // Who uploaded it

            $table->timestamps();
            $table->softDeletes(); // Soft delete for audit trail

            // Indexes
            $table->index(['documentable_id', 'documentable_type']);
            $table->index('document_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
