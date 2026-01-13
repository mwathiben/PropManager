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
        Schema::table('invitations', function (Blueprint $table) {
            // Add target_user_id to track if invitation is for an existing user
            $table->foreignId('target_user_id')
                ->nullable()
                ->after('email')
                ->constrained('users')
                ->nullOnDelete();

            // Add index for querying invitations by target user
            $table->index('target_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropForeign(['target_user_id']);
            $table->dropIndex(['target_user_id']);
            $table->dropColumn('target_user_id');
        });
    }
};
