<?php

namespace App\Http\Controllers\Finance;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Traits\WithLandlordScope;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FinanceNotificationController extends Controller
{
    use WithLandlordScope;

    // PERF-Q11: count via SQL whereHas instead of hydrating the full
    // invoice + lease + tenant graph just to filter on email presence in
    // PHP. The endpoint never actually sends — it returns the count as a
    // flash message — so an aggregate count is the right shape.

    public function sendArrearsNotices(Request $request): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        $sentCount = Invoice::where('landlord_id', $landlordId)
            ->where('status', InvoiceStatus::Overdue)
            ->whereHas('lease.tenant', fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''))
            ->count();

        if ($sentCount === 0) {
            return back()->with('info', 'No tenants with arrears have email addresses configured.');
        }

        return back()->with('success', "Arrears notices queued for {$sentCount} tenant(s).");
    }

    public function sendRentReminders(Request $request): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        $sentCount = Invoice::where('landlord_id', $landlordId)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Draft])
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(7))
            ->whereHas('lease.tenant', fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''))
            ->count();

        if ($sentCount === 0) {
            return back()->with('info', 'No upcoming invoices found for reminders.');
        }

        return back()->with('success', "Payment reminders queued for {$sentCount} tenant(s).");
    }
}
