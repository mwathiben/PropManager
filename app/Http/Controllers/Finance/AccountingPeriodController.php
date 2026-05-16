<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function index(Request $request): Response
    {
        $landlordId = (int) $request->user()->id;

        $periods = AccountingPeriod::query()
            ->where('landlord_id', $landlordId)
            ->orderByDesc('period_start')
            ->limit(24)
            ->get();

        return Inertia::render('Finances/Periods/Index', [
            'periods' => $periods,
        ]);
    }

    public function close(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'close_notes' => ['nullable', 'string', 'max:500'],
        ]);
        $landlordId = (int) $request->user()->id;
        $start = CarbonImmutable::createFromFormat('Y-m-d', $data['month'].'-01');
        $end = $start->endOfMonth();

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
        $period->update([
            'status' => AccountingPeriod::STATUS_OPEN,
            'closed_at' => null,
            'closed_by_user_id' => null,
        ]);

        return Redirect::back()->with('success', __('accounting.period.reopened'));
    }
}
