<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { ref } from 'vue'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

const props = defineProps<{
    posts: {
        data: {
            id: number
            ulid: string
            title: string | null
            caption: string
            platforms: string[]
            post_type: string
            scheduled_at: string | null
            media_count: number
            user: { name: string; email: string } | null
            created_at: string
        }[]
    }
}>()

const rejectForm = useForm({ reason: '' })
const rejectTarget = ref<number | null>(null)

function approve(postId: number) {
    router.post(route('addon.social.admin.approval.approve', postId), {}, {
        preserveScroll: true,
        onFinish: () => router.reload(),
    })
}

function openReject(postId: number) {
    rejectTarget.value = postId
    rejectForm.reason = ''
}

function submitReject() {
    if (!rejectTarget.value) return
    router.post(route('addon.social.admin.approval.reject', rejectTarget.value), {
        reason: rejectForm.reason,
    }, {
        preserveScroll: true,
        onSuccess: () => { rejectTarget.value = null },
        onFinish: () => router.reload(),
    })
}

const platformClass = (p: string) => ({
    instagram: 'text-pink-600', facebook: 'text-blue-600',
    twitter: 'text-sky-500', linkedin: 'text-blue-700',
}[p] ?? 'text-gray-500')
</script>

<template>
    <Head :title="t('Approval Queue')" />

    <div class="p-6 space-y-6">
        <h1 class="text-xl font-semibold">{{ t('Approval Queue') }}</h1>

        <div v-if="posts.data.length === 0" class="card p-8 text-center text-gray-400">
            <p class="text-lg">{{ t('No posts awaiting approval.') }}</p>
        </div>

        <div v-else class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">{{ t('User') }}</th>
                        <th class="px-4 py-3 text-left font-medium">{{ t('Caption') }}</th>
                        <th class="px-4 py-3 text-left font-medium">{{ t('Platforms') }}</th>
                        <th class="px-4 py-3 text-left font-medium hidden md:table-cell">{{ t('Scheduled') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ t('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="post in posts.data" :key="post.ulid" class="border-b hover:bg-gray-50">
                        <td class="px-4 py-3">{{ post.user?.name ?? t('Unknown') }}</td>
                        <td class="px-4 py-3 max-w-[200px] truncate">{{ post.title || post.caption }}</td>
                        <td class="px-4 py-3">
                            <span v-for="p in post.platforms" :key="p" class="mr-2 text-xs capitalize" :class="platformClass(p)">{{ p }}</span>
                        </td>
                        <td class="px-4 py-3 hidden md:table-cell text-gray-500">
                            {{ post.scheduled_at ? new Date(post.scheduled_at).toLocaleDateString() : '-' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button @click="approve(post.id)" class="btn btn-sm btn-emerald mr-2">
                                {{ t('Approve') }}
                            </button>
                            <button @click="openReject(post.id)" class="btn btn-sm btn-ghost text-red-600">
                                {{ t('Reject') }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Reject Modal -->
        <div v-if="rejectTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4 shadow-xl">
                <h3 class="font-semibold mb-3">{{ t('Reject Post') }}</h3>
                <label class="block text-sm mb-1">{{ t('Reason') }}</label>
                <textarea v-model="rejectForm.reason" rows="3" class="input w-full mb-4"
                          :placeholder="t('Provide a reason for rejection')" maxlength="500"></textarea>
                <div class="flex justify-end gap-2">
                    <button @click="rejectTarget = null" class="btn btn-ghost btn-sm">{{ t('Cancel') }}</button>
                    <button @click="submitReject" :disabled="!rejectForm.reason.trim()"
                            class="btn btn-sm btn-danger">{{ t('Reject') }}</button>
                </div>
            </div>
        </div>
    </div>
</template>
