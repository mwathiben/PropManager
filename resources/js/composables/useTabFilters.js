import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Composable for managing Finance Hub tab filters with Inertia router integration.
 * Handles snake_case ↔ camelCase conversion and URL synchronization.
 *
 * @param {object} options
 * @param {string} options.routeName - Inertia route name (e.g., 'finances.invoices')
 * @param {object} options.propsFilters - Server-provided filters from props (snake_case)
 * @param {object} options.filterConfig - Filter field configuration
 */
export function useTabFilters(options) {
    const { routeName, propsFilters = {}, filterConfig = {} } = options;

    // Initialize local filters from props with snake_case → camelCase conversion
    const localFilters = ref(initializeFilters(propsFilters, filterConfig));

    /**
     * Apply filters via Inertia router.
     * Converts camelCase filter values to snake_case URL params.
     */
    const applyFilters = () => {
        const params = buildUrlParams(localFilters.value, filterConfig);
        router.get(route(routeName), params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    /**
     * Clear all filters and navigate to clean URL.
     */
    const clearFilters = () => {
        localFilters.value = getDefaultFilters(filterConfig);
        router.get(route(routeName), {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    /**
     * Check if any filters are currently active.
     */
    const hasActiveFilters = computed(() => {
        return Object.entries(localFilters.value).some(([key, value]) => {
            if (value === null || value === undefined || value === '') return false;
            if (typeof value === 'object') {
                // Handle dateRange objects
                return Object.values(value).some(v => v !== null && v !== undefined && v !== '');
            }
            return true;
        });
    });

    /**
     * Build URLSearchParams for export endpoints.
     * @param {string} format - Export format (xlsx, pdf, csv)
     * @returns {URLSearchParams}
     */
    const getExportParams = (format) => {
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
     * @param {string} key - Filter key in camelCase
     * @param {any} value - New value
     */
    const setFilter = (key, value) => {
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

/**
 * Initialize filters from server props using config defaults.
 * Handles snake_case props → camelCase local state conversion.
 */
function initializeFilters(propsFilters, filterConfig) {
    const result = {};

    for (const [key, config] of Object.entries(filterConfig)) {
        if (config.type === 'dateRange') {
            // Handle nested dateRange object
            const fromKey = config.fromKey || 'date_from';
            const toKey = config.toKey || 'date_to';
            result[key] = {
                from: propsFilters[fromKey] || null,
                to: propsFilters[toKey] || null,
            };
        } else if (config.type === 'flatDateRange') {
            // Handle flat date fields (like ExpensesTab uses)
            const fromKey = config.fromKey || 'date_from';
            const toKey = config.toKey || 'date_to';
            result[config.fromField || 'dateFrom'] = propsFilters[fromKey] || null;
            result[config.toField || 'dateTo'] = propsFilters[toKey] || null;
        } else {
            // Standard field with optional URL key mapping
            const urlKey = config.urlKey || key;
            result[key] = propsFilters[urlKey] ?? config.default ?? '';
        }
    }

    return result;
}

/**
 * Get default/reset values for all filters.
 */
function getDefaultFilters(filterConfig) {
    const result = {};

    for (const [key, config] of Object.entries(filterConfig)) {
        if (config.type === 'dateRange') {
            result[key] = { from: null, to: null };
        } else if (config.type === 'flatDateRange') {
            result[config.fromField || 'dateFrom'] = null;
            result[config.toField || 'dateTo'] = null;
        } else {
            result[key] = config.default ?? '';
        }
    }

    return result;
}

/**
 * Convert local camelCase filters to snake_case URL params.
 * Empty values become undefined (not included in URL).
 */
function buildUrlParams(filters, filterConfig) {
    const params = {};

    for (const [key, config] of Object.entries(filterConfig)) {
        if (config.type === 'dateRange') {
            // Expand nested dateRange to flat URL params
            const dateRange = filters[key];
            const fromKey = config.fromKey || 'date_from';
            const toKey = config.toKey || 'date_to';
            params[fromKey] = dateRange?.from || undefined;
            params[toKey] = dateRange?.to || undefined;
        } else if (config.type === 'flatDateRange') {
            // Flat date fields map directly
            const fromField = config.fromField || 'dateFrom';
            const toField = config.toField || 'dateTo';
            const fromKey = config.fromKey || 'date_from';
            const toKey = config.toKey || 'date_to';
            params[fromKey] = filters[fromField] || undefined;
            params[toKey] = filters[toField] || undefined;
        } else {
            // Standard field with URL key mapping
            const urlKey = config.urlKey || key;
            const value = filters[key];
            params[urlKey] = (value !== '' && value !== null) ? value : undefined;
        }
    }

    return params;
}
