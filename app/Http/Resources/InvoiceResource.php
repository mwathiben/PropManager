<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'currency' => $this->currency?->value ?? 'KES',
            'status' => $this->status,
            'billing_period_start' => $this->billing_period_start?->toIso8601String(),
            'due_date' => $this->due_date?->toIso8601String(),
            'rent_due' => (float) $this->rent_due,
            'water_due' => (float) $this->water_due,
            'arrears' => (float) $this->arrears,
            'total_due' => (float) $this->total_due,
            'amount_paid' => (float) $this->amount_paid,
            'balance' => (float) ($this->total_due - $this->amount_paid),
            // Phase-98: an invoice bills a lease's tenant OR a water connection's client.
            'recipient' => $this->recipientLabel(),
            'unit' => $this->resolveUnitBlock(),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function resolveUnitBlock(): ?array
    {
        if ($this->isWaterClientInvoice()) {
            $unit = $this->waterConnection?->unit;

            return [
                'id' => $unit?->id,
                'unit_number' => $unit?->unit_number ?? $this->waterConnection?->identifier,
                'building' => $unit?->building?->name,
            ];
        }

        $unit = $this->lease?->unit;

        return $unit ? [
            'id' => $unit->id,
            'unit_number' => $unit->unit_number,
            'building' => $unit->building?->name,
        ] : null;
    }
}
