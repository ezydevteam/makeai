<script setup lang="ts">
import { ref, watch } from 'vue'
import ErrorAlert from './ErrorAlert.vue'

const props = defineProps<{
    formData: Record<string, any>
    error?: string | null
    cronCommand?: string
    queueCommand?: string
}>()

const copied = ref('')

const copy = async (text: string | undefined, which: string) => {
    if (!text) return

    try {
        await navigator.clipboard.writeText(text)
        copied.value = which
        setTimeout(() => (copied.value = ''), 2000)
    } catch {
        // Clipboard access is blocked over plain HTTP in most browsers, and a
        // fresh install is usually reached over HTTP. The command stays visible
        // and selectable, so failing silently costs the buyer nothing.
    }
}

// Decides QUEUE_CONNECTION at finalize. Unchecked means `sync` — jobs run inside
// the request, which is slower but always works. Checked moves the install to
// redis/database, where a job is stored and never runs unless the worker cron is
// really there, so this must be the buyer's own claim rather than a guess.
const queueWorkerReady = ref(false)

watch(
    queueWorkerReady,
    (ready) => {
        props.formData.step_6 = { ...(props.formData.step_6 ?? {}), queue_worker_ready: ready }
    },
    { immediate: true },
)

const steps = props.formData

const driver = steps?.step_3?.db_driver ?? 'mysql'
const driverLabel = driver === 'mariadb' ? 'MariaDB' : 'MySQL'

const dbItems = [
    { label: 'Database', value: driverLabel },
    { label: 'Database Host', value: steps?.step_3?.db_host ?? '—' },
    { label: 'Database Port', value: steps?.step_3?.db_port ?? '—' },
    { label: 'Database Name', value: steps?.step_3?.db_database ?? '—' },
    { label: 'Database User', value: steps?.step_3?.db_username ?? '—' },
]

const items = [
    ...dbItems,
    { label: 'Site Name', value: steps?.step_4?.site_name ?? '—' },
    { label: 'Site Tagline', value: steps?.step_4?.site_tagline || '—' },
    { label: 'Site URL', value: steps?.step_4?.site_url ?? '—' },
    { label: 'Purchase Code', value: steps?.step_2?.purchase_code ?? '—' },
    { label: 'Admin Name', value: steps?.step_5?.admin_name ?? '—' },
    { label: 'Admin Email', value: steps?.step_5?.admin_email ?? '—' },
]
</script>

<template>
    <div>
        <h2 class="text-xl font-bold text-slate-900">Ready to Install</h2>
        <p class="mt-1 text-sm text-slate-500">Review your configuration and click install to complete setup.</p>

        <ErrorAlert :message="error" />

        <!-- Summary -->
        <div class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200">
            <div
                v-for="item in items"
                :key="item.label"
                class="flex items-center justify-between px-4 py-3"
            >
                <span class="text-sm text-slate-600">{{ item.label }}</span>
                <span class="ml-4 max-w-[60%] truncate text-right font-mono text-sm font-medium text-slate-900">
                    {{ item.value }}
                </span>
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-[#b7d3ff] bg-[#edf4ff] p-4 text-sm text-[#1757bc]">
            <p class="font-semibold">What will happen:</p>
            <ol class="mt-2 list-inside list-decimal space-y-1 text-xs">
                <li>Database migrations will run</li>
                <li>Admin roles and permissions will be created</li>
                <li>Your admin account will be set up</li>
                <li>Your license will be activated</li>
                <li>The installer will be disabled (404)</li>
            </ol>
        </div>

        <!-- Shown before installing rather than after: finalising redirects
             straight to the login screen, so this is the buyer's last chance to
             see it. Also written to core/deploy/cron.txt in the package. -->
        <div v-if="cronCommand" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-sm font-semibold text-amber-800">One last step — add this cron job</p>
            <p class="mt-1 text-xs text-amber-700">
                Add it in your hosting panel (cPanel &rarr; Cron Jobs) set to run
                <span class="font-semibold">every minute</span>. Without it, scheduled emails,
                subscription renewals and usage resets will not run.
            </p>

            <div class="mt-3 flex items-start gap-2">
                <code
                    class="flex-1 overflow-x-auto rounded-lg border border-amber-200 bg-white px-3 py-2 font-mono text-xs text-slate-800"
                >{{ cronCommand }}</code>
                <button
                    type="button"
                    class="shrink-0 rounded-lg border border-amber-300 bg-white px-3 py-2 text-xs font-medium text-amber-800 hover:bg-amber-100"
                    @click="copy(cronCommand, 'schedule')"
                >
                    {{ copied === 'schedule' ? 'Copied' : 'Copy' }}
                </button>
            </div>

            <p class="mt-2 text-xs text-amber-600">
                Some hosts require the full PHP path (e.g.
                <span class="font-mono">/usr/local/bin/php</span>) instead of
                <span class="font-mono">php</span> — see the documentation folder in your
                download if the job does not run.
            </p>
        </div>

        <!-- Optional second cron. Its presence decides QUEUE_CONNECTION, so the
             question is asked here rather than assumed: without a worker, jobs on
             the redis/database drivers are stored and never run, and the first
             thing anyone notices is that sign-in codes stop arriving. -->
        <div v-if="queueCommand" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-800">Background jobs (optional)</p>
            <p class="mt-1 text-xs text-slate-600">
                By default jobs run inside the page request — everything works, but pages that
                send email feel slower. Adding a second cron job lets them run in the
                background instead.
            </p>

            <div class="mt-3 flex items-start gap-2">
                <code
                    class="flex-1 overflow-x-auto rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-xs text-slate-800"
                >{{ queueCommand }}</code>
                <button
                    type="button"
                    class="shrink-0 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-100"
                    @click="copy(queueCommand, 'queue')"
                >
                    {{ copied === 'queue' ? 'Copied' : 'Copy' }}
                </button>
            </div>

            <label class="mt-3 flex cursor-pointer items-start gap-2">
                <input
                    v-model="queueWorkerReady"
                    type="checkbox"
                    class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-[#1757bc] focus:ring-[#1757bc]"
                />
                <span class="text-xs text-slate-700">
                    I have added this queue worker cron job.
                    <span class="block text-slate-500">
                        Only tick this once the job actually exists. If you tick it without one,
                        emails and sign-in codes will be queued and never sent. You can change
                        this later in <span class="font-mono">core/.env</span>.
                    </span>
                </span>
            </label>
        </div>
    </div>
</template>
