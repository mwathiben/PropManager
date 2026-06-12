import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import { createI18n } from 'vue-i18n';
import { ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * Phase-21 DEFER-AUTHZ-4: client-side route guard. URL-prefix → required
 * ability table. The `before` hook pre-checks the shared abilities map
 * (props.auth.user.abilities) and redirects to /403 client-side, so a
 * user who types /admin/users in the URL bar sees the 403.vue UX without
 * issuing a request the server would reject anyway. This is UX defence
 * in depth — the server-side Gates (Phase-15/18/19 coverage matrix) stay
 * the real authorization boundary.
 */
const ROUTE_ABILITY_MAP = [
    { prefix: '/admin', ability: 'access-admin' },
    { prefix: '/audit-logs', ability: 'view-audit-logs' },
];

let currentAbilities = {};

function requiredAbilityFor(url) {
    let path;
    try {
        path = new URL(url, window.location.origin).pathname;
    } catch {
        return null;
    }
    const match = ROUTE_ABILITY_MAP.find(
        (entry) => path === entry.prefix || path.startsWith(`${entry.prefix}/`),
    );
    return match ? match.ability : null;
}

router.on('before', (event) => {
    const visit = event.detail.visit;
    if (visit.method !== 'get') {
        return;
    }
    const ability = requiredAbilityFor(visit.url);
    if (ability && currentAbilities[ability] !== true) {
        event.preventDefault();
        router.visit('/403');
    }
});

router.on('success', (event) => {
    currentAbilities = event.detail.page?.props?.auth?.user?.abilities ?? {};
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        currentAbilities =
            props.initialPage?.props?.auth?.user?.abilities ?? {};
        const pinia = createPinia();

        /**
         * Phase-24 I18N-FRONT-1: vue-i18n, hydrated from the
         * Inertia-shared `locale` + `i18n` props (HandleInertiaRequests
         * I18N-INFRA-3). Locale + messages come from the server so the
         * first paint is already correct — no flash of untranslated
         * content. The locale only changes via a full Inertia visit
         * after the locale-switch endpoint, so a `success` listener
         * keeps the i18n instance in sync with the new page props.
         */
        const initialLocale = props.initialPage?.props?.locale ?? 'en';
        const i18n = createI18n({
            legacy: false,
            locale: initialLocale,
            fallbackLocale: 'en',
            messages: {
                [initialLocale]: props.initialPage?.props?.i18n ?? {},
            },
        });

        router.on('success', (event) => {
            const pageProps = event.detail.page?.props;
            const nextLocale = pageProps?.locale;
            if (nextLocale && nextLocale !== i18n.global.locale.value) {
                i18n.global.setLocaleMessage(nextLocale, pageProps.i18n ?? {});
                i18n.global.locale.value = nextLocale;
            }
        });

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .use(i18n)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// Register Service Worker.
// Phase-26 PWA-SHELL-1: served by Laravel route /sw.js with
//   Service-Worker-Allowed: / so the SW gets root scope despite living
//   at public/build/sw.js. Registered at window.load so we don't
//   compete with first-paint.
// Phase-26 PWA-NETWORK-1+3: when the SW posts BG_SYNC_DRAINED after
//   replaying queued POSTs, route that to the queuedOps Pinia store
//   so the QueuedOpsTray clears its badge. The handler is attached
//   ONCE at register time — multiple add/remove cycles would leak.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        // Prompt-to-update: reload exactly once, and only after the user
        // accepts the update, so the first-install controllerchange (when
        // the SW first claims the page) never triggers a reload loop.
        let swUpdateAccepted = false;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (swUpdateAccepted) {
                window.location.reload();
            }
        });

        const showSwUpdatePrompt = (worker) => {
            if (! worker || document.getElementById('pm-sw-update-prompt')) {
                return;
            }
            const bar = document.createElement('div');
            bar.id = 'pm-sw-update-prompt';
            bar.setAttribute('role', 'status');
            bar.setAttribute('aria-live', 'polite');
            bar.style.cssText = 'position:fixed;inset-block-end:1rem;inset-inline:0;z-index:2147483646;display:flex;justify-content:center;padding:0 1rem;pointer-events:none';

            const pill = document.createElement('div');
            pill.style.cssText = 'pointer-events:auto;display:flex;align-items:center;gap:0.75rem;padding:0.625rem 0.625rem 0.625rem 1rem;background:#1f2937;color:#fff;border-radius:9999px;box-shadow:0 10px 25px rgba(0,0,0,0.25);font:500 0.875rem/1.25rem ui-sans-serif,system-ui,sans-serif';
            pill.append('A new version of PropManager is available.');

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = 'Refresh';
            btn.style.cssText = 'cursor:pointer;border:0;border-radius:9999px;padding:0.375rem 0.875rem;background:#4f46e5;color:#fff;font:600 0.875rem/1.25rem ui-sans-serif,system-ui,sans-serif';
            btn.addEventListener('click', () => {
                btn.disabled = true;
                btn.textContent = 'Refreshing…';
                swUpdateAccepted = true;
                worker.postMessage({ type: 'SKIP_WAITING' });
            });

            pill.appendChild(btn);
            bar.appendChild(pill);
            document.body.appendChild(bar);
        };

        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                if (import.meta.env.DEV) {
                    // eslint-disable-next-line no-console
                    console.log('Service Worker registered:', registration.scope);
                }

                // An updated SW waits (sw.ts no longer auto-skipWaiting) —
                // surface the refresh prompt so users pick up the new build
                // instead of running stale assets until every tab closes.
                if (registration.waiting && navigator.serviceWorker.controller) {
                    showSwUpdatePrompt(registration.waiting);
                }
                registration.addEventListener('updatefound', () => {
                    const installing = registration.installing;
                    installing?.addEventListener('statechange', () => {
                        if (installing.state === 'installed' && navigator.serviceWorker.controller) {
                            showSwUpdatePrompt(installing);
                        }
                    });
                });
            })
            .catch(error => {
                if (import.meta.env.DEV) {
                    // eslint-disable-next-line no-console
                    console.error('Service Worker registration failed:', error);
                }
            });

        // Phase-64 TELEMETRY-WIRE-2: wire client-side accumulator +
        // flush-on-hide so the 3 PWA gauges reach the server.
        import('@/lib/pwaTelemetry').then(({ registerPwaTelemetry }) => {
            registerPwaTelemetry();
        });

        navigator.serviceWorker.addEventListener('message', (event) => {
            const data = event.data;
            // Phase-64 OFFLINE-MOUNTS-1: 409 surfacing from the replay
            // loop into the global ConflictDialog via writeConflictBus.
            if (data && data.type === 'WRITE_CONFLICT_409') {
                import('@/lib/writeConflictBus').then(({ emit }) => {
                    emit({
                        queue: data.queue,
                        url: data.url,
                        current: data.payload?.current,
                        incoming: data.payload?.incoming,
                        diff: data.payload?.diff,
                    });
                });

                return;
            }
            if (data && data.type === 'BG_SYNC_DRAINED' && typeof data.queue === 'string') {
                import('@/stores/queuedOps').then(({ useQueuedOpsStore }) => {
                    useQueuedOpsStore().drain(data.queue);
                });
                // Phase-62 OFFLINE-WRITES-3: walk the persistent queue
                // looking for ops whose route matches the drained
                // queue. The SW signalled the queue is empty, so each
                // pending entry it had must have succeeded.
                import('@/lib/offlineWriteQueue').then(({ listPending, recordReplaySuccess }) => {
                    listPending().then((pending) => {
                        for (const entry of pending) {
                            void recordReplaySuccess(entry.id);
                        }
                    });
                });
                // Phase-62 CACHE-STRATEGY-3: ask the SW to invalidate
                // the matching SWR cache so list pages auto-revalidate
                // on next focus. Queue name maps to route family by
                // stripping the 'pm-offline-' prefix (or 'pm-invoice-
                // queue' -> 'invoices').
                const family =
                    data.queue === 'pm-invoice-queue'
                        ? 'invoices'
                        : data.queue.replace(/^pm-offline-/, '');
                if (navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage({
                        type: 'CACHE_BUST',
                        routeFamily: family,
                    });
                }
            }
        });

        // Phase-62 OFFLINE-WRITES-3: hydrate the in-memory Pinia store
        // from IDB so a tab reopened after a crash still shows the
        // pending writes from the previous session.
        import('@/lib/offlineWriteQueue').then(({ listPending, listDeadLetter }) => {
            Promise.all([listPending(), listDeadLetter()]).then(([pending, dead]) => {
                if (pending.length === 0 && dead.length === 0) return;
                import('@/stores/queuedOps').then(({ useQueuedOpsStore }) => {
                    const store = useQueuedOpsStore();
                    for (const entry of pending) {
                        store.add({
                            id: entry.id,
                            queue: `pm-offline-${entry.routeFamily}`,
                            label: `Pending ${entry.routeFamily}`,
                            routeFamily: entry.routeFamily,
                        });
                    }
                    for (const entry of dead) {
                        const op = store.add({
                            id: entry.id,
                            queue: `pm-offline-${entry.routeFamily}`,
                            label: `Failed ${entry.routeFamily}`,
                            routeFamily: entry.routeFamily,
                        });
                        store.markDeadLetter(op.id, entry.lastError ?? 'Max attempts reached');
                    }
                });
            });
        });
    });
}
