<script setup lang="ts">
import { ref, reactive, computed, nextTick, watch } from 'vue'
import axios from 'axios'
import ErrorAlert from './ErrorAlert.vue'
import AppSelect, { type SelectOption } from '@/Components/UI/AppSelect.vue'

const props = defineProps<{
    formData: Record<string, any>
    error?: string | null
    // Server-detected DB state ('empty' | 'app' | 'foreign'), flashed by the
    // controller when it refuses a populated database on submit.
    dbState?: string | null
}>()

const db = reactive({
    db_driver: props.formData?.step_3?.db_driver ?? 'mysql',
    db_host: props.formData?.step_3?.db_host ?? '127.0.0.1',
    db_port: props.formData?.step_3?.db_port ?? 3306,
    db_database: props.formData?.step_3?.db_database ?? '',
    db_username: props.formData?.step_3?.db_username ?? '',
    db_password: props.formData?.step_3?.db_password ?? '',
    // Buyer consent to wipe a non-empty database before installing.
    db_reset: props.formData?.step_3?.db_reset ?? false,
})

const testing = ref(false)
const testResult = ref<{ pass: boolean; message: string } | null>(null)

// The most recent state we know about: a fresh "Test Connection" wins over the
// state the server flashed on the last refused submit. Once the buyer edits any
// connection field, both verdicts describe different credentials and must be
// ignored — otherwise a stale "empty" could hide the card for a populated DB (a
// dead-end where the reset checkbox never appears), or vice-versa.
const probedState = ref<string | null>(null)
const dirtied = ref(false)
const existingState = computed(() => {
    if (probedState.value) return probedState.value
    if (dirtied.value) return null
    return props.dbState ?? null
})
const showReset = computed(
    () => existingState.value === 'app' || existingState.value === 'foreign',
)

// The flashed populated-database error and the reset card say the same sentence, so
// rendering both stacks two warnings for one problem. The card wins: it is the only
// one carrying the checkbox that resolves it. Every other database error — bad
// credentials, unreachable host, field validation — still goes to the alert.
const inlineError = computed(() => (showReset.value ? null : props.error ?? null))

const resetCard = ref<HTMLElement | null>(null)

// With the alert suppressed, the card is the sole signal that the click did
// something — and step submits use preserveScroll, so a buyer who was looking at the
// Next button when the server refused would otherwise see the page sit still.
watch(showReset, async (visible) => {
    if (!visible) return

    await nextTick()
    resetCard.value?.scrollIntoView({ behavior: 'smooth', block: 'center' })
})

watch(
    () => [db.db_driver, db.db_host, db.db_port, db.db_database, db.db_username, db.db_password],
    () => {
        probedState.value = null
        testResult.value = null
        dirtied.value = true
    },
)

// SQLite is intentionally absent: a fresh install is bootstrapped from
// database/data/data.sql (a mysqldump), and the seeder classes that once covered
// the SQLite path no longer ship. MySQL and MariaDB take identical parameters.
const driverOptions: SelectOption[] = [
    { value: 'mysql', label: 'MySQL', icon: 'ti ti-database' },
    { value: 'mariadb', label: 'MariaDB', icon: 'ti ti-brand-databricks' },
]

async function testConnection() {
    testing.value = true
    testResult.value = null

    try {
        // Dedicated probe endpoint — actually connects to the DB and reports the
        // result WITHOUT storing the step or advancing the wizard. axios sends the
        // XSRF-TOKEN header so the CSRF-protected route accepts it. A failed
        // connection still returns HTTP 200 with pass:false and a friendly message.
        // route() rather than a literal '/install/test-database': Ziggy builds the
        // URL from the request root, so it stays correct when the buyer installs
        // into a subdirectory (example.com/ai). The literal path posted to the
        // domain root and 404'd there.
        const { data } = await axios.post(route('install.test-database'), { ...db })
        testResult.value = { pass: !!data.pass, message: data.message ?? '' }
        if (data.pass) {
            probedState.value = data.dbState ?? null
        }
    } catch (e: any) {
        const errors = e?.response?.data?.errors
        testResult.value = {
            pass: false,
            message:
                e?.response?.data?.message
                || (errors ? String(Object.values(errors)[0]) : '')
                || 'Could not reach the server. Please try again.',
        }
    } finally {
        testing.value = false
    }
}

