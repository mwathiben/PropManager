<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Import;
use App\Services\ImportService;
use App\Traits\HasBuildingFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ImportsController extends Controller
{
    use HasBuildingFilter;

    protected ImportService $importService;

    public function __construct(ImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Display imports management page
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Import::class);

        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        // Building/Wing filter
        $buildingId = $request->filled('building_id') ? (int) $request->building_id : null;
        $wingId = $request->filled('wing_id') ? (int) $request->wing_id : null;

        $query = Import::where('landlord_id', $landlordId)
            ->with('importer:id,name')
            ->orderBy('created_at', 'desc');

        // Filter imports by building if specified
        if ($buildingId || $wingId) {
            $buildingIds = $this->getBuildingIds($buildingId, $wingId);
            $query->whereIn('building_id', $buildingIds);
        }

        $imports = $query->paginate(10);

        $importTypes = [
            'tenants' => [
                'label' => 'Tenants',
                'description' => 'Import tenant records with contact information',
                'icon' => 'user',
            ],
            'leases' => [
                'label' => 'Leases',
                'description' => 'Import lease agreements and assign tenants to units',
                'icon' => 'document',
            ],
            'water_readings' => [
                'label' => 'Water Readings',
                'description' => 'Import historical water meter readings',
                'icon' => 'water',
            ],
            'invoices' => [
                'label' => 'Invoices',
                'description' => 'Import historical invoices',
                'icon' => 'invoice',
            ],
            'payments' => [
                'label' => 'Payments',
                'description' => 'Import historical payment records',
                'icon' => 'cash',
            ],
            'units' => [
                'label' => 'Units',
                'description' => 'Bulk import units for your buildings',
                'icon' => 'building',
            ],
        ];

        // Get buildings for filter
        $buildings = $this->getBuildingsForFilter();

        return Inertia::render('Imports/Index', [
            'imports' => $imports,
            'importTypes' => $importTypes,
            'buildings' => $buildings,
            'filters' => $request->only(['building_id', 'wing_id']),
        ]);
    }

    /**
     * Upload and queue import file
     */
    public function upload(Request $request)
    {
        $this->authorize('create', Import::class);

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            'type' => 'required|string|in:tenants,leases,water_readings,invoices,payments,units',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'wing_id' => 'nullable|integer|exists:buildings,id',
        ]);

        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        // Use wing_id if provided, otherwise use building_id
        $targetBuildingId = $validated['wing_id'] ?? $validated['building_id'] ?? null;

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();

        // Store file
        $filePath = $file->store('imports', 'local');

        // Create import record
        $import = Import::create([
            'landlord_id' => $landlordId,
            'imported_by' => $user->id,
            'type' => $validated['type'],
            'file_name' => $fileName,
            'file_path' => $filePath,
            'status' => 'pending',
            'building_id' => $targetBuildingId,
        ]);

        // Process import immediately (in a real app, this should be queued)
        try {
            $this->importService->processImport($import);
        } catch (\Exception $e) {
            $import->update([
                'status' => 'failed',
                'errors' => [['message' => $e->getMessage()]],
            ]);
        }

        return redirect()->back()->with('success', 'Import completed. Check the results below.');
    }

    /**
     * Download CSV template for import type
     */
    public function downloadTemplate(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:tenants,leases,water_readings,invoices,payments,units',
        ]);

        $template = ImportService::getTemplate($validated['type']);

        if (empty($template)) {
            return redirect()->back()->with('error', 'Template not found for this import type.');
        }

        // Generate CSV
        $csv = fopen('php://temp', 'r+');

        // Add headers
        fputcsv($csv, $template['headers']);

        // Add sample row
        fputcsv($csv, $template['sample']);

        rewind($csv);
        $csvContent = stream_get_contents($csv);
        fclose($csv);

        $fileName = $validated['type'].'_import_template.csv';

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    /**
     * View import details and errors
     */
    public function show(Import $import)
    {
        $this->authorize('view', $import);

        $import->load('importer:id,name');

        return Inertia::render('Imports/Show', [
            'importRecord' => $import,
        ]);
    }

    /**
     * Delete import record and file
     */
    public function destroy(Import $import)
    {
        $this->authorize('delete', $import);

        // Delete file
        if (Storage::exists($import->file_path)) {
            Storage::delete($import->file_path);
        }

        $import->delete();

        return redirect()->back()->with('success', 'Import record deleted.');
    }

    /**
     * Reprocess failed import
     */
    public function reprocess(Import $import)
    {
        $this->authorize('reprocess', $import);

        if (! $import->isFailed() && ! $import->isCompleted()) {
            return redirect()->back()->with('error', 'Only failed or completed imports can be reprocessed.');
        }

        // Reset import status
        $import->update([
            'status' => 'pending',
            'successful_rows' => 0,
            'failed_rows' => 0,
            'errors' => null,
            'summary' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);

        // Process again
        try {
            $this->importService->processImport($import);
        } catch (\Exception $e) {
            $import->update([
                'status' => 'failed',
                'errors' => [['message' => $e->getMessage()]],
            ]);
        }

        return redirect()->back()->with('success', 'Import reprocessed. Check the results.');
    }
}
