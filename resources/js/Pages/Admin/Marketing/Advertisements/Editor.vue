<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    ad: any | null
    zones: Record<string, string>
}>()

const { t } = useTranslate()
const zoneEntries = computed(() => Object.entries(props.zones))
const form = useForm({
    title: props.ad?.title ?? '',
    type: props.ad?.type ?? 'image_link',
    zone: props.ad?.zone ?? 'header_banner',
    adsense_client: props.ad?.adsense_client ?? '',
    adsense_slot: props.ad?.adsense_slot ?? '',
    adsense_format: props.ad?.adsense_format ?? 'auto',
    custom_html: props.ad?.custom_html ?? '',
    image_url: props.ad?.image_url ?? '',
    link_url: props.ad?.link_url ?? '',
    link_target: props.ad?.link_target ?? '_blank',
    show_to: props.ad?.show_to ?? 'all',
    is_active: props.ad?.is_active ?? true,
    start_at: props.ad?.start_at ? new Date(props.ad.start_at).toISOString().slice(0, 16) : '',
    end_at: props.ad?.end_at ? new Date(props.ad.end_at).toISOString().slice(0, 16) : '',
    sort_order: props.ad?.sort_order ?? 0,
})

const submit = () => {
    const url = props.ad ? route('admin.ads.update', props.ad.id) : route('admin.ads.store')
    form.transform((data) => ({
        ...data,
        start_at: data.start_at || null,
        end_at: data.end_at || null,
        image_url: data.image_url || null,
        adsense_client: data.adsense_client || null,
        adsense_format: data.adsense_format || 'auto',
    })).post(url, {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="ad ? t('Edit Ad') : t('Create Ad')" />

    <div class="mx-auto max-w-6xl px-6 py-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ ad ? t('Edit Advertisement') : t('Create Advertisement') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Configure zone, audience, creative, schedule, and tracking behavior.') }}</p>
            </div>
            <button type="button" :disabled="form.processing" class="rounded-lg btn-primary disabled:opacity-60" @click="submit">
                {{ form.processing ? t('Saving...') : t('Save ad') }}
            </button>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <section class="space-y-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Admin title') }}</span>
                        <input v-model="form.title" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Ad type') }}</span>
                        <select v-model="form.type" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <option value="image_link">{{ t('Image link') }}</option>
                            <option value="custom_html">{{ t('Custom HTML') }}</option>
                            <option value="adsense">{{ t('AdSense') }}</option>
                        </select>
                    </label>
                </div>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Zone') }}</span>
                    <select v-model="form.zone" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                        <option v-for="[slug, label] in zoneEntries" :key="slug" :value="slug">{{ label }}</option>
                    </select>
                </label>

                <div v-if="form.type === 'image_link'" class="space-y-4">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Image URL') }}</span>
                        <input v-model="form.image_url" type="url" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" placeholder="https://..." />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Link URL') }}</span>
                        <input v-model="form.link_url" type="url" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" placeholder="https://..." />
                    </label>
                </div>

                <div v-else-if="form.type === 'custom_html'">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Custom HTML or script') }}</span>
                        <textarea v-model="form.custom_html" rows="10" class="w-full rounded-lg border border-gray-200 bg-gray-950 px-3 py-2 font-mono text-sm text-white" />
                    </label>
                </div>

                <div v-else class="grid gap-4 md:grid-cols-3">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Publisher ID') }}</span>
                        <input v-model="form.adsense_client" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" placeholder="ca-pub-..." />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Slot ID') }}</span>
                        <input v-model="form.adsense_slot" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Format') }}</span>
                        <input v-model="form.adsense_format" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" placeholder="auto" />
                    </label>
                </div>
            </section>

            <aside class="space-y-6">
                <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Show to') }}</span>
                        <select v-model="form.show_to" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <option value="all">{{ t('All visitors') }}</option>
                            <option value="guests">{{ t('Guests only') }}</option>
                            <option value="logged_in">{{ t('Logged in users') }}</option>
                            <option value="free_users">{{ t('Free users') }}</option>
                            <option value="paid_users">{{ t('Paid users') }}</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Link target') }}</span>
                        <select v-model="form.link_target" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <option value="_blank">{{ t('New tab') }}</option>
                            <option value="_self">{{ t('Same tab') }}</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Sort order') }}</span>
                        <input v-model="form.sort_order" type="number" min="0" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </label>
                    <label class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium">
                        {{ t('Active') }}
                        <input v-model="form.is_active" type="checkbox" />
                    </label>
                </section>

                <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Start at') }}</span>
                        <input v-model="form.start_at" type="datetime-local" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('End at') }}</span>
                        <input v-model="form.end_at" type="datetime-local" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </label>
                </section>
            </aside>
        </div>
    </div>
</template>
