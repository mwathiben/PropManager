/**
 * Phase-21 DEFER-FRONT-2: mirrors App\Http\Requests\Auth\LoginRequest.
 * A divergence from the server rules is a bug — keep in sync.
 */
import { z } from 'zod';

export const loginSchema = z.object({
    email: z
        .string()
        .min(1, 'Email is required')
        .email('Enter a valid email address'),
    password: z.string().min(1, 'Password is required'),
});
