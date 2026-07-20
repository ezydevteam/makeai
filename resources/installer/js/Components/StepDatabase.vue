<script setup lang="ts">
import { ref, reactive } from 'vue'
import axios from 'axios'
import ErrorAlert from './ErrorAlert.vue'
import AppSelect, { type SelectOption } from '@/Components/UI/AppSelect.vue'

const props = defineProps<{
    formData: Record<string, any>
    error?: string | null
}>()

const db = reactive({
    db_driver: props.formData?.step_3?.db_driver ?? 'mysql',
    db_host: props.formData?.step_3?.db_host ?? '127.0.0.1',
    db_port: props.formData?.step_3?.db_port ?? 3306,
    db_database: props.formData?.step_3?.db_database ?? '',
    db_username: props.formData?.step_3?.db_username ?? '',
    db_password: props.formData?.step_3?.db_password ?? '',
})

const testing = ref(false)
const testResult = ref<{ pass: boolean; message: string } | null>(null)

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
    getData: () => ({ ...db }),
})
</script>

<template>
    <div>
        <h2 class="text-xl font-bold text-slate-900">Database Configuration</h2>
        <p class="mt-1 text-sm text-slate-500">Enter the database details from your hosting panel. Create an empty database and user first, then paste the credentials here.</p>

        <ErrorAlert :message="error" />

        <div class="mt-6 space-y-4">
            <div class="block">
                <span class="text-sm font-medium text-slate-700">Database Driver</span>
                <AppSelect
                    v-model="db.db_driver"
                    :options="driverOptions"
                    class="mt-1.5"
                />
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <label class="block sm:col-span-3">
                        <span class="text-sm font-medium text-slate-700">Host</span>
                        <input
                            v-model="db.db_host"
                            type="text"
                            placeholder="127.0.0.1"
                            class="mt-1.5 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                        />
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Port</span>
                        <input
                            v-model.number="db.db_port"
                            type="number"
                            placeholder="3306"
                            class="mt-1.5 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                        />
                    </label>
                </div>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Database Name</span>
                    <input
                        v-model="db.db_database"
                        type="text"
                        placeholder="makeai"
                        class="mt-1.5 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                    />
                </label>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Username</span>
                        <input
                            v-model="db.db_username"
                            type="text"
                            placeholder="root"
                            class="mt-1.5 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                        />
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Password</span>
                        <input
                            v-model="db.db_password"
                            type="password"
                            placeholder="••••••"
                            class="mt-1.5 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                        />
                    </label>
                </div>

                <!-- Test Connection -->
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        :disabled="testing || !db.db_host || !db.db_database || !db.db_username"
                        class="inline-flex items-center gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 transition-colors hover:bg-emerald-100 disabled:opacity-50"
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
            </div>
        </div>
    </div>
</template>
