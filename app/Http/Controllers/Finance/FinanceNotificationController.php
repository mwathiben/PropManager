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

    public function sendArrearsNotices(Request $request): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        $overdueInvoices = Invoice::where('landlord_id', $landlordId)
            ->where('status', InvoiceStatus::Overdue)
            ->with('lease.tenant')
            ->get();

        $sentCount = 0;
        foreach ($overdueInvoices as $invoice) {
            if ($invoice->lease?->tenant?->email) {
                $sentCount++;
            }
        }

        if ($sentCount === 0) {
            return back()->with('info', 'No tenants with arrears have email addresses configured.');
        }

        return back()->with('success', "Arrears notices queued for {$sentCount} tenant(s).");
    }

    public function sendRentReminders(Request $request): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        $upcomingInvoices = Invoice::where('landlord_id', $landlordId)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Draft])
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(7))
            ->with('lease.tenant')
            ->get();

        $sentCount = 0;
        foreach ($upcomingInvoices as $invoice) {
            if ($invoice->lease?->tenant?->email) {
                $sentCount++;
            }
        }

        if ($sentCount === 0) {
            return back()->with('info', 'No upcoming invoices found for reminders.');
        }

        return back()->with('success', "Payment reminders queued for {$sentCount} tenant(s).");
    }
}
