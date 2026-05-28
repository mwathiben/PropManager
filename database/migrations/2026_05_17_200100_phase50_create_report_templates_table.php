<?php

declare(strict_types=1);

use Database\Seeders\Phase50ReportTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-50 TEMPLATE-MARKETPLACE-1: platform-curated report templates.
 * No landlord_id — these are global. Landlords clone via
 * ReportTemplateService::cloneFor which creates a SavedReport scoped
 * to the cloning landlord.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 200);
            $table->enum('category', ['finance', 'occupancy', 'tenant', 'maintenance', 'growth', 'other']);
            $table->text('description')->nullable();
            $table->json('config');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'is_active', 'sort_order'], 'report_templates_cat_active_idx');
        });

        (new Phase50ReportTemplateSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('report_templates');
    }
};
