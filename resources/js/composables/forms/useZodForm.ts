/**
 * Phase-21 DEFER-FRONT-2 (closes Phase-15 FRONT-7 deferral): client-side
 * form validation bridge.
 *
 * PropManager is an Inertia app — Inertia's useForm already owns form
 * state, submission, `processing`, and the error channel (`form.errors`)
 * that every template reads via <InputError> / `form.errors.x`. Rather
 * than bolt on vee-validate's parallel form-state engine (two sources of
 * truth kept in sync by hand), useZodForm keeps Inertia's useForm as the
 * single owner and pipes Zod validation failures INTO `form.errors` via
 * setError(). One error channel: a field error looks identical whether
 * it came from a client-side Zod parse or a server-side 422.
 *
 * Zod schemas (resources/js/composables/forms/schemas/) mirror the
 * server-side Form Request rules. The server stays the authoritative
 * validation boundary — this is a pre-network-roundtrip UX win, not a
 * security control. A schema diverging from its server rules is a bug.
 *
 * Usage:
 *   const form = useForm({ email: '', password: '' });
 *   const { validate } = useZodForm(form, loginSchema);
 *   const submit = () => {
 *       if (!validate()) return;
 *       form.post(route('login'));
 *   };
 */
import type { ZodType } from 'zod';

interface InertiaFormLike<TData> {
    data: () => TData;
    setError: (errors: Record<string, string>) => void;
    clearErrors: () => void;
}

export function useZodForm<TData extends Record<string, unknown>>(
    form: InertiaFormLike<TData>,
    schema: ZodType,
) {
    const validate = (): boolean => {
        const result = schema.safeParse(form.data());

        // Clear stale client errors on every attempt. Server errors are
        // re-issued by the subsequent post() if the server disagrees.
        form.clearErrors();

        if (result.success) {
            return true;
        }

        const errors: Record<string, string> = {};
        for (const issue of result.error.issues) {
            const key = issue.path.join('.') || '_form';
            // First issue per field wins — matches Laravel's default
            // "bail after first failure" presentation.
            if (!(key in errors)) {
                errors[key] = issue.message;
            }
        }
        form.setError(errors);

        return false;
    };

    return { validate };
}
