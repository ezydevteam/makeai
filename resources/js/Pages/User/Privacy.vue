<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'

defineOptions({ layout: UserDashboardLayout })

const { t } = useTranslate()
const page = usePage()

interface ExportItem {
    id: number
    status: string
    requested_at: string
    expires_at: string | null
    is_expired: boolean
}

interface SessionItem {
    id: number
    ip: string
    country: string | null
    city: string | null
    user_agent: string
    last_seen: string
}

const props = defineProps<{
    exportHistory: ExportItem[]
    sessions: SessionItem[]
    pendingExport: boolean
    recentExport: boolean
    scheduledDeletion: string | null
    user: {
        email_marketing: boolean
        allow_data_improve: boolean
        cookie_consent: Record<string, boolean> | null
    }
}>()

const exportForm = useForm({})
const deleteForm = useForm({ confirmation: '', otp: '' })
const preferencesForm = useForm({
    email_marketing: props.user?.email_marketing ?? true,
    allow_data_improve: props.user?.allow_data_improve ?? true,
})

const showDeleteModal = ref(false)
const showRevokeConfirm = ref<number | null>(null)

const cookieConsent = ref({
    functional: props.user?.cookie_consent?.functional ?? true,
    analytics: props.user?.cookie_consent?.analytics ?? false,
    marketing: props.user?.cookie_consent?.marketing ?? false,
})

const statusLabel = (status: string) => {
    const map: Record<string, string> = {
        pending: t('Pending'),
        processing: t('Processing'),
        ready: t('Ready'),
        downloaded: t('Downloaded'),
        expired: t('Expired'),
    }
    return map[status] ?? status
}

const requestExport = () => exportForm.post(route('user.dashboard.privacy.export'))

const scheduleDeletion = () => {
    deleteForm.post(route('user.dashboard.privacy.schedule-deletion'), {
        onSuccess: () => { showDeleteModal.value = false },
    })
}

const cancelDeletion = () => {
    useForm({}).post(route('user.dashboard.privacy.cancel-deletion'))
}

const revokeSession = (id: number) => {
    useForm({ session_id: id }).post(route('user.dashboard.privacy.sessions.revoke'))
}

const signOutAll = () => {
    useForm({}).post(route('user.dashboard.privacy.sign-out-all'))
}

const savePreferences = () => {
    preferencesForm.post(route('user.dashboard.privacy.preferences'))
}

const exportedCookieConsent = computed(() => {
    const c = props.user?.cookie_consent
    if (!c) return { necessary: true, functional: true, analytics: false, marketing: false }
    return {
        necessary: true,
        functional: c.functional ?? true,
        analytics: c.analytics ?? false,
        marketing: c.marketing ?? false,
    }
})
</script>

