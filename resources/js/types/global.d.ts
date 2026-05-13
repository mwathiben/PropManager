import { route as ziggyRoute } from 'ziggy-js';

declare global {
    var route: typeof ziggyRoute;
}

declare module 'vue' {
    interface ComponentCustomProperties {
        route: typeof ziggyRoute;
    }
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginationMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    path: string;
    per_page: number;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

export interface PaginatedResponse<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: PaginationMeta;
}

/**
 * Phase-20 FRONT-UX-1: cursor-paginator response shape (Laravel
 * ->cursorPaginate()). Unlike PaginatedResponse, cursor has no
 * from/to/total/last_page counters — only next/prev. See
 * CursorPagination.vue + docs/runbooks/frontend-authz-and-ux.md.
 */
export interface CursorPaginatedResponse<T> {
    data: T[];
    per_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    path?: string;
}
