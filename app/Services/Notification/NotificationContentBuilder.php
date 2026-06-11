<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\Currency;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\PaymentLinkService;

/**
 * Builds the subject / message / template payload for each domain
 * notification type, extracted from NotificationService (M2 decomposition).
 * Separates message COMPOSITION (currency formatting, payment links,
 * per-type copy + WhatsApp template variables) from delivery dispatch —
 * NotificationService's typed senders now build via this class then hand
 * off to send()/sendInAppOnly(). Behaviour is locked by
 * NotificationEmailStandardizationTest + ServiceCurrencyHardcodeTest — a
 * verbatim move.
 *
 * @phpstan-type BuiltContent array{0: string, 1: string, 2: array<string, mixed>}
 */
class NotificationContentBuilder
{
    public function __construct(
        private readonly PaymentLinkService $paymentLinkService,
    ) {}

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    public function rentReminder(int $tenantId, array $data, int $landlordId): array
    {
        $tenant = User::findOrFail($tenantId);
        $symbol = $this->resolveCurrencySymbol($data, $landlordId);

        $paymentLink = isset($data['invoice_id'])
            ? $this->paymentLinkService->generateUrl($data['invoice_id'], 'rent_reminder')
            : route('tenant.finances.index');

        $message = sprintf(
            "Hello %s,\n\nThis is a friendly reminder that your rent of %s %s is due on %s.\n\nPay now: %s\n\nThank you.",
            $tenant->name,
            $symbol,
            number_format($data['amount'], 2),
            $data['due_date'],
            $paymentLink
        );

        $templateData = [
            'tenant_name' => $tenant->name,
            'amount' => number_format($data['amount'], 0),
            'due_date' => $data['due_date'],
        ];

        // Only include payment_link in template data if feature enabled
        // (requires Meta-approved WhatsApp template with payment_link variable)
        if (config('features.whatsapp_payment_links_enabled', false)) {
            $templateData['payment_link'] = $paymentLink;
        }

        return [
            'Rent Reminder - Due '.$data['due_date'],
            $message,
            array_merge($data, $templateData),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    public function arrearsNotice(int $tenantId, array $data, int $landlordId): array
    {
        $tenant = User::findOrFail($tenantId);
        $symbol = $this->resolveCurrencySymbol($data, $landlordId);

        $paymentLink = isset($data['invoice_id'])
            ? $this->paymentLinkService->generateUrl($data['invoice_id'], 'arrears_notice')
            : route('tenant.finances.index');

        $message = sprintf(
            "Hello %s,\n\nYou have an outstanding balance of %s %s. Please clear your arrears as soon as possible.\n\nPay now: %s\n\nThank you.",
            $tenant->name,
            $symbol,
            number_format($data['arrears_amount'], 2),
            $paymentLink
        );

        $templateData = [
            'tenant_name' => $tenant->name,
            'amount' => number_format($data['arrears_amount'], 0),
            'days_overdue' => (string) ($data['days_overdue'] ?? 0),
        ];

        // Only include payment_link in template data if feature enabled
        if (config('features.whatsapp_payment_links_enabled', false)) {
            $templateData['payment_link'] = $paymentLink;
        }

        return [
            'Payment Overdue - Please Clear Arrears',
            $message,
            array_merge($data, $templateData),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    public function invoice(int $tenantId, array $invoiceData, int $landlordId): array
    {
        $tenant = User::findOrFail($tenantId);
        $symbol = $this->resolveCurrencySymbol($invoiceData, $landlordId);

        $message = sprintf(
            "Hello %s,\n\nYour invoice #%s for %s %s has been generated. Due date: %s.\n\nPlease login to view and pay.",
            $tenant->name,
            $invoiceData['invoice_number'],
            $symbol,
            number_format($invoiceData['total_amount'], 2),
            $invoiceData['due_date']
        );

        $templateData = [
            'tenant_name' => $tenant->name,
            'invoice_no' => $invoiceData['invoice_number'],
            'amount' => number_format($invoiceData['total_amount'], 0),
            'due_date' => $invoiceData['due_date'],
            'link' => $invoiceData['link'] ?? url('/tenant/invoices'),
        ];

        return [
            'New Invoice - '.$invoiceData['invoice_number'],
            $message,
            array_merge($invoiceData, $templateData),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    public function receipt(int $tenantId, array $receiptData, int $landlordId): array
    {
        $tenant = User::findOrFail($tenantId);
        $symbol = $this->resolveCurrencySymbol($receiptData, $landlordId);

        $message = sprintf(
            "Hello %s,\n\nPayment of %s %s received successfully. Receipt #%s.\n\nThank you for your payment.",
            $tenant->name,
            $symbol,
            number_format($receiptData['amount'], 2),
            $receiptData['receipt_number']
        );

        $templateData = [
            'tenant_name' => $tenant->name,
            'amount' => number_format($receiptData['amount'], 0),
            'reference' => $receiptData['receipt_number'],
            'balance' => number_format($receiptData['balance'] ?? 0, 0),
        ];

        return [
            'Payment Receipt - '.$receiptData['receipt_number'],
            $message,
            array_merge($receiptData, $templateData),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    public function rentHike(int $tenantId, array $data, int $landlordId): array
    {
        $tenant = User::findOrFail($tenantId);
        $symbol = $this->resolveCurrencySymbol($data, $landlordId);

        $message = sprintf(
            "Hello %s,\n\nThis is to inform you that your rent will be adjusted from %s %s to %s %s effective %s.\n\nThank you for your understanding.",
            $tenant->name,
            $symbol,
            number_format($data['old_rent'], 2),
            $symbol,
            number_format($data['new_rent'], 2),
            $data['effective_date']
        );

        return ['Rent Adjustment Notice', $message, $data];
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    public function evictionNotice(int $tenantId, array $data, int $landlordId): array
    {
        $tenant = User::findOrFail($tenantId);
        $symbol = $this->resolveCurrencySymbol($data, $landlordId);

        $message = sprintf(
            "Hello %s,\n\nThis is a formal notice of eviction. Due to non-payment of rent, you are required to vacate the premises within the specified period.\n\nOutstanding Balance: %s %s\n\nPlease contact your landlord immediately to discuss this matter.\n\nRegards",
            $tenant->name,
            $symbol,
            number_format($data['arrears_amount'] ?? 0, 2)
        );

        return ['Eviction Notice', $message, $data];
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    public function caretakerInvitation(int $targetUserId, array $data, int $landlordId): array
    {
        $targetUser = User::findOrFail($targetUserId);

        $message = sprintf(
            "Hello %s,\n\nYou've been invited by %s to become a caretaker for %s.\n\nPlease log in to your account to accept or decline this invitation.\n\nThis invitation expires on %s.",
            $targetUser->name,
            $data['landlord_name'],
            $data['property_name'],
            $data['expires_at'] ?? 'in 30 days'
        );

        return ['Caretaker Invitation from '.$data['landlord_name'], $message, $data];
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    public function tenantInvitation(int $targetUserId, array $data, int $landlordId): array
    {
        $targetUser = User::findOrFail($targetUserId);
        $symbol = $this->resolveCurrencySymbol($data, $landlordId);

        $message = sprintf(
            "Hello %s,\n\nYou've been invited by %s to lease Unit %s at %s.\n\nMonthly Rent: %s %s\nDeposit: %s %s\n\nPlease log in to your account to accept or decline this invitation.\n\nThis invitation expires on %s.",
            $targetUser->name,
            $data['landlord_name'],
            $data['unit_number'],
            $data['property_name'],
            $symbol,
            number_format($data['rent_amount'] ?? 0, 2),
            $symbol,
            number_format($data['deposit_amount'] ?? 0, 2),
            $data['expires_at'] ?? 'in 30 days'
        );

        return ['Lease Invitation from '.$data['landlord_name'], $message, $data];
    }

    private function resolveCurrencySymbol(array $data, int $landlordId): string
    {
        if (isset($data['currency_symbol'])) {
            return $data['currency_symbol'];
        }

        $config = PaymentConfiguration::where('landlord_id', $landlordId)->first();

        return ($config?->default_currency ?? Currency::default())->symbol();
    }
}
