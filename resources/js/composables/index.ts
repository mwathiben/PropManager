/**
 * Barrel export for all composables
 * Import from '@/composables' for cleaner imports
 */

export { useFormatters } from './useFormatters';
export type { UseFormattersReturn, FormattersOptions, FormatMoneyOptions, DateFormat } from './useFormatters';

export { useStatusColors } from './useStatusColors';
export type { UseStatusColorsReturn } from './useStatusColors';

export { useDebouncedSearch, useFilters } from './useDebouncedSearch';
export type { UseDebouncedSearchReturn, UseFiltersReturn } from './useDebouncedSearch';

export { useAuth } from './useAuth';
export type { UseAuthReturn } from './useAuth';

export { usePushNotifications } from './usePushNotifications';
export type { UsePushNotificationsReturn } from './usePushNotifications';

export { useWebPush } from './useWebPush';
export type { UseWebPushReturn } from './useWebPush';

export { usePayments } from './usePayments';
export type { UsePaymentsReturn } from './usePayments';

export { useTabFilters } from './useTabFilters';
export type {
    UseTabFiltersReturn,
    UseTabFiltersOptions,
    FilterConfig,
    FilterConfigItem,
    DateRangeFilterConfig,
    FlatDateRangeFilterConfig,
    StandardFilterConfig,
    DateRange,
    FilterValue,
    LocalFilters
} from './useTabFilters';

export { useInfiniteScroll } from './useInfiniteScroll';
export type {
    UseInfiniteScrollOptions,
    UseInfiniteScrollReturn,
    CursorPaginatedResponse
} from './useInfiniteScroll';

export { useSWR, clearSWRCache } from './useSWR';
export type {
    UseSWROptions,
    UseSWRReturn
} from './useSWR';

export { useEcho } from './useEcho';
export type {
    UseEchoOptions,
    UseEchoReturn
} from './useEcho';

export { useErrorHandler } from './useErrorHandler';
export type { UseErrorHandlerReturn, ErrorContext } from './useErrorHandler';

export { useCurrency } from './useCurrency';
export type { UseCurrencyReturn } from './useCurrency';

export { usePaymentForm } from './usePaymentForm';
export type { UsePaymentFormReturn } from './usePaymentForm';

export { useDashboardStats } from './useDashboardStats';
export type { UseDashboardStatsReturn, DashboardStatsData } from './useDashboardStats';

export { useRtlAware } from './useRtlAware';
