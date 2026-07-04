<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'

interface Program {
    commission_type: 'percentage' | 'fixed'
    commission_value: string | number
    commission_on: 'first_purchase' | 'all_purchases' | 'subscription'
    cookie_days: number
    min_payout: string | number
    max_payout: string | number
    payouts_enabled: boolean
    payout_methods: string[] | null
    auto_approve_commissions: boolean
    referral_credits_enabled: boolean
    referral_credits_amount: string | number
    commission_hold_days: number
    allow_custom_alias: boolean
    terms_page_slug: string | null
    marketing_banners: Array<{ url: string; label?: string }> | null
    promotional_emails: Array<{ subject: string; body: string }> | null
    social_posts: Array<{ text: string; platform?: string }> | null
}

const props = defineProps<{
    program: Program
    termsPageOptions: Array<{ title: string; slug: string }>
}>()

const { t } = useTranslate()

const payoutMethodOptions = ['paypal', 'bank_transfer', 'credits']

const mkBanners = ref<Array<{ url: string; label: string }>>(
    (props.program.marketing_banners || []).map((b) => ({ url: b.url || '', label: b.label || '' })),
)
const mkEmails = ref<Array<{ subject: string; body: string }>>(
    (props.program.promotional_emails || []).map((e) => ({ subject: e.subject || '', body: e.body || '' })),
)
const mkPosts = ref<Array<{ text: string; platform: string }>>(
    (props.program.social_posts || []).map((p) => ({ text: p.text || '', platform: p.platform || '' })),
)

const addBanner = () => mkBanners.value.push({ url: '', label: '' })
const removeBanner = (i: number) => mkBanners.value.splice(i, 1)
const addEmail = () => mkEmails.value.push({ subject: '', body: '' })
const removeEmail = (i: number) => mkEmails.value.splice(i, 1)
const addPost = () => mkPosts.value.push({ text: '', platform: '' })
const removePost = (i: number) => mkPosts.value.splice(i, 1)

const commissionTypeOptions = computed(() => [
    { value: 'percentage', label: t('Percentage') },
    { value: 'fixed', label: t('Fixed') },
])
const commissionOnOptions = computed(() => [
    { value: 'first_purchase', label: t('First purchase') },
    { value: 'all_purchases', label: t('All purchases') },
    { value: 'subscription', label: t('Subscription') },
])

const form = useForm({
    commission_type: props.program.commission_type,
    commission_value: String(props.program.commission_value),
    commission_on: props.program.commission_on,
    cookie_days: props.program.cookie_days,
    min_payout: String(props.program.min_payout),
    max_payout: String(props.program.max_payout ?? 0),
    payouts_enabled: props.program.payouts_enabled,
    payout_methods: props.program.payout_methods ?? ['paypal', 'bank_transfer', 'credits'],
    auto_approve_commissions: props.program.auto_approve_commissions,
    referral_credits_enabled: props.program.referral_credits_enabled,
    referral_credits_amount: String(props.program.referral_credits_amount),
    commission_hold_days: props.program.commission_hold_days,
    allow_custom_alias: props.program.allow_custom_alias,
    terms_page_slug: props.program.terms_page_slug ?? '',
    marketing_banners: mkBanners.value,
    promotional_emails: mkEmails.value,
    social_posts: mkPosts.value,
})

const toggleMethod = (method: string) => {
    form.payout_methods = form.payout_methods.includes(method)
        ? form.payout_methods.filter((m) => m !== method)
        : [...form.payout_methods, method]
}

const save = () => {
    form.marketing_banners = mkBanners.value
    form.promotional_emails = mkEmails.value
    form.social_posts = mkPosts.value
    form.post(route('admin.affiliate.settings'), { preserveScroll: true })
}
</script>

