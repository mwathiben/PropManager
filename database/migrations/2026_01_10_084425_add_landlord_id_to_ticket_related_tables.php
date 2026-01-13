<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ticket_comments', 'landlord_id')) {
            Schema::table('ticket_comments', function (Blueprint $table) {
                $table->foreignId('landlord_id')->nullable()->after('id')->constrained('users');
            });
        }

        if (! Schema::hasColumn('ticket_activities', 'landlord_id')) {
            Schema::table('ticket_activities', function (Blueprint $table) {
                $table->foreignId('landlord_id')->nullable()->after('id')->constrained('users');
            });
        }

        // SQLite-compatible UPDATE using subquery
        DB::statement('
            UPDATE ticket_comments
            SET landlord_id = (
                SELECT t.landlord_id FROM tickets t WHERE t.id = ticket_comments.ticket_id
            )
            WHERE landlord_id IS NULL
        ');

        DB::statement('
            UPDATE ticket_activities
            SET landlord_id = (
                SELECT t.landlord_id FROM tickets t WHERE t.id = ticket_activities.ticket_id
            )
            WHERE landlord_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropForeign(['landlord_id']);
            $table->dropColumn('landlord_id');
        });

        Schema::table('ticket_activities', function (Blueprint $table) {
            $table->dropForeign(['landlord_id']);
            $table->dropColumn('landlord_id');
        });
    }
};
