<?php

declare(strict_types=1);

namespace App\Services\MoveOut;

use App\Enums\MoveOutStatus;
use App\Models\Lease;
use App\Models\MoveOut;
use App\Models\MoveOutDeduction;
use App\Models\MoveOutDeductionCategory;
use App\Models\TenantActivity;
use App\Models\User;
use App\Services\Finance\DepositSettlementService;
use App\Services\Lease\LeaseGuarantorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Holds the write-heavy move-out workflows extracted from MoveOutController:
 * initiation, inspection, deduction management, settlement and cancellation.
 *
 * Transaction boundaries mirror the controller exactly; the controller stays
 * thin (authorize -> validate -> delegate -> redirect).
 */
class MoveOutService
{
    /**
     * @param  array{notice_date:string, intended_move_out_date:string, reason?:string|null}  $validated
     */
    public function initiate(int $landlordId, Lease $lease, array $validated, User $actor): MoveOut
    {
        return DB::transaction(function () use ($landlordId, $lease, $validated, $actor) {
            $moveOut = MoveOut::create([
                'landlord_id' => $landlordId,
                'lease_id' => $lease->id,
                'notice_date' => $validated['notice_date'],
                'intended_move_out_date' => $validated['intended_move_out_date'],
                'status' => 'notice_given',
                'deposit_held' => $lease->deposit_amount,
                'arrears_balance' => $lease->arrears ?? 0,
                'total_deductions' => 0,
                'refund_amount' => $lease->deposit_amount - ($lease->arrears ?? 0),
            ]);

            TenantActivity::create([
                'landlord_id' => $landlordId,
                'tenant_id' => $lease->tenant_id,
                'performed_by' => $actor->id,
                'type' => 'move_out_initiated',
                'description' => 'Move-out notice given. Expected move-out: '.$validated['intended_move_out_date'],
                'metadata' => [
                    'move_out_id' => $moveOut->id,
                    'reason' => $validated['reason'] ?? null,
                ],
            ]);

            return $moveOut;
        });
    }

    /**
     * @param  array{actual_move_out_date:string}  $validated
     *
     * @throws \RuntimeException when the move-out is not in the NoticeGiven state
     */
    public function startInspection(MoveOut $moveOut, int $landlordId, array $validated, User $actor): void
    {
        DB::transaction(function () use ($moveOut, $validated, $landlordId, $actor) {
            $lockedMoveOut = MoveOut::where('id', $moveOut->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMoveOut->status !== MoveOutStatus::NoticeGiven) {
                throw new \RuntimeException('Inspection already started or move-out in wrong state.');
            }

            $lockedMoveOut->update([
                'actual_move_out_date' => $validated['actual_move_out_date'],
                'status' => 'inspection_pending',
            ]);

            $this->autoApplyDeductions($lockedMoveOut, $landlordId);

            TenantActivity::create([
                'landlord_id' => $landlordId,
                'tenant_id' => $lockedMoveOut->lease->tenant_id,
                'performed_by' => $actor->id,
                'type' => 'move_out_inspection_started',
                'description' => 'Tenant moved out. Inspection started.',
                'metadata' => ['move_out_id' => $lockedMoveOut->id],
            ]);
        });
    }

