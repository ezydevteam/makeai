<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

defineOptions({ layout: UserDashboardLayout })

const { t } = useTranslate()

const props = defineProps<{ render: any }>()

const status = ref(props.render.status)
const fileUrl = ref(props.render.file_url)
let pollTimer: ReturnType<typeof setInterval> | null = null

onMounted(() => {
    if (['completed', 'failed', 'cancelled'].includes(status.value)) return
    pollTimer = setInterval(async () => {
        try {
            const { data } = await axios.get(route('addon.video.user.renders.status', props.render.ulid))
            status.value = data.status
            if (data.file_url) fileUrl.value = data.file_url
            if (['completed', 'failed'].includes(data.status) && pollTimer) {
                clearInterval(pollTimer)
            }
        } catch {}
    }, 5000)
})

onUnmounted(() => { if (pollTimer) clearInterval(pollTimer) })
</script>

<template>
    <Head :title="render.title || t('Video Viewer')" />

    <div class="p-6 max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Player -->
        <div class="lg:col-span-2">
            <div v-if="status === 'queued' || status === 'processing'" class="aspect-video bg-gray-100 rounded-lg flex flex-col items-center justify-center">
                <div class="animate-spin w-10 h-10 border-4 border-emerald-500 border-t-transparent rounded-full mb-3"></div>
                <p class="text-gray-500">{{ status === 'queued' ? t('Queued...') : t('Generating your video...') }}</p>
            </div>
            <div v-else-if="status === 'failed'" class="aspect-video bg-red-50 rounded-lg flex flex-col items-center justify-center">
                <i class="ti ti-alert-circle text-3xl text-red-500 mb-2"></i>
                <p class="text-red-600 font-medium">{{ t('Generation Failed') }}</p>
                <p class="text-sm text-red-400 mt-1">{{ render.error_message }}</p>
            </div>
            <video v-else-if="fileUrl" :src="fileUrl" controls class="w-full rounded-lg bg-black"
                   :poster="render.thumbnail_url"></video>
        </div>

        <!-- Info Panel -->
        <div class="card p-4 space-y-4 self-start">
            <h3 class="font-medium">{{ render.title || t('Untitled') }}</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">{{ t('Type') }}</span><span>{{ render.type_label }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">{{ t('Provider') }}</span><span class="capitalize">{{ render.provider }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">{{ t('Status') }}</span><span class="font-medium">{{ render.status_label }}</span></div>
                <div v-if="render.duration_actual" class="flex justify-between"><span class="text-gray-500">{{ t('Duration') }}</span><span>{{ render.duration_actual }}s</span></div>
                <div class="flex justify-between"><span class="text-gray-500">{{ t('Credits') }}</span><span>{{ render.credits_deducted }}</span></div>
            </div>
            <hr />
            <div class="space-y-2">
                <a v-if="fileUrl" :href="fileUrl" download class="btn btn-sm btn-emerald w-full">{{ t('⬇ Download MP4') }}</a>

                <!-- Subtitles -->
                <div v-if="status === 'completed'" class="space-y-1">
                    <p class="text-xs font-medium text-gray-500">{{ t('Subtitles') }}</p>
                    <button v-for="fmt in ['srt','vtt','json']" :key="fmt" @click="axios.post(route('addon.video.user.renders.subtitles', render.ulid), { format: fmt })"
                            class="btn btn-xs btn-ghost w-full text-left">{{ t('Generate') }} {{ fmt.toUpperCase() }}</button>
                </div>
            </div>
        </div>
    </div>
</template>
