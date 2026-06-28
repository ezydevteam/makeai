<script setup lang="ts">
import { ref, computed } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

interface RagSettings {
    max_file_mb: number
    max_pages?: number
    allowed_file_types: string[]
    multi_file: boolean
    min_files?: number
    max_files?: number
}

const props = defineProps<{
    sourceType: 'file' | 'url' | 'youtube' | 'collection'
    settings: RagSettings
    loading: boolean
}>()

const emit = defineEmits<{ submit: [payload: FormData | Record<string, string>] }>()
const { t } = useTranslate()

const dragOver = ref(false)
const filesList = ref<File[]>([])
const url = ref('')
const title = ref('')
const knowledgeBaseId = ref<number | null>(null)
const error = ref('')

async function handleRightClick(e: MouseEvent) {
    if (navigator.clipboard) {
        e.preventDefault()
        try {
            const text = await navigator.clipboard.readText()
            if (text) {
                url.value = text.trim()
                error.value = ''
            }
        } catch (err) {
            console.warn('Auto-paste failed:', err)
        }
    }
}

const acceptString = computed(() => {
    const types = props.settings.allowed_file_types
    if (!types || types.length === 0) return '.pdf,.docx,.txt,.md'
    return types.map(t => '.' + t).join(',')
})

const maxSizeMb = computed(() => props.settings.max_file_mb || 25)
const minFiles = computed(() => props.settings.min_files ?? 2)
const maxFiles = computed(() => props.settings.max_files ?? 3)

function formatSize(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return (bytes / 1024).toFixed(1) + ' KB'
    }
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function handleFileDrop(e: DragEvent) {
    dragOver.value = false
    const droppedFiles = e.dataTransfer?.files
    if (droppedFiles && droppedFiles.length > 0) {
        if (props.settings.multi_file) {
            for (let i = 0; i < droppedFiles.length; i++) {
                validateAndAddFile(droppedFiles[i])
            }
        } else {
            validateAndAddFile(droppedFiles[0], true)
        }
    }
}

function handleFileSelect(e: Event) {
    const input = e.target as HTMLInputElement
    const selectedFiles = input.files
    if (selectedFiles && selectedFiles.length > 0) {
        if (props.settings.multi_file) {
            for (let i = 0; i < selectedFiles.length; i++) {
                validateAndAddFile(selectedFiles[i])
            }
        } else {
            validateAndAddFile(selectedFiles[0], true)
        }
    }
    // Reset the input value so the same file can be selected again if removed
    input.value = ''
}

function validateAndAddFile(f: File, replace: boolean = false) {
    error.value = ''
    const ext = '.' + f.name.split('.').pop()?.toLowerCase()
    if (props.settings.allowed_file_types.length > 0) {
        const allowed = props.settings.allowed_file_types.map(t => '.' + t)
        if (!allowed.includes(ext)) {
            error.value = `File type ${ext} is not supported. Allowed: ${allowed.join(', ')}`
            return
        }
    }
    if (f.size > maxSizeMb.value * 1024 * 1024) {
        error.value = `File is too large. Maximum size is ${maxSizeMb.value} MB.`
        return
    }

    if (replace) {
        filesList.value = [f]
    } else {
        // Prevent duplicate file selection
        const duplicate = filesList.value.some(existing => existing.name === f.name && existing.size === f.size)
        if (duplicate) return

        if (filesList.value.length >= maxFiles.value) {
            error.value = `You can upload a maximum of ${maxFiles.value} files.`
            return
        }
        filesList.value.push(f)
    }
}

function removeFile(index: number) {
    filesList.value.splice(index, 1)
    error.value = ''
}

function submit() {
    if (props.sourceType === 'file' && filesList.value.length > 0) {
        const form = new FormData()
        if (props.settings.multi_file) {
            filesList.value.forEach(f => {
                form.append('files[]', f)
            })
            const sessionTitle = title.value || filesList.value.map(f => f.name).join(', ')
            form.append('title', sessionTitle)
        } else {
            form.append('file', filesList.value[0])
            form.append('title', title.value || filesList.value[0].name)
        }
        emit('submit', form)
    } else if ((props.sourceType === 'url' || props.sourceType === 'youtube') && url.value) {
        emit('submit', { url: url.value, title: title.value || '' })
    } else if (props.sourceType === 'collection' && knowledgeBaseId.value) {
        emit('submit', { knowledge_base_id: knowledgeBaseId.value.toString(), title: title.value || '' })
    }
}

