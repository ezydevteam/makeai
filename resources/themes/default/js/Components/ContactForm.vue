<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useTranslate } from '@/Composables/useTranslate'
import AuthCaptchaField from '@/Components/Utility/AuthCaptchaField.vue'

const props = defineProps<{
    settings?: {
        enabled: boolean
        subject_mode: 'text' | 'dropdown'
        subject_options: string[]
        success_message: string
    } | null
}>();

const { t } = useTranslate()
const page = usePage()

interface CaptchaConfig {
    enabled: boolean
    provider: 'recaptcha' | 'hcaptcha'
    site_key: string
}

const captcha = computed<CaptchaConfig>(
    () => (page.props as unknown as { captcha?: CaptchaConfig }).captcha
        ?? { enabled: false, provider: 'recaptcha', site_key: '' }
)

// ContactController::store answers a rejection (rate limit, disabled form) with
// back()->with('error') — a redirect carrying no validation errors, which Inertia
// still reports as a success. Watching the flash instead of the callback keeps a
// throttled sender from being told their message was sent.
const flash = computed(() => (page.props.flash ?? {}) as { success?: string | null; error?: string | null })

const MESSAGE_MAX = 5000

const form = useForm({
    name: '',
    email: '',
    subject: '',
    message: '',
    website: '',
    captcha_token: '',
});

const isSubmitted = ref(false);
const submitError = ref('');

const remaining = computed(() => MESSAGE_MAX - form.message.length)

const submit = () => {
    submitError.value = '';

    form.post(route('contact.store'), {
        preserveScroll: true,
        onSuccess: () => {
            if (flash.value.error) {
                submitError.value = flash.value.error;

                return;
            }

            form.reset();
            isSubmitted.value = true;
        },
    });
};

// Clear a lingering rejection as soon as the sender starts editing again.
watch(() => [form.name, form.email, form.subject, form.message], () => {
    if (submitError.value) submitError.value = '';
});

const fieldClass = (hasError: unknown) => [
    'w-full rounded-xl border bg-white px-4 py-3 text-sm text-gray-900 outline-none transition-all placeholder:text-gray-400',
    'focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10',
    'dark:bg-surface-900/60 dark:text-white dark:placeholder:text-gray-500',
    hasError
        ? 'border-danger-400 dark:border-danger-500/60'
        : 'border-gray-200 hover:border-gray-300 dark:border-surface-800 dark:hover:border-surface-700',
]
</script>

