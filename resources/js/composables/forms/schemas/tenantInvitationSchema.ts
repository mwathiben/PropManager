/**
 * Phase-21 DEFER-FRONT-2: mirrors the inline rules in
 * App\Http\Controllers\TenantInvitationController::store — including the
 * conditional "phone required when SMS/WhatsApp is selected" rule. The
 * `exists:units,id` check is server-only. A divergence from the server
 * rules is a bug — keep in sync.
 */
import { z } from 'zod';

const channel = z.enum(['email', 'sms', 'whatsapp']);

const optionalString = z
    .string()
    .optional()
    .or(z.literal(''));

export const tenantInvitationSchema = z
    .object({
        unit_id: z
            .union([z.number(), z.string()])
            .refine((v) => `${v}`.length > 0, 'A unit is required'),
        email: z
            .string()
            .min(1, 'Email is required')
            .email('Enter a valid email address')
            .max(255),
        tenant_name: optionalString.pipe(z.string().max(255).optional()),
        tenant_phone: optionalString.pipe(z.string().max(20).optional()),
        rent_amount: z.coerce
            .number({ message: 'Enter a valid rent amount' })
            .min(0, 'Rent cannot be negative'),
        service_charge: z.coerce
            .number({ message: 'Enter a valid service charge' })
            .min(0, 'Service charge cannot be negative'),
        deposit_amount: z.coerce
            .number({ message: 'Enter a valid deposit amount' })
            .min(0, 'Deposit cannot be negative'),
        start_date: z.string().min(1, 'A lease start date is required'),
        end_date: optionalString,
        notification_channels: z
            .array(channel)
            .min(1, 'Select at least one notification channel'),
    })
    .refine(
        (data) => {
            const needsPhone =
                data.notification_channels.includes('sms') ||
                data.notification_channels.includes('whatsapp');
            return !needsPhone || (data.tenant_phone ?? '').length >= 10;
        },
        {
            message: 'A phone number is required for SMS/WhatsApp delivery',
            path: ['tenant_phone'],
        },
    )
    .refine(
        (data) => !data.end_date || data.end_date > data.start_date,
        {
            message: 'The end date must be after the start date',
            path: ['end_date'],
        },
    );
