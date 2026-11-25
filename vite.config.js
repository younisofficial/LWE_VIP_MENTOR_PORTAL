import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            // app.js = the portal (Vue). site.css = public mentor pages (plain HTML, fast for ads and Google).
            input: ['resources/js/app.js', 'resources/css/site.css'],
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
    server: {
        // Same address as "php artisan serve" (avoids the [::1] address on Windows).
        host: '127.0.0.1',
    },
    optimizeDeps: {
        // The Zoom SDK is only loaded on the class page. Pre-bundle it when "npm run dev" starts,
        // otherwise Vite rebuilds it on first use and the class page fails to load it.
        include: ['@zoom/meetingsdk/embedded'],
    },
});
