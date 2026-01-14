/**
 * Composable for formatting currencies, dates, and numbers
 * Replaces 137+ duplicate formatting functions across the codebase
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
}

export function useFormatters(options: FormattersOptions = {}): UseFormattersReturn {
    const config = {
        locale: options.locale || 'en-KE',
        currency: options.currency || 'KES',
        dateLocale: options.dateLocale || 'en-GB',
        ...options
    };

    /**
     * Format a value as currency (KES by default)
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

        if (diffDays < -1) return `${Math.abs(diffDays)} days ago`;
        if (diffDays === -1) return 'Yesterday';
        if (diffDays === 0) return 'Today';
        if (diffDays === 1) return 'Tomorrow';
        return `In ${diffDays} days`;
    };

    /**
     * Format a number as percentage
     */
    const formatPercent = (value: number | null | undefined, decimals: number = 0): string => {
        if (value === null || value === undefined) return '-';
        return `${value.toFixed(decimals)}%`;
    };

    /**
     * Format a number with thousands separator
     */
    const formatNumber = (value: number | null | undefined): string => {
        if (value === null || value === undefined) return '-';
        return new Intl.NumberFormat(config.locale).format(value);
    };

    /**
     * Format file size in human-readable format
     */
    const formatFileSize = (bytes: number | null | undefined): string => {
        if (!bytes) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
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

        if (diffSec < 60) return 'Just now';
        if (diffMin < 60) return `${diffMin} minute${diffMin === 1 ? '' : 's'} ago`;
        if (diffHour < 24) return `${diffHour} hour${diffHour === 1 ? '' : 's'} ago`;
        if (diffDay < 7) return `${diffDay} day${diffDay === 1 ? '' : 's'} ago`;

        return formatDate(date, 'short');
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
        formatFileSize
    };
}
