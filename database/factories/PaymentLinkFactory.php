<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\PaymentLink;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentLinkFactory extends Factory
{
    protected $model = PaymentLink::class;

    public function definition(): array
    {
        $invoice = Invoice::factory()->create();

        return [
            'token' => PaymentLink::generateToken(),
            'invoice_id' => $invoice->id,
            'landlord_id' => $invoice->landlord_id,
            'expires_at' => now()->addDays(7),
            'clicked_at' => null,
            'clicked_ip' => null,
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'is_revoked' => false,
        ];
    }

    public function expired(): static
    {
        return $this->state([
            'expires_at' => now()->subDay(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(['is_revoked' => true]);
    }

    public function clicked(): static
    {
        return $this->state([
            'clicked_at' => now()->subHours(fake()->numberBetween(1, 72)),
            'clicked_ip' => fake()->ipv4(),
        ]);
    }

    public function withUtm(string $source = 'email', string $medium = 'notification', ?string $campaign = null): static
    {
        return $this->state([
            'utm_source' => $source,
            'utm_medium' => $medium,
            'utm_campaign' => $campaign ?? 'invoice_reminder_'.now()->format('Ym'),
        ]);
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state([
            'invoice_id' => $invoice->id,
            'landlord_id' => $invoice->landlord_id,
        ]);
    }
}
