<script setup lang="ts">
import { computed, ref } from 'vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import { useTranslate } from '@/Composables/useTranslate'

const { t } = useTranslate()

interface Note {
    id: number
    subject: string
    description: string | null
    reminder_date: string | null
    auto_delete_date?: string | null
    reminder_sent?: boolean
    created_at: string
}

const props = defineProps<{
    open: boolean
    notes: Note[]
}>()

const emit = defineEmits<{
    close: []
    create: []
    edit: [noteId: number]
    delete: [noteId: number]
}>()

const search = ref('')
const deletingNoteId = ref<number | null>(null)
const deleting = ref(false)
const deleteError = ref('')

const filteredNotes = computed(() => {
    const term = search.value.trim().toLowerCase()
    if (!term) return props.notes

    return props.notes.filter((note) => {
        const subject = note.subject.toLowerCase()
        const description = (note.description || '').toLowerCase()
        return subject.includes(term) || description.includes(term)
    })
})

function formatDate(value: string | null): string {
    if (!value) return ''
    return new Date(value).toLocaleString()
}

function requestDelete(noteId: number) {
    deleteError.value = ''
    deletingNoteId.value = noteId
}

async function confirmDelete() {
    if (deletingNoteId.value === null || deleting.value) {
        return
    }

    deleting.value = true
    const noteId = deletingNoteId.value
    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.getAttribute('content') || ''

    try {
        const res = await fetch(route('admin.notes.delete', noteId), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        })

        if (res.ok || res.redirected) {
            emit('delete', noteId)
        } else {
            deleteError.value = t('Delete failed. Please try again.')
        }
    } catch {
        deleteError.value = t('Network error.')
    } finally {
        deleting.value = false
        deletingNoteId.value = null
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 backdrop-blur-sm p-4 pt-16" @click.self="emit('close')">
            <div class="w-full max-w-4xl rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-surface-800 dark:bg-surface-900">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('All Notes') }}</h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ t('Search, edit, or delete notes') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="emit('create')"
                            class="inline-flex items-center gap-2 rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-semibold text-primary-700 hover:bg-primary-100 dark:border-primary-900/40 dark:bg-primary-900/20 dark:text-primary-300"
                        >
                            <i class="ti ti-plus text-sm"></i>
                            {{ t('Create') }}
                        </button>
                        <button @click="emit('close')" class="rounded-full w-9 h-9 flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800">
                            <i class="ti ti-x text-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                    <div class="relative">
                        <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="t('Search notes...')"
                            class="w-full rounded-xl border border-gray-200 bg-white py-2.5 pl-10 pr-4 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-900 dark:text-white dark:placeholder-gray-500"
                        />
                    </div>
                </div>

                <div class="max-h-[70vh] overflow-y-auto px-6 py-5">
                    <p v-if="deleteError" class="mb-3 text-sm text-red-600 dark:text-red-400">{{ deleteError }}</p>
                    <div v-if="filteredNotes.length" class="grid grid-cols-1 gap-3">
                        <div
                            v-for="note in filteredNotes"
                            :key="note.id"
                            class="rounded-2xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/50"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ note.subject }}</p>
                                    <p v-if="note.description" class="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">{{ note.description }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2 text-[10px] text-gray-500 dark:text-gray-400">
                                        <span v-if="note.reminder_date && !note.reminder_sent && new Date(note.reminder_date) > new Date()" class="rounded-full bg-amber-50 px-2 py-1 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                                            {{ t('Reminder') }}: {{ formatDate(note.reminder_date) }}
                                        </span>
                                        <span v-if="note.auto_delete_date" class="rounded-full bg-red-50 px-2 py-1 text-red-700 dark:bg-red-900/20 dark:text-red-300">
                                            {{ t('Auto-delete') }}: {{ formatDate(note.auto_delete_date) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <button
                                        @click="emit('edit', note.id)"
                                        class="rounded-full w-9 h-9 flex items-center justify-center text-gray-400 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-surface-700 dark:hover:text-gray-200"
                                        :aria-label="t('Edit note')"
                                    >
                                        <i class="ti ti-edit text-base"></i>
                                    </button>
                                    <button
                                        @click="requestDelete(note.id)"
                                        class="rounded-full w-9 h-9 flex items-center justify-center text-gray-400 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-300"
                                        :aria-label="t('Delete note')"
                                    >
                                        <i class="ti ti-trash text-base"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-500">
                        <svg class="mb-3 h-10 w-10 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        <p class="text-sm">{{ t('No notes found') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <ActionConfirmModal
        :open="deletingNoteId !== null"
        :title="t('Delete note?')"
        :message="t('This note will be removed permanently.')"
        :confirm-label="t('Delete')"
        :cancel-label="t('Cancel')"
        :processing="deleting"
        :processing-label="t('Deleting...')"
        :variant="'danger'"
        @confirm="confirmDelete"
        @cancel="deletingNoteId = null"
    />
</template>
