<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import { computed, reactive, ref, onBeforeUnmount } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'

defineOptions({ layout: AdminLayout })

declare const route: (name: string, params?: Record<string, unknown>) => string

interface DriverInfo {
    value: string
    label: string
    cloud: boolean
    doc_url?: string
}

interface ProviderState {
    configured_secrets: Record<string, boolean>
    options: Record<string, string>
}

interface MigrationState {
    status: string
    total: number
    processed: number
    failed: number
    message: string
    source: string | null
    target: string | null
}

const props = defineProps<{
    currentDriver: string
    drivers: DriverInfo[]
    providers: Record<string, ProviderState>
    migration: MigrationState
}>()

const { t } = useTranslate()
const toastr = useToastr()

const SECRET_FIELDS = ['access_key', 'secret_key'] as const
const OPTION_FIELDS = ['region', 'bucket', 'endpoint', 'url'] as const

const selectedDriver = ref(props.currentDriver)

const driverOptions = computed(() =>
    props.drivers.map((d) => ({ value: d.value, label: d.label })),
)

const activeDriverInfo = computed(() => props.drivers.find((d) => d.value === selectedDriver.value))
const isCloud = computed(() => activeDriverInfo.value?.cloud === true)

// Editable credential buffers, one per cloud driver, seeded from stored options.
// Secrets start blank — a configured secret is kept server-side unless retyped.
const credentials = reactive<Record<string, Record<string, string>>>({})
for (const d of props.drivers) {
    if (!d.cloud) continue
    const state = props.providers[d.value]
    credentials[d.value] = {
        access_key: '',
        secret_key: '',
        region: state?.options.region ?? '',
        bucket: state?.options.bucket ?? '',
        endpoint: state?.options.endpoint ?? '',
        url: state?.options.url ?? '',
    }
}

const isSecretConfigured = (driver: string, field: string) =>
    props.providers[driver]?.configured_secrets[field] ?? false

const testing = ref(false)
const testResult = ref<{ success: boolean; message?: string; error?: string } | null>(null)

const form = useForm({
    driver: props.currentDriver,
    credentials: {} as Record<string, string>,
})

const buildCredentialPayload = (driver: string): Record<string, string> => {
    const payload: Record<string, string> = {}
    const buf = credentials[driver] ?? {}
    for (const field of SECRET_FIELDS) {
        if (buf[field]) payload[field] = buf[field]
    }
    for (const field of OPTION_FIELDS) {
        payload[field] = buf[field] ?? ''
    }
    return payload
}

const csrf = () =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''

const testConnection = async () => {
    if (!isCloud.value) return
    testing.value = true
    testResult.value = null
    try {
        const res = await fetch(route('admin.storage.settings.test'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ driver: selectedDriver.value, credentials: buildCredentialPayload(selectedDriver.value) }),
        })
        testResult.value = await res.json()
    } catch {
        testResult.value = { success: false, error: t('Network error while testing the connection.') }
    } finally {
        testing.value = false
    }
}

const save = () => {
    form.driver = selectedDriver.value
    form.credentials = isCloud.value ? buildCredentialPayload(selectedDriver.value) : {}
    form.post(route('admin.storage.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            testResult.value = null
        },
    })
}

// ── Migration ────────────────────────────────────────────────────────────────
const migration = reactive<MigrationState>({ ...props.migration })
const migrateSource = ref<string>(props.migration.source ?? 'local')
const defaultTarget = props.migration.target ?? (props.currentDriver !== 'local' ? props.currentDriver : 's3')
const migrateTarget = ref<string>(defaultTarget)

const allDriverOptions = computed(() => props.drivers.map((d) => ({ value: d.value, label: d.label })))

const isMigrating = computed(() => migration.status === 'running')
const migrationPct = computed(() =>
    migration.total > 0 ? Math.round((migration.processed / migration.total) * 100) : 0,
)

