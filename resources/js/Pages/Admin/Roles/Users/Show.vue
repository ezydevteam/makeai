<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useTranslate } from '@/Composables/useTranslate';

defineOptions({ layout: AdminLayout });
const { formatDateTime } = useDateFormat();
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const { t } = useTranslate();

const props = defineProps<{
    user: any,
    plans: Array<{ id: number, name: string }>,
    usageHistory: Array<{
        id: number
        tool_slug: string | null
        model: string | null
        provider: string
        input_tokens: number
        output_tokens: number
        credits_used: number | string
        status: string
        created_at: string
    }>
}>();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    credits: props.user.credits,
    plan_id: props.user.plan_id ?? '',
    is_active: props.user.is_active,
    password: '',
    password_confirmation: '',
});

const notificationForm = useForm({
    title: '',
    message: '',
    level: 'info',
    deliver_via: 'in_app',
    scheduled_at: '',
    action_url: '',
    action_label: '',
});

const twoFactorForm = useForm({});
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
const notificationModalOpen = ref(false);
const deleteModalOpen = ref(false);

const planOptions = computed(() => [
    { value: '', label: t('No Plan') },
    ...props.plans.map((plan) => ({
        value: plan.id,
        label: plan.name,
    })),
]);

const formatCredits = (value: number | string) => {
    const numericValue = typeof value === 'number' ? value : Number(value);
    return Number.isFinite(numericValue) ? numericValue.toFixed(2) : '0.00';
};

const totalTokens = (item: { input_tokens: number; output_tokens: number }) => {
    return Number(item.input_tokens || 0) + Number(item.output_tokens || 0);
};

const statusBadgeClass = (status: string) => {
    switch (status) {
        case 'success':
            return 'bg-green-100 text-green-800';
        case 'failed':
        case 'error':
            return 'bg-red-100 text-red-800';
        case 'pending':
            return 'bg-amber-100 text-amber-800';
        default:
            return 'bg-gray-100 text-gray-700';
    }
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        plan_id: data.plan_id || null,
    })).post(route('admin.users.update', props.user.ulid), {
        onSuccess: () => {
            form.password = '';
            form.password_confirmation = '';
        }
    });
};

const sendNotification = () => {
    notificationForm.post(route('admin.users.notification', props.user.ulid), {
        preserveScroll: true,
        onSuccess: () => {
            notificationForm.reset();
            notificationForm.level = 'info';
            notificationForm.deliver_via = 'in_app';
            notificationModalOpen.value = false;
        },
    });
};

