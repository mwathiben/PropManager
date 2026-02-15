import { computed, type ComputedRef } from 'vue';
import { usePage } from '@inertiajs/vue3';

interface CurrencySharedData {
    code: string;
    symbol: string;
}

export interface UseCurrencyReturn {
    currencyCode: ComputedRef<string>;
    currencySymbol: ComputedRef<string>;
}

const SYMBOL_MAP: Record<string, string> = {
    KES: 'KSh',
    USD: '$',
    EUR: '€',
    GBP: '£',
};

export function useCurrency(overrideCode?: string): UseCurrencyReturn {
    const page = usePage();

    const currencyCode = computed(() => {
        if (overrideCode) return overrideCode;
        const shared = page.props.currency as CurrencySharedData | null | undefined;
        return shared?.code ?? 'KES';
    });

    const currencySymbol = computed(() => {
        if (overrideCode) return SYMBOL_MAP[overrideCode] ?? overrideCode;
        const shared = page.props.currency as CurrencySharedData | null | undefined;
        return shared?.symbol ?? 'KSh';
    });

    return { currencyCode, currencySymbol };
}
