<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

const props = defineProps<{
    formData: Record<string, any>
}>()

const db = reactive({
    db_driver: props.formData?.step_2?.db_driver ?? 'mysql',
    db_host: props.formData?.step_2?.db_host ?? '127.0.0.1',
    db_port: props.formData?.step_2?.db_port ?? 3306,
    db_database: props.formData?.step_2?.db_database ?? '',
    db_username: props.formData?.step_2?.db_username ?? '',
    db_password: props.formData?.step_2?.db_password ?? '',
})

const testing = ref(false)
const testResult = ref<{ pass: boolean; message: string } | null>(null)
const { t } = useTranslate()

const isMysql = computed(() => db.db_driver === 'mysql')

async function testConnection() {
    testing.value = true
    testResult.value = null

    try {
        const res = await fetch('/install/step/2', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Inertia': 'true',
                'Accept': 'text/html, application/xhtml+xml',
            },
            body: JSON.stringify(db),
        })

        // If DB test passes, controller stores & redirects.
        // If it fails, it returns error flash.
        if (res.redirected) {
            testResult.value = { pass: true, message: t('Connection successful!') }
        }
    } catch {
        testResult.value = { pass: false, message: t('Could not reach server.') }
    } finally {
        testing.value = false
    }
}

defineExpose({ getData: () => ({ ...db }) })
</script>

<template>
    <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('Database Configuration') }}</h2>
        <p class="mt-1 text-sm text-gray-500">{{ t('Choose your database driver. SQLite is recommended for shared hosting as it requires no separate setup.') }}</p>

        <div class="mt-6 space-y-4">
            <label class="block">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Database Driver') }}</span>
                <select
                    v-model="db.db_driver"
                    class="mt-1.5 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                >
                    <option value="mysql">MySQL / MariaDB</option>
                    <option value="sqlite">SQLite (Recommended for Shared Hosting)</option>
                </select>
            </label>

            <div v-if="isMysql" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <label class="sm:col-span-3 block">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Host') }}</span>
                        <input
                            v-model="db.db_host"
                            type="text"
                            placeholder="127.0.0.1"
                            class="mt-1.5 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        />
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Port') }}</span>
                        <input
                            v-model.number="db.db_port"
                            type="number"
                            placeholder="3306"
                            class="mt-1.5 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        />
                    </label>
                </div>

                <label class="block">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Database Name') }}</span>
                    <input
                        v-model="db.db_database"
                        type="text"
                        :placeholder="t('makeai')"
                        class="mt-1.5 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                    />
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Username') }}</span>
                        <input
                            v-model="db.db_username"
                            type="text"
                            :placeholder="t('root')"
                            class="mt-1.5 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        />
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Password') }}</span>
                        <input
                            v-model="db.db_password"
                            type="password"
                            placeholder="••••••"
                            class="mt-1.5 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        />
                    </label>
                </div>

                <!-- Test Connection -->
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        :disabled="testing || !db.db_host || !db.db_database || !db.db_username"
                        class="inline-flex items-center gap-2 rounded-lg border border-blue-300 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 transition-colors hover:bg-blue-100 disabled:opacity-50 dark:border-blue-600 dark:bg-blue-900/20 dark:text-blue-300 dark:hover:bg-blue-900/30"
                        @click="testConnection"
                    >
                        <svg v-if="testing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                        </svg>
                        {{ testing ? t('Testing...') : t('Test Connection') }}
                    </button>

                    <span
                        v-if="testResult"
                        class="text-sm font-medium"
                        :class="testResult.pass ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                    >
                        {{ testResult.message }}
                    </span>
                </div>
            </div>

            <div v-else class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-700 dark:bg-blue-900/20 dark:text-blue-300">
                <p class="font-semibold">{{ t('SQLite Selected') }}</p>
                <p class="mt-1">{{ t('The installer will automatically create a SQLite database file. No additional configuration is needed.') }}</p>
            </div>
        </div>
    </div>
</template>
