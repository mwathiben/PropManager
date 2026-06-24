<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\Property;
use App\Models\TenantActivity;
use App\Models\TenantVerification;
use App\Models\VerificationItem;
use App\Models\VerificationTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class VerificationController extends Controller
{
    // ==================== TEMPLATE MANAGEMENT ====================

    /**
     * Display verification templates list
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if (! $user->isScopeOwner() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $templates = VerificationTemplate::where('landlord_id', $landlordId)
            ->with(['items', 'property'])
            ->withCount('items')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        $properties = Property::where('landlord_id', $landlordId)->get(['id', 'name']);

        return Inertia::render('Verifications/Templates', [
            'templates' => $templates,
            'properties' => $properties,
        ]);
    }

    /**
     * Store a new verification template
     */
    public function storeTemplate(Request $request)
    {
        $user = auth()->user();
        // PRIV-8: pre-fix, a tenant could POST here and create a template
        // (TenantScope hides the read but doesn't gate the write).
        if (! $user || (! $user->isScopeOwner() && ! $user->isCaretaker())) {
            abort(403);
        }
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // PRIV-8: scope property_id to caller's landlord so a landlord
            // can't reach into another landlord's property by id.
            'property_id' => [
                'nullable',
                \Illuminate\Validation\Rule::exists('properties', 'id')
                    ->where('landlord_id', $landlordId),
            ],
            'is_default' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:255',
            'items.*.document_type' => 'nullable|string|max:100',
            'items.*.description' => 'nullable|string|max:1000',
            'items.*.is_required' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            // If setting as default, unset other defaults
            if ($validated['is_default'] ?? false) {
                VerificationTemplate::where('landlord_id', $landlordId)
                    ->update(['is_default' => false]);
            }

            $template = VerificationTemplate::create([
                'landlord_id' => $landlordId,
                'property_id' => $validated['property_id'] ?? null,
                'name' => $validated['name'],
                'is_default' => $validated['is_default'] ?? false,
            ]);

            // Create items
            foreach ($validated['items'] as $index => $item) {
                VerificationItem::create([
                    'verification_template_id' => $template->id,
                    'name' => $item['name'],
                    'document_type' => $item['document_type'] ?? null,
                    'description' => $item['description'] ?? null,
                    'is_required' => $item['is_required'] ?? true,
                    'sort_order' => $index,
                ]);
            }

            DB::commit();

            return Redirect::back()->with('success', 'Verification template created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return Redirect::back()->withErrors(['template' => 'Failed to create template.']);
        }
    }

    /**
     * Update a verification template
     */
    public function updateTemplate(Request $request, VerificationTemplate $template)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($template->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'property_id' => 'nullable|exists:properties,id',
            'is_default' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer',
            'items.*.name' => 'required|string|max:255',
            'items.*.document_type' => 'nullable|string|max:100',
            'items.*.description' => 'nullable|string|max:1000',
            'items.*.is_required' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            // If setting as default, unset other defaults
            if ($validated['is_default'] ?? false) {
                VerificationTemplate::where('landlord_id', $landlordId)
                    ->where('id', '!=', $template->id)
                    ->update(['is_default' => false]);
            }

            $template->update([
                'name' => $validated['name'],
                'property_id' => $validated['property_id'] ?? null,
                'is_default' => $validated['is_default'] ?? false,
            ]);

            // Get existing item IDs
            $existingItemIds = $template->items->pluck('id')->toArray();
            $newItemIds = collect($validated['items'])->pluck('id')->filter()->toArray();

            // Delete removed items
            $itemsToDelete = array_diff($existingItemIds, $newItemIds);
            VerificationItem::whereIn('id', $itemsToDelete)->delete();

            // Update/create items
            foreach ($validated['items'] as $index => $itemData) {
                if (isset($itemData['id']) && in_array($itemData['id'], $existingItemIds)) {
                    // Update existing
                    VerificationItem::where('id', $itemData['id'])->update([
                        'name' => $itemData['name'],
                        'document_type' => $itemData['document_type'] ?? null,
                        'description' => $itemData['description'] ?? null,
                        'is_required' => $itemData['is_required'] ?? true,
                        'sort_order' => $index,
                    ]);
                } else {
                    // Create new
                    VerificationItem::create([
                        'verification_template_id' => $template->id,
                        'name' => $itemData['name'],
                        'document_type' => $itemData['document_type'] ?? null,
                        'description' => $itemData['description'] ?? null,
                        'is_required' => $itemData['is_required'] ?? true,
                        'sort_order' => $index,
                    ]);
                }
            }

            DB::commit();

            return Redirect::back()->with('success', 'Template updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return Redirect::back()->withErrors(['template' => 'Failed to update template.']);
        }
    }

    /**
     * Delete a verification template
     */
    public function destroyTemplate(VerificationTemplate $template)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($template->landlord_id !== $landlordId) {
            abort(403);
        }

        // Check if template is in use
        $inUse = TenantVerification::whereIn('verification_item_id', $template->items->pluck('id'))->exists();
        if ($inUse) {
            return Redirect::back()->withErrors(['template' => 'Cannot delete template that is in use.']);
        }

        $template->items()->delete();
        $template->delete();

        return Redirect::back()->with('success', 'Template deleted successfully.');
    }

    // ==================== CONDUCTING VERIFICATIONS ====================

    /**
     * Show verification page for a lease
     */
    public function showLeaseVerification(Lease $lease)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($lease->landlord_id !== $landlordId) {
            abort(403);
        }

        $lease->load(['tenant', 'unit.building.property', 'verifications.item', 'verifications.verifier']);

        // Get available templates
        $templates = VerificationTemplate::where('landlord_id', $landlordId)
            ->with('items')
            ->get();

        // Get default template or first one
        $defaultTemplate = $templates->firstWhere('is_default', true) ?? $templates->first();

        // Check if verification has been started
        $hasVerifications = $lease->verifications->isNotEmpty();

        // Calculate progress
        $totalItems = $lease->verifications->count();
        $verifiedItems = $lease->verifications->where('status', 'verified')->count();
        $progress = $totalItems > 0 ? round(($verifiedItems / $totalItems) * 100) : 0;

        return Inertia::render('Verifications/Conduct', [
            'lease' => $lease,
            'templates' => $templates,
            'defaultTemplate' => $defaultTemplate,
            'hasVerifications' => $hasVerifications,
            'progress' => $progress,
        ]);
    }

    /**
     * Start verification process for a lease using a template
     */
    public function startVerification(Request $request, Lease $lease)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($lease->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validate([
            'template_id' => 'required|exists:verification_templates,id',
        ]);

        $template = VerificationTemplate::with('items')->findOrFail($validated['template_id']);

        if ($template->landlord_id !== $landlordId) {
            abort(403);
        }

        // Check if verification already started
        if ($lease->verifications()->exists()) {
            return Redirect::back()->withErrors(['template' => 'Verification already started for this lease.']);
        }

        try {
            DB::beginTransaction();

            // Create verification records for each item in the template
            foreach ($template->items as $item) {
                TenantVerification::create([
                    'landlord_id' => $landlordId,
                    'lease_id' => $lease->id,
                    'verification_item_id' => $item->id,
                    'status' => 'pending',
                ]);
            }

            // Log activity
            TenantActivity::create([
                'landlord_id' => $landlordId,
                'tenant_id' => $lease->tenant_id,
                'performed_by' => $user->id,
                'type' => 'verification_started',
                'description' => "Verification started using template: {$template->name}",
                'metadata' => ['template_id' => $template->id, 'lease_id' => $lease->id],
            ]);

            DB::commit();

            return Redirect::back()->with('success', 'Verification process started.');
        } catch (\Exception $e) {
            DB::rollBack();

            return Redirect::back()->withErrors(['template' => 'Failed to start verification.']);
        }
    }

    /**
     * Update a single verification item status
     */
    public function updateVerification(Request $request, TenantVerification $verification)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($verification->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,verified,rejected,waived',
            'notes' => 'nullable|string|max:1000',
        ]);

        $verification->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'verified_by' => $user->id,
            'verified_at' => in_array($validated['status'], ['verified', 'rejected', 'waived']) ? now() : null,
        ]);

        // Log activity
        $lease = $verification->lease;
        $itemName = $verification->item->name;

        TenantActivity::create([
            'landlord_id' => $landlordId,
            'tenant_id' => $lease->tenant_id,
            'performed_by' => $user->id,
            'type' => 'verification_updated',
            'description' => "Verification item '{$itemName}' marked as {$validated['status']}",
            'metadata' => [
                'verification_id' => $verification->id,
                'status' => $validated['status'],
            ],
        ]);

        return Redirect::back()->with('success', 'Verification updated.');
    }

    /**
     * Bulk update verifications
     */
    public function bulkUpdateVerifications(Request $request, Lease $lease)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($lease->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validate([
            'verifications' => 'required|array',
            'verifications.*.id' => 'required|exists:tenant_verifications,id',
            'verifications.*.status' => 'required|in:pending,verified,rejected,waived',
            'verifications.*.notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            foreach ($validated['verifications'] as $data) {
                $verification = TenantVerification::findOrFail($data['id']);

                if ($verification->landlord_id !== $landlordId) {
                    continue;
                }

                $verification->update([
                    'status' => $data['status'],
                    'notes' => $data['notes'] ?? null,
                    'verified_by' => $user->id,
                    'verified_at' => in_array($data['status'], ['verified', 'rejected', 'waived']) ? now() : null,
                ]);
            }

            // Log activity
            TenantActivity::create([
                'landlord_id' => $landlordId,
                'tenant_id' => $lease->tenant_id,
                'performed_by' => $user->id,
                'type' => 'verification_bulk_update',
                'description' => 'Bulk verification update performed',
                'metadata' => ['lease_id' => $lease->id, 'count' => count($validated['verifications'])],
            ]);

            DB::commit();

            return Redirect::back()->with('success', 'Verifications updated.');
        } catch (\Exception $e) {
            DB::rollBack();

            return Redirect::back()->withErrors(['verifications' => 'Failed to update verifications.']);
        }
    }

    /**
     * Reset verification for a lease (start over)
     */
    public function resetVerification(Lease $lease)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($lease->landlord_id !== $landlordId) {
            abort(403);
        }

        // Delete all verifications for this lease
        $lease->verifications()->delete();

        // Log activity
        TenantActivity::create([
            'landlord_id' => $landlordId,
            'tenant_id' => $lease->tenant_id,
            'performed_by' => $user->id,
            'type' => 'verification_reset',
            'description' => 'Verification process was reset',
            'metadata' => ['lease_id' => $lease->id],
        ]);

        return Redirect::back()->with('success', 'Verification reset. You can start a new verification.');
    }

    /**
     * Complete verification and mark lease as verified
     */
    public function completeVerification(Lease $lease)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($lease->landlord_id !== $landlordId) {
            abort(403);
        }

        // Check all required items are verified or waived
        $pendingRequired = $lease->verifications()
            ->whereHas('item', function ($q) {
                $q->where('is_required', true);
            })
            ->where('status', 'pending')
            ->count();

        if ($pendingRequired > 0) {
            return Redirect::back()->withErrors(['verification' => 'All required items must be verified or waived before completing.']);
        }

        // No lease.is_verified column exists; verified status is derived from
        // the verification records (TenantController::show -> lease_verified).

        // Log activity
        TenantActivity::create([
            'landlord_id' => $landlordId,
            'tenant_id' => $lease->tenant_id,
            'performed_by' => $user->id,
            'type' => 'verification_completed',
            'description' => 'Tenant verification completed successfully',
            'metadata' => ['lease_id' => $lease->id],
        ]);

        return Redirect::route('tenants.show', $lease->tenant_id)->with('success', 'Verification completed successfully.');
    }

    // ==================== API ENDPOINTS ====================

    /**
     * Get verification status for a lease (JSON)
     */
    public function getVerificationStatus(Lease $lease)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($lease->landlord_id !== $landlordId) {
            abort(403);
        }

        $verifications = $lease->verifications()->with('item')->get();

        $total = $verifications->count();
        $verified = $verifications->where('status', 'verified')->count();
        $waived = $verifications->where('status', 'waived')->count();
        $rejected = $verifications->where('status', 'rejected')->count();
        $pending = $verifications->where('status', 'pending')->count();

        return response()->json([
            'total' => $total,
            'verified' => $verified,
            'waived' => $waived,
            'rejected' => $rejected,
            'pending' => $pending,
            'progress' => $total > 0 ? round((($verified + $waived) / $total) * 100) : 0,
            'is_complete' => $pending === 0 && $rejected === 0,
            'verifications' => $verifications,
        ]);
    }
}
