<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import StatsCard from '@/Components/UI/StatsCard.vue'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

defineProps<{
    total_renders: number
    processing: number
    completed_today: number
    failed_today: number
    total_storage_gb: number
    by_type: { type: string; count: number }[]
    by_provider: { provider: string; count: number; avg_polls: number }[]
    top_users: { user_name: string; user_email: string; renders: number; credits: number }[]
}>()

const typeLabel = (t: string) => ({ text_to_video: 'Text → Video', image_to_video: 'Image → Video', avatar_video: 'AI Avatar', slideshow: 'Slideshow' }[t] ?? t)
</script>

<template>
    <Head :title="t('Video Creator Overview')" />

    <div class="p-6 space-y-6">
        <h1 class="text-xl font-semibold">{{ t('Video Creator') }}</h1>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
            <StatsCard :title="t('Total Renders')" :value="`${total_renders}`" />
            <StatsCard :title="t('Processing')" :value="`${processing}`" :color="processing > 0 ? 'warning' : undefined" />
            <StatsCard :title="t('Completed Today')" :value="`${completed_today}`" />
            <StatsCard :title="t('Failed Today')" :value="`${failed_today}`" :color="failed_today > 0 ? 'danger' : undefined" />
            <StatsCard :title="t('Storage')" :value="`${total_storage_gb} GB`" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="card p-4">
                <h3 class="font-medium mb-3">{{ t('By Type') }}</h3>
                <div class="space-y-2">
                    <div v-for="row in by_type" :key="row.type" class="flex items-center justify-between text-sm">
                        <span>{{ typeLabel(row.type) }}</span>
                        <span class="font-medium">{{ row.count }}</span>
                    </div>
                </div>
            </div>
            <div class="card p-4">
                <h3 class="font-medium mb-3">{{ t('By Provider') }}</h3>
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-gray-500"><th>{{ t('Provider') }}</th><th>{{ t('Renders') }}</th><th>{{ t('Avg Polls') }}</th></tr></thead>
                    <tbody>
                        <tr v-for="row in by_provider" :key="row.provider" class="border-t">
                            <td class="py-1.5 capitalize">{{ row.provider }}</td>
                            <td>{{ row.count }}</td>
                            <td>{{ parseFloat(row.avg_polls).toFixed(1) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card p-4">
            <h3 class="font-medium mb-3">{{ t('Top Users') }}</h3>
            <table v-if="top_users.length" class="w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th>{{ t('User') }}</th><th>{{ t('Renders') }}</th><th>{{ t('Credits Spent') }}</th></tr></thead>
                <tbody>
                    <tr v-for="u in top_users" :key="u.user_email" class="border-t">
                        <td class="py-1.5">{{ u.user_name }} <span class="text-gray-400 text-xs">({{ u.user_email }})</span></td>
                        <td>{{ u.renders }}</td>
                        <td>{{ u.credits }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