defineExpose({
    // Called once by the wizard immediately before the step is POSTed. Whatever is
    // typed at this point is exactly what the server is about to judge, so the edits
    // that raised `dirtied` are no longer unverified and the flag has to drop — the
    // db_state flashed back describes these very credentials.
    //
    // Clearing it here rather than on the response is deliberate: Vue skips a
    // watcher when a prop is re-assigned its current value, so a second refusal
    // carrying the same state ('app' → 'app') would never re-trigger one, and the
    // stale flag would keep the reset card hidden behind an error the buyer cannot
    // act on.
    getData: () => {
        dirtied.value = false

        return { ...db }
    },
})
</script>

<template>
    <div>
        <h2 class="text-xl font-bold text-slate-900">Database Configuration</h2>
        <p class="mt-1 text-sm text-slate-500">Enter the database details from your hosting panel. Create an empty database and user first, then paste the credentials here.</p>

        <ErrorAlert :message="inlineError" />

        <div class="mt-6 space-y-4">
            <div class="block">
                <span class="text-sm font-medium text-slate-700">Database Driver</span>
                <AppSelect
                    v-model="db.db_driver"
                    :options="driverOptions"
                    class="mt-1.5"
                />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <label class="block sm:col-span-3">
                    <span class="text-sm font-medium text-slate-700">Host</span>
                    <input
                        v-model="db.db_host"
                        type="text"
                        placeholder="127.0.0.1"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                    />
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Port</span>
                    <input
                        v-model.number="db.db_port"
                        type="number"
                        placeholder="3306"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                    />
                </label>
            </div>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">Database Name</span>
                <input
                    v-model="db.db_database"
                    type="text"
                    placeholder="makeai"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                />
            </label>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Username</span>
                    <input
                        v-model="db.db_username"
                        type="text"
                        placeholder="root"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                    />
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Password</span>
                    <input
                        v-model="db.db_password"
                        type="password"
                        placeholder="••••••"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                    />
                </label>
            </div>

            <!-- Test Connection -->
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    :disabled="testing || !db.db_host || !db.db_database || !db.db_username"
                    class="inline-flex items-center gap-2 rounded-xl border border-[#93bdff] bg-[#edf4ff] px-4 py-2 text-sm font-medium text-[#1757bc] transition-colors hover:bg-[#d7e6ff] disabled:opacity-50"
                    @click="testConnection"
                >
                    <svg v-if="testing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    {{ testing ? 'Testing...' : 'Test Connection' }}
                </button>

                <span
                    v-if="testResult"
                    class="text-sm font-medium"
                    :class="testResult.pass ? 'text-emerald-600' : 'text-red-600'"
                >
                    {{ testResult.message }}
                </span>
            </div>

            <!-- Existing-data warning + destructive reset confirmation -->
            <div
                v-if="showReset"
                ref="resetCard"
                class="rounded-xl border p-4"
                :class="existingState === 'app' ? 'border-amber-300 bg-amber-50' : 'border-red-300 bg-red-50'"
            >
                <div class="flex items-start gap-3">
                    <i
                        class="ti ti-alert-triangle mt-0.5 text-lg"
                        :class="existingState === 'app' ? 'text-amber-600' : 'text-red-600'"
                    />
                    <div class="flex-1">
                        <p
                            class="text-sm font-semibold"
                            :class="existingState === 'app' ? 'text-amber-800' : 'text-red-800'"
                        >
                            {{ existingState === 'app'
                                ? 'A previous installation was found in this database'
                                : 'This database already contains data from another application' }}
                        </p>
                        <p
                            class="mt-1 text-xs"
                            :class="existingState === 'app' ? 'text-amber-700' : 'text-red-700'"
                        >
                            The installer will not overwrite it unless you reset. Resetting permanently
                            erases every table in this database before installing — there is no undo.
                        </p>
                        <label
                            class="mt-3 flex items-start gap-2 text-sm font-medium"
                            :class="existingState === 'app' ? 'text-amber-900' : 'text-red-900'"
                        >
                            <input
                                v-model="db.db_reset"
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 rounded border-slate-300"
                            />
                            <span>Reset the database and reinstall (permanently erases all existing data)</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
