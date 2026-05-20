<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Services\Vendors\VendorStatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase-70 PAYOUT-STATEMENT-2/3: the vendor's read-only statement of
 * recorded ticket costs + expenses over a period. Scoped to the SESSION
 * vendor (request->attributes('portal_vendor')); never a client id.
 */
class VendorPortalStatementController extends Controller
{
    public function __construct(private readonly VendorStatementService $statements) {}

    public function index(Request $request): Response
    {
        $vendor = $this->vendor($request);
        [$from, $to] = $this->period($request);

        return Inertia::render('VendorPortal/Statement', [
            'vendor' => ['id' => $vendor->id, 'name' => $vendor->name],
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'statement' => $this->statements->forVendor($vendor, $from, $to),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $vendor = $this->vendor($request);
        [$from, $to] = $this->period($request);
        $csv = $this->statements->toCsv($this->statements->forVendor($vendor, $from, $to));

        $filename = 'vendor-statement-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return response()->streamDownload(
            fn () => print ($csv),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function vendor(Request $request): Vendor
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('portal_vendor');

        return $vendor;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $to = isset($validated['to']) ? Carbon::parse($validated['to']) : Carbon::now();
        $from = isset($validated['from']) ? Carbon::parse($validated['from']) : $to->copy()->subDays(90);

        return [$from, $to];
    }
}
