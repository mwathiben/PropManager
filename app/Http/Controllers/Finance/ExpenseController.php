<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreExpenseCategoryRequest;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Http\Requests\Finance\StoreVendorRequest;
use App\Http\Requests\Finance\UpdateExpenseCategoryRequest;
use App\Http\Requests\Finance\UpdateExpenseRequest;
use App\Http\Requests\Finance\UpdateVendorRequest;
use App\Http\Traits\WithETag;
use App\Http\Traits\WithFinanceRendering;
use App\Http\Traits\WithLandlordScope;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Vendor;
use App\Services\FinanceExportService;
use App\Services\FinanceFilterService;
use App\Services\FinanceStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExpenseController extends Controller
{
    use WithETag;
    use WithFinanceRendering;
    use WithLandlordScope;

    public function __construct(
        protected FinanceStatsService $statsService,
        protected FinanceFilterService $filterService,
        protected FinanceExportService $exportService,
    ) {}

    public function index(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('expenses', [
            'expenses' => $this->filterService->getPaginatedExpenses($request, $landlordId),
            'filters' => $request->only(['search', 'category_id', 'vendor_id', 'building_id', 'date_from', 'date_to']),
            'categories' => $this->filterService->getExpenseCategories($landlordId),
            'vendors' => $this->filterService->getVendors($landlordId),
            'stats' => $this->statsService->getExpenseStats($landlordId),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->authorize('create', Expense::class);

        Expense::create($validated);

        return back()->with('success', 'Expense recorded successfully.');
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        $expense->update($request->validated());

        return back()->with('success', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return back()->with('success', 'Expense deleted successfully.');
    }

    public function show(Expense $expense): JsonResponse
    {
        $this->authorize('view', $expense);

        $expense->load(['category', 'vendor', 'property', 'building', 'unit']);

        return $this->jsonWithCache([
            'expense' => [
                'id' => $expense->id,
                'description' => $expense->description,
                'amount' => $expense->amount,
                'expense_date' => $expense->expense_date->format('Y-m-d'),
                'payment_method' => $expense->payment_method,
                'reference' => $expense->reference,
                'notes' => $expense->notes,
                'is_recurring' => $expense->is_recurring,
                'recurring_frequency' => $expense->recurring_frequency,
                'category_id' => $expense->category_id,
                'vendor_id' => $expense->vendor_id,
                'property_id' => $expense->property_id,
                'building_id' => $expense->building_id,
                'unit_id' => $expense->unit_id,
                'category' => $expense->category?->name,
                'vendor' => $expense->vendor?->name,
                'location' => $expense->getLocationLabel(),
                'created_at' => $expense->created_at->format('Y-m-d H:i'),
            ],
        ], 60, 300);
    }

    public function export(Request $request): BinaryFileResponse|\Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filters = array_merge(
            ['landlord_id' => $this->getLandlordId()],
            $request->only(['category_id', 'vendor_id', 'building_id', 'date_from', 'date_to'])
        );

        return $this->exportService->exportExpenses($filters, $request->query('format', 'xlsx'));
    }

    public function storeCategory(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['is_active'] = true;

        $this->authorize('create', ExpenseCategory::class);

        ExpenseCategory::create($validated);

        return back()->with('success', 'Category created successfully.');
    }

    public function updateCategory(UpdateExpenseCategoryRequest $request, ExpenseCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        return back()->with('success', 'Category updated successfully.');
    }

    public function destroyCategory(ExpenseCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        if ($category->expenses()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete category with existing expenses.']);
        }

        $category->delete();

        return back()->with('success', 'Category deleted successfully.');
    }

    public function storeVendor(StoreVendorRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['is_active'] = true;

        $this->authorize('create', Vendor::class);

        Vendor::create($validated);

        return back()->with('success', 'Vendor created successfully.');
    }

    public function updateVendor(UpdateVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $this->authorize('update', $vendor);

        $vendor->update($request->validated());

        return back()->with('success', 'Vendor updated successfully.');
    }

    public function destroyVendor(Vendor $vendor): RedirectResponse
    {
        $this->authorize('delete', $vendor);

        if ($vendor->expenses()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete vendor with existing expenses.']);
        }

        $vendor->delete();

        return back()->with('success', 'Vendor deleted successfully.');
    }

    public function exportVendors(Request $request): BinaryFileResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filters = ['landlord_id' => $this->getLandlordId()];

        return $this->exportService->exportVendors($filters, $request->query('format', 'xlsx'));
    }
}
