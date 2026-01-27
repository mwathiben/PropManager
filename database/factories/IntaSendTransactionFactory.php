<?php

namespace Database\Factories;

use App\Models\IntaSendTransaction;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntaSendTransactionFactory extends Factory
{
    protected $model = IntaSendTransaction::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 1000, 50000);
        $charges = round($amount * 0.015, 2);
        $netAmount = $amount - $charges;
        $platformFee = round($netAmount * 0.03, 2);
        $landlordAmount = $netAmount - $platformFee;

        return [
            'payment_id' => null,
            'invoice_id' => null,
            'landlord_id' => null,
            'intasend_invoice_id' => strtoupper(fake()->unique()->bothify('???????')),
            'api_ref' => 'ITS-'.time().'-'.strtoupper(substr(uniqid(), -6)),
            'phone_number' => '254'.fake()->numerify('7########'),
            'amount' => $amount,
            'intasend_charges' => $charges,
            'net_amount' => $netAmount,
            'platform_fee' => $platformFee,
            'landlord_amount' => $landlordAmount,
            'state' => IntaSendTransaction::STATE_PENDING,
            'mpesa_receipt' => null,
            'failure_reason' => null,
            'webhook_payload' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);
    }

    public function processing(): static
    {
        return $this->state([
            'state' => IntaSendTransaction::STATE_PROCESSING,
        ]);
    }

    public function complete(): static
    {
        return $this->state([
            'state' => IntaSendTransaction::STATE_COMPLETE,
            'mpesa_receipt' => 'QKL'.fake()->unique()->numberBetween(100000000, 999999999),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'state' => IntaSendTransaction::STATE_FAILED,
            'failure_reason' => fake()->randomElement([
                'User cancelled the request',
                'Insufficient balance',
                'Wrong PIN entered',
                'Request timeout',
            ]),
        ]);
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state([
            'invoice_id' => $invoice->id,
            'landlord_id' => $invoice->landlord_id,
        ]);
    }

    public function withWebhookPayload(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'webhook_payload' => [
                    'invoice' => [
                        'invoice_id' => $attributes['intasend_invoice_id'],
                        'state' => $attributes['state'],
                        'provider' => 'M-PESA',
                        'value' => $attributes['amount'],
                        'charges' => $attributes['intasend_charges'],
                        'net_amount' => $attributes['net_amount'],
                        'api_ref' => $attributes['api_ref'],
                    ],
                ],
            ];
        });
    }
}
