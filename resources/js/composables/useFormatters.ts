import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

/**
 * Centralized composable for formatting currencies, dates, and numbers.
 * All date/currency/number formatting in Vue components MUST use this composable.
 *
 * ESLint rules (no-restricted-syntax) enforce usage and prevent inline formatting:
 * - toLocaleDateString() → Use formatDate() instead
 * - toLocaleString() with args → Use formatMoney() or formatNumber() instead
 * - new Intl.NumberFormat() → Use formatMoney() or formatNumber() instead
 *
 * Available functions:
 * - formatMoney(value, opts?) - Format as currency (uses shared currency or KES default)
 * - formatCurrency(value, opts?) - Alias for formatMoney
 * - formatDate(date, format?) - Format date (short/long/numeric)
 * - formatDateTime(date) - Format date with time
 * - formatRelativeDate(date) - "2 days ago", "Tomorrow", etc.
 * - formatRelativeTime(date) - "5 minutes ago", etc.
 * - formatPercent(value, decimals?) - Format as percentage
 * - formatNumber(value) - Format with thousands separator
 * - formatFileSize(bytes) - "1.2 MB", etc.
 * - todayAsISODate() - Returns "YYYY-MM-DD" for form defaults
 *
 * Phase-24 I18N-FORMAT-1/2: Intl locale arguments are derived from the
 * shared Inertia `locale` prop (set by the SetLocale middleware), so
 * dates / relative phrases / numbers track the user's chosen language.
 * Relative-date phrasing comes from vue-i18n keys (format.relative.*).
 */

export interface FormattersOptions {
    locale?: string;
    currency?: string;
    dateLocale?: string;
}

export interface FormatMoneyOptions {
    maximumFractionDigits?: number;
    currency?: string;
}

export type DateFormat = 'short' | 'long' | 'numeric';

export interface UseFormattersReturn {
    formatMoney: (value: number | null | undefined, opts?: FormatMoneyOptions) => string;
    formatCurrency: (value: number | null | undefined, opts?: FormatMoneyOptions) => string;
    formatDate: (date: string | Date | null | undefined, format?: DateFormat) => string;
    formatDateTime: (date: string | Date | null | undefined) => string;
    formatRelativeDate: (date: string | Date | null | undefined) => string;
    formatRelativeTime: (date: string | Date | null | undefined) => string;
    formatPercent: (value: number | null | undefined, decimals?: number) => string;
    formatNumber: (value: number | null | undefined) => string;
    formatFileSize: (bytes: number | null | undefined) => string;
    todayAsISODate: () => string;
}

/**
 * Map the application locale code (en / sw) to a BCP-47 tag suitable
 * for Intl.* APIs. KE region is the default because PropManager's
 * audience is Kenyan landlords/tenants — overridable via `options`.
 */
const INTL_LOCALES: Record<string, { number: string; date: string }> = {
    en: { number: 'en-KE', date: 'en-GB' },
    sw: { number: 'sw-KE', date: 'sw-KE' },
};

function resolveIntlLocales(appLocale: string | undefined): { number: string; date: string } {
    if (appLocale && INTL_LOCALES[appLocale]) {
        return INTL_LOCALES[appLocale];
    }
    return INTL_LOCALES.en;
}

