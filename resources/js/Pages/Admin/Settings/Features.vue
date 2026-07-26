<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    features: {
        subscriptions_enabled: boolean
        coupons_enabled: boolean
        affiliate_enabled: boolean
        tickets_enabled: boolean
        contact_enabled: boolean
        blog_enabled: boolean
        notifications_enabled: boolean
        registration_enabled: boolean
        email_verification_enabled: boolean
        onboarding_enabled: boolean
        tools_review_approval_enabled: boolean
        byok_enabled: boolean
        account_deletion_enabled: boolean
        playground_enabled: boolean
        chains_enabled: boolean
        tool_embeds_enabled: boolean
        global_tools_brand_voice_enabled: boolean
        optin_preferences_enabled: boolean
        cookie_preferences_enabled: boolean
        phone_required: boolean
        two_factor_required: boolean
        two_factor_sms_enabled: boolean
    }
}>()

const { t } = useTranslate()

const form = useForm({
    subscriptions_enabled: props.features.subscriptions_enabled,
    coupons_enabled: props.features.coupons_enabled,
    affiliate_enabled: props.features.affiliate_enabled,
    tickets_enabled: props.features.tickets_enabled,
    contact_enabled: props.features.contact_enabled,
    blog_enabled: props.features.blog_enabled,
    notifications_enabled: props.features.notifications_enabled,
    registration_enabled: props.features.registration_enabled,
    email_verification_enabled: props.features.email_verification_enabled,
    onboarding_enabled: props.features.onboarding_enabled,
    tools_review_approval_enabled: props.features.tools_review_approval_enabled,
    byok_enabled: props.features.byok_enabled,
    account_deletion_enabled: props.features.account_deletion_enabled,
    playground_enabled: props.features.playground_enabled,
    chains_enabled: props.features.chains_enabled,
    tool_embeds_enabled: props.features.tool_embeds_enabled,
    global_tools_brand_voice_enabled: props.features.global_tools_brand_voice_enabled,
    optin_preferences_enabled: props.features.optin_preferences_enabled,
    cookie_preferences_enabled: props.features.cookie_preferences_enabled,
    phone_required: props.features.phone_required,
    two_factor_required: props.features.two_factor_required,
    two_factor_sms_enabled: props.features.two_factor_sms_enabled,
})

const submit = () => {
    form.post(route('admin.features.settings.update'), {
        preserveScroll: true,
    })
}

const page = usePage()
const isExtendedLicense = computed(() => Boolean(page.props.isExtendedLicense))

type FeatureToggle = {
    key: string
    label: string
    description: string
    /** Monetization features that require an Extended License (also enforced on save). */
    extendedOnly?: boolean
    /** Sub-option: only shown while the named parent toggle is on. */
    dependsOn?: string
}

type FeatureGroup = {
    title: string
    icon: string
    features: FeatureToggle[]
}

