/**
 * Phase-21 DEFER-FRONT-2: mirrors App\Http\Requests\MoveOut\StoreMoveOutRequest
 * — including `intended_move_out_date >= notice_date`. A divergence from
 * the server rules is a bug — keep in sync.
 */
import { z } from 'zod';

export const moveOutSchema = z
    .object({
        notice_date: z.string().min(1, 'A notice date is required'),
        intended_move_out_date: z
            .string()
            .min(1, 'An intended move-out date is required'),
        reason: z.string().max(500).optional().or(z.literal('')),
    })
    .refine(
        (data) =>
            !data.notice_date ||
            !data.intended_move_out_date ||
            data.intended_move_out_date >= data.notice_date,
        {
            message: 'The move-out date must be on or after the notice date',
            path: ['intended_move_out_date'],
        },
    );
