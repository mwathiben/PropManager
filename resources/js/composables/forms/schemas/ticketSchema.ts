/**
 * Phase-21 DEFER-FRONT-2: mirrors App\Http\Requests\Ticket\StoreTicketRequest.
 * The `exists:` checks for building_id/unit_id are server-only. A
 * divergence from the server rules is a bug — keep in sync.
 */
import { z } from 'zod';

export const ticketSchema = z.object({
    building_id: z
        .union([z.number(), z.string()])
        .refine((v) => `${v}`.length > 0, 'Please select a building'),
    unit_id: z
        .union([z.number(), z.string()])
        .optional()
        .or(z.literal('')),
    category: z.enum(['issue', 'complaint']),
    subcategory: z
        .string()
        .min(1, 'Please select a type')
        .max(100),
    title: z
        .string()
        .min(1, 'A brief summary is required')
        .max(255),
    description: z
        .string()
        .min(1, 'A detailed description is required')
        .max(2000),
    location: z.string().max(255).optional().or(z.literal('')),
    priority: z.enum(['low', 'medium', 'high', 'urgent']),
});
