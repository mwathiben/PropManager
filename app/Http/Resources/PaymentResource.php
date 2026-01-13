<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'payment_date' => $this->payment_date?->toIso8601String(),
            'reference' => $this->reference,
            'notes' => $this->notes,
            'invoice' => $this->whenLoaded('invoice', fn () => [
                'id' => $this->invoice->id,
                'invoice_number' => $this->invoice->invoice_number,
                'total_due' => (float) $this->invoice->total_due,
            ]),
            'unit' => $this->whenLoaded('lease', fn () => [
                'id' => $this->lease->unit->id,
                'unit_number' => $this->lease->unit->unit_number,
                'building' => $this->lease->unit->building?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