const disableTwoFactor = () => {
    twoFactorForm.post(route('admin.users.2fa.disable', props.user.ulid), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`${user.name} - ${t('User Details')}`" />
    <div class="mx-auto w-full sm:max-w-5xl px-6 py-6">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ t('Edit User') }}: {{ user.name }}</h1>
                <p class="text-sm text-gray-500">{{ t('ULID') }}: <span class="font-mono text-gray-400">{{ user.ulid }}</span></p>
            </div>

            <Link
                :href="route('admin.users.index')"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
            >
                <i class="ti ti-arrow-left text-base"></i>
                {{ t('Back') }}
            </Link>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left: Profile Info -->
            <div class="lg:col-span-2 space-y-6">
                <form @submit.prevent="submit" class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                        <h3 class="font-bold text-gray-900">{{ t('Account Details') }}</h3>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('Full Name') }}</label>
                                <input v-model="form.name" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('Email Address') }}</label>
                                <input v-model="form.email" type="email" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('Credits Balance') }}</label>
                                <input v-model="form.credits" type="number" step="0.01" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none" required />
                            </div>
                            <div>
                                <AppSelect v-model="form.plan_id" :options="planOptions" :label="t('Active Plan')" :placeholder="t('No Plan (Free)')" live-search />
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-100">
                            <h4 class="text-sm font-bold text-gray-900 mb-4 uppercase tracking-wider">{{ t('Change Password') }}</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('New Password') }}</label>
                                    <div class="relative">
                                        <input v-model="form.password" :type="showPassword ? 'text' : 'password'" :placeholder="t('Leave blank to keep current')" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 pr-11 text-sm text-gray-900 focus:border-primary-500 focus:outline-none" />
                                        <button type="button" class="absolute inset-y-0 right-0 inline-flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600" :aria-label="showPassword ? t('Hide password') : t('Show password')" @click="showPassword = !showPassword">
                                            <i :class="showPassword ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-base"></i>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('Confirm New Password') }}</label>
                                    <div class="relative">
                                        <input v-model="form.password_confirmation" :type="showPasswordConfirmation ? 'text' : 'password'" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 pr-11 text-sm text-gray-900 focus:border-primary-500 focus:outline-none" />
                                        <button type="button" class="absolute inset-y-0 right-0 inline-flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600" :aria-label="showPasswordConfirmation ? t('Hide password confirmation') : t('Show password confirmation')" @click="showPasswordConfirmation = !showPasswordConfirmation">
                                            <i :class="showPasswordConfirmation ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-base"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 pt-4">
                            <button type="button" @click="form.is_active = !form.is_active" :class="form.is_active ? 'bg-success-500' : 'bg-gray-300'" class="relative w-12 h-6 rounded-full transition-colors">
                                <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform shadow-sm" :class="form.is_active ? 'translate-x-6' : 'translate-x-0'"></span>
                            </button>
                            <span class="text-sm font-medium text-gray-700">{{ form.is_active ? t('User account is active') : t('User account is disabled') }}</span>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
                        <Link :href="route('admin.users.index')" class="px-5 py-2.5 text-sm font-bold text-gray-600 hover:text-gray-900 transition-colors">{{ t('Cancel') }}</Link>
                        <button type="submit" :disabled="form.processing" class="px-6 py-2.5 btn-primary rounded-xl text-sm font-bold transition-colors shadow-lg shadow-primary-500/20 disabled:opacity-50">
                            {{ form.processing ? t('Saving...') : t('Update User') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right: Activity & Sidebar -->
            <div class="space-y-6">
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6">
                    <h3 class="font-bold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <form :action="route('admin.users.impersonate', user.ulid)" method="POST" target="_blank">
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <button type="submit" class="w-full flex items-center justify-center gap-2 py-3 bg-accent-600 text-white font-bold text-sm rounded-xl hover:bg-accent-500 transition-colors shadow-lg shadow-accent-500/20">
                                <i class="ti ti-login-2 text-base"></i>
                                {{ $t('Login as User') }}
                            </button>
                        </form>
                        <button type="button" @click="notificationModalOpen = true" class="w-full py-3 bg-gray-50 border border-gray-200 text-gray-700 font-bold text-sm rounded-xl hover:bg-gray-100 transition-colors">
                            <span class="inline-flex items-center justify-center gap-2">
                                <i class="ti ti-bell text-base"></i>
                                {{ $t('Send Notification') }}
                            </span>
                        </button>
                        <button class="w-full py-3 bg-gray-50 border border-gray-200 text-gray-700 font-bold text-sm rounded-xl hover:bg-gray-100 transition-colors">
                            <span class="inline-flex items-center justify-center gap-2">
                                <i class="ti ti-mail text-base"></i>
                                {{ $t('Send Reset Email') }}
                            </span>
                        </button>
                        <button
                            type="button"
                            @click="deleteModalOpen = true"
                            class="w-full py-3 bg-red-50 border border-red-200 text-red-700 font-bold text-sm rounded-xl hover:bg-danger-100 transition-colors"
                        >
                            <span class="inline-flex items-center justify-center gap-2">
                                <i class="ti ti-trash text-base"></i>
                                {{ $t('Delete User') }}
                            </span>
                        </button>
                        <button
                            v-if="user.two_factor_enabled"
                            type="button"
                            :disabled="twoFactorForm.processing"
                            @click="disableTwoFactor"
                            class="w-full py-3 bg-red-50 border border-red-200 text-red-700 font-bold text-sm rounded-xl hover:bg-danger-100 transition-colors disabled:opacity-50"
                        >
                            <span class="inline-flex items-center justify-center gap-2">
                                <i class="ti ti-shield-x text-base"></i>
                                {{ twoFactorForm.processing ? $t('Disabling...') : $t('Disable User 2FA') }}
                            </span>
                        </button>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                        <h3 class="font-bold text-gray-900">{{ t('Recent Logins') }}</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <div v-for="log in user.login_history" :key="log.id" class="px-6 py-4">
                            <p class="text-sm font-medium text-gray-900">{{ log.ip }}</p>
                            <p class="text-[10px] text-gray-500 truncate mb-1">{{ log.user_agent }}</p>
                            <p class="text-[10px] text-gray-400">{{ formatDateTime(log.created_at) }}</p>
                        </div>
                        <div v-if="!user.login_history?.length" class="px-6 py-6 text-center text-xs text-gray-500 italic">
                            No login history available.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usage Stats -->
        <div class="mt-8 bg-white border border-gray-200 rounded-2xl shadow-sm p-6 grid grid-cols-2 md:grid-cols-4 gap-6">
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Today Usage</p>
                <p class="text-xl font-bold text-gray-900">{{ parseFloat(user.credits_used_today).toFixed(2) }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Month Usage</p>
                <p class="text-xl font-bold text-gray-900">{{ parseFloat(user.credits_used_month).toFixed(2) }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Total Referrals</p>
                <p class="text-xl font-bold text-gray-900">{{ user.referral_count }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Referral Earned</p>
                <p class="text-xl font-bold text-gray-900">${{ parseFloat(user.referral_earnings).toFixed(2) }}</p>
            </div>
        </div>

        <div class="mt-8 rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-3">
                <h3 class="text-lg font-semibold text-gray-900">{{ t('Usage History') }}</h3>
                <p class="text-sm text-gray-500">{{ t('Recent AI usage activity for this user.') }}</p>
            </div>

            <div v-if="usageHistory.length === 0" class="px-6 py-12 text-center text-sm text-gray-500">
                {{ t('No usage history found.') }}
            </div>

            <div v-else>
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">{{ t('Tool') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">{{ t('Model') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">{{ t('Provider') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">{{ t('Tokens') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">{{ t('Credits') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">{{ t('Status') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">{{ t('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="item in usageHistory" :key="item.id" class="hover:bg-gray-50/60 transition-colors">
                                <td class="px-6 py-4 font-medium text-gray-900">{{ item.tool_slug || t('Direct') }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ item.model || '—' }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ item.provider }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ totalTokens(item).toLocaleString() }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ formatCredits(item.credits_used) }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="statusBadgeClass(item.status)">
                                        {{ item.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-500">{{ formatDateTime(item.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-3 p-4 lg:hidden">
                    <div v-for="item in usageHistory" :key="item.id" class="rounded-xl border border-gray-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900">{{ item.tool_slug || t('Direct') }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ item.model || '—' }} · {{ item.provider }}</p>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="statusBadgeClass(item.status)">
                                {{ item.status }}
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <p class="text-gray-400">{{ t('Tokens') }}</p>
                                <p class="mt-1 font-medium text-gray-700">{{ totalTokens(item).toLocaleString() }}</p>
                            </div>
                            <div>
                                <p class="text-gray-400">{{ t('Credits') }}</p>
                                <p class="mt-1 font-medium text-gray-700">{{ formatCredits(item.credits_used) }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-gray-400">{{ t('Date') }}</p>
                                <p class="mt-1 font-medium text-gray-700">{{ formatDateTime(item.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ActionConfirmModal
            :open="deleteModalOpen"
            :title="t('Delete user?')"
            :message="t('This will soft delete :name and remove access until restored from the database.', { name: user.name })"
            :confirm-label="t('Delete User')"
            :processing-label="t('Deleting...')"
            variant="danger"
            @cancel="deleteModalOpen = false"
            @confirm="router.delete(route('admin.users.delete', user.ulid))"
        />

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="notificationModalOpen"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm"
                    @click.self="notificationModalOpen = false"
                >
                    <div class="w-full max-w-2xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg">
                        <div class="border-b border-gray-100 px-6 py-4">
                            <h3 class="text-lg font-bold text-gray-900">{{ t('Send Notification') }}</h3>
                        </div>

                        <div class="max-h-[70vh] overflow-y-auto p-6">
                            <div class="space-y-4 text-left">
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Title') }}</span>
                                    <input v-model="notificationForm.title" type="text" maxlength="120" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none">
                                    <p v-if="notificationForm.errors.title" class="mt-1 text-xs text-danger-600">{{ notificationForm.errors.title }}</p>
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Message') }}</span>
                                    <textarea v-model="notificationForm.message" rows="4" maxlength="1000" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none"></textarea>
                                    <p v-if="notificationForm.errors.message" class="mt-1 text-xs text-danger-600">{{ notificationForm.errors.message }}</p>
                                </label>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <AppSelect v-model="notificationForm.level" :label="t('Type')" :options="[
                                        { value: 'info', label: t('Info') },
                                        { value: 'success', label: t('Success') },
                                        { value: 'warning', label: t('Warning') },
                                        { value: 'error', label: t('Error') },
                                    ]" />
                                    <AppSelect v-model="notificationForm.deliver_via" :label="t('Deliver via')" :options="[
                                        { value: 'in_app', label: t('In-app only') },
                                        { value: 'in_app_email', label: t('In-app + email') },
                                        { value: 'email', label: t('Email only') },
                                    ]" />
                                </div>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Schedule') }}</span>
                                    <input v-model="notificationForm.scheduled_at" type="datetime-local" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none">
                                    <p class="mt-1 text-xs text-gray-400">{{ t('Leave blank to send now.') }}</p>
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Action URL') }}</span>
                                    <input v-model="notificationForm.action_url" type="text" maxlength="500" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none" :placeholder="t('Optional')">
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4">
                            <button type="button" class="px-4 py-2 text-sm font-semibold text-gray-600 transition hover:text-gray-900" :disabled="notificationForm.processing" @click="notificationModalOpen = false">
                                {{ t('Cancel') }}
                            </button>
                            <button type="button" class="btn-primary rounded-xl px-5 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="notificationForm.processing" @click="sendNotification">
                                {{ notificationForm.processing ? t('Sending...') : t('Send Notification') }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