const canSubmit = computed(() => {
    if (props.loading) return false
    if (props.sourceType === 'file') {
        if (props.settings.multi_file) {
            return filesList.value.length >= minFiles.value && filesList.value.length <= maxFiles.value && !error.value
        } else {
            return filesList.value.length === 1 && !error.value
        }
    }
    if (props.sourceType === 'collection') return !!knowledgeBaseId.value
    return !!url.value && !error.value
})

const welcomeTitle = computed(() => {
    switch (props.sourceType) {
        case 'url':
            return t('Analyze Web Page')
        case 'youtube':
            return t('Analyze YouTube Video')
        case 'collection':
            return t('Chat with Knowledge Base')
        case 'file':
        default:
            return props.settings.multi_file ? t('Upload comparison documents') : t('Upload your document')
    }
})

const welcomeDesc = computed(() => {
    switch (props.sourceType) {
        case 'url':
            return t('Enter a web page URL to extract its text and start chatting with it.')
        case 'youtube':
            return t('Paste a YouTube video link to fetch its transcript and ask questions about the video.')
        case 'collection':
            return t('Select one of your knowledge bases to start chatting with its compiled documents.')
        case 'file':
        default:
            return props.settings.multi_file 
                ? t('Upload 2 to 3 documents to start a side-by-side comparative analysis.') 
                : t('Upload a PDF, Word, or text document to start chatting with your content.')
    }
})

const welcomeIcon = computed(() => {
    switch (props.sourceType) {
        case 'url':
            return 'ti-world'
        case 'youtube':
            return 'ti-brand-youtube'
        case 'collection':
            return 'ti-folder'
        case 'file':
        default:
            return props.settings.multi_file ? 'ti-files' : 'ti-cloud-upload'
    }
})

const submitButtonText = computed(() => {
    switch (props.sourceType) {
        case 'url':
            return t('Start chatting with web page')
        case 'youtube':
            return t('Start chatting with video')
        case 'collection':
            return t('Start chatting with knowledge base')
        case 'file':
        default:
            return props.settings.multi_file ? t('Compare documents') : t('Start chatting with document')
    }
})
</script>

