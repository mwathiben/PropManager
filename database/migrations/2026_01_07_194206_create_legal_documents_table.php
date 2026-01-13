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
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50); // terms, privacy, cookies, dpa
            $table->string('version', 20);
            $table->string('title');
            $table->longText('content');
            $table->text('summary')->nullable(); // Brief summary of changes
            $table->boolean('is_active')->default(false);
            $table->date('effective_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['type', 'version']);
            $table->index(['type', 'is_active']);
            $table->index('effective_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