export function useFormatters(options: FormattersOptions = {}): UseFormattersReturn {
    let sharedCurrencyCode: string | undefined;
    let sharedLocale: string | undefined;
    try {
        const page = usePage();
        const shared = page.props.currency as { code: string; symbol: string } | null | undefined;
        if (shared?.code) {
            sharedCurrencyCode = shared.code;
        }
        const pageLocale = page.props.locale as string | undefined;
        if (pageLocale) {
            sharedLocale = pageLocale;
        }
    } catch {
        // Outside Vue component context — use default
    }

    // vue-i18n's t() is the source of truth for translated relative
    // phrases. Outside a Vue setup context (rare — tests, ad-hoc
    // formatters) it throws, so we fall back to English literals.
    let t: ((key: string, named?: Record<string, unknown>, plural?: number) => string) | null = null;
    try {
        const i18n = useI18n();
        t = (key, named, plural) => {
            if (plural !== undefined) {
                return i18n.t(key, named ?? {}, plural);
            }
            return named ? i18n.t(key, named) : i18n.t(key);
        };
    } catch {
        t = null;
    }

    const intl = resolveIntlLocales(sharedLocale);

    const config = {
        locale: options.locale || intl.number,
        currency: options.currency || sharedCurrencyCode || 'KES',
        dateLocale: options.dateLocale || intl.date,
    };

    /**
     * Format a value as currency (uses shared currency or KES default)
     */
    const formatMoney = (value: number | null | undefined, opts: FormatMoneyOptions = {}): string => {
        if (value === null || value === undefined || Number.isNaN(value)) return '-';
        return new Intl.NumberFormat(config.locale, {
            style: 'currency',
            currency: opts.currency || config.currency,
            maximumFractionDigits: opts.maximumFractionDigits ?? 0
        }).format(value);
    };

    // Alias for backwards compatibility
    const formatCurrency = formatMoney;

    /**
     * Format a date string
     */
    const formatDate = (date: string | Date | null | undefined, format: DateFormat = 'short'): string => {
        if (!date) return '-';

        const d = new Date(date);
        if (isNaN(d.getTime())) return '-';

        const formats: Record<DateFormat, Intl.DateTimeFormatOptions> = {
            short: { day: 'numeric', month: 'short', year: 'numeric' },
            long: { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' },
            numeric: { day: '2-digit', month: '2-digit', year: 'numeric' }
        };

        return d.toLocaleDateString(config.dateLocale, formats[format] || formats.short);
    };

    /**
     * Format a date with time
     */
    const formatDateTime = (date: string | Date | null | undefined): string => {
        if (!date) return '-';

        const d = new Date(date);
        if (isNaN(d.getTime())) return '-';

        return d.toLocaleDateString(config.dateLocale, {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const tr = (key: string, fallback: string, named?: Record<string, unknown>, plural?: number): string => {
        if (!t) return fallback;
        try {
            return t(key, named, plural);
        } catch {
            return fallback;
        }
    };

    /**
     * Format a date relative to today (e.g., "2 days ago", "Tomorrow")
     */
    const formatRelativeDate = (date: string | Date | null | undefined): string => {
        if (!date) return '-';

        const d = new Date(date);
        if (isNaN(d.getTime())) return '-';

        const now = new Date();
        const diffTime = d.getTime() - now.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays < -1) {
            const count = Math.abs(diffDays);
            return tr('format.relative.days_ago', `${count} days ago`, { count }, count);
        }
        if (diffDays === -1) return tr('format.relative.yesterday', 'Yesterday');
        if (diffDays === 0) return tr('format.relative.today', 'Today');
        if (diffDays === 1) return tr('format.relative.tomorrow', 'Tomorrow');
        return tr('format.relative.in_days', `In ${diffDays} days`, { count: diffDays }, diffDays);
    };

    /**
     * Format a number as percentage
     */
    const formatPercent = (value: number | null | undefined, decimals: number = 0): string => {
        if (value === null || value === undefined || !Number.isFinite(value)) return '-';
        return `${value.toFixed(decimals)}%`;
    };

    /**
     * Format a number with thousands separator
     */
    const formatNumber = (value: number | null | undefined): string => {
        if (value === null || value === undefined || !Number.isFinite(value)) return '-';
        return new Intl.NumberFormat(config.locale).format(value);
    };

    /**
     * Format file size in human-readable format
     */
    const formatFileSize = (bytes: number | null | undefined): string => {
        // Handle null, undefined, non-finite, zero, and negative values
        if (bytes === null || bytes === undefined || !Number.isFinite(bytes) || bytes <= 0) {
            return '0 B';
        }

        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];

        // Calculate the appropriate unit index and clamp to valid range
        const rawIndex = Math.floor(Math.log(bytes) / Math.log(k));
        const i = Math.max(0, Math.min(rawIndex, sizes.length - 1));

        const value = bytes / Math.pow(k, i);
        return `${parseFloat(value.toFixed(1))} ${sizes[i]}`;
    };

    /**
     * Format a timestamp relative to now (e.g., "5 minutes ago", "2 hours ago")
     */
    const formatRelativeTime = (date: string | Date | null | undefined): string => {
        if (!date) return '-';

        const d = new Date(date);
        if (isNaN(d.getTime())) return '-';

        const now = new Date();
        const diffMs = now.getTime() - d.getTime();
        const diffSec = Math.floor(diffMs / 1000);
        const diffMin = Math.floor(diffSec / 60);
        const diffHour = Math.floor(diffMin / 60);
        const diffDay = Math.floor(diffHour / 24);

        if (diffSec < 60) return tr('format.relative.just_now', 'Just now');
        if (diffMin < 60) {
            return tr('format.relative.minutes_ago', `${diffMin} minute${diffMin === 1 ? '' : 's'} ago`, { count: diffMin }, diffMin);
        }
        if (diffHour < 24) {
            return tr('format.relative.hours_ago', `${diffHour} hour${diffHour === 1 ? '' : 's'} ago`, { count: diffHour }, diffHour);
        }
        if (diffDay < 7) {
            return tr('format.relative.days_ago', `${diffDay} day${diffDay === 1 ? '' : 's'} ago`, { count: diffDay }, diffDay);
        }

        return formatDate(date, 'short');
    };

    /**
     * Get today's date as ISO string (YYYY-MM-DD) for form defaults
     */
    const todayAsISODate = (): string => {
        const d = new Date();
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    return {
        formatMoney,
        formatCurrency,
        formatDate,
        formatDateTime,
        formatRelativeDate,
        formatRelativeTime,
        formatPercent,
        formatNumber,
        formatFileSize,
        todayAsISODate
    };
}
