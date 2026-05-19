<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LegalHold;
use App\Services\Legal\LegalHoldAuditExportService;
use App\Services\Storage\TenantDiskResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Phase-65 AUDIT-DEPTH-2: regulator-ready CSV export streamed via
 * Phase 59 SIGNED-URLS pattern (browser-direct redirect; PHP-FPM
 * not tied up for the duration of the download).
 */
class LegalHoldAuditExportController extends Controller
{
    public function __construct(
        private readonly LegalHoldAuditExportService $exporter,
        private readonly TenantDiskResolver $resolver,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->can('auditExport', LegalHold::class)) {
            throw new AuthorizationException;
        }

        $validated = $request->validate([
            'from' => ['required', 'date', 'before_or_equal:to'],
            'to' => ['required', 'date'],
        ]);

        $from = Carbon::parse($validated['from']);
        $to = Carbon::parse($validated['to']);

        if ($from->diffInDays($to) > LegalHoldAuditExportService::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'to' => __('legal_holds.date_range_exceeded'),
            ]);
        }

        $relativePath = $this->exporter->exportToCsv($user, $from, $to);

        $filename = 'legal-hold-audit-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        $url = $this->resolver->temporaryUrl(
            $relativePath,
            (int) $user->id,
            5,
            $filename,
            'attachment',
        );

        return redirect()->away($url);
    }
}
