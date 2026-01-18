<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentLink;
use Illuminate\Http\Request;

class PaymentLinkService
{
    public function generate(Invoice $invoice, array $utm = []): PaymentLink
    {
        $existingLink = PaymentLink::where('invoice_id', $invoice->id)
            ->valid()
            ->first();

        if ($existingLink) {
            return $existingLink;
        }

        return PaymentLink::create([
            'token' => PaymentLink::generateToken(),
            'invoice_id' => $invoice->id,
            'landlord_id' => $invoice->landlord_id,
            'expires_at' => now()->addDays(30),
            'utm_source' => $utm['source'] ?? null,
            'utm_medium' => $utm['medium'] ?? null,
            'utm_campaign' => $utm['campaign'] ?? null,
        ]);
    }

    public function generateForNotification(int $invoiceId, string $notificationType): ?string
    {
        $invoice = Invoice::find($invoiceId);

        if (! $invoice || in_array($invoice->status, ['paid', 'cancelled', 'voided'])) {
            return null;
        }

        $link = $this->generate($invoice, [
            'source' => 'whatsapp',
            'medium' => 'notification',
            'campaign' => $notificationType,
        ]);

        return $link->url;
    }

    public function generateUrl(int $invoiceId, string $notificationType): ?string
    {
        return $this->generateForNotification($invoiceId, $notificationType);
    }

    public function resolve(string $token): ?PaymentLink
    {
        return PaymentLink::withoutGlobalScope('landlord')
            ->where('token', $token)
            ->with(['invoice.lease.tenant', 'invoice.lease.unit.building'])
            ->first();
    }

    public function trackClick(PaymentLink $link, Request $request): void
    {
        $link->markClicked($request->ip());
    }

    public function revokeForInvoice(int $invoiceId): int
    {
        return PaymentLink::withoutGlobalScope('landlord')
            ->where('invoice_id', $invoiceId)
            ->where('is_revoked', false)
            ->update(['is_revoked' => true]);
    }

    public function cleanupExpired(): int
    {
        return PaymentLink::withoutGlobalScope('landlord')
            ->where('expires_at', '<', now()->subDays(7))
            ->delete();
    }

    public function getStats(int $landlordId): array
    {
        return [
            'total' => PaymentLink::where('landlord_id', $landlordId)->count(),
            'active' => PaymentLink::where('landlord_id', $landlordId)->valid()->count(),
            'clicked' => PaymentLink::where('landlord_id', $landlordId)->clicked()->count(),
            'revoked' => PaymentLink::where('landlord_id', $landlordId)->revoked()->count(),
            'expired' => PaymentLink::where('landlord_id', $landlordId)->expired()->count(),
        ];
    }
}
