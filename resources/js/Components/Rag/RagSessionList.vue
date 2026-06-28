<script setup lang="ts">
import { ref, nextTick } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import Tooltip from '@/Components/UI/Tooltip.vue'

interface Session {
    id: string
    title: string | null
    status: string
    source_meta: Record<string, unknown> | null
    created_at: string
}

const props = defineProps<{ sessions: Session[]; activeSessionId: string | null }>()
const emit = defineEmits<{ 
    reopen: [session: Session]
    delete: [id: string]
    rename: [id: string, title: string]
}>()
const { t } = useTranslate()

const editingSessionId = ref<string | null>(null)
const editTitle = ref('')
const editInputRef = ref<HTMLInputElement | HTMLInputElement[] | null>(null)

function startEdit(s: Session) {
    editingSessionId.value = s.id
    editTitle.value = String(s.title || s.source_meta?.filename || s.source_meta?.url || '')
    nextTick(() => {
        const el = Array.isArray(editInputRef.value) ? editInputRef.value[0] : editInputRef.value
        el?.focus()
        el?.select()
    })
}

function cancelEdit() {
    editingSessionId.value = null
    editTitle.value = ''
}

function saveEdit(id: string) {
    if (!editingSessionId.value) return
    const titleVal = editTitle.value.trim()
    if (titleVal && titleVal !== (props.sessions.find(s => s.id === id)?.title || '')) {
        emit('rename', id, titleVal)
    }
    editingSessionId.value = null
    editTitle.value = ''
}

function formatDate(iso: string): string {
    const d = new Date(iso)
    const now = new Date()
    const diff = now.getTime() - d.getTime()
    const mins = Math.floor(diff / 60000)
    if (mins < 1) return t('Just now')
    if (mins < 60) return `${mins}m ago`
    const hours = Math.floor(mins / 60)
    if (hours < 24) return `${hours}h ago`
    return d.toLocaleDateString()
}

function statusDot(status: string): string {
    return { ingesting: 'bg-yellow-500 animate-pulse shadow-sm shadow-yellow-500/35', ready: 'bg-emerald-500 shadow-sm shadow-emerald-500/35', failed: 'bg-red-500 shadow-sm shadow-red-500/35' }[status] || 'bg-surface-300'
}
</script>

<template>
    <div class="flex-1 overflow-y-auto py-3 px-3 space-y-4">
        <div>
            <div class="text-[10px] font-bold text-surface-400 dark:text-surface-500 uppercase tracking-wider px-3 py-1">
                {{ t('Recent sessions') }}
            </div>
            <div v-if="sessions.length === 0" class="text-xs text-surface-400 dark:text-surface-600 text-center py-8 px-2 font-medium">
                {{ t('No sessions yet') }}
            </div>
            <div class="space-y-1 mt-1.5">
                <button
                    v-for="s in sessions"
                    :key="s.id"
                    class="w-full text-left px-3.5 py-2.5 rounded-xl transition-all text-sm group relative border"
                    :class="s.id === activeSessionId 
                        ? 'bg-white dark:bg-surface-950 border-surface-200/60 dark:border-surface-850/60 shadow-sm text-primary-500 dark:text-primary-400 font-semibold' 
                        : 'bg-transparent border-transparent hover:bg-surface-200/50 dark:hover:bg-surface-800/40 text-surface-700 dark:text-surface-300'"
                    @click="editingSessionId !== s.id && emit('reopen', s)"
                >
                    <!-- Left Active Indicator -->
                    <span 
                        v-if="s.id === activeSessionId" 
                        class="absolute left-0 top-2 bottom-2 w-1 bg-primary-500 rounded-r-md"
                    ></span>

                    <div class="flex items-start gap-2.5">
                        <span :class="['inline-block w-1.5 h-1.5 rounded-full mt-1.5 shrink-0', statusDot(s.status)]"></span>
                        
                        <div class="min-w-0 flex-1">
                            <input
                                v-if="editingSessionId === s.id"
                                ref="editInputRef"
                                v-model="editTitle"
                                type="text"
                                class="w-full px-2 py-1 text-xs border rounded bg-white dark:bg-surface-900 border-surface-200 dark:border-surface-800 text-surface-900 dark:text-surface-50 focus:border-primary-500 focus:outline-none"
                                @click.stop
                                @keyup.enter="saveEdit(s.id)"
                                @keyup.esc="cancelEdit"
                                @blur="saveEdit(s.id)"
                            />
                            <template v-else>
                                <p class="truncate text-[13px] leading-snug pr-12">
                                    {{ s.title || s.source_meta?.filename || s.source_meta?.url || t('Untitled') }}
                                </p>
                                <p class="text-[11px] text-surface-400 dark:text-surface-500 mt-1 font-medium">{{ formatDate(s.created_at) }}</p>
                            </template>
                        </div>

                        <!-- Actions on hover -->
                        <div 
                            v-if="editingSessionId !== s.id"
                            class="opacity-0 group-hover:opacity-100 transition-opacity absolute right-2 top-2.5 z-10 flex items-center gap-0.5"
                        >
                            <Tooltip :content="t('Rename session')">
                                <button
                                    class="hover:text-primary-500 text-surface-400 dark:text-surface-500 w-6 h-6 flex items-center justify-center rounded-lg hover:bg-surface-150 dark:hover:bg-surface-800 transition-colors"
                                    @click.stop="startEdit(s)"
                                >
                                    <i class="ti ti-pencil text-sm"></i>
                                </button>
                            </Tooltip>
                            <Tooltip :content="t('Delete session')">
                                <button
                                    class="hover:text-red-500 text-surface-400 dark:text-surface-500 w-6 h-6 flex items-center justify-center rounded-lg hover:bg-surface-150 dark:hover:bg-surface-800 transition-colors"
                                    @click.stop="emit('delete', s.id)"
                                >
                                    <i class="ti ti-trash text-sm"></i>
                                </button>
                            </Tooltip>
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </div>
</template>
