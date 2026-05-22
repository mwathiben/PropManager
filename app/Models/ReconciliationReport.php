<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use App\ValueObjects\ReconciliationResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationReport extends Model
{
    use HasFactory, TenantScope;

    protected $fillable = [
        'landlord_id',
        'provider',
        'status',
        'period_from',
        'period_to',
        'local_count',
        'remote_count',
        'matched_count',
        'discrepancy_count',
        'result_data',
        'error_message',
        'alert_sent',
        'reconciled_at',
    ];

    protected $casts = [
        'result_data' => 'array',
        'period_from' => 'date',
        'period_to' => 'date',
        'alert_sent' => 'boolean',
        'reconciled_at' => 'datetime',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function hasDiscrepancies(): bool
    {
        return $this->discrepancy_count > 0;
    }

    /**
     * Phase-85 RECON-VIEW-2: the stored discrepancy list (type | reference |
     * local vs remote amount) for the in-app report view.
     *
     * @return list<array<string, mixed>>
     */
    public function getDiscrepanciesAttribute(): array
    {
        return $this->result_data ?? [];
    }

    /**
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $period  [$from, $to]
     */
    public static function storeFromResult(
        int $landlordId,
        string $provider,
        ReconciliationResult $result,
        array $period,
    ): self {
        return self::create([
            'landlord_id' => $landlordId,
            'provider' => $provider,
            'status' => 'completed',
            'period_from' => $period[0],
            'period_to' => $period[1],
            'local_count' => $result->localCount,
            'remote_count' => $result->remoteCount,
            'matched_count' => $result->matchedCount,
            'discrepancy_count' => $result->discrepancyCount(),
            'result_data' => $result->toArray(),
            'alert_sent' => false,
            'reconciled_at' => now(),
        ]);
    }

    /**
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $period  [$from, $to]
     */
    public static function storeFailed(
        int $landlordId,
        string $provider,
        string $errorMessage,
        array $period,
    ): self {
        return self::create([
            'landlord_id' => $landlordId,
            'provider' => $provider,
            'status' => 'failed',
            'period_from' => $period[0],
            'period_to' => $period[1],
            'discrepancy_count' => 0,
            'error_message' => $errorMessage,
            'alert_sent' => false,
            'reconciled_at' => now(),
        ]);
    }
}
