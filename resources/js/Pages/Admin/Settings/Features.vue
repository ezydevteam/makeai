<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    features: {
        subscriptions_enabled: boolean
        affiliate_enabled: boolean
        tickets_enabled: boolean
        contact_enabled: boolean
        blog_enabled: boolean
        notifications_enabled: boolean
        registration_enabled: boolean
        email_verification_enabled: boolean
        tools_review_approval_enabled: boolean
    }
}>()

const { t } = useTranslate()

const form = useForm({
    subscriptions_enabled: props.features.subscriptions_enabled,
    affiliate_enabled: props.features.affiliate_enabled,
    tickets_enabled: props.features.tickets_enabled,
    contact_enabled: props.features.contact_enabled,
    blog_enabled: props.features.blog_enabled,
    notifications_enabled: props.features.notifications_enabled,
    registration_enabled: props.features.registration_enabled,
    email_verification_enabled: props.features.email_verification_enabled,
    tools_review_approval_enabled: props.features.tools_review_approval_enabled,
})

const submit = () => {
    form.post(route('admin.features.settings.update'), {
        preserveScroll: true,
    })
}

const page = usePage()
const isExtendedLicense = computed(() => Boolean(page.props.isExtendedLicense))

const allFeatureToggles = [
    // The subscriptions toggle only exists on Extended License installs; the server
    // enforces this on save as well.
    { key: 'subscriptions_enabled', label: 'Premium Subscriptions', description: 'Enable the subscription/billing system for premium plans (requires Extended License).', extendedOnly: true },
    { key: 'affiliate_enabled', label: 'Affiliate Program', description: 'Enable the affiliate/referral program system.', extendedOnly: false },
    { key: 'tickets_enabled', label: 'Support Tickets', description: 'Enable the support ticket system for user inquiries.', extendedOnly: false },
    { key: 'contact_enabled', label: 'Contact Form', description: 'Enable the public contact form for visitor inquiries.', extendedOnly: false },
    { key: 'blog_enabled', label: 'Blog', description: 'Enable the blog system for publishing articles and posts.', extendedOnly: false },
    { key: 'notifications_enabled', label: 'Notifications', description: 'Hide notification bells and stop delivery when disabled.', extendedOnly: false },
    { key: 'registration_enabled', label: 'User Registration', description: 'Allow new users to register on the site.', extendedOnly: false },
    { key: 'email_verification_enabled', label: 'Email Verification', description: 'Require email verification after registration.', extendedOnly: false },
    { key: 'tools_review_approval_enabled', label: 'Tools Review Approval', description: 'Require admin approval before new tool reviews are published.', extendedOnly: false },
] as const

const featureToggles = computed(() => allFeatureToggles.filter((feature) => !feature.extendedOnly || isExtendedLicense.value))
</script>

<template>
    <Head :title="t('Features Settings')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Features') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Enable or disable core system features from one place.') }}</p>
            </div>
            <button type="button" @click="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg btn-primary px-5 py-2.5 text-sm font-semibold disabled:opacity-60">
                <span>{{ form.processing ? t('Saving...') : t('Save Settings') }}</span>
            </button>
        </section>

        <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="feature in featureToggles" :key="feature.key" class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/70">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t(feature.label) }}</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t(feature.description) }}</p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="(form as any)[feature.key]"
                            class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition"
                            :class="(form as any)[feature.key] ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                            @click="(form as any)[feature.key] = !(form as any)[feature.key]"
                        >
                            <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="(form as any)[feature.key] ? 'translate-x-5' : 'translate-x-0.5'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
