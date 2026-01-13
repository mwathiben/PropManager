<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('invoice_type_id')->nullable()->after('landlord_id')->constrained('invoice_types')->nullOnDelete();
            $table->foreignId('invoice_template_id')->nullable()->after('invoice_type_id')->constrained('invoice_templates')->nullOnDelete();
            $table->foreignId('credit_note_for_id')->nullable()->after('invoice_template_id')->constrained('invoices')->nullOnDelete();

            $table->text('notes')->nullable()->after('status');
            $table->timestamp('sent_at')->nullable()->after('notes');
            $table->timestamp('viewed_at')->nullable()->after('sent_at');
        });

        $standardType = DB::table('invoice_types')->where('code', 'standard')->first();
        if ($standardType) {
            DB::table('invoices')->whereNull('invoice_type_id')->update([
                'invoice_type_id' => $standardType->id,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['invoice_type_id']);
            $table->dropForeign(['invoice_template_id']);
            $table->dropForeign(['credit_note_for_id']);
            $table->dropColumn([
                'invoice_type_id',
                'invoice_template_id',
                'credit_note_for_id',
                'notes',
                'sent_at',
                'viewed_at',
            ]);
        });
    }
};