let pollTimer: ReturnType<typeof setInterval> | null = null

const stopPolling = () => {
    if (pollTimer) {
        clearInterval(pollTimer)
        pollTimer = null
    }
}

const pollStatus = async () => {
    try {
        const res = await fetch(route('admin.storage.settings.migrate.status'), {
            headers: { Accept: 'application/json' },
        })
        const data: MigrationState = await res.json()
        Object.assign(migration, data)
        if (data.status !== 'running') {
            stopPolling()
            if (data.status === 'completed') toastr.success(data.message || t('Migration complete.'))
            else if (data.status === 'completed_with_errors') toastr.warning(data.message || t('Migration finished with some errors.'))
            else if (data.status === 'failed') toastr.error(data.message || t('Migration failed.'))
            else if (data.status === 'stalled') toastr.warning(data.message || t('Migration stalled — is a queue worker running?'))
        }
    } catch {
        /* transient network hiccup — keep polling */
    }
}

const startPolling = () => {
    stopPolling()
    pollTimer = setInterval(pollStatus, 2000)
}

if (migration.status === 'running') startPolling()
onBeforeUnmount(stopPolling)

const startingMigration = ref(false)
const startMigration = () => {
    if (migrateSource.value === migrateTarget.value) {
        toastr.error(t('Source and destination must be different.'))
        return
    }
    startingMigration.value = true
    router.post(
        route('admin.storage.settings.migrate'),
        { source: migrateSource.value, target: migrateTarget.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                migration.status = 'running'
                migration.message = t('Starting…')
                migration.processed = 0
                migration.total = 0
                migration.failed = 0
                startPolling()
            },
            onFinish: () => {
                startingMigration.value = false
            },
        },
    )
}

const driverLabel = (value: string | null) =>
    props.drivers.find((d) => d.value === value)?.label ?? value ?? '—'

// A migration can be cleared when it is running (e.g. no worker), stalled, or finished
// in a non-clean state — so the admin is never blocked from starting a fresh one.
const canClearMigration = computed(() =>
    ['running', 'stalled', 'failed', 'completed_with_errors'].includes(migration.status),
)

const clearMigration = () => {
    router.post(route('admin.storage.settings.migrate.cancel'), {}, {
        preserveScroll: true,
        onSuccess: () => {
            stopPolling()
            migration.status = 'idle'
            migration.message = ''
            migration.processed = 0
            migration.total = 0
            migration.failed = 0
        },
    })
}
</script>

