<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

interface Plan {
    id: number
    name: string
}

const props = defineProps<{
    plans: Plan[]
    countries: Array<{ value: string, label: string }>
}>()

const { t } = useTranslate()
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    credits: '0',
    plan_id: '',
    country: '',
    profession: '',
    is_active: true,
})

// Plans are a subscription (Extended License) feature. Hide the assignment on a
// Regular license, matching the edit form (Show.vue) and the backend, which nulls
// plan_id server-side regardless of what the form sends.
const page = usePage()
const isProAvailable = computed(() => Boolean(page.props.isProAvailable))

const planOptions = computed(() => [
    { value: '', label: t('No Plan (Free)') },
    ...props.plans.map((plan) => ({
        value: String(plan.id),
        label: plan.name,
    })),
])

const submit = () => {
    form.post(route('admin.users.store'))
}
</script>

<template>
    <Head :title="t('Create User')" />

    <AdminLayout>
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <div class="mb-8 flex items-center gap-3">
                <Link
                    :href="route('admin.users.index')"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-900 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                >
                    <i class="ti ti-arrow-left text-base"></i>
                </Link>

                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ t('Create User') }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Add a new platform user, assign credits, and set an optional plan.') }}
                    </p>
                </div>
            </div>

            <form @submit.prevent="submit" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ t('Account Details') }}
                    </h2>
                </div>

                <div class="space-y-6 p-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('Full Name') }}
                            </span>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                :placeholder="t('Enter full name')"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-danger-600">{{ form.errors.name }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('Email Address') }}
                            </span>
                            <input
                                v-model="form.email"
                                type="email"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                :placeholder="t('Enter email address')"
                            />
                            <p v-if="form.errors.email" class="mt-1 text-xs text-danger-600">{{ form.errors.email }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('Password') }}
                            </span>
                            <div class="relative">
                                <input
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 pr-11 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    :placeholder="t('Enter password')"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 inline-flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                    :aria-label="showPassword ? t('Hide password') : t('Show password')"
                                    :title="showPassword ? t('Hide password') : t('Show password')"
                                    @click="showPassword = !showPassword"
                                >
                                    <i :class="showPassword ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-base"></i>
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="mt-1 text-xs text-danger-600">{{ form.errors.password }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('Confirm Password') }}
                            </span>
                            <div class="relative">
                                <input
                                    v-model="form.password_confirmation"
                                    :type="showPasswordConfirmation ? 'text' : 'password'"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 pr-11 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    :placeholder="t('Confirm password')"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 inline-flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                    :aria-label="showPasswordConfirmation ? t('Hide password confirmation') : t('Show password confirmation')"
                                    :title="showPasswordConfirmation ? t('Hide password confirmation') : t('Show password confirmation')"
                                    @click="showPasswordConfirmation = !showPasswordConfirmation"
                                >
                                    <i :class="showPasswordConfirmation ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-base"></i>
                                </button>
                            </div>
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('Credits Balance') }}
                            </span>
                            <input
                                v-model="form.credits"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            />
                            <p v-if="form.errors.credits" class="mt-1 text-xs text-danger-600">{{ form.errors.credits }}</p>
                        </label>

                        <div v-if="isProAvailable" class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('Active Plan') }}
                            </span>
                            <AppSelect
                                v-model="form.plan_id"
                                :options="planOptions"
                                :placeholder="t('No Plan (Free)')"
                                live-search
                            />
                            <p v-if="form.errors.plan_id" class="mt-1 text-xs text-danger-600">{{ form.errors.plan_id }}</p>
                        </div>

                        <div class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('Country') }}
                            </span>
                            <AppSelect
                                v-model="form.country"
                                :options="countries"
                                :placeholder="t('Select Country')"
                                live-search
                            />
                            <p v-if="form.errors.country" class="mt-1 text-xs text-danger-600">{{ form.errors.country }}</p>
                        </div>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('Profession') }}
                            </span>
                            <input
                                v-model="form.profession"
                                type="text"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                :placeholder="t('e.g. Developer, Designer')"
                            />
                            <p v-if="form.errors.profession" class="mt-1 text-xs text-danger-600">{{ form.errors.profession }}</p>
                        </label>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ form.is_active ? t('User account is active') : t('User account is disabled') }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ t('Inactive users cannot sign in until you enable their account.') }}
                                </p>
                            </div>

                            <AppSwitch v-model="form.is_active" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                    <Link
                        :href="route('admin.users.index')"
                        class="px-5 py-2.5 text-sm font-medium text-gray-600 transition-colors hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                    >
                        {{ t('Cancel') }}
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="btn-primary-admin rounded-xl px-6 py-2.5 text-sm font-semibold text-white transition-colors disabled:opacity-50"
                    >
                        {{ form.processing ? t('Creating...') : t('Create User') }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
