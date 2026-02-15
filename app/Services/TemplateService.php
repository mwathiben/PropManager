<?php

namespace App\Services;

use App\Enums\Currency;
use App\Models\NotificationTemplate;
use Illuminate\Support\Collection;

class TemplateService
{
    /**
     * Get available placeholders for a notification type
     */
    public function getAvailablePlaceholders(string $type): array
    {
        return NotificationTemplate::getAllPlaceholders($type);
    }

    /**
     * Render a template with context
     */
    public function render(NotificationTemplate $template, array $context): array
    {
        return $template->render($context);
    }

    /**
     * Validate placeholders in a template body
     */
    public function validatePlaceholders(string $body, string $type): array
    {
        $validPlaceholders = array_keys(NotificationTemplate::getAllPlaceholders($type));
        $errors = [];

        // Find all placeholders in the body
        preg_match_all('/\{\{(\w+)\}\}/', $body, $matches);
        $usedPlaceholders = $matches[1] ?? [];

        foreach ($usedPlaceholders as $placeholder) {
            if (! in_array($placeholder, $validPlaceholders)) {
                $errors[] = "Invalid placeholder: {{{$placeholder}}}";
            }
        }

        return $errors;
    }

    /**
     * Get default templates collection
     */
    public function getDefaultTemplates(): Collection
    {
        return collect($this->getDefaultTemplateData());
    }

