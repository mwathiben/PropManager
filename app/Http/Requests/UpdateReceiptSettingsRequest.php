<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReceiptSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user->isScopeOwner() || $user->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'auto_email_receipt' => 'boolean',
            'receipt_show_logo' => 'boolean',
            'receipt_show_tenant_details' => 'boolean',
            'receipt_show_invoice_details' => 'boolean',
            'receipt_show_payment_method' => 'boolean',
            'receipt_header_text' => 'nullable|string|max:255',
            'receipt_footer_text' => 'nullable|string|max:2000',
            'receipt_thank_you_message' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'auto_email_receipt.boolean' => 'Auto-email receipt must be true or false.',
            'receipt_show_logo.boolean' => 'Show logo must be true or false.',
            'receipt_show_tenant_details.boolean' => 'Show tenant details must be true or false.',
            'receipt_show_invoice_details.boolean' => 'Show invoice details must be true or false.',
            'receipt_show_payment_method.boolean' => 'Show payment method must be true or false.',
            'receipt_header_text.max' => 'Header text cannot exceed 255 characters.',
            'receipt_footer_text.max' => 'Footer text cannot exceed 2000 characters.',
            'receipt_thank_you_message.max' => 'Thank you message cannot exceed 500 characters.',
        ];
    }
}
