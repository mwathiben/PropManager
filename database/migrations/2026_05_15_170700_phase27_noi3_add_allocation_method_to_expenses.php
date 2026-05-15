<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-27 BI-NOI-3: expense-to-property allocation methodology.
 *
 * Today expenses link to property_id / building_id / unit_id directly
 * (the existing fillable). Many expenses are "general" (accountant
 * fee, software subscription, marketing) — they have no specific
 * property. Today those inflate the portfolio NOI but understate
 * per-property NOI.
 *
 * Allocation methods (per Phase-27 BI-NOI-3 PRD):
 *   - direct          — already attributed (the existing path)
 *   - per_unit        — split by unit count per property
 *   - per_revenue     — split by revenue share per property
 *   - per_floor_area  — split by floor area per property (future,
 *                       requires units.floor_area_m2 which doesn't
 *                       exist yet — documented + accepted but
 *                       returns 0 in NoiService until the field lands)
 *
 * Default to 'direct' so existing rows behave exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('expenses', 'allocation_method')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('allocation_method', 32)
                    ->default('direct')
                    ->after('unit_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('expenses', 'allocation_method')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropColumn('allocation_method');
            });
        }
    }
};
