<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandlordUsageMetric extends Model
{
    use TenantScope;

    public const METRIC_DB_QUERIES = 'db_queries';

    public const METRIC_S3_BYTES = 's3_bytes';

    public const METRIC_SMS_SENDS = 'sms_sends';

    public const METRIC_CRON_MINUTES = 'cron_minutes';

    public const METRIC_LOG_BYTES = 'log_bytes';

    public const METRICS = [
        self::METRIC_DB_QUERIES,
        self::METRIC_S3_BYTES,
        self::METRIC_SMS_SENDS,
        self::METRIC_CRON_MINUTES,
        self::METRIC_LOG_BYTES,
    ];

    protected $fillable = [
        'landlord_id',
        'metric',
        'day',
        'value',
    ];

    protected $casts = [
        'day' => 'date',
        'value' => 'integer',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }
}
