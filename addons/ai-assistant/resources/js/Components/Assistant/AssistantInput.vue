<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps<{
    disabled: boolean
    isAdmin: boolean
    allowUpload: boolean
    allowedTypes: string
}>()

const emit = defineEmits<{
    (e: 'send', text: string, file?: { name: string; text: string }): void
}>()

const page = usePage()
const $t = (key: string, replace?: Record<string, string | number>) => {
    const translations = (page.props.translations ?? {}) as Record<string, string>
    let text = translations[key] ?? key
    if (replace) {
        for (const [k, v] of Object.entries(replace)) {
            text = text.replace(new RegExp(`:${k}`, 'g'), String(v))
        }
    }
    return text
}

const input = ref('')
const textarea = ref<HTMLTextAreaElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const attachedFile = ref<{ name: string; text: string } | null>(null)

const acceptFilter = computed(() => {
    if (!props.allowedTypes) return ''
    return props.allowedTypes
        .split(',')
        .map(ext => {
            const trimmed = ext.trim().toLowerCase()
            return trimmed.startsWith('.') ? trimmed : `.${trimmed}`
        })
        .join(',')
})

function triggerFileInput() {
    fileInput.value?.click()
}

async function onFileSelected(e: Event) {
    const target = e.target as HTMLInputElement
    const file = target.files?.[0]
    if (!file) return

    // Validate extension
    const extension = file.name.split('.').pop()?.toLowerCase() || ''
    const allowedList = props.allowedTypes
        .split(',')
        .map(e => e.trim().toLowerCase().replace(/^\./, ''))

    if (!allowedList.includes(extension)) {
        alert($t('File type not allowed.'))
        target.value = ''
        return
    }

    // Limit to 10MB
    if (file.size > 10 * 1024 * 1024) {
        alert($t('File is too large. Max size is 10MB.'))
        target.value = ''
        return
    }

    uploading.value = true
    const formData = new FormData()
    formData.append('file', file)

    const endpoint = props.isAdmin ? '/api/admin/assistant/extract' : '/api/assistant/extract'
    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: formData,
        })

        const data = await response.json()
        if (!response.ok) {
            throw new Error(data.error || $t('Failed to upload and parse file.'))
        }

        attachedFile.value = {
            name: data.filename,
            text: data.text,
        }
    } catch (err: any) {
        alert(err.message || $t('Something went wrong during file upload.'))
    } finally {
        uploading.value = false
        target.value = ''
    }
}

function removeAttachedFile() {
    attachedFile.value = null
}

function onSubmit() {
    const text = input.value.trim()
    if ((!text && !attachedFile.value) || props.disabled || uploading.value) return

    const finalMsg = text || `${$t('Analyze attached file')}: ${attachedFile.value?.name}`
    emit('send', finalMsg, attachedFile.value || undefined)
    
    input.value = ''
    attachedFile.value = null

    // Reset height
    if (textarea.value) {
        textarea.value.style.height = 'auto'
    }
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault()
        onSubmit()
    }
}

function autoResize() {
    const el = textarea.value
    if (!el) return
    el.style.height = 'auto'
    el.style.height = Math.min(el.scrollHeight, 120) + 'px'
}
</script>

<template>
    <div class="ai-input-wrapper border-t border-gray-200 dark:border-surface-800 shrink-0 flex flex-col bg-white px-3 py-2.5 dark:bg-surface-900">
        <!-- Attached file preview -->
        <transition name="ai-fade">
            <div v-if="attachedFile" class="mb-2 flex max-w-full self-start items-center gap-2 rounded-full border border-gray-200 bg-gray-100 px-3 py-1 text-xs text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300">
                <i class="ti ti-file text-sm shrink-0"></i>
                <span class="truncate max-w-[200px]">{{ attachedFile.name }}</span>
                <button type="button" class="hover:text-red-500 text-gray-400 ml-1 transition-colors shrink-0" @click="removeAttachedFile">
                    <i class="ti ti-x text-[10px]"></i>
                </button>
            </div>
        </transition>

        <div class="flex items-end gap-2 rounded-2xl border border-gray-200 bg-gray-100 p-1.5 transition-all focus-within:border-transparent focus-within:ring-2 focus-within:ring-[var(--ai-accent,#1F75FE)] dark:border-surface-700 dark:bg-surface-800">
            <!-- File upload button -->
            <button
                v-if="allowUpload"
                type="button"
                class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-xl text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-surface-700 dark:hover:text-gray-300"
                :disabled="disabled || uploading"
                @click="triggerFileInput"
            >
                <i v-if="!uploading" class="ti ti-plus text-[18px]"></i>
                <i v-else class="ti ti-loader text-[18px] animate-spin"></i>
            </button>
            
            <input
                ref="fileInput"
                type="file"
                class="hidden"
                :accept="acceptFilter"
                @change="onFileSelected"
            />

            <textarea
                ref="textarea"
                v-model="input"
                :placeholder="$t('Type a message...')"
                :disabled="disabled || uploading"
                rows="1"
                class="chat-textarea max-h-[120px] flex-1 resize-none border-0 bg-transparent px-2 py-1.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-0 dark:text-gray-100 dark:placeholder-gray-500"
                @keydown="onKeydown"
                @input="autoResize"
            />
            
            <button
                class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 transition-colors disabled:opacity-40"
                :disabled="disabled || uploading || (!input.trim() && !attachedFile)"
                :style="{ background: (input.trim() || attachedFile) ? 'var(--ai-accent, #1F75FE)' : 'transparent', color: (input.trim() || attachedFile) ? '#fff' : '#9ca3af' }"
                @click="onSubmit"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                </svg>
            </button>
        </div>
    </div>
</template>

<style scoped>
.ai-fade-enter-active,
.ai-fade-leave-active {
    transition: all 0.2s ease-in-out;
}
.ai-fade-enter-from,
.ai-fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