<template>
    <Head :title="t('Privacy Settings')" />

    <div class="mx-auto max-w-3xl space-y-8 py-8 px-4 sm:px-6 lg:px-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Privacy Settings') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ t('Manage your data, preferences, and account privacy.') }}</p>
        </div>

        <!-- Scheduled Deletion -->
        <section v-if="scheduledDeletion" class="rounded-xl border border-red-200 bg-red-50 p-6 dark:border-red-800 dark:bg-red-900/20">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-red-800 dark:text-red-300">{{ t('Account Scheduled for Deletion') }}</p>
                    <p class="mt-1 text-sm text-red-700 dark:text-red-400">{{ t('Your account will be permanently deleted on :date. Log in before then to cancel.', { date: new Date(scheduledDeletion).toLocaleDateString() }) }}</p>
                </div>
                <button @click="cancelDeletion" class="shrink-0 rounded-lg bg-red-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-red-700 transition-colors">{{ t('Cancel Deletion') }}</button>
            </div>
        </section>

        <!-- Data & Export -->
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Data & Export') }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ t('Download a complete archive of your data including profile, documents, chat history, usage logs, credit transactions, and login history.') }}</p>

            <div class="mt-4 flex items-center gap-3">
                <button @click="requestExport" :disabled="exportForm.processing || props.pendingExport || props.recentExport" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50 transition-colors">
                    <svg v-if="exportForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
                    <span>{{ props.pendingExport ? t('Export in progress...') : t('Download My Data') }}</span>
                </button>
                <p v-if="props.recentExport" class="text-xs text-amber-600 dark:text-amber-400">{{ t('You can request one export per 24 hours.') }}</p>
            </div>

            <!-- Export History -->
            <div v-if="exportHistory.length > 0" class="mt-6">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ t('Export History') }}</h3>
                <div class="space-y-2">
                    <div v-for="item in exportHistory" :key="item.id" class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2.5 dark:bg-surface-800">
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-500">{{ new Date(item.requested_at).toLocaleDateString() }}</span>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase" :class="item.status === 'ready' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'">{{ statusLabel(item.status) }}</span>
                        </div>
                        <Link v-if="item.status === 'ready' && !item.is_expired" :href="route('user.dashboard.privacy.export.download', item.id)" class="text-xs font-bold text-primary-600 hover:text-primary-700">{{ t('Download') }}</Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Account Deletion -->
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Account Deletion') }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ t('Permanently delete your account and all associated data. This action includes a 30-day grace period during which you can cancel.') }}</p>
            <button @click="showDeleteModal = true" class="mt-4 inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-50 dark:border-red-800 dark:bg-transparent dark:hover:bg-red-900/20 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                {{ t('Delete My Account') }}
            </button>
        </section>

        <!-- Preferences -->
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Preferences') }}</h2>

            <div class="mt-4 space-y-5">
                <label class="flex items-center gap-3">
                    <input v-model="preferencesForm.email_marketing" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                    <div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Email Marketing') }}</span>
                        <p class="text-xs text-gray-500">{{ t('Receive product updates, tips, and promotional offers via email.') }}</p>
                    </div>
                </label>

                <label class="flex items-center gap-3">
                    <input v-model="preferencesForm.allow_data_improve" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                    <div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Help Improve AI Models') }}</span>
                        <p class="text-xs text-gray-500">{{ t('Allow your generations to improve the AI model (sends opt-in flag to providers that support it).') }}</p>
                    </div>
                </label>
            </div>

            <button @click="savePreferences" :disabled="preferencesForm.processing" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50 transition-colors">{{ t('Save Preferences') }}</button>
        </section>

        <!-- Cookie Preferences -->
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Cookie Preferences') }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ t('Manage which cookie categories you accept. Necessary cookies are always enabled.') }}</p>

            <div class="mt-4 space-y-4">
                <label v-for="cat in [
                    { key: 'functional', label: t('Functional'), desc: t('Language, theme, and display preferences.') },
                    { key: 'analytics', label: t('Analytics'), desc: t('Usage tracking and performance measurement.') },
                    { key: 'marketing', label: t('Marketing'), desc: t('Ad tracking and retargeting pixels.') },
                ]" :key="cat.key" class="flex items-center gap-3">
                    <input v-model="cookieConsent[cat.key]" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @change="preferencesForm.cookie_consent = { ...cookieConsent }" />
                    <div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ cat.label }}</span>
                        <p class="text-xs text-gray-500">{{ cat.desc }}</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 opacity-60 cursor-not-allowed">
                    <input checked disabled type="checkbox" class="w-4 h-4 rounded border-gray-300 text-gray-400" />
                    <div>
                        <span class="text-sm font-medium text-gray-500">{{ t('Necessary') }}</span>
                        <p class="text-xs text-gray-400">{{ t('Required for the site to function (session, CSRF, authentication).') }}</p>
                    </div>
                </label>
            </div>

            <button @click="savePreferences" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50 transition-colors">{{ t('Save Cookie Preferences') }}</button>
        </section>

        <!-- Session Management -->
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Session Management') }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ t('View and manage your active login sessions.') }}</p>

            <button @click="signOutAll" class="mt-4 inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                {{ t('Sign Out All Devices') }}
            </button>

            <div v-if="sessions.length > 0" class="mt-5 space-y-2">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Recent Sessions') }}</h3>
                <div v-for="session in sessions.slice(0, 10)" :key="session.id" class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2.5 text-sm dark:bg-surface-800">
                    <div class="flex flex-col gap-0.5">
                        <span class="text-gray-700 dark:text-gray-300">{{ session.ip }} <span v-if="session.city" class="text-gray-400">· {{ session.city }}{{ session.country ? ', ' + session.country : '' }}</span></span>
                        <span class="text-[11px] text-gray-400 truncate max-w-[300px]">{{ session.user_agent }}</span>
                        <span class="text-[11px] text-gray-400">{{ new Date(session.last_seen).toLocaleString() }}</span>
                    </div>
                    <button v-if="showRevokeConfirm !== session.id" @click="showRevokeConfirm = session.id" class="text-xs text-red-500 hover:text-red-700 transition-colors">{{ t('Revoke') }}</button>
                    <div v-else class="flex items-center gap-2">
                        <span class="text-xs text-red-500">{{ t('Confirm?') }}</span>
                        <button @click="revokeSession(session.id); showRevokeConfirm = null" class="text-xs font-bold text-red-600">{{ t('Yes') }}</button>
                        <button @click="showRevokeConfirm = null" class="text-xs text-gray-500">{{ t('No') }}</button>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Delete Account Modal -->
    <ActionConfirmModal :open="showDeleteModal" :title="t('Delete Your Account')" :message="t('This action is permanent. All your data will be deleted after a 30-day grace period. You can cancel during this time by logging in.')" :confirm-label="t('Schedule Deletion')" :variant="'danger'" :processing="deleteForm.processing" @confirm="scheduleDeletion" @cancel="showDeleteModal = false">
        <div class="space-y-4 pt-3">
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ t('Type DELETE to confirm and enter the OTP sent to your email.') }}</p>
            <input v-model="deleteForm.confirmation" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" placeholder="DELETE" />
            <input v-model="deleteForm.otp" type="text" maxlength="6" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-mono text-center tracking-[0.5em] dark:border-surface-700 dark:bg-surface-800 dark:text-white" placeholder="000000" />
        </div>
    </ActionConfirmModal>
</template>
