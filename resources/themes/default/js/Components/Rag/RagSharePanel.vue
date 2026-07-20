<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToastr } from '@/Composables/useToastr'
import { useTranslate } from '@/Composables/useTranslate'

const props = defineProps<{ sessionId: string }>()
const emit = defineEmits<{ close: [] }>()

const { t } = useTranslate()
const toastr = useToastr()
const shareUrl = ref<string | null>(null)
const loading = ref(false)

onMounted(async () => {
    await fetchShareStatus()
})

async function fetchShareStatus() {
    loading.value = true
    try {
        const res = await fetch(`/tools/rag/sessions/${props.sessionId}/share`, {
            method: 'GET',
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
        if (res.ok) {
            const data = await res.json()
            shareUrl.value = data.share_url
        }
    } catch {
    } finally {
        loading.value = false
    }
}

async function generateShareUrl() {
    loading.value = true
    try {
        const res = await fetch(`/tools/rag/sessions/${props.sessionId}/share`, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getCsrf() },
            credentials: 'same-origin',
        })
        if (res.ok) {
            const data = await res.json()
            shareUrl.value = data.share_url
        }
    } catch {
    } finally {
        loading.value = false
    }
}

async function revoke() {
    const res = await fetch(`/tools/rag/sessions/${props.sessionId}/share`, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getCsrf() },
        credentials: 'same-origin',
    })
    if (res.ok) {
        shareUrl.value = null
        toastr.success(t('Share link revoked'))
    }
}

function copyLink() {
    if (shareUrl.value) {
        navigator.clipboard.writeText(shareUrl.value)
        toastr.success(t('Link copied to clipboard'))
    }
}

function getCsrf(): string {
    const cookie = document.cookie.match('(^|;)\\s*XSRF-TOKEN\\s*=\\s*([^;]+)')
    return cookie ? decodeURIComponent(cookie.pop() || '') : ''
}
</script>

<template>
    <div class="w-80 bg-white dark:bg-surface-900 border border-surface-200/80 dark:border-surface-800/80 rounded-2xl shadow-xl p-5 z-40 text-surface-900 dark:text-surface-100">
        <div class="flex items-center justify-between mb-3 border-b border-surface-100 dark:border-surface-950 pb-2">
            <h4 class="font-bold text-xs text-surface-400 dark:text-surface-500 uppercase tracking-wider">{{ t('Share Session') }}</h4>
            <button 
                class="w-6 h-6 flex items-center justify-center text-surface-400 hover:text-surface-600 dark:hover:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800 rounded-lg transition-colors"
                @click="emit('close')"
            >
                <i class="ti ti-x text-sm"></i>
            </button>
        </div>

        <div v-if="loading" class="flex justify-center items-center py-6">
            <div class="animate-spin rounded-full h-5 w-5 border-2 border-primary-500 border-t-transparent"></div>
        </div>

        <template v-else-if="shareUrl">
            <p class="text-xs text-surface-500 dark:text-surface-400 mb-3 font-medium">{{ t('Anyone with this link can view this chat session.') }}</p>
            <div class="flex gap-2">
                <input
                    :value="shareUrl"
                    readonly
                    class="input input-bordered input-sm flex-1 text-xs h-9 px-3 rounded-xl bg-surface-50 dark:bg-surface-950 border-surface-200 dark:border-surface-800 focus:ring-1 focus:ring-primary-500/10 focus:border-primary-500 font-medium"
                    @click="($event.target as HTMLInputElement).select()"
                />
                <button 
                    class="inline-flex items-center justify-center w-9 h-9 text-white bg-primary-500 hover:bg-primary-600 rounded-xl shadow-sm transition-all shrink-0" 
                    @click="copyLink"
                >
                    <i class="ti ti-copy text-sm"></i>
                </button>
            </div>
            <button 
                class="inline-flex items-center gap-1.5 mt-3 text-[11px] font-bold text-red-500 bg-red-500/5 hover:bg-red-500/10 border border-red-500/10 rounded-lg px-2.5 py-1.5 transition-all" 
                @click="revoke"
            >
                <i class="ti ti-unlink"></i>
                {{ t('Revoke Link') }}
            </button>
        </template>

        <div v-else class="text-center py-3">
            <p class="text-sm text-surface-400 dark:text-surface-500 font-medium">{{ t('Share link is currently inactive.') }}</p>
            <button 
                class="inline-flex items-center gap-1.5 mt-3 px-3.5 py-2 text-xs font-bold text-white bg-primary-500 hover:bg-primary-600 rounded-xl shadow-sm transition-all mx-auto" 
                @click="generateShareUrl"
            >
                <i class="ti ti-link"></i>
                {{ t('Create Share Link') }}
            </button>
        </div>
    </div>
</template>