<template>
    <Head :title="t('Affiliate Settings')" />

    <AdminLayout>
                <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Affiliate Settings') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure commission rules, payout handling, and campaign assets.') }}</p>
                </div>
                <button type="button" :disabled="form.processing" class="btn-primary self-start rounded-xl px-5 py-2.5 text-sm font-semibold text-white transition-colors disabled:opacity-50" @click="save">{{ form.processing ? t('Saving...') : t('Save Settings') }}</button>
            </div>

            <form class="space-y-6" @submit.prevent="save">
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <h2 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">{{ t('Program Settings') }}</h2>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ t('Control default commission behavior and affiliate eligibility rules.') }}</p>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <AppSelect v-model="form.commission_type" :label="t('Commission type')" :options="commissionTypeOptions" />
                        </div>
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Value') }}</span>
                            <input v-model="form.commission_value" type="number" min="0" step="0.01" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                            <p v-if="form.errors.commission_value" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.commission_value }}</p>
                        </label>
                        <div>
                            <AppSelect v-model="form.commission_on" :label="t('Commission on')" :options="commissionOnOptions" />
                        </div>
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Terms page slug') }}</span>
                            <input v-model="form.terms_page_slug" list="affiliate-terms-pages" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" :placeholder="t('Enter page slug')" />
                            <datalist id="affiliate-terms-pages">
                                <option v-for="page in termsPageOptions" :key="page.slug" :value="page.slug">{{ page.title }}</option>
                            </datalist>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Create a CMS page first, then enter its slug here.') }}</p>
                        </label>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Cookie days') }}</span>
                            <input v-model="form.cookie_days" type="number" min="1" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                            <p v-if="form.errors.cookie_days" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.cookie_days }}</p>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Minimum payout') }}</span>
                            <input v-model="form.min_payout" type="number" min="0.01" step="0.01" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                            <p v-if="form.errors.min_payout" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.min_payout }}</p>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Maximum payout') }}</span>
                            <input v-model="form.max_payout" type="number" min="0" step="0.01" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" :placeholder="t('0 = no limit')" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Set 0 for no upper limit on a single withdrawal.') }}</p>
                            <p v-if="form.errors.max_payout" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.max_payout }}</p>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Commission hold days') }}</span>
                            <input v-model="form.commission_hold_days" type="number" min="0" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                            <p v-if="form.errors.commission_hold_days" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.commission_hold_days }}</p>
                        </label>
                    </div>

                    <div class="mt-4">
                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Payout methods') }}</span>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="method in payoutMethodOptions" :key="method" type="button" class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors" :class="form.payout_methods.includes(method) ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'" @click="toggleMethod(method)">{{ t(method.replace('_', ' ')) }}</button>
                        </div>
                        <p v-if="form.errors.payout_methods" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.payout_methods }}</p>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                            <span>{{ t('Enable payout requests') }}</span>
                            <button type="button" role="switch" :aria-checked="form.payouts_enabled" class="relative inline-flex h-6 w-11 rounded-full transition" :class="form.payouts_enabled ? 'bg-primary-600' : 'bg-gray-300 dark:bg-gray-700'" @click="form.payouts_enabled = !form.payouts_enabled">
                                <span class="pointer-events-none inline-block h-6 w-6 rounded-full bg-white shadow ring-0 transition-transform" :class="form.payouts_enabled ? 'translate-x-5' : 'translate-x-0'"></span>
                            </button>
                        </div>
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                            <span>{{ t('Auto approve commissions') }}</span>
                            <button type="button" role="switch" :aria-checked="form.auto_approve_commissions" class="relative inline-flex h-6 w-11 rounded-full transition" :class="form.auto_approve_commissions ? 'bg-primary-600' : 'bg-gray-300 dark:bg-gray-700'" @click="form.auto_approve_commissions = !form.auto_approve_commissions">
                                <span class="pointer-events-none inline-block h-6 w-6 rounded-full bg-white shadow ring-0 transition-transform" :class="form.auto_approve_commissions ? 'translate-x-5' : 'translate-x-0'"></span>
                            </button>
                        </div>
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                            <span>{{ t('Award credits on first purchase') }}</span>
                            <button type="button" role="switch" :aria-checked="form.referral_credits_enabled" class="relative inline-flex h-6 w-11 rounded-full transition" :class="form.referral_credits_enabled ? 'bg-primary-600' : 'bg-gray-300 dark:bg-gray-700'" @click="form.referral_credits_enabled = !form.referral_credits_enabled">
                                <span class="pointer-events-none inline-block h-6 w-6 rounded-full bg-white shadow ring-0 transition-transform" :class="form.referral_credits_enabled ? 'translate-x-5' : 'translate-x-0'"></span>
                            </button>
                        </div>
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                            <span>{{ t('Allow custom alias') }}</span>
                            <button type="button" role="switch" :aria-checked="form.allow_custom_alias" class="relative inline-flex h-6 w-11 rounded-full transition" :class="form.allow_custom_alias ? 'bg-primary-600' : 'bg-gray-300 dark:bg-gray-700'" @click="form.allow_custom_alias = !form.allow_custom_alias">
                                <span class="pointer-events-none inline-block h-6 w-6 rounded-full bg-white shadow ring-0 transition-transform" :class="form.allow_custom_alias ? 'translate-x-5' : 'translate-x-0'"></span>
                            </button>
                        </div>
                    </div>

                    <label class="mt-4 block">
                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('First purchase credit amount') }}</span>
                        <input v-model="form.referral_credits_amount" type="number" min="0" step="0.0001" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Credits are added to the referrer only once, when the referred user completes the first purchase.') }}</p>
                        <p v-if="form.errors.referral_credits_amount" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.referral_credits_amount }}</p>
                    </label>
                </section>

                <div class="grid gap-6 xl:grid-cols-2">
                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Marketing Banners') }}</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Share banner assets with affiliates for external promotion.') }}</p>
                            </div>
                            <button type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-700" @click="addBanner">{{ t('Add banner') }}</button>
                        </div>
                        <div class="space-y-3">
                            <div v-for="(banner, index) in mkBanners" :key="index" class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                <div class="mb-3 flex items-center justify-between">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">#{{ index + 1 }}</span>
                                    <button type="button" class="text-xs font-medium text-red-600 transition-colors hover:text-red-700 dark:text-red-400 dark:hover:text-red-300" @click="removeBanner(index)">{{ t('Remove') }}</button>
                                </div>
                                <div class="space-y-3">
                                    <input v-model="banner.url" type="url" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" :placeholder="t('Banner URL')" />
                                    <input v-model="banner.label" type="text" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" :placeholder="t('Label optional')" />
                                </div>
                            </div>
                            <p v-if="mkBanners.length === 0" class="text-sm text-gray-500 dark:text-gray-400">{{ t('No banners configured.') }}</p>
                        </div>
                    </section>

                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Promotional Emails') }}</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Prepare reusable outreach copy for affiliate campaigns.') }}</p>
                            </div>
                            <button type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-700" @click="addEmail">{{ t('Add email') }}</button>
                        </div>
                        <div class="space-y-3">
                            <div v-for="(email, index) in mkEmails" :key="index" class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                <div class="mb-3 flex items-center justify-between">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">#{{ index + 1 }}</span>
                                    <button type="button" class="text-xs font-medium text-red-600 transition-colors hover:text-red-700 dark:text-red-400 dark:hover:text-red-300" @click="removeEmail(index)">{{ t('Remove') }}</button>
                                </div>
                                <div class="space-y-3">
                                    <input v-model="email.subject" type="text" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" :placeholder="t('Subject')" />
                                    <textarea v-model="email.body" rows="4" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" :placeholder="t('Email body')" />
                                </div>
                            </div>
                            <p v-if="mkEmails.length === 0" class="text-sm text-gray-500 dark:text-gray-400">{{ t('No email templates configured.') }}</p>
                        </div>
                    </section>
                </div>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Social Media Posts') }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Save short affiliate-ready post ideas for reuse across channels.') }}</p>
                        </div>
                        <button type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-700" @click="addPost">{{ t('Add post') }}</button>
                    </div>
                    <div class="space-y-3">
                        <div v-for="(post, index) in mkPosts" :key="index" class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">#{{ index + 1 }}</span>
                                <button type="button" class="text-xs font-medium text-red-600 transition-colors hover:text-red-700 dark:text-red-400 dark:hover:text-red-300" @click="removePost(index)">{{ t('Remove') }}</button>
                            </div>
                            <div class="space-y-3">
                                <input v-model="post.platform" type="text" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" :placeholder="t('Platform name')" />
                                <textarea v-model="post.text" rows="3" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" :placeholder="t('Post text')" />
                            </div>
                        </div>
                        <p v-if="mkPosts.length === 0" class="text-sm text-gray-500 dark:text-gray-400">{{ t('No social posts configured.') }}</p>
                    </div>
                </section>
            </form>
        </div>
    
    </AdminLayout>
</template>
