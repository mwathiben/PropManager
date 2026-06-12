import axios from 'axios';
import './echo';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Inertia asset-version guard.
 *
 * When the server's compiled-asset version no longer matches the version
 * this tab loaded with (e.g. the assets were rebuilt while a page stayed
 * open), Inertia answers a POST/PUT/DELETE with `409 + X-Inertia-Location`
 * and silently hard-reloads. To the user a form submit — the Architect's
 * rent / add-unit actions, say — just appears to do nothing.
 *
 * Instead, surface a clear "a new version is available, refreshing" notice
 * and then reload to the current build. We swallow Inertia's own reload
 * (never-settling promise) so it can't race ours, and key off the
 * `X-Inertia-Location` header so genuine application 409s (data conflicts,
 * which never carry that header) fall through untouched.
 */
let versionReloadStarted = false;

window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        const location =
            error?.response?.status === 409
                ? error.response.headers?.['x-inertia-location']
                : null;

        if (location && ! versionReloadStarted) {
            versionReloadStarted = true;
            showAppUpdatedNotice();
            window.setTimeout(() => {
                window.location.href = location;
            }, 1500);

            // Suppress Inertia's own version-mismatch reload — ours wins.
            return new Promise(() => {});
        }

        return Promise.reject(error);
    },
);

function showAppUpdatedNotice() {
    if (document.getElementById('pm-app-updated-notice')) {
        return;
    }

    const bar = document.createElement('div');
    bar.id = 'pm-app-updated-notice';
    bar.setAttribute('role', 'status');
    bar.setAttribute('aria-live', 'polite');
    bar.style.cssText = [
        'position:fixed',
        'inset-block-start:0',
        'inset-inline:0',
        'z-index:2147483647',
        'display:flex',
        'align-items:center',
        'justify-content:center',
        'gap:0.5rem',
        'padding:0.75rem 1rem',
        'background:#4f46e5',
        'color:#fff',
        'font:500 0.875rem/1.25rem ui-sans-serif,system-ui,sans-serif',
        'box-shadow:0 1px 3px rgba(0,0,0,0.25)',
    ].join(';');
    bar.textContent =
        'A new version of PropManager is available — refreshing…';

    document.body.appendChild(bar);
}
