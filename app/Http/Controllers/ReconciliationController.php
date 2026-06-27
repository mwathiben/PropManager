<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Imports\BankStatementImport;
use App\Models\BankReconciliationQueue;
use App\Models\Invoice;
use App\Services\Banking\BankReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReconciliationController extends Controller
{
    public function __construct(
        protected BankReconciliationService $reconciliationService
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();

        if (! $user->isScopeOwner() && ! $user->isCaretaker()) {
            abort(403);
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $status = $request->query('status', 'pending');

        $query = BankReconciliationQueue::where('landlord_id', $landlordId)
            ->with(['payment', 'matchedInvoice']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $queueItems = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $stats = $this->reconciliationService->getStats($landlordId);

        $invoices = Invoice::where('landlord_id', $landlordId)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Partial, InvoiceStatus::Overdue])
            ->with(['lease.tenant', 'lease.unit.building'])
            ->orderBy('due_date', 'desc')
            ->limit(100)
            ->get();

        return Inertia::render('Reconciliation/Index', [
            'queueItems' => $queueItems,
            'stats' => $stats,
            'invoices' => $invoices,
            'filters' => $request->only(['status']),
        ]);
    }

    public function match(Request $request, BankReconciliationQueue $item)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($item->landlord_id !== $landlordId) {
            abort(403);
        }

        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
        ]);

        $invoice = Invoice::findOrFail($request->invoice_id);

        if ($invoice->landlord_id !== $landlordId) {
            abort(403);
        }

        try {
            $this->reconciliationService->manualMatch($item, $invoice);

            return back()->with('success', 'Payment matched successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['match' => $e->getMessage()]);
        }
    }

    public function retry(BankReconciliationQueue $item)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($item->landlord_id !== $landlordId) {
            abort(403);
        }

        if (! $item->canRetry()) {
            return back()->withErrors(['retry' => 'This item cannot be retried.']);
        }

        try {
            $success = $this->reconciliationService->processItem($item);

            if ($success) {
                return back()->with('success', 'Payment matched successfully.');
            }

            return back()->with('info', 'Unable to auto-match. Please match manually.');
        } catch (\Exception $e) {
            return back()->withErrors(['retry' => $e->getMessage()]);
        }
    }

    public function destroy(BankReconciliationQueue $item)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($item->landlord_id !== $landlordId) {
            abort(403);
        }

        if ($item->isMatched()) {
            return back()->withErrors(['delete' => 'Cannot delete matched items.']);
        }

        $item->delete();

        return back()->with('success', 'Item removed from queue.');
    }

    public function import(Request $request): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isScopeOwner() && ! $user->isCaretaker()) {
            abort(403);
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
            'bank_code' => 'required|string|max:50',
            'column_mapping' => 'nullable|array',
            'column_mapping.reference' => 'nullable|string|max:100',
            'column_mapping.amount' => 'nullable|string|max:100',
            'column_mapping.date' => 'nullable|string|max:100',
            'column_mapping.description' => 'nullable|string|max:100',
        ]);

        $columnMapping = $request->input('column_mapping', []);

        $import = new BankStatementImport($landlordId, $request->bank_code, $columnMapping);

        try {
            $import->import($request->file('file'));

            return $this->redirectWithImportResults($import->getResults());
        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'Failed to import file: '.$e->getMessage()]);
        }
    }

    /**
     * @param  array{imported: int, skipped: int}  $results
     */
    private function redirectWithImportResults(array $results): RedirectResponse
    {
        if ($results['imported'] === 0 && $results['skipped'] > 0) {
            return back()->with('warning', "No new transactions imported. {$results['skipped']} row(s) were skipped (duplicates or invalid).");
        }

        $message = "{$results['imported']} transaction(s) imported successfully.";
        if ($results['skipped'] > 0) {
            $message .= " {$results['skipped']} row(s) skipped.";
        }

        return back()->with('success', $message);
    }

    public function processQueue(): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isScopeOwner() && ! $user->isCaretaker()) {
            abort(403);
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $processed = $this->reconciliationService->processQueueForLandlord($landlordId);

        if ($processed['matched'] > 0) {
            return back()->with('success', "Auto-matched {$processed['matched']} payment(s).");
        }

        return back()->with('info', 'No payments could be auto-matched. Please match manually.');
    }
}