const featureGroups: FeatureGroup[] = [
    {
        title: 'Account & Security',
        icon: 'ti ti-users',
        features: [
            { key: 'registration_enabled', label: 'User Registration', description: 'Allow new users to register on the site.' },
            { key: 'email_verification_enabled', label: 'Email Verification', description: 'Require email verification after registration.' },
            { key: 'onboarding_enabled', label: 'Onboarding After Registration', description: 'Show the guided onboarding modal to new users after they register.' },
            { key: 'phone_required', label: 'Require Phone Number', description: 'Force every user to add a phone number after registration before they can navigate the site.' },
            { key: 'two_factor_required', label: 'Require 2FA', description: 'Force every user to set up two-factor authentication before they can navigate the site.' },
            { key: 'two_factor_sms_enabled', label: 'SMS 2FA', description: 'Let users choose SMS text codes as their two-factor method (requires a configured SMS gateway and a verified phone number).' },
            { key: 'account_deletion_enabled', label: 'Self Account Deletion', description: 'Allow users to permanently delete their own account from their settings.' },
            { key: 'optin_preferences_enabled', label: 'Opt-in Preferences', description: 'Show the communication preferences (email, SMS and AI-improvement opt-ins) on the user privacy page.' },
            { key: 'cookie_preferences_enabled', label: 'Cookie Preferences', description: 'Let users review and change their cookie choices from the privacy page.' },
        ],
    },
    {
        title: 'Monetization',
        icon: 'ti ti-credit-card',
        features: [
            { key: 'subscriptions_enabled', label: 'Premium Subscriptions', description: 'Enable the subscription/billing system for premium plans (requires Extended License).', extendedOnly: true },
            { key: 'coupons_enabled', label: 'Coupon System', description: 'Enable discount coupons at checkout and in the admin (requires Extended License).', extendedOnly: true },
            { key: 'affiliate_enabled', label: 'Affiliate Program', description: 'Enable the affiliate/referral program system (requires Extended License).', extendedOnly: true },
        ],
    },
    {
        title: 'AI Features',
        icon: 'ti ti-sparkles',
        features: [
            { key: 'playground_enabled', label: 'AI Playground', description: 'Enable the AI Playground for users to test prompts across models.' },
            { key: 'chains_enabled', label: 'AI Chains', description: 'Enable multi-step AI tool chains/workflows.' },
            { key: 'global_tools_brand_voice_enabled', label: 'Brand Voice', description: 'Let users save a brand voice on their profile and apply it on tools that support it.' },
            { key: 'tool_embeds_enabled', label: 'Tool Embeds', description: 'Allow users to embed AI tools on external websites.' },
            { key: 'byok_enabled', label: 'User BYOK System', description: 'Let users add their own AI provider API keys (bring your own key) and generate without spending platform credits.' },
        ],
    },
    {
        title: 'Content & Support',
        icon: 'ti ti-article',
        features: [
            { key: 'blog_enabled', label: 'Blog', description: 'Enable the blog system for publishing articles and posts.' },
            { key: 'notifications_enabled', label: 'Notifications', description: 'Hide notification bells and stop delivery when disabled.' },
            { key: 'tickets_enabled', label: 'Support Tickets', description: 'Enable the support ticket system for user inquiries.' },
            { key: 'contact_enabled', label: 'Contact Form', description: 'Enable the public contact form for visitor inquiries.' },
            { key: 'tools_review_approval_enabled', label: 'Tools Review Approval', description: 'Require admin approval before new tool reviews are published.' },
        ],
    }
]

const isVisible = (feature: FeatureToggle) => {
    if (feature.extendedOnly && !isExtendedLicense.value) return false
    // Sub-option: hidden while its parent toggle is off.
    if (feature.dependsOn && !(form as any)[feature.dependsOn]) return false
    return true
}

// Groups that end up empty (e.g. Monetization on a Regular License) are dropped.
const visibleGroups = computed(() =>
    featureGroups
        .map((group) => ({ ...group, features: group.features.filter(isVisible) }))
        .filter((group) => group.features.length > 0),
)
</script>

<template>
    <Head :title="t('Features Settings')" />

    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Features') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Enable or disable core system features from one place.') }}</p>
            </div>
            <button type="button" @click="submit" :disabled="form.processing" class="inline-flex items-center gap-2 btn-primary-admin disabled:opacity-60">
                <i class="ti ti-device-floppy"></i>
                <span>{{ form.processing ? t('Saving...') : t('Save Settings') }}</span>
            </button>
        </section>

        <div
            v-for="group in visibleGroups"
            :key="group.title"
            class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-700 dark:bg-surface-900"
        >
            <div class="mb-4 flex items-center gap-2 border-b border-gray-100 pb-3 dark:border-surface-800">
                <i :class="group.icon" class="text-lg text-primary-600 dark:text-primary-400"></i>
                <h2 class="text-sm font-bold uppercase tracking-wide text-gray-700 dark:text-gray-200">{{ t(group.title) }}</h2>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="feature in group.features" :key="feature.key" class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/70">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t(feature.label) }}</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t(feature.description) }}</p>
                        </div>
                        <AppSwitch v-model="(form as any)[feature.key]" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
