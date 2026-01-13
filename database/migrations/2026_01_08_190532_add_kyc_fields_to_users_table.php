<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add KYC fields for tenant verification.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('emergency_contact_name')->nullable()->after('national_id');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->string('profile_photo_path')->nullable()->after('emergency_contact_phone');
            $table->timestamp('kyc_completed_at')->nullable()->after('profile_photo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'emergency_contact_name',
                'emergency_contact_phone',
                'profile_photo_path',
                'kyc_completed_at',
            ]);
        });
    }
};
