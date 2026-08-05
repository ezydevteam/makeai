import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'
import fs from 'node:fs'
import { fileURLToPath, URL } from 'node:url'

// ─── Dev-server HTTPS ────────────────────────────────────────────────
// The app is served by Laragon over https://makeai.test, so the Vite dev
// server must share that origin/scheme — otherwise the browser blocks its
// assets (http on an IP host = mixed content) and HMR fails.
//
// Reuses Laragon's shared cert (covers *.test incl. makeai.test). Guarded by
// existsSync so any machine/CI without the cert falls back to plain http and
// `vite build` (which ignores `server`) is never affected. Override paths/host
// with VITE_DEV_SSL_KEY / VITE_DEV_SSL_CERT / VITE_DEV_HOST.
const devHost = process.env.VITE_DEV_HOST ?? 'makeai.test'
const sslKeyPath = process.env.VITE_DEV_SSL_KEY ?? 'D:/laragon/etc/ssl/laragon.key'
const sslCertPath = process.env.VITE_DEV_SSL_CERT ?? 'D:/laragon/etc/ssl/laragon.crt'
const hasDevCerts = fs.existsSync(sslKeyPath) && fs.existsSync(sslCertPath)

const httpsServer = hasDevCerts
    ? {
          host: devHost,
          https: {
              key: fs.readFileSync(sslKeyPath),
              cert: fs.readFileSync(sslCertPath),
          },
          hmr: { host: devHost },
      }
    : {}

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
        // Explicit filename, not `true`: the default writes the manifest to
        // public/build/.vite/manifest.json, but Laravel's Vite helper reads
        // public/build/manifest.json. Local dev never noticed (it runs off the
        // dev server via `hot`), but every test that renders a Blade view with
        // @vite failed with ViteManifestNotFoundException.
        manifest: 'manifest.json',
        outDir: 'public/build',
        chunkSizeWarningLimit: 1000, // Increase limit to 1MB to suppress expected warnings for large admin components
        rollupOptions: {
            // @vueuse/core 14 ships two misplaced PURE annotations in its prebuilt
            // dist — one on its own line above the statement it means to mark, one in
            // front of an object literal (they are only meaningful before a call or
            // `new`). Rolldown reports both as [INVALID_ANNOTATION] on every build.
            // Nothing here can fix a dependency's published bundle, and the only
            // consequence is that those two expressions miss out on tree-shaking, so
            // the warnings are pure noise that buries real ones. Safe to silence
            // project-wide: no file we author contains a PURE annotation. Drop this
            // once @vueuse/core corrects its build.
            checks: { invalidAnnotation: false },
        },
    },
    server: {
        ...httpsServer,
        fs: {
            allow: [
                '.',
                'addons',
            ],
        },
    },
})
