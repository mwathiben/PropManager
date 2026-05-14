import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
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
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// Register Service Worker for Push Notifications
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
    });
}
