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
            'status' => $this->status,
            'billing_period_start' => $this->billing_period_start?->toIso8601String(),
            'due_date' => $this->due_date?->toIso8601String(),
            'rent_due' => (float) $this->rent_due,
            'water_due' => (float) $this->water_due,
            'arrears' => (float) $this->arrears,
            'total_due' => (float) $this->total_due,
            'amount_paid' => (float) $this->amount_paid,
            'balance' => (float) ($this->total_due - $this->amount_paid),
            'unit' => $this->whenLoaded('lease', fn () => [
                'id' => $this->lease->unit->id,
                'unit_number' => $this->lease->unit->unit_number,
                'building' => $this->lease->unit->building?->name,
            ]),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
