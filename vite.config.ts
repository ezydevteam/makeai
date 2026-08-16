import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'
import fs from 'node:fs'
import { fileURLToPath, URL } from 'node:url'

// ─── Dev-server HTTPS ────────────────────────────────────────────────
// When the app is served over https locally (Laragon, Valet, Herd), the Vite dev
// server must share that origin/scheme — otherwise the browser blocks its assets
// as mixed content and HMR fails. Point it at your local cert with three keys in
// .env (NOT .env.example — these are per-machine and must never ship):
//
//   VITE_DEV_HOST=myapp.test
//   VITE_DEV_SSL_KEY=/path/to/cert.key
//   VITE_DEV_SSL_CERT=/path/to/cert.crt
//
// Absent or pointing at a file that is not there, the dev server falls back to
// plain http on localhost. `vite build` ignores `server` entirely, so a buyer
// rebuilding the frontend is unaffected either way.
//
// Read via loadEnv rather than hardcoded: defaults baked in here are one
// developer's machine layout, and this file ships to buyers.
export default defineConfig(({ mode }) => {
const env = loadEnv(mode, process.cwd(), '')

const devHost = env.VITE_DEV_HOST || 'localhost'
const sslKeyPath = env.VITE_DEV_SSL_KEY || ''
const sslCertPath = env.VITE_DEV_SSL_CERT || ''
const hasDevCerts = sslKeyPath !== '' && sslCertPath !== ''
    && fs.existsSync(sslKeyPath) && fs.existsSync(sslCertPath)

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

return {
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
}
})