<template>
    <Head :title="t('Storage Settings')" />

    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Storage') }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Choose where uploaded and AI-generated media is stored — on this server or in an S3-compatible cloud bucket. Cloud drivers are verified before they go live.') }}
                </p>
            </div>
            <button type="button" :disabled="form.processing" class="shrink-0 btn-primary-admin disabled:opacity-60" @click="save">
                <i class="ti ti-device-floppy"></i>
                {{ form.processing ? t('Saving...') : t('Save Changes') }}
            </button>
        </div>

        <!-- Driver selector -->
        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900">
            <div class="mb-5">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Storage Driver') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Local keeps files on this server. Selecting a cloud driver stores all new uploads in your bucket and serves them from there.') }}</p>
            </div>

            <div class="max-w-sm">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Default Driver') }}</label>
                <AppSelect v-model="selectedDriver" :options="driverOptions" />
            </div>

            <p v-if="form.errors.driver" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                {{ form.errors.driver }}
            </p>

            <div v-if="selectedDriver === 'local'" class="mt-4 flex items-start gap-2 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:bg-gray-800/60 dark:text-gray-300">
                <i class="ti ti-server mt-0.5"></i>
                <span>{{ t('Files are stored under this server\'s public storage. Recommended for a single server; use a cloud driver to scale or offload bandwidth.') }}</span>
            </div>
        </section>

        <!-- Cloud credentials -->
        <section v-if="isCloud" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ activeDriverInfo?.label }} {{ t('Credentials') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Stored encrypted. Test the connection before saving — the driver only activates if the test passes.') }}</p>
                </div>
                <a v-if="activeDriverInfo?.doc_url" :href="activeDriverInfo.doc_url" target="_blank" rel="noopener noreferrer" class="shrink-0 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                    <i class="ti ti-external-link"></i> {{ t('Setup guide') }}
                </a>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div v-for="field in SECRET_FIELDS" :key="field">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ field === 'access_key' ? t('Access Key ID') : t('Secret Access Key') }}
                    </label>
                    <input
                        v-model="credentials[selectedDriver][field]"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="isSecretConfigured(selectedDriver, field) ? '••••••••' : t('Enter value')"
                        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30"
                    />
                    <p :class="isSecretConfigured(selectedDriver, field) ? 'text-green-600' : ''" class="mt-1 text-xs text-gray-400">
                        {{ isSecretConfigured(selectedDriver, field) ? t('Encrypted value saved. Leave blank to keep it.') : t('Not configured yet.') }}
                    </p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Bucket') }}</label>
                    <input v-model="credentials[selectedDriver].bucket" type="text" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30" :placeholder="t('my-media-bucket')" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Region') }}</label>
                    <input v-model="credentials[selectedDriver].region" type="text" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30" :placeholder="t('e.g. us-east-1')" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Endpoint') }} <span :class="selectedDriver === 's3' ? 'inline' : 'hidden'" class="text-gray-400">({{ t('optional for AWS S3') }})</span>
                    </label>
                    <input v-model="credentials[selectedDriver].endpoint" type="text" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30" placeholder="https://…" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Public URL') }} <span class="text-gray-400">({{ t('optional CDN / custom domain') }})</span>
                    </label>
                    <input v-model="credentials[selectedDriver].url" type="text" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-100 dark:focus:border-primary-500 dark:focus:ring-primary-900/30" placeholder="https://cdn.example.com" />
                </div>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <button type="button" :disabled="testing" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-xs hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:focus:ring-primary-900/30 disabled:opacity-60" @click="testConnection">
                    <i class="ti ti-plug-connected"></i>
                    {{ testing ? t('Testing...') : t('Test Connection') }}
                </button>
                <span v-if="testResult?.success" class="flex items-center gap-1.5 text-sm font-medium text-green-600 dark:text-green-400">
                    <i class="ti ti-circle-check"></i> {{ testResult.message || t('Connection succeeded.') }}
                </span>
                <span v-else-if="testResult" class="flex items-center gap-1.5 text-sm font-medium text-red-600 dark:text-red-400">
                    <i class="ti ti-alert-circle"></i> {{ testResult.error || t('Connection failed.') }}
                </span>
            </div>
        </section>

        <!-- Migration -->
        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900">
            <div class="mb-5">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Migrate Existing Files') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Switching drivers does not move files that are already uploaded. Copy them so nothing 404s. Files are copied, never deleted from the source, so this is safe to run.') }}
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Copy From') }}</label>
                    <AppSelect v-model="migrateSource" :options="allDriverOptions" :disabled="isMigrating" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Copy To') }}</label>
                    <AppSelect v-model="migrateTarget" :options="allDriverOptions" :disabled="isMigrating" />
                </div>
            </div>

            <div class="mt-5">
                <button type="button" :disabled="isMigrating || startingMigration || migrateSource === migrateTarget" class="btn-primary-admin disabled:opacity-60" @click="startMigration">
                    <svg v-if="startingMigration" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    <i v-else class="ti ti-transfer"></i>
                    {{ startingMigration ? t('Starting...') : (isMigrating ? t('Migration running...') : t('Start Migration')) }}
                </button>
            </div>

            <!-- Progress -->
            <div v-if="migration.status !== 'idle'" class="mt-5 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-800 dark:bg-gray-800/50">
                <div class="mb-2 flex items-center justify-between text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-200">
                        {{ driverLabel(migration.source) }} <i class="ti ti-arrow-right"></i> {{ driverLabel(migration.target) }}
                    </span>
                    <span class="text-gray-500 dark:text-gray-400">{{ migration.processed }} / {{ migration.total }}</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div
                        class="h-full rounded-full transition-all duration-500"
                        :class="{
                            'bg-primary-500': migration.status === 'running',
                            'bg-green-500': migration.status === 'completed',
                            'bg-amber-500': migration.status === 'completed_with_errors' || migration.status === 'stalled',
                            'bg-red-500': migration.status === 'failed',
                        }"
                        :style="{ width: (migration.status === 'running' ? migrationPct : 100) + '%' }"
                    ></div>
                </div>
                <div class="mt-2 flex items-start justify-between gap-3">
                    <p class="text-sm" :class="{
                        'text-gray-500 dark:text-gray-400': migration.status === 'running',
                        'text-green-600 dark:text-green-400': migration.status === 'completed',
                        'text-amber-600 dark:text-amber-400': migration.status === 'completed_with_errors' || migration.status === 'stalled',
                        'text-red-600 dark:text-red-400': migration.status === 'failed',
                    }">
                        {{ migration.message }}
                        <span v-if="migration.failed > 0"> · {{ t(':n failed', { n: migration.failed }) }}</span>
                    </p>
                    <button
                        v-if="canClearMigration"
                        type="button"
                        class="shrink-0 text-sm font-medium text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                        @click="clearMigration"
                    >
                        {{ migration.status === 'running' ? t('Cancel') : t('Clear') }}
                    </button>
                </div>
            </div>
        </section>

        <!-- Provider-specific docs -->
        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900">
            <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('How to migrate — provider notes') }}</h2>
            <div class="space-y-4 text-sm text-gray-600 dark:text-gray-300">
                <div>
                    <p class="font-semibold text-gray-800 dark:text-gray-100"><i class="ti ti-info-circle"></i> {{ t('General steps') }}</p>
                    <ol class="mt-1 list-decimal space-y-1 pl-5">
                        <li>{{ t('Create a bucket at your provider and generate an access key / secret with read-write permission.') }}</li>
                        <li>{{ t('Enter the credentials above, click Test Connection, then Save Changes to activate.') }}</li>
                        <li>{{ t('Run Migrate Existing Files (Local → your cloud) so already-uploaded media is copied over.') }}</li>
                        <li>{{ t('Verify a few images load on the site, then optionally delete the local copies to reclaim disk space.') }}</li>
                    </ol>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="font-semibold text-gray-800 dark:text-gray-100">Amazon S3</p>
                        <p>{{ t('Leave Endpoint blank. Set Region to your bucket region (e.g. us-east-1). Bucket ACLs or a bucket policy must allow public read for served media.') }}</p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800 dark:text-gray-100">Cloudflare R2</p>
                        <p>{{ t('Endpoint is https://<account_id>.r2.cloudflarestorage.com. Region = auto. Add a public r2.dev or custom domain as the Public URL.') }}</p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800 dark:text-gray-100">DigitalOcean Spaces</p>
                        <p>{{ t('Endpoint is https://<region>.digitaloceanspaces.com. Region matches (e.g. nyc3). Enable the Space CDN and use its URL as Public URL.') }}</p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800 dark:text-gray-100">Wasabi / Backblaze B2</p>
                        <p>{{ t('Use the provider\'s S3-compatible endpoint for your region and set the matching Region. Make the bucket public for direct media serving.') }}</p>
                    </div>
                </div>
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                    <i class="ti ti-shield-lock"></i>
                    {{ t('Credentials are stored encrypted and never returned to the browser. Use a dedicated key scoped to this one bucket for least privilege.') }}
                </p>
            </div>
        </section>
    </div>
</template>
