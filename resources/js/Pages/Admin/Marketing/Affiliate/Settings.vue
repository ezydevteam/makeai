<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useNumberFormat } from '@/Composables/useNumberFormat'

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
const { currencySymbol } = useNumberFormat()

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

// Affix inside the commission value input: % for percentage, store currency
// symbol for a fixed amount.
const commissionValueAffix = computed(() =>
    form.commission_type === 'percentage' ? '%' : currencySymbol.value,
)
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
        <div class="mx-auto max-w-5xl px-4 sm:px-6">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Affiliate Settings') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure commission rules, payout handling, and campaign assets.') }}</p>
                </div>
                <button type="button" :disabled="form.processing" class="shrink-0 btn-primary-admin disabled:opacity-50" @click="save">
                    <i class="ti ti-device-floppy"></i>
                    {{ form.processing ? t('Saving...') : t('Save Settings') }}
                </button>
            </div>

            <form class="space-y-6" @submit.prevent="save">
                <!-- Program Settings Card -->
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <h2 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">{{ t('Program Settings') }}</h2>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ t('Control default commission behavior and affiliate eligibility rules.') }}</p>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <AppSelect v-model="form.commission_type" :label="t('Commission type')" :options="commissionTypeOptions" />
                        </div>
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Value') }}</span>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-sm font-medium text-gray-400 dark:text-gray-500">{{ commissionValueAffix }}</span>
                                <input v-model="form.commission_value" type="number" min="0" step="0.01" class="w-full rounded-xl border border-gray-200 bg-white py-2.5 pl-9 pr-4 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                            </div>
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
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Cookie days') }}</span>
                            <input v-model="form.cookie_days" type="number" min="1" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                            <p v-if="form.errors.cookie_days" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.cookie_days }}</p>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Commission hold days') }}</span>
                            <input v-model="form.commission_hold_days" type="number" min="0" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                            <p v-if="form.errors.commission_hold_days" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.commission_hold_days }}</p>
                        </label>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-3">
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                            <span>{{ t('Auto approve commissions') }}</span>
                            <AppSwitch v-model="form.auto_approve_commissions" />
                        </div>
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                            <span>{{ t('Allow custom alias') }}</span>
                            <AppSwitch v-model="form.allow_custom_alias" />
                        </div>
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                            <span>{{ t('Award credits on first purchase') }}</span>
                            <AppSwitch v-model="form.referral_credits_enabled" />
                        </div>
                    </div>

                    <div v-if="form.referral_credits_enabled" class="mt-4">
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('First purchase credit amount') }}</span>
                            <input v-model="form.referral_credits_amount" type="number" min="0" step="0.0001" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Credits are added to the referrer only once, when the referred user completes the first purchase.') }}</p>
                            <p v-if="form.errors.referral_credits_amount" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.referral_credits_amount }}</p>
                        </label>
                    </div>
                </section>

                <!-- Payout Settings Card -->
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <h2 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">{{ t('Payout Settings') }}</h2>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure withdrawal limits, locking terms, and supported payment methods.') }}</p>

                    <div class="grid gap-4 md:grid-cols-2">
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
                    </div>

                    <div class="mt-4">
                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Payout methods') }}</span>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="method in payoutMethodOptions" :key="method" type="button" class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors" :class="form.payout_methods.includes(method) ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'" @click="toggleMethod(method)">{{ t(method.replace('_', ' ')) }}</button>
                        </div>
                        <p v-if="form.errors.payout_methods" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.payout_methods }}</p>
                    </div>

                    <div class="mt-4">
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 max-w-md">
                            <span>{{ t('Enable payout requests') }}</span>
                            <AppSwitch v-model="form.payouts_enabled" />
                        </div>
                    </div>
                </section>

                <div class="grid gap-6 xl:grid-cols-2">
                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Marketing Banners') }}</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Share banner assets with affiliates for external promotion.') }}</p>
                            </div>
                            <button type="button" class="shrink-0 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-700" @click="addBanner">
                                <i class="ti ti-plus"></i>
                                {{ t('Add banner') }}
                            </button>
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
                            <button type="button" class="shrink-0 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-700" @click="addEmail">
                                <i class="ti ti-plus"></i>
                                {{ t('Add email') }}
                            </button>
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
                        <button type="button" class="rshrink-0 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-700" @click="addPost">
                            <i class="ti ti-plus"></i>
                            {{ t('Add post') }}
                        </button>
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
