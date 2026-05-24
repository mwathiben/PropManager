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
        // Phase-99: a water-client invoice has no lease/tenant — broadcast to the
        // landlord (for the dashboard) and only add the tenant channel when there is one.
        $channels = [new PrivateChannel('landlord.'.$this->invoice->landlord_id)];

        if ($tenantId = $this->invoice->lease?->tenant_id) {
            $channels[] = new PrivateChannel('tenant.'.$tenantId);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        $lease = $this->invoice->lease;

        $updatedMetrics = app(DashboardService::class)
            ->calculateQuickMetrics($this->invoice->landlord_id);

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
            'tenant_name' => $lease?->tenant?->name
                ?? $this->invoice->waterConnection?->client?->name
                ?? $this->invoice->waterConnection?->client_name,
            'unit_name' => $lease?->unit?->unit_number
                ?? $this->invoice->waterConnection?->identifier,
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
