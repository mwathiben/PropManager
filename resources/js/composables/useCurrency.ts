import { computed, type ComputedRef } from 'vue';
import { usePage } from '@inertiajs/vue3';

interface CurrencySharedData {
    code: string;
    symbol: string;
}

export interface UseCurrencyReturn {
    currencyCode: ComputedRef<string>;
    currencySymbol: ComputedRef<string>;
    format: (amount: number, overrideCurrency?: string) => string;
    formatMinor: (amountMinorUnits: number, overrideCurrency?: string) => string;
}

// Phase-43 NUMERIC-FORMATTING-1: the hand-rolled symbol map stays
// as a *fallback* for environments where Intl.NumberFormat lacks
// the currency (rare, but defensive — narrow-symbol output for
// 'KES' on older Node builds returns the ISO code instead of 'KSh').
const SYMBOL_FALLBACK: Record<string, string> = {
    KES: 'KSh',
    USD: '$',
    EUR: '€',
    GBP: '£',
};

// Map an app locale ('en' | 'sw') to the BCP-47 tag Intl APIs want.
// Mirrors useFormatters.ts INTL_LOCALES so currency + date + number
// formatting stay in lock-step.
const INTL_LOCALE: Record<string, string> = {
    en: 'en-KE',
    sw: 'sw-KE',
};

function resolveLocale(appLocale: string | undefined): string {
    return INTL_LOCALE[appLocale ?? 'en'] ?? 'en-KE';
}

function narrowSymbolFor(currency: string, appLocale: string | undefined): string {
    try {
        const parts = new Intl.NumberFormat(resolveLocale(appLocale), {
            style: 'currency',
            currency,
            currencyDisplay: 'narrowSymbol',
        }).formatToParts(0);
        const symbol = parts.find((p) => p.type === 'currency')?.value;
        if (symbol && symbol !== currency) return symbol;
    } catch {
        /* fall through */
    }
    return SYMBOL_FALLBACK[currency] ?? currency;
}

export function useCurrency(overrideCode?: string): UseCurrencyReturn {
    const page = usePage();

    const currencyCode = computed(() => {
        if (overrideCode) return overrideCode;
        const shared = page.props.currency as CurrencySharedData | null | undefined;
        return shared?.code ?? 'KES';
    });

    const appLocale = computed(() => (page.props.locale as string | undefined) ?? 'en');

    const currencySymbol = computed(() => {
        return narrowSymbolFor(currencyCode.value, appLocale.value);
    });

    const format = (amount: number, overrideCurrency?: string): string => {
        const currency = overrideCurrency ?? currencyCode.value;
        try {
            return new Intl.NumberFormat(resolveLocale(appLocale.value), {
                style: 'currency',
                currency,
                currencyDisplay: 'narrowSymbol',
            }).format(amount);
        } catch {
            const symbol = SYMBOL_FALLBACK[currency] ?? currency;
            return `${symbol} ${amount.toFixed(2)}`;
        }
    };

    const formatMinor = (amountMinorUnits: number, overrideCurrency?: string): string => {
        return format(amountMinorUnits / 100, overrideCurrency);
    };

    return { currencyCode, currencySymbol, format, formatMinor };
}
