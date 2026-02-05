<?php

namespace App\Http\Requests\Settings;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentMethodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isLandlord();
    }

    public function rules(): array
    {
        return [
            'accepted_payment_methods' => 'required|array|min:1',
            'accepted_payment_methods.*' => ['string', Rule::in(PaymentMethod::values())],
            'bank_name' => 'nullable|string|max:255',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_branch' => 'nullable|string|max:255',
            'mpesa_paybill' => 'nullable|string|max:20',
            'mpesa_account_name' => 'nullable|string|max:255',
            'mpesa_shortcode' => 'nullable|string|max:20',
            'mpesa_shortcode_type' => 'nullable|string|in:paybill,till',
            'mpesa_passkey' => 'nullable|string|max:255',
            'mpesa_consumer_key' => 'nullable|string|max:255',
            'mpesa_consumer_secret' => 'nullable|string|max:255',
            'mpesa_environment' => 'nullable|string|in:sandbox,production',
            'mpesa_b2c_shortcode' => 'nullable|string|max:20',
            'mpesa_b2c_initiator' => 'nullable|string|max:255',
            'mpesa_b2c_password' => 'nullable|string|max:255',
            'mpesa_b2c_security_credential' => 'nullable|string|max:500',
            'paystack_enabled' => 'boolean',
            'paystack_public_key' => 'nullable|string|max:255',
            'paystack_secret_key' => 'nullable|string|max:255',
            'intasend_enabled' => 'boolean',
            'intasend_publishable_key' => 'nullable|string|max:255',
            'intasend_secret_key' => 'nullable|string|max:255',
            'intasend_webhook_challenge' => 'nullable|string|max:255',
            'intasend_environment' => 'nullable|string|in:sandbox,production',
        ];
    }
}
