<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Models\Lease;
use App\Models\TenantActivity;
use App\Models\TenantVerification;
use App\Models\User;
use App\Models\VerificationItem;
use App\Models\VerificationTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Holds the write-heavy verification workflows extracted from
 * VerificationController: template create/update and the per-lease verification
 * start / bulk-update.
 *
 * Transaction boundaries mirror the controller exactly (atomic; rolled back and
 * the exception rethrown on failure). The controller stays thin
 * (authorize -> validate -> delegate -> redirect) and keeps the generic
 * withErrors() messages on the failure path.
 */
class VerificationService
{
    /**
     * @param  array{name: string, property_id?: int|null, is_default?: bool, items: array<int, array<string, mixed>>}  $validated
     */
    public function createTemplate(int $landlordId, array $validated): VerificationTemplate
    {
        return DB::transaction(function () use ($landlordId, $validated) {
            $isDefault = $validated['is_default'] ?? false;

            if ($isDefault) {
                VerificationTemplate::where('landlord_id', $landlordId)
                    ->update(['is_default' => false]);
            }

            $template = VerificationTemplate::create([
                'landlord_id' => $landlordId,
                'property_id' => $validated['property_id'] ?? null,
                'name' => $validated['name'],
                'is_default' => $isDefault,
            ]);

            foreach ($validated['items'] as $index => $item) {
                $this->createItem($template->id, $item, $index);
            }

            return $template;
        });
    }

    /**
     * @param  array{name: string, property_id?: int|null, is_default?: bool, items: array<int, array<string, mixed>>}  $validated
     */
    public function updateTemplate(VerificationTemplate $template, int $landlordId, array $validated): void
    {
        DB::transaction(function () use ($template, $landlordId, $validated) {
            $isDefault = $validated['is_default'] ?? false;

            if ($isDefault) {
                VerificationTemplate::where('landlord_id', $landlordId)
                    ->where('id', '!=', $template->id)
                    ->update(['is_default' => false]);
            }

            $template->update([
                'name' => $validated['name'],
                'property_id' => $validated['property_id'] ?? null,
                'is_default' => $isDefault,
            ]);

            $this->syncItems($template, $validated['items']);
        });
    }

    public function startVerification(Lease $lease, int $landlordId, VerificationTemplate $template, User $actor): void
    {
        DB::transaction(function () use ($lease, $landlordId, $template, $actor) {
            foreach ($template->items as $item) {
                TenantVerification::create([
                    'landlord_id' => $landlordId,
                    'lease_id' => $lease->id,
                    'verification_item_id' => $item->id,
                    'status' => 'pending',
                ]);
            }

            TenantActivity::create([
                'landlord_id' => $landlordId,
                'tenant_id' => $lease->tenant_id,
                'performed_by' => $actor->id,
                'type' => 'verification_started',
                'description' => "Verification started using template: {$template->name}",
                'metadata' => ['template_id' => $template->id, 'lease_id' => $lease->id],
            ]);
        });
    }

    /**
     * @param  array{verifications: array<int, array{id: int, status: string, notes?: string|null}>}  $validated
     */
    public function bulkUpdate(Lease $lease, int $landlordId, array $validated, User $actor): void
    {
        DB::transaction(function () use ($lease, $landlordId, $validated, $actor) {
            foreach ($validated['verifications'] as $data) {
                $verification = TenantVerification::findOrFail($data['id']);

                if ($verification->landlord_id !== $landlordId) {
                    continue;
                }

                $verification->update([
                    'status' => $data['status'],
                    'notes' => $data['notes'] ?? null,
                    'verified_by' => $actor->id,
                    'verified_at' => in_array($data['status'], ['verified', 'rejected', 'waived']) ? now() : null,
                ]);
            }

            TenantActivity::create([
                'landlord_id' => $landlordId,
                'tenant_id' => $lease->tenant_id,
                'performed_by' => $actor->id,
                'type' => 'verification_bulk_update',
                'description' => 'Bulk verification update performed',
                'metadata' => ['lease_id' => $lease->id, 'count' => count($validated['verifications'])],
            ]);
        });
    }

    /**
     * Replace a template's items: delete removed, update existing by id, create new.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(VerificationTemplate $template, array $items): void
    {
        $existingItemIds = $template->items->pluck('id')->toArray();
        $newItemIds = collect($items)->pluck('id')->filter()->toArray();

        $itemsToDelete = array_diff($existingItemIds, $newItemIds);
        VerificationItem::whereIn('id', $itemsToDelete)->delete();

        foreach ($items as $index => $itemData) {
            $this->upsertItem($template->id, $itemData, $index, $existingItemIds);
        }
    }

    /**
     * @param  array<string, mixed>  $itemData
     * @param  array<int, int>  $existingItemIds
     */
    private function upsertItem(int $templateId, array $itemData, int $sortOrder, array $existingItemIds): void
    {
        if (isset($itemData['id']) && in_array($itemData['id'], $existingItemIds)) {
            VerificationItem::where('id', $itemData['id'])->update([
                'name' => $itemData['name'],
                'document_type' => $itemData['document_type'] ?? null,
                'description' => $itemData['description'] ?? null,
                'is_required' => $itemData['is_required'] ?? true,
                'sort_order' => $sortOrder,
            ]);

            return;
        }

        $this->createItem($templateId, $itemData, $sortOrder);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function createItem(int $templateId, array $item, int $sortOrder): void
    {
        VerificationItem::create([
            'verification_template_id' => $templateId,
            'name' => $item['name'],
            'document_type' => $item['document_type'] ?? null,
            'description' => $item['description'] ?? null,
            'is_required' => $item['is_required'] ?? true,
            'sort_order' => $sortOrder,
        ]);
    }
}
