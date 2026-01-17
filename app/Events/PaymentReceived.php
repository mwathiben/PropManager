<?php

namespace App\Events;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\DashboardService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Payment $payment,
        public Invoice $invoice
    ) {}

    public function broadcastOn(): array
    {
        $lease = $this->invoice->lease;

        return [
            new PrivateChannel('landlord.'.$lease->landlord_id),
            new PrivateChannel('tenant.'.$lease->tenant_id),
        ];
    }

    public function broadcastWith(): array
    {
        $lease = $this->invoice->lease;

        $updatedMetrics = app(DashboardService::class)
            ->calculateQuickMetrics($lease->landlord_id);

        return [
            'payment_id' => $this->payment->id,
            'amount' => (float) $this->payment->amount,
            'reference' => $this->payment->reference,
            'payment_method' => $this->payment->payment_method,
            'invoice_id' => $this->invoice->id,
            'invoice_status' => $this->invoice->status,
            'remaining_balance' => (float) $this->invoice->balance,
            'tenant_name' => $lease->tenant->name,
            'unit_name' => $lease->unit->name,
            'updated_metrics' => $updatedMetrics,
        ];
    }
}
