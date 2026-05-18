<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-59 FILE-RETENTION-1: per-subject file retention windows.
 * Six platform defaults are seeded (subject NULL landlord_id); any
 * landlord can override with a row that has both subject and
 * landlord_id populated. resolveFor() reads the per-landlord row if
 * present, falls back to the platform default.
 */
class FileRetentionPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject',
        'retention_days',
        'landlord_id',
    ];

    protected $casts = [
        'retention_days' => 'integer',
        'landlord_id' => 'integer',
    ];

    public const SUBJECTS = [
        'ocr_temp',
        'export_zip',
        'kyc_doc',
        'lease_doc',
        'water_reading_photo',
        'invoice_pdf',
        'file_access_audit',
    ];

    public static function resolveFor(string $subject, ?int $landlordId = null): ?int
    {
        $cacheKey = "phase59:retention:{$subject}:".($landlordId ?? 'platform');

        return Cache::remember($cacheKey, 300, function () use ($subject, $landlordId) {
            $row = static::query()
                ->where('subject', $subject)
                ->where('landlord_id', $landlordId)
                ->first();

            if (! $row && $landlordId !== null) {
                $row = static::query()
                    ->where('subject', $subject)
                    ->whereNull('landlord_id')
                    ->first();
            }

            return $row?->retention_days;
        });
    }
}
