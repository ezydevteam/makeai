<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import StatsCard from '@/Components/UI/StatsCard.vue'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

const props = defineProps<{
    total_posts: number
    scheduled_posts: number
    pending_approval: number
    published_today: number
    failed_posts: number
    connected_accounts: number
    platform_breakdown: { platform: string; count: number }[]
    recent_posts: {
        ulid: string
        title: string | null
        caption: string
        status: string
        platforms: string[]
        scheduled_at: string | null
        user: { name: string } | null
    }[]
}>()

const platformLabel = (p: string) => ({
    instagram: 'Instagram', facebook: 'Facebook', twitter: 'X / Twitter', linkedin: 'LinkedIn',
}[p] ?? p)

const statusClass = (s: string) =>
    s === 'published' ? 'bg-emerald-100 text-emerald-800' :
    s === 'scheduled' ? 'bg-blue-100 text-blue-800' :
    s === 'pending_approval' ? 'bg-amber-100 text-amber-800' :
    s === 'failed' ? 'bg-red-100 text-red-800' :
    'bg-gray-100 text-gray-700'
</script>

<template>
    <Head :title="t('Social Scheduler Overview')" />

    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ t('Social Scheduler') }}</h1>
            <Link :href="route('addon.social.admin.approval.index')"
                  class="btn btn-sm" :class="{ 'bg-amber-100 text-amber-800': pending_approval > 0 }">
                <template v-if="pending_approval > 0">
                    {{ t('Approval Queue') }} ({{ pending_approval }})
                </template>
                <template v-else>{{ t('Approval Queue') }}</template>
            </Link>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <StatsCard :title="t('Total Posts')" :value="`${total_posts}`" />
            <StatsCard :title="t('Scheduled')" :value="`${scheduled_posts}`" />
            <StatsCard :title="t('Pending Approval')" :value="`${pending_approval}`"
                       :color="pending_approval > 0 ? 'warning' : undefined" />
            <StatsCard :title="t('Published Today')" :value="`${published_today}`" />
            <StatsCard :title="t('Failed')" :value="`${failed_posts}`"
                       :color="failed_posts > 0 ? 'danger' : undefined" />
            <StatsCard :title="t('Accounts')" :value="`${connected_accounts}`" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="card p-4">
                <h3 class="font-medium mb-3">{{ t('Accounts by Platform') }}</h3>
                <div v-if="platform_breakdown.length" class="space-y-2">
                    <div v-for="row in platform_breakdown" :key="row.platform" class="flex items-center justify-between">
                        <span>{{ platformLabel(row.platform) }}</span>
                        <div class="flex items-center gap-2">
                            <div class="h-2 bg-emerald-500 rounded" :style="{ width: `${Math.max(row.count * 20, 10)}px` }"></div>
                            <span class="text-xs text-gray-500">{{ row.count }}</span>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400">{{ t('No connected accounts yet.') }}</p>
            </div>

            <div class="card p-4">
                <h3 class="font-medium mb-3">{{ t('Recent Posts') }}</h3>
                <div v-if="recent_posts.length" class="space-y-2">
                    <div v-for="post in recent_posts" :key="post.ulid"
                         class="flex items-center justify-between text-sm border-b border-gray-100 pb-2">
                        <div class="flex-1 min-w-0">
                            <div class="truncate font-medium">{{ post.title || post.caption }}</div>
                            <div class="flex items-center gap-2 text-xs text-gray-400">
                                <span>{{ post.user?.name ?? t('Unknown') }}</span>
                                <span v-for="p in post.platforms" :key="p" class="capitalize">{{ p }}</span>
                            </div>
                        </div>
                        <span class="ml-2 px-2 py-0.5 rounded text-xs font-medium shrink-0" :class="statusClass(post.status)">
                            {{ post.status }}
                        </span>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400">{{ t('No posts yet.') }}</p>
            </div>
        </div>
    </div>
</template>
