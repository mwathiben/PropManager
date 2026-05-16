<?php

declare(strict_types=1);

namespace App\Http\Controllers\Insight;

use App\Http\Controllers\Controller;
use App\Models\MrrSnapshot;
use App\Services\Reports\XlsxExportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Phase-36 INSIGHT-EXPORTS-1: MRR snapshot xlsx (super_admin only).
 *
 * Leadership wants the trend in their spreadsheet for the monthly
 * review. Reuses Phase-27 XlsxExportService writer + the
 * write-to-tmp + downloadable + deleteFileAfterSend pattern from
 * TenantStatementController.
 */
class MrrExportController extends Controller
{
    public function export(XlsxExportService $xlsx): BinaryFileResponse
    {
        $rows = MrrSnapshot::query()
            ->with('plan:id,slug,name')
            ->where('day', '>=', now()->subDays(90)->toDateString())
            ->orderBy('day', 'desc')
            ->orderBy('plan_id')
            ->get()
            ->map(function ($row) {
                return [
                    'day' => $row->day,
                    'plan_slug' => $row->plan?->slug,
                    'plan_name' => $row->plan?->name,
                    'mrr_kes' => (float) $row->mrr_kes,
                    'active_subscriptions' => (int) $row->active_subscriptions,
                    'new_mrr_kes' => (float) $row->new_mrr_kes,
                    'expansion_mrr_kes' => (float) $row->expansion_mrr_kes,
                    'contraction_mrr_kes' => (float) $row->contraction_mrr_kes,
                    'churned_mrr_kes' => (float) $row->churned_mrr_kes,
                ];
            })
            ->all();

        $columns = [
            ['label' => 'Day', 'key' => 'day', 'type' => 'date'],
            ['label' => 'Plan slug', 'key' => 'plan_slug', 'type' => 'string'],
            ['label' => 'Plan name', 'key' => 'plan_name', 'type' => 'string'],
            ['label' => 'MRR (KES)', 'key' => 'mrr_kes', 'type' => 'currency'],
            ['label' => 'Active subscriptions', 'key' => 'active_subscriptions', 'type' => 'integer'],
            ['label' => 'New MRR (KES)', 'key' => 'new_mrr_kes', 'type' => 'currency'],
            ['label' => 'Expansion MRR (KES)', 'key' => 'expansion_mrr_kes', 'type' => 'currency'],
            ['label' => 'Contraction MRR (KES)', 'key' => 'contraction_mrr_kes', 'type' => 'currency'],
            ['label' => 'Churned MRR (KES)', 'key' => 'churned_mrr_kes', 'type' => 'currency'],
        ];

        $tmpDir = storage_path('app/tmp/insight-mrr');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0o755, true);
        }
        $today = now()->format('Y-m-d');
        $path = $tmpDir.'/mrr-snapshot-'.$today.'-'.uniqid().'.xlsx';

        $xlsx->write('MRR snapshot', $columns, $rows, $path);

        return response()
            ->download($path, 'mrr-snapshot-'.$today.'.xlsx')
            ->deleteFileAfterSend(true);
    }
}
