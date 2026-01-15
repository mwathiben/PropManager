import { ref, computed, type Ref, type ComputedRef } from 'vue';
import { useIntersectionObserver } from '@vueuse/core';
import { router } from '@inertiajs/vue3';

export interface UseInfiniteScrollOptions {
    /** Inertia route name to fetch more data */
    routeName: string;
    /** The key in props containing the paginated data */
    dataKey: string;
    /** Current filters to preserve on load */
    filters?: Record<string, unknown>;
    /** Root margin for intersection observer */
    rootMargin?: string;
    /** Threshold for intersection observer */
    threshold?: number;
}

export interface CursorPaginatedResponse<T> {
    data: T[];
    next_cursor: string | null;
    prev_cursor: string | null;
    per_page: number;
}

export interface UseInfiniteScrollReturn<T> {
    /** All loaded items */
    items: Ref<T[]>;
    /** Whether more items are being loaded */
    isLoading: Ref<boolean>;
    /** Whether there are more items to load */
    hasMore: ComputedRef<boolean>;
    /** Reference to attach to sentinel element */
    sentinelRef: Ref<HTMLElement | null>;
    /** Manually trigger loading more items */
    loadMore: () => void;
    /** Reset and reload from beginning */
    reset: () => void;
    /** Current cursor position */
    cursor: Ref<string | null>;
}

export function useInfiniteScroll<T>(
    initialData: CursorPaginatedResponse<T>,
    options: UseInfiniteScrollOptions
): UseInfiniteScrollReturn<T> {
    const items = ref<T[]>([...initialData.data]) as Ref<T[]>;
    const cursor = ref<string | null>(initialData.next_cursor);
    const isLoading = ref(false);
    const sentinelRef = ref<HTMLElement | null>(null);

    const hasMore = computed(() => cursor.value !== null);

    const loadMore = () => {
        if (isLoading.value || !hasMore.value) return;

        isLoading.value = true;

        const params = {
            ...options.filters,
            cursor: cursor.value,
        };

        router.get(
            route(options.routeName, params),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: [options.dataKey],
                onSuccess: (page) => {
                    const newData = page.props[options.dataKey] as CursorPaginatedResponse<T>;
                    items.value = [...items.value, ...newData.data];
                    cursor.value = newData.next_cursor;
                    isLoading.value = false;
                },
                onError: () => {
                    isLoading.value = false;
                },
            }
        );
    };

    const reset = () => {
        items.value = [...initialData.data];
        cursor.value = initialData.next_cursor;
        isLoading.value = false;
    };

    useIntersectionObserver(
        sentinelRef,
        ([{ isIntersecting }]) => {
            if (isIntersecting && hasMore.value && !isLoading.value) {
                loadMore();
            }
        },
        {
            rootMargin: options.rootMargin ?? '100px',
            threshold: options.threshold ?? 0,
        }
    );

    return {
        items,
        isLoading,
        hasMore,
        sentinelRef,
        loadMore,
        reset,
        cursor,
    };
}
