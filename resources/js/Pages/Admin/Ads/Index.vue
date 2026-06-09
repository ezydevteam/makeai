<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useNumberFormat } from '@/Composables/useNumberFormat'

defineOptions({ layout: AdminLayout })

interface PlanOption { id: number; name: string }

const props = defineProps<{
    ads: any[]
    zones: Record<string, string>
    settings: {
        ads_enabled: boolean
        adsense_publisher_id: string
        ads_auto_ads_enabled: boolean
        ads_disable_for_subscribed_users: boolean
        ads_disabled_plan_ids: number[]
    }
    plans: PlanOption[]
}>()

const { t } = useTranslate()
const { formatNumber } = useNumberFormat()
const settingsForm = useForm({
    ads_enabled: props.settings.ads_enabled,
    adsense_publisher_id: props.settings.adsense_publisher_id,
    ads_auto_ads_enabled: props.settings.ads_auto_ads_enabled,
    ads_disable_for_subscribed_users: props.settings.ads_disable_for_subscribed_users,
    ads_disabled_plan_ids: props.settings.ads_disabled_plan_ids ?? [],
})

const toggleAd = (id: number) => {
    router.post(route('admin.ads.toggle', id), {}, { preserveScroll: true })
}

const deleteAd = (id: number) => {
    if (confirm(t('Are you sure you want to delete this advertisement?'))) {
        router.delete(route('admin.ads.delete', id), { preserveScroll: true })
    }
}

const togglePlan = (planId: number) => {
    settingsForm.ads_disabled_plan_ids = settingsForm.ads_disabled_plan_ids.includes(planId)
        ? settingsForm.ads_disabled_plan_ids.filter((id) => id !== planId)
        : [...settingsForm.ads_disabled_plan_ids, planId]
}

const saveSettings = () => settingsForm.post(route('admin.ads.settings'), { preserveScroll: true })
</script>

<template>
    <Head :title="t('Ads')" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ t('Ads') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Manage zones, AdSense, custom HTML, image links, and audience rules.') }}</p>
            </div>
            <Link :href="route('admin.ads.create')" class="rounded-lg btn-primary">
                {{ t('Create ad') }}
            </Link>
        </div>

        <form class="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900" @submit.prevent="saveSettings">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Global Ad Settings') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ t('Set publisher ID, auto ads, and subscriber visibility rules.') }}</p>
                </div>
                <button type="submit" :disabled="settingsForm.processing" class="rounded-lg btn-primary disabled:opacity-60">
                    {{ settingsForm.processing ? t('Saving...') : t('Save settings') }}
                </button>
            </div>

            <div class="grid gap-4 lg:grid-cols-4">
                <label class="block lg:col-span-2">
                    <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('AdSense Publisher ID') }}</span>
                    <input v-model="settingsForm.adsense_publisher_id" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" placeholder="ca-pub-xxxxxxxx" />
                </label>
                <label class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium">
                    {{ t('Enable ads') }}
                    <input v-model="settingsForm.ads_enabled" type="checkbox" />
                </label>
                <label class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium">
                    {{ t('Auto ads') }}
                    <input v-model="settingsForm.ads_auto_ads_enabled" type="checkbox" />
                </label>
                <label class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium lg:col-span-2">
                    {{ t('Disable ads for subscribed users') }}
                    <input v-model="settingsForm.ads_disable_for_subscribed_users" type="checkbox" />
                </label>
                <div class="lg:col-span-2">
                    <span class="mb-2 block text-sm font-medium text-gray-700">{{ t('Disable ads for plans') }}</span>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="plan in plans" :key="plan.id" type="button" :class="settingsForm.ads_disabled_plan_ids.includes(plan.id) ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-600'" class="rounded-full px-3 py-1 text-xs font-bold" @click="togglePlan(plan.id)">
                            {{ plan.name }}
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3">{{ t('Ad') }}</th>
                        <th class="px-4 py-3">{{ t('Zone') }}</th>
                        <th class="px-4 py-3">{{ t('Audience') }}</th>
                        <th class="px-4 py-3 text-center">{{ t('Stats') }}</th>
                        <th class="px-4 py-3 text-center">{{ t('Status') }}</th>
                        <th class="px-4 py-3 text-right">{{ t('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="ad in ads" :key="ad.id" class="border-t border-gray-100 hover:bg-primary-50/30">
                        <td class="px-4 py-3">
                            <div class="font-semibold text-gray-900">{{ ad.title }}</div>
                            <div class="text-xs text-gray-500">{{ ad.type.replace('_', ' ') }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-600">{{ zones[ad.zone] || ad.zone }}</span>
                        </td>
                        <td class="px-4 py-3">{{ t(String(ad.show_to).replace('_', ' ')) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-center gap-4 text-center text-xs">
                                <span><b>{{ formatNumber(Number(ad.impressions || 0)) }}</b><br>{{ t('Impressions') }}</span>
                                <span><b>{{ formatNumber(Number(ad.clicks || 0)) }}</b><br>{{ t('Clicks') }}</span>
                                <span><b>{{ ad.ctr }}%</b><br>{{ t('CTR') }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button type="button" :class="ad.is_active ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-500'" class="rounded-full px-3 py-1 text-xs font-bold" @click="toggleAd(ad.id)">
                                {{ ad.is_active ? t('Active') : t('Paused') }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="route('admin.ads.edit', ad.id)" class="mr-2 rounded-lg border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-700 hover:border-primary-300">{{ t('Edit') }}</Link>
                            <button type="button" class="rounded-lg bg-red-500 px-3 py-1 text-xs font-semibold text-white" @click="deleteAd(ad.id)">{{ t('Delete') }}</button>
                        </td>
                    </tr>
                    <tr v-if="ads.length === 0">
                        <td colspan="6" class="px-4 py-12 text-center text-gray-400">{{ t('No advertisements found.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
