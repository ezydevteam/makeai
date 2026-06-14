<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserDashboardLayout })

const { t } = useTranslate()

const props = defineProps<{
    posts: { data: any[] }
}>()

function deletePost(ulid: string) {
    if (!confirm(t('Delete this post?'))) return
    router.delete(route('addon.social.user.posts.index') + '/' + ulid, {
        preserveScroll: true,
    })
}

const statusClass = (s: string) =>
    s === 'published' ? 'bg-emerald-100 text-emerald-800' :
    s === 'scheduled' ? 'bg-blue-100 text-blue-800' :
    s === 'pending_approval' ? 'bg-amber-100 text-amber-800' :
    s === 'publishing' ? 'bg-purple-100 text-purple-800' :
    s === 'partial' ? 'bg-orange-100 text-orange-800' :
    s === 'failed' ? 'bg-red-100 text-red-800' :
    'bg-gray-100 text-gray-700'
</script>

<template>
    <Head :title="t('Posts')" />

    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ t('Scheduled Posts') }}</h1>
            <Link :href="route('addon.social.user.posts.create')" class="btn btn-sm btn-emerald">
                + {{ t('New Post') }}
            </Link>
        </div>

        <div v-if="posts.data.length === 0" class="card p-8 text-center text-gray-400">
            <p class="text-lg">{{ t('No posts yet.') }}</p>
            <Link :href="route('addon.social.user.posts.create')" class="btn btn-sm btn-emerald mt-3">
                {{ t('Create your first post') }}
            </Link>
        </div>

        <div v-else class="space-y-3">
            <div v-for="post in posts.data" :key="post.ulid"
                 class="card p-4 flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium truncate">{{ post.title || post.caption }}</span>
                        <span class="px-2 py-0.5 rounded text-xs font-medium shrink-0" :class="statusClass(post.status)">
                            {{ post.status_label }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3 mt-1 text-xs text-gray-400">
                        <span v-for="p in post.platforms" :key="p" class="capitalize">{{ p }}</span>
                        <span v-if="post.scheduled_at">{{ new Date(post.scheduled_at).toLocaleString() }}</span>
                    </div>
                </div>
                <div class="flex gap-2 shrink-0 ml-4">
                    <Link v-if="['draft','scheduled','pending_approval'].includes(post.status)"
                          :href="route('addon.social.user.posts.index') + '/' + post.ulid + '/edit'"
                          class="btn btn-xs btn-ghost">{{ t('Edit') }}</Link>
                    <button @click="deletePost(post.ulid)" class="btn btn-xs btn-ghost text-red-500"
                            :disabled="post.status === 'publishing'">{{ t('Delete') }}</button>
                </div>
            </div>
        </div>
    </div>
</template>
