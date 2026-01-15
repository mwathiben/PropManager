import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

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