<template>
    <div class="flex items-center justify-center h-full p-8 overflow-y-auto">
        <div class="max-w-xl w-full py-6">
            <div class="text-center mb-8">
                <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-primary-500/10 dark:bg-primary-500/20 flex items-center justify-center shadow-sm">
                    <i :class="[welcomeIcon]" class="text-3xl text-primary-500"></i>
                </div>
                <h2 class="text-xl font-bold mb-2 text-surface-900 dark:text-surface-50">{{ welcomeTitle }}</h2>
                <p class="text-sm text-surface-500 dark:text-surface-400 max-w-sm mx-auto font-medium">
                    {{ welcomeDesc }}
                </p>
            </div>

            <!-- File Drop Zone -->
            <template v-if="sourceType === 'file'">
                <!-- Dropzone: visible when no files are selected OR in multi-file mode (and files list is less than max) -->
                <div
                    v-if="settings.multi_file || filesList.length === 0"
                    class="border-2 border-dashed rounded-3xl p-10 text-center transition-all duration-300 cursor-pointer bg-white/40 dark:bg-surface-900/40 hover:bg-white dark:hover:bg-surface-900 hover:shadow-lg"
                    :class="dragOver 
                        ? 'border-primary-500 bg-primary-500/[0.04] scale-[1.01] shadow-xl shadow-primary-500/[0.02]' 
                        : 'border-surface-200/80 dark:border-surface-800/80 hover:border-primary-500/50 dark:hover:border-primary-500/40'"
                    @dragover.prevent="dragOver = true"
                    @dragleave="dragOver = false"
                    @drop.prevent="handleFileDrop"
                    @click="($refs.fileInput as HTMLInputElement)?.click()"
                >
                    <input
                        ref="fileInput"
                        type="file"
                        class="hidden"
                        :accept="acceptString"
                        :multiple="settings.multi_file"
                        @change="handleFileSelect"
                    />

                    <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-surface-100 dark:bg-surface-800/50 flex items-center justify-center text-surface-400 dark:text-surface-500">
                        <i class="ti ti-file-upload text-2xl"></i>
                    </div>
                    <p class="text-sm font-bold text-surface-800 dark:text-surface-200 mb-1.5">
                        {{ settings.multi_file ? t('Drop 2-3 files here or click to browse') : t('Drop your file here or click to browse') }}
                    </p>
                    <p class="text-xs text-surface-400 dark:text-surface-500 font-medium">
                        {{ acceptString.replace(/\.+/g, '').replace(/,/g, ', ').toUpperCase() }} &middot; {{ t('Up to') }} {{ maxSizeMb }} MB {{ settings.multi_file ? `(${minFiles}-${maxFiles} files)` : '' }}
                    </p>
                </div>

                <!-- Single File Preview (Original UI) -->
                <div
                    v-if="!settings.multi_file && filesList.length === 1"
                    class="border border-surface-200/85 dark:border-surface-800/80 rounded-3xl p-10 text-center bg-white dark:bg-surface-900 shadow-md"
                >
                    <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-emerald-500/10 dark:bg-emerald-500/20 flex items-center justify-center text-emerald-500">
                        <i class="ti ti-file-check text-2xl animate-bounce"></i>
                    </div>
                    <p class="text-sm font-bold text-surface-800 dark:text-surface-200 mb-1 truncate max-w-[280px] mx-auto" :title="filesList[0].name">{{ filesList[0].name }}</p>
                    <p class="text-xs text-surface-400 dark:text-surface-500 mb-4 font-mono font-medium">{{ formatSize(filesList[0].size) }}</p>
                    <button type="button" class="inline-flex items-center px-2.5 py-1 text-[11px] font-bold text-red-500 bg-red-500/5 hover:bg-red-500/10 border border-red-500/10 rounded-lg transition-all" @click.stop="removeFile(0)">{{ t('Remove') }}</button>
                </div>

                <!-- Multiple Files Preview List -->
                <div v-if="settings.multi_file && filesList.length > 0" class="mt-6 space-y-3">
                    <div class="text-[10px] font-semibold text-surface-400 dark:text-surface-500 uppercase tracking-wider mb-2">
                        {{ t('Selected Files') }} ({{ filesList.length }}/{{ maxFiles }})
                    </div>
                    <div v-for="(f, index) in filesList" :key="index" class="flex items-center justify-between p-3.5 bg-white dark:bg-surface-900 border border-surface-200/80 dark:border-surface-800/80 rounded-2xl shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 dark:bg-emerald-500/20 flex items-center justify-center text-emerald-500 shrink-0">
                                <i class="ti ti-file-check text-xl"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-surface-800 dark:text-surface-200 truncate" :title="f.name">
                                    {{ f.name }}
                                </p>
                                <p class="text-xs text-surface-400 dark:text-surface-500 font-mono">
                                    {{ formatSize(f.size) }}
                                </p>
                            </div>
                        </div>
                        <button 
                            type="button" 
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-surface-400 hover:text-red-500 hover:bg-red-500/5 transition-all" 
                            @click.stop="removeFile(index)"
                        >
                            <i class="ti ti-trash text-base"></i>
                        </button>
                    </div>
                </div>
            </template>

            <!-- URL Input -->
            <template v-if="sourceType === 'url' || sourceType === 'youtube'">
                <div class="space-y-4">
                    <div class="form-control">
                        <label class="label pb-1">
                            <span class="label-text font-bold text-[10px] text-surface-400 dark:text-surface-500 uppercase tracking-wider">{{ sourceType === 'youtube' ? t('YouTube URL') : t('Website URL') }}</span>
                        </label>
                        <div class="relative">
                            <i :class="sourceType === 'youtube' ? 'ti ti-brand-youtube text-red-500' : 'ti ti-world text-primary-500'" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-lg"></i>
                            <input
                                v-model="url"
                                :type="sourceType === 'url' ? 'url' : 'text'"
                                class="input input-bordered w-full h-11 pl-11 rounded-xl bg-white dark:bg-surface-900 border-surface-200/80 dark:border-surface-800/80 text-sm focus:border-primary-500 dark:focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all duration-200 text-surface-900 dark:text-surface-50"
                                :placeholder="sourceType === 'youtube' ? 'https://youtube.com/watch?v=...' : 'https://example.com/article...'"
                                @input="error = ''"
                                @contextmenu="handleRightClick"
                            />
                        </div>
                    </div>
                </div>
            </template>


            <!-- Error -->
            <div v-if="error" class="alert border border-red-500/20 bg-red-500/5 text-red-500 mt-4 text-xs rounded-xl py-3 px-4 flex items-center gap-2">
                <i class="ti ti-alert-circle text-base"></i>
                <span class="font-medium">{{ error }}</span>
            </div>

            <!-- Submit -->
            <button
                class="w-full h-12 mt-6 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 text-white font-bold rounded-xl transition-all duration-300 shadow-sm hover:shadow-lg shadow-primary-500/10 hover:shadow-primary-500/25 inline-flex items-center justify-center gap-2 disabled:opacity-50 disabled:pointer-events-none text-sm"
                :disabled="!canSubmit"
                @click="submit"
            >
                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                <template v-else>
                    <i class="ti ti-messages text-base"></i>
                </template>
                {{ loading ? t('Processing...') : submitButtonText }}
            </button>
        </div>
    </div>
</template>
