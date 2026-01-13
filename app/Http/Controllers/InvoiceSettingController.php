<?php

namespace App\Http\Controllers;

use App\Models\InvoiceSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class InvoiceSettingController extends Controller
{
    public function edit()
    {
        $user = Auth::user();
        $settings = $user->invoiceSetting ?? InvoiceSetting::create([
            'landlord_id' => $user->id,
        ]);

        return Inertia::render('InvoiceSettings/Edit', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'business_name' => 'nullable|string|max:255',
            'business_address' => 'nullable|string|max:1000',
            'business_phone' => 'nullable|string|max:50',
            'business_email' => 'nullable|email|max:255',
            'tax_number' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_branch' => 'nullable|string|max:255',
            'bank_swift_code' => 'nullable|string|max:50',
            'invoice_prefix' => 'nullable|string|max:10',
            'invoice_next_number' => 'nullable|integer|min:1',
            'receipt_prefix' => 'nullable|string|max:10',
            'receipt_next_number' => 'nullable|integer|min:1',
            'credit_note_prefix' => 'nullable|string|max:10',
            'credit_note_next_number' => 'nullable|integer|min:1',
            'default_due_days' => 'nullable|integer|min:1|max:90',
            'late_penalty_percentage' => 'nullable|numeric|min:0|max:100',
            'grace_period_days' => 'nullable|integer|min:0|max:30',
            'terms_and_conditions' => 'nullable|string|max:5000',
            'footer_note' => 'nullable|string|max:1000',
            'auto_generate_enabled' => 'boolean',
            'auto_generate_day' => 'nullable|integer|min:1|max:28',
            'auto_send_enabled' => 'boolean',
            'prorate_first_month' => 'boolean',
            'include_last_month_rent' => 'boolean',
            'admin_fee_amount' => 'nullable|numeric|min:0',
            'key_deposit_amount' => 'nullable|numeric|min:0',
            'first_invoice_due_days' => 'nullable|integer|min:0|max:30',
            'auto_generate_first_invoice' => 'boolean',
        ]);

        $settings = $user->invoiceSetting ?? InvoiceSetting::create([
            'landlord_id' => $user->id,
        ]);

        $settings->update($validated);

        return back()->with('success', 'Invoice settings updated successfully.');
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = Auth::user();
        $settings = $user->invoiceSetting ?? InvoiceSetting::create([
            'landlord_id' => $user->id,
        ]);

        if ($settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
        }

        $path = $request->file('logo')->store('logos/'.$user->id, 'public');
        $settings->update(['logo_path' => $path]);

        return back()->with('success', 'Logo uploaded successfully.');
    }

    public function removeLogo()
    {
        $user = Auth::user();
        $settings = $user->invoiceSetting;

        if ($settings && $settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
            $settings->update(['logo_path' => null]);
        }

        return back()->with('success', 'Logo removed successfully.');
    }
}