<template>
    <div class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-xl shadow-gray-200/40 dark:border-surface-800 dark:bg-surface-900/50 dark:shadow-none">
        <!-- Disabled by the admin -->
        <div v-if="settings && !settings.enabled" class="px-8 py-16 text-center">
            <div class="mx-auto mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-50 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                <i class="ti ti-message-off text-2xl"></i>
            </div>
            <h3 class="mb-1 text-lg font-bold text-gray-900 dark:text-white">{{ t('Contact form unavailable') }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Please try again later.') }}</p>
        </div>

        <!-- Sent -->
        <div v-else-if="isSubmitted" class="px-8 py-16 text-center">
            <div class="mx-auto mb-6 inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-success-50 text-success-600 dark:bg-success-950/40 dark:text-success-400">
                <i class="ti ti-check text-3xl"></i>
            </div>
            <h3 class="mb-2 text-xl font-bold text-gray-900 dark:text-white">{{ t('Message sent') }}</h3>
            <p class="mx-auto max-w-sm text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                {{ settings?.success_message || t("Thank you for reaching out. We'll get back to you shortly.") }}
            </p>
            <button
                type="button"
                class="mt-7 inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 transition-all hover:border-primary-300 hover:text-primary-600 dark:border-surface-800 dark:text-gray-300 dark:hover:border-primary-500/40 dark:hover:text-primary-400"
                @click="isSubmitted = false"
            >
                <i class="ti ti-arrow-back-up"></i>
                {{ t('Send another message') }}
            </button>
        </div>

        <!-- Form -->
        <form v-else @submit.prevent="submit" class="p-6 sm:p-8 md:p-10">
            <input v-model="form.website" type="text" class="hidden" tabindex="-1" autocomplete="off">

            <div class="mb-8">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Send us a message') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Fill in the form and our team will get back to you.') }}
                </p>
            </div>

            <div
                v-if="submitError"
                class="mb-6 flex items-start gap-2.5 rounded-xl border border-danger-200 bg-danger-50/60 p-3.5 dark:border-danger-900/40 dark:bg-danger-950/20"
            >
                <i class="ti ti-alert-triangle mt-0.5 text-base text-danger-600 dark:text-danger-400"></i>
                <span class="text-sm text-danger-700 dark:text-danger-300">{{ submitError }}</span>
            </div>

            <div class="space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="name" class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Full Name') }}</label>
                        <div class="relative">
                            <i class="ti ti-user pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-base text-gray-400 dark:text-gray-500"></i>
                            <input
                                v-model="form.name"
                                type="text"
                                id="name"
                                required
                                maxlength="255"
                                autocomplete="name"
                                :class="[fieldClass(form.errors.name), '!pl-11']"
                                :placeholder="t('John Doe')"
                            >
                        </div>
                        <p v-if="form.errors.name" class="mt-1.5 text-xs font-medium text-danger-600 dark:text-danger-400">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label for="email" class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Email Address') }}</label>
                        <div class="relative">
                            <i class="ti ti-mail pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-base text-gray-400 dark:text-gray-500"></i>
                            <input
                                v-model="form.email"
                                type="email"
                                id="email"
                                required
                                maxlength="255"
                                autocomplete="email"
                                :class="[fieldClass(form.errors.email), '!pl-11']"
                                :placeholder="t('john@example.com')"
                            >
                        </div>
                        <p v-if="form.errors.email" class="mt-1.5 text-xs font-medium text-danger-600 dark:text-danger-400">{{ form.errors.email }}</p>
                    </div>
                </div>

                <div>
                    <label for="subject" class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Subject') }}</label>
                    <div class="relative">
                        <i class="ti ti-tag pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-base text-gray-400 dark:text-gray-500"></i>
                        <select
                            v-if="settings?.subject_mode === 'dropdown' && settings.subject_options.length"
                            v-model="form.subject"
                            id="subject"
                            :class="[fieldClass(form.errors.subject), '!pl-11 appearance-none pr-10']"
                        >
                            <option value="">{{ t('Select a subject') }}</option>
                            <option v-for="subject in settings.subject_options" :key="subject" :value="subject">{{ subject }}</option>
                        </select>
                        <input
                            v-else
                            v-model="form.subject"
                            type="text"
                            id="subject"
                            maxlength="255"
                            :class="[fieldClass(form.errors.subject), '!pl-11']"
                            :placeholder="t('How can we help you?')"
                        >
                        <i
                            v-if="settings?.subject_mode === 'dropdown' && settings.subject_options.length"
                            class="ti ti-chevron-down pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-base text-gray-400 dark:text-gray-500"
                        ></i>
                    </div>
                    <p v-if="form.errors.subject" class="mt-1.5 text-xs font-medium text-danger-600 dark:text-danger-400">{{ form.errors.subject }}</p>
                </div>

                <div>
                    <div class="mb-2 flex items-baseline justify-between gap-3">
                        <label for="message" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Message') }}</label>
                        <span
                            class="text-xs tabular-nums"
                            :class="remaining < 100 ? 'font-semibold text-danger-500' : 'text-gray-400 dark:text-gray-500'"
                        >
                            {{ remaining }}
                        </span>
                    </div>
                    <textarea
                        v-model="form.message"
                        id="message"
                        rows="6"
                        required
                        :maxlength="MESSAGE_MAX"
                        :class="[fieldClass(form.errors.message), 'resize-none leading-relaxed']"
                        :placeholder="t('Tell us more about your inquiry...')"
                    ></textarea>
                    <p v-if="form.errors.message" class="mt-1.5 text-xs font-medium text-danger-600 dark:text-danger-400">{{ form.errors.message }}</p>
                </div>

                <AuthCaptchaField
                    v-if="captcha.enabled"
                    v-model="form.captcha_token"
                    :config="captcha"
                    :error="form.errors.captcha_token"
                />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="btn-primary mt-8 flex w-full items-center justify-center gap-2 rounded-xl py-4 text-sm font-bold shadow-lg shadow-primary-600/20 transition-all hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0"
            >
                <i :class="form.processing ? 'ti ti-loader-2 animate-spin' : 'ti ti-send'" class="text-base"></i>
                {{ form.processing ? t('Sending...') : t('Send Message') }}
            </button>

            <p class="mt-4 text-center text-xs text-gray-400 dark:text-gray-500">
                {{ t('We usually reply within one business day.') }}
            </p>
        </form>
    </div>
</template>
