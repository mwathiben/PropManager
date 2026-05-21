<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Services\Finance\PeriodCloseReadinessService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-30 INT-PERIOD-LOCK-3: landlord-facing accounting period UI.
 * Lists the rolling 24-month window; close/reopen are scoped to the
 * authed landlord. Manual close mirrors the cron — same idempotent
 * write to AccountingPeriod, just operator-driven.
 */
class AccountingPeriodController extends Controller
{
    public function index(Request $request, PeriodCloseReadinessService $readiness): Response
    {
        $landlordId = (int) $request->user()->id;

        $periods = AccountingPeriod::query()
            ->where('landlord_id', $landlordId)
            ->orderByDesc('period_start')
            ->limit(24)
            ->get();

        // Phase-81 PERIOD-CLOSE-2: readiness for the previous month (the usual
        // close target) so the UI can show blockers before the landlord closes.
        $prev = CarbonImmutable::now()->subMonthNoOverflow();

        return Inertia::render('Finances/Periods/Index', [
            'periods' => $periods,
            'readinessMonth' => $prev->format('Y-m'),
            'readiness' => $readiness->check($landlordId, $prev->startOfMonth(), $prev->endOfMonth()),
        ]);
    }

    public function close(Request $request, PeriodCloseReadinessService $readiness): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'close_notes' => ['nullable', 'string', 'max:500'],
        ]);
        $landlordId = (int) $request->user()->id;
        $start = CarbonImmutable::createFromFormat('Y-m-d', $data['month'].'-01');
        $end = $start->endOfMonth();

        // Phase-81 PERIOD-CLOSE-1: refuse to lock a month with unfinished work
        // unless the landlord explicitly forces it (audited).
        $check = $readiness->check($landlordId, $start, $end);
        if (! $check['ready'] && ! $request->boolean('force')) {
            return Redirect::back()->withErrors([
                'period' => __('finance.period_close.blocked'),
                'draft_invoices' => (string) $check['draft_invoices'],
                'pending_reconciliation' => (string) $check['pending_reconciliation'],
            ]);
        }
        if (! $check['ready']) {
            Log::warning('accounting period force-closed with blockers', [
                'landlord_id' => $landlordId,
                'month' => $data['month'],
                'blockers' => $check,
            ]);
        }

        AccountingPeriod::firstOrCreate(
            [
                'landlord_id' => $landlordId,
                'period_start' => $start->toDateString(),
            ],
            [
                'period_end' => $end->toDateString(),
                'status' => AccountingPeriod::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by_user_id' => $landlordId,
                'close_notes' => $data['close_notes'] ?? null,
            ],
        );

        return Redirect::back()->with('success', __('accounting.period.closed'));
    }

    public function reopen(Request $request, AccountingPeriod $period): RedirectResponse
    {
        abort_unless((int) $period->landlord_id === (int) $request->user()->id, 403);
        $data = $request->validate(['reopen_reason' => ['nullable', 'string', 'max:500']]);
        $period->update([
            'status' => AccountingPeriod::STATUS_OPEN,
            'closed_at' => null,
            'closed_by_user_id' => null,
            // Phase-81 PERIOD-CLOSE-3: audit the reopen.
            'reopened_at' => now(),
            'reopened_by_user_id' => (int) $request->user()->id,
            'reopen_reason' => $data['reopen_reason'] ?? null,
        ]);

        return Redirect::back()->with('success', __('accounting.period.reopened'));
    }
}
