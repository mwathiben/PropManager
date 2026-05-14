/**
 * Phase-21 DEFER-FRONT-2: mirrors the validation in
 * App\Http\Controllers\Auth\RegisteredUserController::store
 * (name/email/password + `confirmed`). The `unique:users` email check
 * is server-only — it can't be mirrored client-side. A divergence from
 * the server rules is a bug — keep in sync.
 */
import { z } from 'zod';

export const registerSchema = z
    .object({
        name: z.string().min(1, 'Name is required').max(255),
        email: z
            .string()
            .min(1, 'Email is required')
            .email('Enter a valid email address')
            .max(255),
        password: z
            .string()
            .min(8, 'Password must be at least 8 characters'),
        password_confirmation: z
            .string()
            .min(1, 'Please confirm your password'),
    })
    .refine((data) => data.password === data.password_confirmation, {
        message: 'Passwords do not match',
        path: ['password_confirmation'],
    });
