import { ref, watch } from 'vue';
import { refDebounced } from '@vueuse/core';

/**
 * Composable for debounced search functionality
 * Prevents excessive API calls on rapid input
 */
export function useDebouncedSearch(initialValue = '', delay = 300) {
    const search = ref(initialValue);
    const debouncedSearch = refDebounced(search, delay);

    /**
     * Clear the search value
     */
    const clear = () => {
        search.value = '';
    };

    /**
     * Set the search value directly (bypasses debounce)
     * @param {string} value - The value to set
     */
    const setSearch = (value) => {
        search.value = value;
    };

    return {
        search,           // Immediate value for input binding (v-model)
        debouncedSearch,  // Debounced value for API calls/filtering
        clear,
        setSearch
    };
}

/**
 * Composable for managing list filters with Inertia router
 * @param {object} initialFilters - Initial filter values from props
 * @param {string} routeName - The route name to navigate to
 */
export function useFilters(initialFilters = {}, routeName) {
    const filters = ref({ ...initialFilters });

    /**
     * Apply filters by navigating with Inertia
     * @param {object} router - Inertia router instance
     * @param {function} route - Ziggy route function
     */
    const applyFilters = (router, route) => {
        // Remove empty values
        const cleanFilters = Object.fromEntries(
            Object.entries(filters.value).filter(([_, v]) => v !== '' && v !== null && v !== undefined)
        );

        router.get(route(routeName), cleanFilters, {
            preserveState: true,
            replace: true
        });
    };

    /**
     * Clear all filters
     * @param {object} router - Inertia router instance
     * @param {function} route - Ziggy route function
     */
    const clearFilters = (router, route) => {
        Object.keys(filters.value).forEach(key => {
            filters.value[key] = '';
        });
        applyFilters(router, route);
    };

    /**
     * Check if any filters are active
     * @returns {boolean}
     */
    const hasActiveFilters = () => {
        return Object.values(filters.value).some(v => v !== '' && v !== null && v !== undefined);
    };

    return {
        filters,
        applyFilters,
        clearFilters,
        hasActiveFilters
    };
}
