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
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                if (import.meta.env.DEV) {
                    // eslint-disable-next-line no-console
                    console.log('Service Worker registered:', registration.scope);
                }
            })
            .catch(error => {
                if (import.meta.env.DEV) {
                    // eslint-disable-next-line no-console
                    console.error('Service Worker registration failed:', error);
                }
            });

        navigator.serviceWorker.addEventListener('message', (event) => {
            const data = event.data;
            if (data && data.type === 'BG_SYNC_DRAINED' && typeof data.queue === 'string') {
                import('@/stores/queuedOps').then(({ useQueuedOpsStore }) => {
                    useQueuedOpsStore().drain(data.queue);
                });
            }
        });
    });
}
