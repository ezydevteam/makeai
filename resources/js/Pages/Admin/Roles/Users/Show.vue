<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue';
import AppModal from '@/Components/UI/AppModal.vue';
import AppSelect from '@/Components/UI/AppSelect.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useTranslate } from '@/Composables/useTranslate';
import { useNumberFormat } from '@/Composables/useNumberFormat';
import { useAdminCan } from '@/Composables/useAdminCan';

defineOptions({ layout: AdminLayout });
const { formatDateTime } = useDateFormat();
const { formatCurrency } = useNumberFormat();
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const { t } = useTranslate();
const { canAny } = useAdminCan();

const props = defineProps<{
    user: any,
    plans: Array<{ id: number, name: string }>,
    countries: Array<{ value: string, label: string }>,
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
    country: props.user.country ?? '',
    profession: props.user.profession ?? '',
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
const isDeleting = ref(false);

const deleteUser = () => {
    router.delete(route('admin.users.delete', props.user.ulid), {
        onBefore: () => {
            isDeleting.value = true;
        },
        onFinish: () => {
            isDeleting.value = false;
            deleteModalOpen.value = false;
        },
    });
};

const isTogglingStatus = ref(false);
const statusModalOpen = ref(false);

const toggleUserStatus = () => {
    router.post(route('admin.users.toggle-status', props.user.ulid), {}, {
        onBefore: () => {
            isTogglingStatus.value = true;
        },
        onFinish: () => {
            isTogglingStatus.value = false;
            statusModalOpen.value = false;
            form.is_active = props.user.is_active;
        },
    });
};

const handleStatusToggle = () => {
    statusModalOpen.value = true;
};

const isTogglingBan = ref(false);
const banModalOpen = ref(false);
const banForm = useForm({ ban_reason: '' });

const handleBanToggle = () => {
    banForm.clearErrors();
    banForm.ban_reason = '';
    banModalOpen.value = true;
};

const toggleUserBan = () => {
    banForm.post(route('admin.users.toggle-ban', props.user.ulid), {
        preserveScroll: true,
        onBefore: () => { isTogglingBan.value = true; },
        onSuccess: () => { banModalOpen.value = false; },
        onFinish: () => { isTogglingBan.value = false; },
    });
};

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
    <div class="mx-auto w-full sm:max-w-5xl px-6">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ t('Edit User') }}: {{ user.name }}</h1>
                <p class="text-sm text-gray-500">{{ t('ULID') }}: <span class="font-mono text-gray-400">{{ user.ulid }}</span></p>
            </div>

            <Link
                :href="route('admin.users.index')"
                class="shrink-0 inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
            >
                <i class="ti ti-arrow-left text-base"></i>
                {{ t('Back') }}
            </Link>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left: Profile Info -->
            <div class="lg:col-span-2 space-y-6">
                <form @submit.prevent="submit" class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden dark:bg-surface-900 dark:border-gray-800">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 dark:border-gray-800 dark:bg-surface-800/50">
                        <h3 class="font-bold text-gray-900 dark:text-white">{{ t('Account Details') }}</h3>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ t('Full Name') }}</label>
                                <input v-model="form.name" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:bg-surface-800 dark:border-gray-700 dark:text-white" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ t('Email Address') }}</label>
                                <input v-model="form.email" type="email" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:bg-surface-800 dark:border-gray-700 dark:text-white" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ t('Credits Balance') }}</label>
                                <input v-model="form.credits" type="number" step="0.01" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:bg-surface-800 dark:border-gray-700 dark:text-white" required />
                            </div>
                            <div>
                                <AppSelect v-model="form.plan_id" :options="planOptions" :label="t('Active Plan')" :placeholder="t('No Plan (Free)')" live-search />
                            </div>
                            <div>
                                <AppSelect v-model="form.country" :options="countries" :label="t('Country')" :placeholder="t('Select Country')" live-search />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ t('Profession') }}</label>
                                <input v-model="form.profession" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:bg-surface-800 dark:border-gray-700 dark:text-white" :placeholder="t('e.g. Developer, Designer')" />
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-100 dark:border-gray-800">
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-4 uppercase tracking-wider">{{ t('Change Password') }}</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ t('New Password') }}</label>
                                    <div class="relative">
                                        <input v-model="form.password" :type="showPassword ? 'text' : 'password'" :placeholder="t('Leave blank to keep current')" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 pr-11 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:bg-surface-800 dark:border-gray-700 dark:text-white" />
                                        <button type="button" class="absolute inset-y-0 right-0 inline-flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300" :aria-label="showPassword ? t('Hide password') : t('Show password')" @click="showPassword = !showPassword">
                                            <i :class="showPassword ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-base"></i>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ t('Confirm New Password') }}</label>
                                    <div class="relative">
                                        <input v-model="form.password_confirmation" :type="showPasswordConfirmation ? 'text' : 'password'" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 pr-11 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:bg-surface-800 dark:border-gray-700 dark:text-white" />
                                        <button type="button" class="absolute inset-y-0 right-0 inline-flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300" :aria-label="showPasswordConfirmation ? t('Hide password confirmation') : t('Show password confirmation')" @click="showPasswordConfirmation = !showPasswordConfirmation">
                                            <i :class="showPasswordConfirmation ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-base"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>


                    </div>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3 dark:bg-surface-800/40 dark:border-gray-800">
                        <Link :href="route('admin.users.index')" class="px-5 py-2.5 text-sm font-bold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">{{ t('Cancel') }}</Link>
                        <button v-if="canAny(['users.edit', 'users.manage'])" type="submit" :disabled="form.processing" class="px-6 py-2.5 btn-primary-admin rounded-xl text-sm font-bold transition-colors shadow-lg shadow-primary-500/20 disabled:opacity-50">
                            {{ form.processing ? t('Saving...') : t('Update User') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right: Activity & Sidebar -->
            <div class="space-y-6">
                <div v-if="canAny(['users.edit', 'users.delete', 'users.impersonate', 'users.manage'])" class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 dark:bg-surface-900 dark:border-gray-800">
                    <h3 class="font-bold text-gray-900 dark:text-white mb-4">{{ t('Quick Actions') }}</h3>
                    <div class="space-y-3">
                        <form v-if="canAny(['users.impersonate', 'users.manage'])" :action="route('admin.users.impersonate', user.ulid)" method="POST" target="_blank">
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <button type="submit" class="w-full flex items-center justify-center gap-2 py-3 bg-primary-50 text-primary-600 border border-primary-200 rounded-xl font-bold text-sm hover:bg-primary-100 dark:bg-primary-950/20 dark:border-primary-900/30 dark:text-primary-400 dark:hover:bg-primary-900/30 transition-colors">
                                <i class="ti ti-login-2 text-base"></i>
                                {{ $t('Login as User') }}
                            </button>
                        </form>
                        <button v-if="canAny(['users.edit', 'users.manage'])" type="button" @click="notificationModalOpen = true" class="w-full py-3 bg-purple-50 border border-purple-200 text-purple-600 font-bold text-sm rounded-xl hover:bg-purple-100 dark:bg-purple-950/20 dark:border-purple-900/30 dark:text-purple-400 dark:hover:bg-purple-900/30 transition-colors">
                            <span class="inline-flex items-center justify-center gap-2">
                                <i class="ti ti-bell text-base"></i>
                                {{ $t('Send Notification') }}
                            </span>
                        </button>
                        <button
                            v-if="canAny(['users.edit', 'users.manage'])"
                            type="button"
                            :disabled="isTogglingStatus"
                            @click="handleStatusToggle"
                            :class="user.is_active
                                ? 'w-full py-3 bg-red-50 border border-red-200 text-red-700 font-bold text-sm rounded-xl hover:bg-danger-100 dark:bg-red-950/20 dark:border-red-900/30 dark:text-red-400 dark:hover:bg-red-900/30 transition-colors disabled:opacity-50'
                                : 'w-full py-3 bg-green-50 border border-green-200 text-green-700 font-bold text-sm rounded-xl hover:bg-green-100 dark:bg-green-950/20 dark:border-green-900/30 dark:text-green-400 dark:hover:bg-green-900/30 transition-colors disabled:opacity-50'"
                        >
                            <span class="inline-flex items-center justify-center gap-2">
                                <i :class="user.is_active ? 'ti ti-user-off text-base' : 'ti ti-user-check text-base'"></i>
                                {{ isTogglingStatus ? $t('Processing...') : (user.is_active ? $t('Deactivate Account') : $t('Activate Account')) }}
                            </span>
                        </button>
                        <button
                            v-if="canAny(['users.edit', 'users.manage'])"
                            type="button"
                            :disabled="isTogglingBan"
                            @click="handleBanToggle"
                            :class="user.is_banned
                                ? 'w-full py-3 bg-green-50 border border-green-200 text-green-700 font-bold text-sm rounded-xl hover:bg-green-100 dark:bg-green-950/20 dark:border-green-900/30 dark:text-green-400 dark:hover:bg-green-900/30 transition-colors disabled:opacity-50'
                                : 'w-full py-3 bg-orange-50 border border-orange-200 text-orange-700 font-bold text-sm rounded-xl hover:bg-orange-100 dark:bg-orange-950/20 dark:border-orange-900/30 dark:text-orange-400 dark:hover:bg-orange-900/30 transition-colors disabled:opacity-50'"
                        >
                            <span class="inline-flex items-center justify-center gap-2">
                                <i :class="user.is_banned ? 'ti ti-lock-open text-base' : 'ti ti-ban text-base'"></i>
                                {{ isTogglingBan ? $t('Processing...') : (user.is_banned ? $t('Unban User') : $t('Ban User')) }}
                            </span>
                        </button>
                        <button
                            v-if="canAny(['users.delete', 'users.manage'])"
                            type="button"
                            @click="deleteModalOpen = true"
                            class="w-full py-3 bg-red-50 border border-red-200 text-red-700 font-bold text-sm rounded-xl hover:bg-danger-100 dark:bg-red-950/20 dark:border-red-900/30 dark:text-red-400 dark:hover:bg-red-900/30 transition-colors"
                        >
                            <span class="inline-flex items-center justify-center gap-2">
                                <i class="ti ti-trash text-base"></i>
                                {{ $t('Delete User') }}
                            </span>
                        </button>
                        <button
                            v-if="user.two_factor_enabled && canAny(['users.edit', 'users.manage'])"
                            type="button"
                            :disabled="twoFactorForm.processing"
                            @click="disableTwoFactor"
                            class="w-full py-3 bg-red-50 border border-red-200 text-red-700 font-bold text-sm rounded-xl hover:bg-danger-100 dark:bg-red-950/20 dark:border-red-900/30 dark:text-red-400 dark:hover:bg-red-900/30 transition-colors disabled:opacity-50"
                        >
                            <span class="inline-flex items-center justify-center gap-2">
                                <i class="ti ti-shield-x text-base"></i>
                                {{ twoFactorForm.processing ? $t('Disabling...') : $t('Disable User 2FA') }}
                            </span>
                        </button>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden dark:bg-surface-900 dark:border-gray-800">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 dark:border-gray-800 dark:bg-surface-800/50">
                        <h3 class="font-bold text-gray-900 dark:text-white">{{ t('Recent Logins') }}</h3>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        <div v-for="log in user.login_history" :key="log.id" class="px-6 py-4">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ log.ip }}</p>
                            <p class="text-[10px] text-gray-500 truncate mb-1 dark:text-gray-400">{{ log.user_agent }}</p>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500">{{ formatDateTime(log.created_at) }}</p>
                        </div>
                        <div v-if="!user.login_history?.length" class="px-6 py-6 text-center text-xs text-gray-500 italic dark:text-gray-400">
                            No login history available.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usage Stats -->
        <div class="mt-8 bg-white border border-gray-200 rounded-2xl shadow-sm p-6 grid grid-cols-2 md:grid-cols-4 gap-6 dark:bg-surface-900 dark:border-gray-800">
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase mb-1 dark:text-gray-500">Today Usage</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ parseFloat(user.credits_used_today).toFixed(2) }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase mb-1 dark:text-gray-500">Month Usage</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ parseFloat(user.credits_used_month).toFixed(2) }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase mb-1 dark:text-gray-500">Total Referrals</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ user.referral_count }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase mb-1 dark:text-gray-500">Referral Earned</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(parseFloat(user.referral_earnings)) }}</p>
            </div>
        </div>

        <div class="mt-8 rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:bg-surface-900 dark:border-gray-800">
            <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-3 dark:border-gray-800 dark:bg-surface-800/80">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Usage History') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Recent AI usage activity for this user.') }}</p>
            </div>

            <div v-if="usageHistory.length === 0" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ t('No usage history found.') }}
            </div>

            <div v-else>
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 border-b border-gray-100 dark:bg-surface-850 dark:border-gray-800">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Tool') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Model') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Provider') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Tokens') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Credits') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="item in usageHistory" :key="item.id" class="hover:bg-gray-50/60 dark:hover:bg-gray-800/40 transition-colors">
                                <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">{{ item.tool_slug || t('Direct') }}</td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ item.model || 'â€”' }}</td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ item.provider }}</td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ totalTokens(item).toLocaleString() }}</td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ formatCredits(item.credits_used) }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="statusBadgeClass(item.status)">
                                        {{ item.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400">{{ formatDateTime(item.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-3 p-4 lg:hidden">
                    <div v-for="item in usageHistory" :key="item.id" class="rounded-xl border border-gray-200 dark:border-gray-800 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ item.tool_slug || t('Direct') }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ item.model || 'â€”' }} Â· {{ item.provider }}</p>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="statusBadgeClass(item.status)">
                                {{ item.status }}
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <p class="text-gray-400 dark:text-gray-500">{{ t('Tokens') }}</p>
                                <p class="mt-1 font-medium text-gray-700 dark:text-gray-200">{{ totalTokens(item).toLocaleString() }}</p>
                            </div>
                            <div>
                                <p class="text-gray-400 dark:text-gray-500">{{ t('Credits') }}</p>
                                <p class="mt-1 font-medium text-gray-700 dark:text-gray-200">{{ formatCredits(item.credits_used) }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-gray-400 dark:text-gray-500">{{ t('Date') }}</p>
                                <p class="mt-1 font-medium text-gray-700 dark:text-gray-200">{{ formatDateTime(item.created_at) }}</p>
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
            :processing="isDeleting"
            variant="danger"
            @cancel="deleteModalOpen = false"
            @confirm="deleteUser"
        />

        <ActionConfirmModal
            :open="statusModalOpen"
            :title="user.is_active ? t('Deactivate Account?') : t('Activate Account?')"
            :message="user.is_active
                ? t('Are you sure you want to deactivate the account of :name? This will suspend their access to the application immediately.', { name: user.name })
                : t('Are you sure you want to activate the account of :name? This will restore their access to the application immediately.', { name: user.name })"
            :confirm-label="user.is_active ? t('Deactivate') : t('Activate')"
            :processing-label="user.is_active ? t('Deactivating...') : t('Activating...')"
            :processing="isTogglingStatus"
            :variant="user.is_active ? 'danger' : 'primary'"
            @cancel="statusModalOpen = false"
            @confirm="toggleUserStatus"
        />

        <ActionConfirmModal
            :open="banModalOpen"
            :title="user.is_banned ? t('Unban User?') : t('Ban User?')"
            :message="user.is_banned
                ? t('Are you sure you want to unban :name? This restores their access across the application immediately.', { name: user.name })
                : t('Are you sure you want to ban :name? This blocks their access across the entire application â€” login, AI usage, comments, reviews and support â€” immediately.', { name: user.name })"
            :confirm-label="user.is_banned ? t('Unban') : t('Ban User')"
            :processing-label="user.is_banned ? t('Unbanning...') : t('Banning...')"
            :processing="isTogglingBan"
            :variant="user.is_banned ? 'primary' : 'danger'"
            @cancel="banModalOpen = false"
            @confirm="toggleUserBan"
        >
            <div v-if="!user.is_banned" class="mt-4 text-left">
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ t('Ban reason') }} <span class="text-gray-400">({{ t('optional') }})</span>
                </label>
                <textarea
                    v-model="banForm.ban_reason"
                    rows="3"
                    maxlength="500"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    :placeholder="t('Visible to admins only. e.g. Spam / abuse / chargeback.')"
                ></textarea>
                <p v-if="banForm.errors.ban_reason" class="mt-1 text-xs text-danger-600">{{ banForm.errors.ban_reason }}</p>
            </div>
            <p v-else-if="user.ban_reason" class="mt-4 text-left text-xs text-gray-500 dark:text-gray-400">
                <span class="font-semibold">{{ t('Current reason') }}:</span> {{ user.ban_reason }}
            </p>
        </ActionConfirmModal>

        <AppModal
            :open="notificationModalOpen"
            max-width="max-w-2xl"
            :title="t('Send Notification')"
            has-form
            :confirm-text="t('Send Notification')"
            :confirm-loading="notificationForm.processing"
            confirm-loading-text="Sending..."
            @close="notificationModalOpen = false"
            @submit="sendNotification"
        >
            <div class="space-y-4 text-left">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Title') }}</span>
                    <input v-model="notificationForm.title" type="text" maxlength="120" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" :placeholder="t('e.g. System maintenance update')">
                    <p v-if="notificationForm.errors.title" class="mt-1 text-xs text-danger-600">{{ notificationForm.errors.title }}</p>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Message') }}</span>
                    <textarea v-model="notificationForm.message" rows="4" maxlength="1000" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" :placeholder="t('Enter the details of the notification here...')"></textarea>
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
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Schedule') }}</span>
                    <input v-model="notificationForm.scheduled_at" type="datetime-local" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ t('Leave blank to send now.') }}</p>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Action URL') }}</span>
                    <input v-model="notificationForm.action_url" type="text" maxlength="500" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" :placeholder="t('e.g. https://example.com/billing (Optional)')">
                </label>
            </div>
        </AppModal>
    </div>
</template>
