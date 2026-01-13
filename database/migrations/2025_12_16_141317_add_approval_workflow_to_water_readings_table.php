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
        Schema::table('water_readings', function (Blueprint $table) {
            // Photo evidence of meter reading
            $table->string('photo_path')->nullable()->after('cost');

            // Approval workflow
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('photo_path');

            // Track who recorded the reading (caretaker/landlord)
            $table->foreignId('recorded_by')->nullable()->constrained('users')->after('status');

            // Track approval/rejection
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->after('recorded_by');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at'); // For rejection reasons or approval notes

            // Optional: OCR verification data
            $table->decimal('ocr_reading', 10, 2)->nullable()->after('review_notes'); // What OCR detected
            $table->boolean('ocr_verified')->default(false)->after('ocr_reading'); // If OCR matches manual input

            // Index for querying pending readings
            $table->index('status');
            $table->index(['landlord_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('water_readings', function (Blueprint $table) {
            $table->dropIndex(['landlord_id', 'status']);
            $table->dropIndex(['status']);

            $table->dropColumn([
                'photo_path',
                'status',
                'recorded_by',
                'reviewed_by',
                'reviewed_at',
                'review_notes',
                'ocr_reading',
                'ocr_verified',
            ]);
        });
    }
};
