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
        Schema::table('invoice_settings', function (Blueprint $table) {
            $table->boolean('auto_email_receipt')->default(true)->after('auto_generate_first_invoice');
            $table->boolean('receipt_show_logo')->default(true)->after('auto_email_receipt');
            $table->boolean('receipt_show_tenant_details')->default(true)->after('receipt_show_logo');
            $table->boolean('receipt_show_invoice_details')->default(true)->after('receipt_show_tenant_details');
            $table->boolean('receipt_show_payment_method')->default(true)->after('receipt_show_invoice_details');
            $table->string('receipt_header_text', 255)->nullable()->after('receipt_show_payment_method');
            $table->text('receipt_footer_text')->nullable()->after('receipt_header_text');
            $table->string('receipt_thank_you_message', 500)->nullable()->after('receipt_footer_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_settings', function (Blueprint $table) {
            $table->dropColumn([
                'auto_email_receipt',
                'receipt_show_logo',
                'receipt_show_tenant_details',
                'receipt_show_invoice_details',
                'receipt_show_payment_method',
                'receipt_header_text',
                'receipt_footer_text',
                'receipt_thank_you_message',
            ]);
        });
    }
};
