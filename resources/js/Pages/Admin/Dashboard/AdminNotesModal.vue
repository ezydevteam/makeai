<script setup lang="ts">
import { ref, watch } from 'vue'
import AppModal from '@/Components/UI/AppModal.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { fromDateTimeLocalInput, toDateTimeLocalInput } from '@/lib/datetime'

const { t } = useTranslate()

interface Note {
    id: number
    subject: string
    description: string | null
    reminder_date: string | null
    auto_delete_date: string | null
    reminder_sent: boolean
    created_at: string
}

const props = defineProps<{ open: boolean; editNoteId?: number }>()
const emit = defineEmits<{ close: [] }>()

const notes = ref<Note[]>([])
const editingNote = ref<Note | null>(null)
const form = ref({ subject: '', description: '', reminder_date: '', auto_delete_date: '' })
const loading = ref(false)
const error = ref('')

function resetForm() {
    editingNote.value = null
    form.value = { subject: '', description: '', reminder_date: '', auto_delete_date: '' }
    error.value = ''
}

function openEdit(note: Note) {
    editingNote.value = note
    form.value = {
        subject: note.subject,
        description: note.description || '',
        reminder_date: toDateTimeLocalInput(note.reminder_date),
        auto_delete_date: toDateTimeLocalInput(note.auto_delete_date),
    }
}

async function save() {
    if (!form.value.subject.trim()) {
        error.value = 'Subject is required.'
        return
    }
    loading.value = true
    error.value = ''

    const payload = {
        subject: form.value.subject,
        description: form.value.description || null,
        reminder_date: fromDateTimeLocalInput(form.value.reminder_date),
        auto_delete_date: fromDateTimeLocalInput(form.value.auto_delete_date),
    }

    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.getAttribute('content') || ''

    try {
        const url = editingNote.value
            ? route('admin.notes.update', editingNote.value.id)
            : route('admin.notes.store')

        const method = 'POST'

        const res = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        })

        if (res.ok || res.redirected) {
            resetForm()
            fetchNotes()
            emit('close')
        } else {
            const data = await res.json()
            error.value = data.message || 'Something went wrong.'
        }
    } catch {
        error.value = 'Network error.'
    } finally {
        loading.value = false
    }
}

async function deleteNote(note: Note) {
    if (!confirm(t('Delete this note?'))) return

    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.getAttribute('content') || ''

    const res = await fetch(route('admin.notes.delete', note.id), {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
    })

    if (res.ok || res.redirected) {
        fetchNotes()
    }
}

async function fetchNotes() {
    const res = await fetch(route('admin.notes.index'), {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
    })
    if (res.ok) {
        const data = await res.json()
        notes.value = data.notes
    }
}

const isReminderDue = (n: Note) => n.reminder_date && !n.reminder_sent && new Date(n.reminder_date) <= new Date()

watch(() => props.open, async (isOpen) => {
    if (!isOpen) {
        resetForm()
        return
    }

    resetForm()
    await fetchNotes()
    if (props.editNoteId) {
        const note = notes.value.find(n => n.id === props.editNoteId)
        if (note) openEdit(note)
    }
})
</script>

<template>
    <AppModal
        :open="open"
        max-width="max-w-2xl"
        :title="t('Admin Notes')"
        @close="emit('close')"
    >
        <!-- Form -->
        <div class="grid grid-cols-1 gap-3">
            <input v-model="form.subject" type="text" :placeholder="t('Subject')" maxlength="255"
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-900 dark:text-white dark:placeholder-gray-500" />
            <textarea v-model="form.description" rows="2" :placeholder="t('Description (optional)')"
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-900 dark:text-white dark:placeholder-gray-500"></textarea>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ t('Reminder date') }}</label>
                    <input v-model="form.reminder_date" type="datetime-local"
                        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-900 dark:text-white" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ t('Auto-delete date') }}</label>
                    <input v-model="form.auto_delete_date" type="datetime-local"
                        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-900 dark:text-white" />
                </div>
            </div>
            <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
            <div class="flex items-center gap-2 pt-1">
                <button @click="save" :disabled="loading"
                    class="px-5 py-2.5 btn-primary-admin rounded-xl text-sm font-bold transition-all disabled:opacity-50">
                    {{ editingNote ? t('Update') : t('Add Note') }}
                </button>
                <button v-if="editingNote" @click="resetForm" class="px-4 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white">{{ t('Cancel') }}</button>
            </div>
        </div>
    </AppModal>
</template>