    /**
     * @param  array{category_id?:int|null, description:string, amount:float|int|string, notes?:string|null}  $validated
     */
    public function addDeduction(MoveOut $moveOut, array $validated, ?UploadedFile $photo): void
    {
        $photoPath = null;
        if ($photo) {
            $photoPath = $photo->store("move-outs/{$moveOut->id}", 'private');
        }

        try {
            DB::transaction(function () use ($moveOut, $validated, $photoPath) {
                MoveOutDeduction::create([
                    'move_out_id' => $moveOut->id,
                    'category_id' => $validated['category_id'] ?? null,
                    'description' => $validated['description'],
                    'amount' => $validated['amount'],
                    'notes' => $validated['notes'] ?? null,
                    'photo_path' => $photoPath,
                    'auto_applied' => false,
                ]);

                $moveOut->calculateRefund();
                $moveOut->save();
            });
        } catch (\Exception $e) {
            if ($photoPath) {
                Storage::disk('private')->delete($photoPath);
            }
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateDeduction(MoveOutDeduction $deduction, MoveOut $moveOut, array $validated): void
    {
        DB::transaction(function () use ($deduction, $moveOut, $validated) {
            $deduction->update($validated);

            $moveOut->calculateRefund();
            $moveOut->save();
        });
    }

    public function deleteDeduction(MoveOutDeduction $deduction, MoveOut $moveOut): void
    {
        if ($deduction->photo_path) {
            Storage::disk('private')->delete($deduction->photo_path);
        }

        $deduction->delete();

        $moveOut->calculateRefund();
        $moveOut->save();
    }

    /**
     * @param  array{inspection_notes?:string|null}  $validated
     */
    public function completeInspection(MoveOut $moveOut, int $landlordId, array $validated, User $actor): void
    {
        DB::transaction(function () use ($moveOut, $validated, $landlordId, $actor) {
            $moveOut->update([
                'status' => 'settlement_pending',
                'inspection_notes' => $validated['inspection_notes'] ?? null,
            ]);

            $moveOut->calculateRefund();
            $moveOut->save();

            TenantActivity::create([
                'landlord_id' => $landlordId,
                'tenant_id' => $moveOut->lease->tenant_id,
                'performed_by' => $actor->id,
                'type' => 'move_out_inspection_complete',
                'description' => 'Inspection completed. Settlement pending.',
                'metadata' => [
                    'move_out_id' => $moveOut->id,
                    'total_deductions' => $moveOut->total_deductions,
                    'refund_amount' => $moveOut->refund_amount,
                ],
            ]);
        });
    }

    /**
     * @param  array{settlement_method:string, settlement_reference?:string|null}  $validated
     */
    public function complete(MoveOut $moveOut, int $landlordId, array $validated, User $actor): void
    {
        DB::transaction(function () use ($moveOut, $validated, $landlordId, $actor) {
            $moveOut->calculateRefund();

            $moveOut->update([
                'status' => 'completed',
                'settlement_method' => $validated['settlement_method'],
                'settlement_reference' => $validated['settlement_reference'] ?? null,
                'settled_at' => now(),
                'processed_by' => $actor->id,
            ]);

            $lease = $moveOut->lease;
            $lease->update([
                'is_active' => false,
                'end_date' => $moveOut->actual_move_out_date ?? now(),
            ]);

            $lease->unit->update(['status' => 'vacant']);

            // Phase-81 DEPOSIT-SETTLEMENT-2: journal the deposit to the ledger
            // (deductions + arrears offset + refund) and flip lease.deposit_status
            // atomically as part of completing the move-out.
            app(DepositSettlementService::class)->settle($moveOut, $actor);

            // Phase-83 GUARANTOR-2: the lease has ended — release any guarantors
            // standing behind it (their obligation no longer applies).
            app(LeaseGuarantorService::class)
                ->releaseAllForLease($lease, __('lease.guarantor.released_move_out'));

            TenantActivity::create([
                'landlord_id' => $landlordId,
                'tenant_id' => $lease->tenant_id,
                'performed_by' => $actor->id,
                'type' => 'move_out_completed',
                'description' => 'Move-out completed. Deposit settled via '.$validated['settlement_method'].'. Refund: KES '.number_format((float) $moveOut->refund_amount, 2),
                'metadata' => [
                    'move_out_id' => $moveOut->id,
                    'refund_amount' => $moveOut->refund_amount,
                    'settlement_method' => $validated['settlement_method'],
                ],
            ]);
        });
    }

    /**
     * @param  array{cancellation_reason?:string|null}  $validated
     */
    public function cancel(MoveOut $moveOut, int $landlordId, array $validated, User $actor): void
    {
        DB::transaction(function () use ($moveOut, $validated, $landlordId, $actor) {
            $moveOut->update(['status' => 'cancelled']);

            TenantActivity::create([
                'landlord_id' => $landlordId,
                'tenant_id' => $moveOut->lease->tenant_id,
                'performed_by' => $actor->id,
                'type' => 'move_out_cancelled',
                'description' => 'Move-out process cancelled.',
                'metadata' => [
                    'move_out_id' => $moveOut->id,
                    'reason' => $validated['cancellation_reason'] ?? null,
                ],
            ]);
        });
    }

    /**
     * Auto-apply deductions from categories with the always_apply flag.
     */
    private function autoApplyDeductions(MoveOut $moveOut, int $landlordId): void
    {
        try {
            $buildingId = $moveOut->lease->unit->building_id;

            $categories = MoveOutDeductionCategory::query()
                ->where('landlord_id', $landlordId)
                ->active()
                ->alwaysApply()
                ->where(function ($query) use ($buildingId) {
                    $query->where('building_id', $buildingId)
                        ->orWhereNull('building_id');
                })
                ->ordered()
                ->get();

            $existingCategoryIds = MoveOutDeduction::where('move_out_id', $moveOut->id)
                ->where('auto_applied', true)
                ->pluck('category_id')
                ->toArray();

            $categoriesToApply = $categories->filter(function ($category) use ($existingCategoryIds) {
                return ! in_array($category->id, $existingCategoryIds);
            });

            $totalApplied = 0;
            foreach ($categoriesToApply as $category) {
                MoveOutDeduction::create([
                    'move_out_id' => $moveOut->id,
                    'category_id' => $category->id,
                    'description' => $category->name,
                    'amount' => $category->default_amount,
                    'auto_applied' => true,
                ]);
                $totalApplied++;
            }

            if ($totalApplied > 0) {
                $moveOut->calculateRefund();
                $moveOut->save();

                Log::info('Auto-applied move-out deductions', [
                    'move_out_id' => $moveOut->id,
                    'building_id' => $buildingId,
                    'count' => $totalApplied,
                    'category_ids' => $categoriesToApply->pluck('id')->toArray(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to auto-apply deductions', [
                'move_out_id' => $moveOut->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
