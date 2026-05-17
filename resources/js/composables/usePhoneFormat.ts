/**
 * Phase-43 NUMERIC-FORMATTING-3: Kenyan-phone-first formatter
 * scaffold. Pure regex implementation — no libphonenumber-js
 * dependency in Phase 43. Phase 44+ may bring it in for full
 * international support if a market beyond Kenya/+254 emerges.
 *
 * Accepts:
 *   - +254712345678         (E.164)
 *   - 0712345678            (national 0-prefix)
 *   - 254712345678          (no plus)
 *   - 712345678             (loose national, missing 0)
 *
 * Emits one of:
 *   - 'international'        '+254 712 345 678'
 *   - 'national'             '0712 345 678'
 *   - 'compact'              '+254712345678'
 *
 * Returns the original string unchanged for inputs that don't
 * look like Kenyan mobile numbers — the formatter is non-throwing
 * so it's safe to drop into any template.
 */

export type PhoneFormat = 'international' | 'national' | 'compact';

const KENYA_COUNTRY_CODE = '254';

function normaliseToDigits(raw: string): { ok: boolean; digits: string } {
    const trimmed = raw.replace(/[\s\-()]/g, '');
    if (trimmed.startsWith('+')) {
        return { ok: true, digits: trimmed.slice(1) };
    }
    if (trimmed.startsWith('0') && trimmed.length === 10) {
        return { ok: true, digits: `${KENYA_COUNTRY_CODE}${trimmed.slice(1)}` };
    }
    if (/^\d+$/.test(trimmed)) {
        return { ok: true, digits: trimmed };
    }
    return { ok: false, digits: trimmed };
}

export function formatPhone(raw: string, format: PhoneFormat = 'international'): string {
    if (!raw) return raw;
    const { ok, digits } = normaliseToDigits(raw);
    if (!ok || !digits.startsWith(KENYA_COUNTRY_CODE) || digits.length !== 12) {
        return raw;
    }
    const subscriber = digits.slice(3); // 9 digits after country code
    if (format === 'compact') return `+${digits}`;
    if (format === 'national') return `0${subscriber.slice(0, 3)} ${subscriber.slice(3, 6)} ${subscriber.slice(6)}`;
    return `+${KENYA_COUNTRY_CODE} ${subscriber.slice(0, 3)} ${subscriber.slice(3, 6)} ${subscriber.slice(6)}`;
}

export function usePhoneFormat() {
    return { formatPhone };
}
