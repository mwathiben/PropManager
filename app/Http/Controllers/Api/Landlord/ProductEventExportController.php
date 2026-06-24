<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Models\ProductEvent;
use App\Services\Reports\XlsxExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Phase-36 INSIGHT-EXPORTS-3: per-landlord product_events xlsx,
 * last 90 days. properties JSON flattened to the 10 most-frequent
 * keys + a residual properties_json column.
 */
class ProductEventExportController extends Controller
{
    public function export(Request $request, XlsxExportService $xlsx): BinaryFileResponse
    {
        $user = $request->user();
        $landlordId = $user->effectiveScopeId();

        $events = ProductEvent::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->where('created_at', '>=', now()->subDays(90))
            ->orderBy('created_at', 'desc')
            ->limit(10_000)
            ->get(['id', 'user_id', 'event_name', 'properties', 'created_at']);

        $topPropertyKeys = $this->topPropertyKeys($events);

        $rows = $events->map(function ($event) use ($topPropertyKeys) {
            $properties = $event->properties ?? [];
            $row = [
                'id' => $event->id,
                'user_id' => $event->user_id,
                'event_name' => $event->event_name,
                'created_at' => $event->created_at,
            ];
            $residual = $properties;
            foreach ($topPropertyKeys as $key) {
                $row[$key] = $properties[$key] ?? null;
                unset($residual[$key]);
            }
            $row['properties_json'] = $residual === [] ? '' : json_encode($residual);

            return $row;
        })->all();

        $columns = [
            ['label' => 'ID', 'key' => 'id', 'type' => 'integer'],
            ['label' => 'User ID', 'key' => 'user_id', 'type' => 'integer'],
            ['label' => 'Event', 'key' => 'event_name', 'type' => 'string'],
            ['label' => 'Created at', 'key' => 'created_at', 'type' => 'date'],
        ];
        foreach ($topPropertyKeys as $key) {
            $columns[] = ['label' => $key, 'key' => $key, 'type' => 'string'];
        }
        $columns[] = ['label' => 'Properties (residual JSON)', 'key' => 'properties_json', 'type' => 'string'];

        $tmpDir = storage_path('app/tmp/insight-product-events');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0o755, true);
        }
        $today = now()->format('Y-m-d');
        $path = $tmpDir.'/product-events-'.$landlordId.'-'.$today.'-'.uniqid().'.xlsx';

        $xlsx->write('Product events', $columns, $rows, $path);

        return response()
            ->download($path, 'product-events-'.$today.'.xlsx')
            ->deleteFileAfterSend(true);
    }

    /**
     * Returns the 10 most-frequent property keys across the event
     * sample so the export gets explicit columns for the data
     * landlords actually use.
     */
    private function topPropertyKeys($events): array
    {
        $counts = [];
        foreach ($events as $event) {
            foreach (array_keys((array) ($event->properties ?? [])) as $key) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }
        arsort($counts);

        return array_slice(array_keys($counts), 0, 10);
    }
}
