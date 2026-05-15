import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
        // Phase-26 PWA-SHELL-1/2/3: Workbox-managed service worker.
        // injectManifest mode preserves our custom push handlers
        // (resources/js/sw.ts) while letting the plugin inject the
        // precache manifest at build time so cache versioning rides
        // the Vite asset hash. We register manually in app.js so the
        // SW path stays /sw.js (served by Laravel route in
        // routes/web.php with Service-Worker-Allowed: / so it has
        // root scope despite living in public/build/).
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'sw.ts',
            registerType: 'autoUpdate',
            injectRegister: null,
            manifest: false,
            injectManifest: {
                globPatterns: ['**/*.{js,css,woff2}'],
                maximumFileSizeToCacheInBytes: 5 * 1024 * 1024,
            },
            devOptions: {
                enabled: false,
            },
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    'vue-core': ['vue', '@inertiajs/vue3', 'pinia'],
                    'vendor': ['axios', '@vueuse/core', 'ziggy-js'],
                    'leaflet': ['leaflet'],
                    'marked': ['marked'],
                },
            },
        },
    },
});
