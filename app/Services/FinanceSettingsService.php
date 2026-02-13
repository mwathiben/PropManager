<?php

namespace App\Services;

use App\Models\PaymentConfiguration;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;

class FinanceSettingsService
{
    public function getPaymentConfig(int $landlordId): ?array
    {
        $config = PaymentConfiguration::where('landlord_id', $landlordId)->first();

        if (! $config) {
            return null;
        }

        return [
            'accepted_payment_methods' => $config->accepted_payment_methods ?? [],
            'bank_name' => $config->bank_name,
            'bank_account_name' => $config->bank_account_name,
            'bank_account_number' => $config->bank_account_number,
            'bank_branch' => $config->bank_branch,
            'mpesa_shortcode_type' => $config->mpesa_shortcode_type ?? 'paybill',
            'mpesa_shortcode' => $config->mpesa_shortcode,
            'mpesa_account_name' => $config->mpesa_account_name,
            'has_mpesa_passkey' => ! empty($config->mpesa_passkey),
            'paystack_enabled' => $config->paystack_enabled,
            'default_currency' => $config->default_currency?->value ?? 'KES',
        ];
    }

    public function getInvoiceSettings(int $landlordId): array
    {
        return [
            'include_water_charges' => filter_var(
                Setting::get('invoice_include_water_charges', 'true', $landlordId),
                FILTER_VALIDATE_BOOLEAN
            ),
            'include_arrears' => filter_var(
                Setting::get('invoice_include_arrears', 'true', $landlordId),
                FILTER_VALIDATE_BOOLEAN
            ),
            'auto_generate_monthly' => filter_var(
                Setting::get('invoice_auto_generate_monthly', 'false', $landlordId),
                FILTER_VALIDATE_BOOLEAN
            ),
        ];
    }

    public function getReminderSettings(int $landlordId): array
    {
        $channels = Setting::get('reminder_channels', null, $landlordId);

        return [
            'reminder_days_before_due' => (int) Setting::get('reminder_days_before_due', '3', $landlordId),
            'overdue_reminder_frequency' => Setting::get('overdue_reminder_frequency', 'weekly', $landlordId),
            'reminder_channels' => $channels ? json_decode($channels, true) : ['email'],
        ];
    }

    public function getReceiptSettings(int $landlordId): array
    {
        $user = User::find($landlordId);
        $settings = $user?->invoiceSetting;

        return [
            'auto_email_receipt' => $settings?->auto_email_receipt ?? true,
            'receipt_show_logo' => $settings?->receipt_show_logo ?? true,
            'receipt_show_tenant_details' => $settings?->receipt_show_tenant_details ?? true,
            'receipt_show_invoice_details' => $settings?->receipt_show_invoice_details ?? true,
            'receipt_show_payment_method' => $settings?->receipt_show_payment_method ?? true,
            'receipt_header_text' => $settings?->receipt_header_text,
            'receipt_footer_text' => $settings?->receipt_footer_text,
            'receipt_thank_you_message' => $settings?->receipt_thank_you_message,
        ];
    }

    public function getFiscalYearSettings(int $landlordId): array
    {
        $user = User::find($landlordId);
        $settings = $user?->invoiceSetting;

        return [
            'fiscal_year_type' => $settings?->fiscal_year_type ?? 'calendar',
            'fiscal_year_start_month' => $settings?->fiscal_year_start_month ?? 1,
        ];
    }

    public function updateDefaultCurrency(int $landlordId, Request $request): void
    {
        PaymentConfiguration::updateOrCreate(
            ['landlord_id' => $landlordId],
            ['default_currency' => $request->default_currency]
        );
    }

    public function updatePaymentMethods(int $landlordId, Request $request): void
    {
        $data = [
            'accepted_payment_methods' => $request->accepted_payment_methods,
            'bank_name' => $request->bank_name,
            'bank_account_name' => $request->bank_account_name,
            'bank_account_number' => $request->bank_account_number,
            'bank_branch' => $request->bank_branch,
            'mpesa_shortcode_type' => $request->mpesa_shortcode_type ?? 'paybill',
            'mpesa_shortcode' => $request->mpesa_shortcode,
            'mpesa_account_name' => $request->mpesa_account_name,
        ];

        if ($request->filled('mpesa_passkey')) {
            $data['mpesa_passkey'] = $request->mpesa_passkey;
        }

        PaymentConfiguration::updateOrCreate(
            ['landlord_id' => $landlordId],
            $data
        );
    }

    public function updateInvoiceSettings(int $landlordId, Request $request): void
    {
        Setting::set('invoice_include_water_charges', $request->include_water_charges ? 'true' : 'false', false, 'invoice', null, $landlordId);
        Setting::set('invoice_include_arrears', $request->include_arrears ? 'true' : 'false', false, 'invoice', null, $landlordId);
        Setting::set('invoice_auto_generate_monthly', $request->auto_generate_monthly ? 'true' : 'false', false, 'invoice', null, $landlordId);
    }

    public function updateReminderSettings(int $landlordId, Request $request): void
    {
        Setting::set('reminder_days_before_due', (string) $request->reminder_days_before_due, false, 'notification', null, $landlordId);
        Setting::set('overdue_reminder_frequency', $request->overdue_reminder_frequency, false, 'notification', null, $landlordId);
        Setting::set('reminder_channels', json_encode($request->reminder_channels), false, 'notification', null, $landlordId);
    }

    public function updateReceiptSettings(int $landlordId, Request $request): void
    {
        $user = User::find($landlordId);
        $settings = $user->getOrCreateInvoiceSetting();

        $settings->update([
            'auto_email_receipt' => $request->boolean('auto_email_receipt'),
            'receipt_show_logo' => $request->boolean('receipt_show_logo'),
            'receipt_show_tenant_details' => $request->boolean('receipt_show_tenant_details'),
            'receipt_show_invoice_details' => $request->boolean('receipt_show_invoice_details'),
            'receipt_show_payment_method' => $request->boolean('receipt_show_payment_method'),
            'receipt_header_text' => $request->receipt_header_text,
            'receipt_footer_text' => $request->receipt_footer_text,
            'receipt_thank_you_message' => $request->receipt_thank_you_message,
        ]);
    }

    public function updateFiscalYearSettings(int $landlordId, Request $request): void
    {
        $user = User::find($landlordId);
        $settings = $user->getOrCreateInvoiceSetting();

        $settings->update([
            'fiscal_year_type' => $request->fiscal_year_type,
            'fiscal_year_start_month' => $request->fiscal_year_start_month,
        ]);
    }
}
