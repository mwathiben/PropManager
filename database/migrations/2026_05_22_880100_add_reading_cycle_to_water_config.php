<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-88 WATER-READING-CYCLE (CYCLE-CONFIG-1): the read -> review -> bill
 * cadence config. water_reading_day = day-of-month the caretaker is reminded to
 * read; water_review_days = the landlord review window (days from a reading being
 * recorded) after which a still-pending reading auto-approves so billing is never
 * silently halted. water_readings.auto_approved flags those system approvals.
 * All nullable / default-off so behaviour is unchanged until configured.
 */
return new class extends Migration
{
    private array $configTables = ['payment_configurations', 'buildings'];

    public function up(): void
    {
        foreach ($this->configTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedTinyInteger('water_reading_day')->nullable();
                $t->unsignedTinyInteger('water_review_days')->nullable();
            });
        }

        Schema::table('water_readings', function (Blueprint $t) {
            $t->boolean('auto_approved')->default(false)->after('is_anomalous');
        });
    }

    public function down(): void
    {
        foreach ($this->configTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['water_reading_day', 'water_review_days']);
            });
        }

        Schema::table('water_readings', function (Blueprint $t) {
            $t->dropColumn('auto_approved');
        });
    }
};
