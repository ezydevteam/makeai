import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
    base: '/build/',
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
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
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            '@themes': fileURLToPath(new URL('./resources/themes', import.meta.url))
        },
    },
    build: {
        manifest: true,
        outDir: 'public/build',
        chunkSizeWarningLimit: 1000, // Increase limit to 1MB to suppress expected warnings for large admin components
    },
    server: {
        fs: {
            allow: [
                '.',
                'addons',
            ],
        },
    },
})
