<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-31 ONB-HELP-2: per-route helpKey mapping. The drawer needs a
 * way to surface "the article(s) for THIS page" — we add a help_key
 * string column on help_articles, indexed for lookup, and the Vue
 * pages publish their helpKey constant via a defineOptions block.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_articles', function (Blueprint $table) {
            $table->string('help_key')->nullable()->after('category')->index('ha_help_key_idx');
        });
    }

    public function down(): void
    {
        Schema::table('help_articles', function (Blueprint $table) {
            $table->dropIndex('ha_help_key_idx');
            $table->dropColumn('help_key');
        });
    }
};
