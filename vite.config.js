import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

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
