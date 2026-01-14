import { ref, type Ref } from 'vue';
import { refDebounced } from '@vueuse/core';
import { router } from '@inertiajs/vue3';

/**
 * Composable for debounced search functionality
 * Prevents excessive API calls on rapid input
 */
export interface UseDebouncedSearchReturn {
    search: Ref<string>;
    debouncedSearch: Readonly<Ref<string>>;
    clear: () => void;
    setSearch: (value: string) => void;
}

export function useDebouncedSearch(
    initialValue: string = '',
    delay: number = 300
): UseDebouncedSearchReturn {
    const search = ref(initialValue);
    const debouncedSearch = refDebounced(search, delay);

    /**
     * Clear the search value
     */
    const clear = (): void => {
        search.value = '';
    };

    /**
     * Set the search value directly (bypasses debounce)
     */
    const setSearch = (value: string): void => {
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
 */
export interface UseFiltersReturn {
    filters: Ref<Record<string, string | null | undefined>>;
    applyFilters: (routerInstance: typeof router, routeFn: (name: string) => string) => void;
    clearFilters: (routerInstance: typeof router, routeFn: (name: string) => string) => void;
    hasActiveFilters: () => boolean;
}

export function useFilters(
    initialFilters: Record<string, string | null | undefined> = {},
    routeName: string
): UseFiltersReturn {
    const filters = ref({ ...initialFilters });

    /**
     * Apply filters by navigating with Inertia
     */
    const applyFilters = (
        routerInstance: typeof router,
        routeFn: (name: string) => string
    ): void => {
        // Remove empty values
        const cleanFilters = Object.fromEntries(
            Object.entries(filters.value).filter(([_, v]) => v !== '' && v !== null && v !== undefined)
        );

        routerInstance.get(routeFn(routeName), cleanFilters, {
            preserveState: true,
            replace: true
        });
    };

    /**
     * Clear all filters
     */
    const clearFilters = (
        routerInstance: typeof router,
        routeFn: (name: string) => string
    ): void => {
        Object.keys(filters.value).forEach(key => {
            filters.value[key] = '';
        });
        applyFilters(routerInstance, routeFn);
    };

    /**
     * Check if any filters are active
     */
    const hasActiveFilters = (): boolean => {
        return Object.values(filters.value).some(v => v !== '' && v !== null && v !== undefined);
    };

    return {
        filters,
        applyFilters,
        clearFilters,
        hasActiveFilters
    };
}
