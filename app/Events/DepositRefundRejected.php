<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\DepositRefundRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DepositRefundRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly DepositRefundRequest $refund)
    {
    }
}
