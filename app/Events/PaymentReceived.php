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

        // Load platform fee relationship if not already loaded (per laraveleloquent-relationships skill)
        $this->payment->loadMissing('platformFee');

        // Determine split provider from payment method
        $splitProvider = match ($this->payment->payment_method) {
            'mobile_money' => 'intasend',
            'paystack' => 'paystack',
            default => null,
        };

        // Get split details from PlatformFee relationship
        $platformFee = $this->payment->platformFee;

        return [
            'payment_id' => $this->payment->id,
            'amount' => (float) $this->payment->amount,
            'reference' => $this->payment->reference,
            'payment_method' => $this->payment->payment_method,
            'invoice_id' => $this->invoice->id,
            'invoice_status' => $this->invoice->status,
            'remaining_balance' => $this->invoice->getOutstandingAmount(),
            'tenant_name' => $lease->tenant->name,
            'unit_name' => $lease->unit->name,
            'updated_metrics' => $updatedMetrics,
            // Split payment details
            'platform_fee' => $platformFee?->fee_amount !== null
                ? (float) $platformFee->fee_amount
                : null,
            'landlord_amount' => $platformFee?->net_amount !== null
                ? (float) $platformFee->net_amount
                : (float) $this->payment->amount,
            'split_provider' => $splitProvider,
            'currency' => $this->payment->currency?->value ?? 'KES',
        ];
    }
}
