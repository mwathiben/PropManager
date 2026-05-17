<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-50 DRILL-DOWN-1: link reports into a parent→child hierarchy.
 *
 * parent_report_id is set on a child synthesised by DrillDownService —
 * the link is metadata for "this report was born from drilling X" so
 * landlords can navigate back up.
 *
 * drill_field names the ALLOWED_FIELDS column the child will filter on
 * (e.g. 'payment.payment_method'). NULL = no drill-down available.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_reports', function (Blueprint $table) {
            $table->foreignId('parent_report_id')->nullable()->after('description')
                ->constrained('saved_reports')->nullOnDelete();
            $table->string('drill_field', 64)->nullable()->after('parent_report_id');
            $table->index('parent_report_id', 'saved_reports_parent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('saved_reports', function (Blueprint $table) {
            $table->dropForeign(['parent_report_id']);
            $table->dropIndex('saved_reports_parent_idx');
            $table->dropColumn(['parent_report_id', 'drill_field']);
        });
    }
};
