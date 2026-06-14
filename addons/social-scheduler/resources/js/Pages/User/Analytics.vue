<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserDashboardLayout })

const { t } = useTranslate()

defineProps<{
    platforms: { platform: string; total_impressions: number; total_likes: number; total_comments: number; total_shares: number; avg_engagement: number; post_count: number }[]
    top_posts: { title: string | null; caption: string; platform: string; external_post_url: string | null; impressions: number; engagement_rate: number; likes: number; comments: number }[]
}>()

const platformLabel = (p: string) => ({
    instagram: 'Instagram', facebook: 'Facebook', twitter: 'X / Twitter', linkedin: 'LinkedIn',
}[p] ?? p)
</script>

<template>
    <Head :title="t('Analytics')" />

    <div class="p-6 space-y-6">
        <h1 class="text-xl font-semibold">{{ t('Post Analytics') }}</h1>

        <!-- Platform stats -->
        <div v-if="platforms.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div v-for="pl in platforms" :key="pl.platform" class="card p-4">
                <h3 class="font-medium text-sm mb-2">{{ platformLabel(pl.platform) }}</h3>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <span class="text-gray-400 block text-xs">{{ t('Impressions') }}</span>
                        <span class="font-medium">{{ pl.total_impressions.toLocaleString() }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block text-xs">{{ t('Engagement') }}</span>
                        <span class="font-medium">{{ parseFloat(pl.avg_engagement).toFixed(1) }}%</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block text-xs">{{ t('Posts') }}</span>
                        <span class="font-medium">{{ pl.post_count }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block text-xs">{{ t('Likes') }}</span>
                        <span class="font-medium">{{ pl.total_likes.toLocaleString() }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top posts -->
        <div class="card overflow-hidden">
            <h3 class="p-4 font-medium border-b">{{ t('Top Performing Posts') }}</h3>
            <table v-if="top_posts.length" class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium">{{ t('Caption') }}</th>
                        <th class="px-4 py-2 text-left font-medium">{{ t('Platform') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ t('Impressions') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ t('Engagement') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(p, idx) in top_posts" :key="idx" class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2 max-w-[200px] truncate">{{ p.title || p.caption }}</td>
                        <td class="px-4 py-2 capitalize">{{ p.platform }}</td>
                        <td class="px-4 py-2 text-right">{{ p.impressions.toLocaleString() }}</td>
                        <td class="px-4 py-2 text-right">{{ parseFloat(p.engagement_rate).toFixed(1) }}%</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="p-4 text-center text-gray-400 text-sm">{{ t('No analytics yet. Publish your first post to start seeing data.') }}</p>
        </div>
    </div>
</template>
