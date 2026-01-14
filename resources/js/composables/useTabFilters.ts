import { ref, computed, type Ref, type ComputedRef } from 'vue';
import { router } from '@inertiajs/vue3';

declare function route(name: string): string;

// Filter configuration types
export interface DateRangeFilterConfig {
    type: 'dateRange';
    fromKey?: string;
    toKey?: string;
}

export interface FlatDateRangeFilterConfig {
    type: 'flatDateRange';
    fromKey?: string;
    toKey?: string;
    fromField?: string;
    toField?: string;
}

export interface StandardFilterConfig {
    type?: 'string' | 'number';
    default: unknown;
    urlKey?: string;
}

export type FilterConfigItem = DateRangeFilterConfig | FlatDateRangeFilterConfig | StandardFilterConfig;

export interface FilterConfig {
    [key: string]: FilterConfigItem;
}

export interface DateRange {
    from: string | null;
    to: string | null;
}

export type FilterValue = string | number | null | DateRange | undefined;

export interface LocalFilters {
    [key: string]: FilterValue;
}

export interface UseTabFiltersOptions {
    routeName: string;
    propsFilters?: Record<string, unknown>;
    filterConfig?: FilterConfig;
}

export interface UseTabFiltersReturn {
    localFilters: Ref<LocalFilters>;
    applyFilters: () => void;
    clearFilters: () => void;
    hasActiveFilters: ComputedRef<boolean>;
    getExportParams: (format: string) => URLSearchParams;
    setFilter: (key: string, value: FilterValue) => void;
}

/**
 * Initialize filters from server props using config defaults.
 * Handles snake_case props → camelCase local state conversion.
 */
function initializeFilters(
    propsFilters: Record<string, unknown>,
    filterConfig: FilterConfig
): LocalFilters {
    const result: LocalFilters = {};

    for (const [key, config] of Object.entries(filterConfig)) {
        if (config.type === 'dateRange') {
            // Handle nested dateRange object
            const dateRangeConfig = config as DateRangeFilterConfig;
            const fromKey = dateRangeConfig.fromKey || 'date_from';
            const toKey = dateRangeConfig.toKey || 'date_to';
            result[key] = {
                from: (propsFilters[fromKey] as string) || null,
                to: (propsFilters[toKey] as string) || null,
            };
        } else if (config.type === 'flatDateRange') {
            // Handle flat date fields (like ExpensesTab uses)
            const flatConfig = config as FlatDateRangeFilterConfig;
            const fromKey = flatConfig.fromKey || 'date_from';
            const toKey = flatConfig.toKey || 'date_to';
            result[flatConfig.fromField || 'dateFrom'] = (propsFilters[fromKey] as string) || null;
            result[flatConfig.toField || 'dateTo'] = (propsFilters[toKey] as string) || null;
        } else {
            // Standard field with optional URL key mapping
            const stdConfig = config as StandardFilterConfig;
            const urlKey = stdConfig.urlKey || key;
            result[key] = (propsFilters[urlKey] as FilterValue) ?? stdConfig.default ?? '';
        }
    }

    return result;
}

/**
 * Get default/reset values for all filters.
 */
function getDefaultFilters(filterConfig: FilterConfig): LocalFilters {
    const result: LocalFilters = {};

    for (const [key, config] of Object.entries(filterConfig)) {
        if (config.type === 'dateRange') {
            result[key] = { from: null, to: null };
        } else if (config.type === 'flatDateRange') {
            const flatConfig = config as FlatDateRangeFilterConfig;
            result[flatConfig.fromField || 'dateFrom'] = null;
            result[flatConfig.toField || 'dateTo'] = null;
        } else {
            const stdConfig = config as StandardFilterConfig;
            result[key] = stdConfig.default ?? '';
        }
    }

    return result;
}

/**
 * Convert local camelCase filters to snake_case URL params.
 * Empty values become undefined (not included in URL).
 */
function buildUrlParams(
    filters: LocalFilters,
    filterConfig: FilterConfig
): Record<string, string | undefined> {
    const params: Record<string, string | undefined> = {};

    for (const [key, config] of Object.entries(filterConfig)) {
        if (config.type === 'dateRange') {
            // Expand nested dateRange to flat URL params
            const dateRangeConfig = config as DateRangeFilterConfig;
            const dateRange = filters[key] as DateRange | undefined;
            const fromKey = dateRangeConfig.fromKey || 'date_from';
            const toKey = dateRangeConfig.toKey || 'date_to';
            params[fromKey] = dateRange?.from || undefined;
            params[toKey] = dateRange?.to || undefined;
        } else if (config.type === 'flatDateRange') {
            // Flat date fields map directly
            const flatConfig = config as FlatDateRangeFilterConfig;
            const fromField = flatConfig.fromField || 'dateFrom';
            const toField = flatConfig.toField || 'dateTo';
            const fromKey = flatConfig.fromKey || 'date_from';
            const toKey = flatConfig.toKey || 'date_to';
            params[fromKey] = (filters[fromField] as string) || undefined;
            params[toKey] = (filters[toField] as string) || undefined;
        } else {
            // Standard field with URL key mapping
            const stdConfig = config as StandardFilterConfig;
            const urlKey = stdConfig.urlKey || key;
            const value = filters[key];
            params[urlKey] = (value !== '' && value !== null && value !== undefined)
                ? String(value)
                : undefined;
        }
    }

    return params;
}

/**
 * Composable for managing Finance Hub tab filters with Inertia router integration.
 * Handles snake_case ↔ camelCase conversion and URL synchronization.
 */
export function useTabFilters(options: UseTabFiltersOptions): UseTabFiltersReturn {
    const { routeName, propsFilters = {}, filterConfig = {} } = options;

    // Initialize local filters from props with snake_case → camelCase conversion
    const localFilters = ref<LocalFilters>(initializeFilters(propsFilters, filterConfig));

    /**
     * Apply filters via Inertia router.
     * Converts camelCase filter values to snake_case URL params.
     */
    const applyFilters = (): void => {
        const params = buildUrlParams(localFilters.value, filterConfig);
        router.get(route(routeName), params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    /**
     * Clear all filters and navigate to clean URL.
     */
    const clearFilters = (): void => {
        localFilters.value = getDefaultFilters(filterConfig);
        router.get(route(routeName), {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    /**
     * Check if any filters are currently active.
     */
    const hasActiveFilters = computed((): boolean => {
        return Object.entries(localFilters.value).some(([_, value]) => {
            if (value === null || value === undefined || value === '') return false;
            if (typeof value === 'object' && value !== null) {
                // Handle dateRange objects
                return Object.values(value).some(v => v !== null && v !== undefined && v !== '');
            }
            return true;
        });
    });

    /**
     * Build URLSearchParams for export endpoints.
     */
    const getExportParams = (format: string): URLSearchParams => {
        const params = new URLSearchParams();
        params.append('format', format);

        const urlParams = buildUrlParams(localFilters.value, filterConfig);
        Object.entries(urlParams).forEach(([key, value]) => {
            if (value !== undefined) {
                params.append(key, value);
            }
        });

        return params;
    };

    /**
     * Update a single filter value.
     */
    const setFilter = (key: string, value: FilterValue): void => {
        localFilters.value[key] = value;
    };

    return {
        localFilters,
        applyFilters,
        clearFilters,
        hasActiveFilters,
        getExportParams,
        setFilter,
    };
}