    /**
     * Seed default templates for a landlord
     */
    public function seedDefaultTemplates(int $landlordId): void
    {
        foreach ($this->getDefaultTemplateData() as $template) {
            NotificationTemplate::firstOrCreate(
                [
                    'landlord_id' => $landlordId,
                    'slug' => $template['slug'],
                ],
                [
                    'name' => $template['name'],
                    'type' => $template['type'],
                    'subject' => $template['subject'],
                    'body' => $template['body'],
                    'available_placeholders' => array_keys(NotificationTemplate::getAllPlaceholders($template['type'])),
                    'is_default' => true,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Build context for a tenant notification
     */
    public function buildTenantContext(
        $tenant,
        $lease = null,
        array $additionalData = []
    ): array {
        $context = [
            'tenant_name' => $tenant->name,
            'tenant_email' => $tenant->email,
            'current_date' => now()->format('F j, Y'),
        ];

        if ($lease) {
            $currency = $lease->unit?->building?->getEffectiveCurrency() ?? Currency::default();
            $context['currency_symbol'] = $currency->symbol();
            $context['unit_number'] = $lease->unit?->unit_number ?? 'N/A';
            $context['building_name'] = $lease->unit?->building?->name ?? 'N/A';
            $context['property_name'] = $lease->unit?->building?->property?->name ?? 'N/A';
            $context['rent_amount'] = number_format($lease->rent_amount, 2);
            $context['arrears_amount'] = number_format($lease->arrears ?? 0, 2);
        }

        // Get landlord name
        $landlordId = $lease?->landlord_id ?? $tenant->landlord_id;
        if ($landlordId) {
            $landlord = \App\Models\User::find($landlordId);
            $context['landlord_name'] = $landlord?->name ?? 'Management';
        }

        return array_merge($context, $additionalData);
    }

    /**
     * Get the default template data
     */
    private function getDefaultTemplateData(): array
    {
        return [
            [
                'name' => 'Default Rent Reminder',
                'slug' => 'default-rent-reminder',
                'type' => 'rent_reminder',
                'subject' => 'Rent Reminder - Due {{due_date}}',
                'body' => "Hello {{tenant_name}},\n\nThis is a friendly reminder that your rent of {{currency_symbol}} {{rent_amount}} is due on {{due_date}}.\n\nPlease ensure payment is made on time to avoid late fees.\n\nIf you have already made this payment, please disregard this notice.\n\nThank you,\n{{landlord_name}}",
            ],
            [
                'name' => 'Default Arrears Notice',
                'slug' => 'default-arrears-notice',
                'type' => 'arrears_notice',
                'subject' => 'Payment Overdue - Outstanding Balance Notice',
                'body' => "Hello {{tenant_name}},\n\nOur records indicate that you have an outstanding balance of {{currency_symbol}} {{arrears_amount}} which is {{days_overdue}} days overdue.\n\nPlease clear your arrears as soon as possible to avoid any inconvenience.\n\nFor payment options or to discuss a payment plan, please contact us.\n\nThank you,\n{{landlord_name}}",
            ],
            [
                'name' => 'Default Invoice Notification',
                'slug' => 'default-invoice',
                'type' => 'invoice',
                'subject' => 'New Invoice - {{invoice_number}}',
                'body' => "Hello {{tenant_name}},\n\nYour invoice {{invoice_number}} for {{currency_symbol}} {{total_amount}} has been generated.\n\nDue Date: {{due_date}}\n\nYou can view and pay your invoice online.\n\nThank you,\n{{landlord_name}}",
            ],
            [
                'name' => 'Default Payment Receipt',
                'slug' => 'default-receipt',
                'type' => 'receipt',
                'subject' => 'Payment Receipt - {{receipt_number}}',
                'body' => "Hello {{tenant_name}},\n\nThank you for your payment of {{currency_symbol}} {{payment_amount}}.\n\nReceipt Number: {{receipt_number}}\nPayment Date: {{payment_date}}\nPayment Method: {{payment_method}}\n\nThank you for your prompt payment.\n\n{{landlord_name}}",
            ],
            [
                'name' => 'Default Rent Adjustment Notice',
                'slug' => 'default-rent-hike',
                'type' => 'rent_hike',
                'subject' => 'Rent Adjustment Notice',
                'body' => "Hello {{tenant_name}},\n\nThis is to inform you that your monthly rent will be adjusted from {{currency_symbol}} {{old_rent}} to {{currency_symbol}} {{new_rent}}, effective {{effective_date}}.\n\nThis represents an increase of {{percentage_increase}}%.\n\nIf you have any questions, please don't hesitate to contact us.\n\nThank you for your understanding,\n{{landlord_name}}",
            ],
            [
                'name' => 'Default Lease Expiry Reminder',
                'slug' => 'default-lease-expiry',
                'type' => 'lease_expiry',
                'subject' => 'Lease Expiry Reminder - Action Required',
                'body' => "Hello {{tenant_name}},\n\nThis is a reminder that your lease for Unit {{unit_number}} at {{building_name}} will expire on {{expiry_date}} (in {{days_until_expiry}} days).\n\nPlease contact us to discuss your renewal options or to provide notice if you don't intend to renew.\n\nThank you,\n{{landlord_name}}",
            ],
            [
                'name' => 'Default Lease Renewal Confirmation',
                'slug' => 'default-lease-renewal',
                'type' => 'lease_renewal',
                'subject' => 'Lease Renewal Confirmation',
                'body' => "Hello {{tenant_name}},\n\nWe are pleased to confirm the renewal of your lease for Unit {{unit_number}} at {{building_name}}.\n\nYour new lease begins on {{renewal_date}}.\nNew Monthly Rent: {{currency_symbol}} {{new_rent}}\n\nThank you for continuing to be our valued tenant.\n\n{{landlord_name}}",
            ],
            [
                'name' => 'Default Eviction Notice',
                'slug' => 'default-eviction-notice',
                'type' => 'eviction_notice',
                'subject' => 'IMPORTANT: Eviction Notice',
                'body' => "Hello {{tenant_name}},\n\nThis is a formal notice of eviction for Unit {{unit_number}} at {{building_name}}.\n\nDue to non-payment of rent, you are required to vacate the premises by {{vacate_date}}.\n\nOutstanding Balance: {{currency_symbol}} {{arrears_amount}}\n\nThis notice gives you {{notice_period}} days to either:\n1. Clear your outstanding balance in full, OR\n2. Vacate the premises\n\nPlease contact us immediately to discuss this matter.\n\n{{landlord_name}}",
            ],
            [
                'name' => 'Default Maintenance Notice',
                'slug' => 'default-maintenance-notice',
                'type' => 'maintenance_notice',
                'subject' => 'Maintenance Notice - {{building_name}}',
                'body' => "Hello {{tenant_name}},\n\nThis is to inform you of scheduled maintenance work at {{building_name}}.\n\nPlease refer to the details below and plan accordingly.\n\nWe apologize for any inconvenience this may cause.\n\nThank you for your cooperation,\n{{landlord_name}}",
            ],
            [
                'name' => 'Default General Message',
                'slug' => 'default-general',
                'type' => 'general',
                'subject' => 'Message from {{landlord_name}}',
                'body' => "Hello {{tenant_name}},\n\n{{landlord_name}}",
            ],
        ];
    }
}
