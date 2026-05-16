<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueryCostLog extends Model
{
    protected $fillable = [
        'landlord_id',
        'route_class',
        'query_count',
        'rows_scanned',
        'rows_returned',
        'request_at',
    ];

    protected $casts = [
        'query_count' => 'integer',
        'rows_scanned' => 'integer',
        'rows_returned' => 'integer',
        'request_at' => 'datetime',
    ];
}
