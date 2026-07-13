<script setup lang="ts">
import { ref, computed } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import type { useChat, ConversationTag } from '../Composables/useChat'
import { inject } from 'vue'

const { t } = useTranslate()
const chat = inject<ReturnType<typeof useChat>>('chat')!

const emit = defineEmits<{
    (e: 'close'): void
}>()

const editingTag = ref<ConversationTag | null>(null)
const newTagName = ref('')
const newTagColor = ref('#3B82F6')
const isCreating = ref(false)
const isSaving = ref(false)
const saveError = ref('')

const colorPresets = [
    '#3B82F6', // blue
    '#10B981', // green
    '#F59E0B', // yellow
    '#EF4444', // red
    '#8B5CF6', // purple
    '#EC4899', // pink
    '#06B6D4', // cyan
    '#F97316', // orange
]

function startCreate() {
    isCreating.value = true
    editingTag.value = null
    newTagName.value = ''
    newTagColor.value = '#3B82F6'
}

function startEdit(tag: ConversationTag) {
    editingTag.value = tag
    isCreating.value = false
    newTagName.value = tag.name
    newTagColor.value = tag.color
}

function cancelEdit() {
    editingTag.value = null
    isCreating.value = false
    newTagName.value = ''
    newTagColor.value = '#3B82F6'
    saveError.value = ''
}

async function saveTag() {
    // Guard against double-submit: an unguarded second click created duplicate tags.
    if (!newTagName.value.trim() || isSaving.value) return

    isSaving.value = true
    saveError.value = ''
    try {
        if (isCreating.value) {
            await chat.createTag(newTagName.value.trim(), newTagColor.value)
        } else if (editingTag.value) {
            await chat.updateTag(editingTag.value.id, newTagName.value.trim(), newTagColor.value)
        }
        cancelEdit()
    } catch (e) {
        saveError.value = e instanceof Error ? e.message : t('Failed to save tag')
    } finally {
        isSaving.value = false
    }
}

async function deleteTag(tag: ConversationTag) {
    if (!confirm(t('Are you sure you want to delete this tag?'))) return
    saveError.value = ''
    try {
        await chat.deleteTag(tag.id)
    } catch (e) {
        saveError.value = e instanceof Error ? e.message : t('Failed to delete tag')
    }
}
</script>

<template>
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="emit('close')">
        <div class="bg-white dark:bg-[#252525] rounded-2xl shadow-xl max-w-md w-full mx-4 max-h-[80vh] flex flex-col">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                <h2 class="text-lg font-semibold text-[#1a1a1a] dark:text-white">{{ t('Manage Tags') }}</h2>
                <button class="text-[#6e6a65] dark:text-white/50 hover:text-[#1a1a1a] dark:hover:text-white transition-colors" @click="emit('close')">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto px-6 py-4">
                <!-- Create/Edit Form -->
                <div v-if="isCreating || editingTag" class="mb-6 p-4 bg-black/5 dark:bg-white/5 rounded-xl">
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-[#6e6a65] dark:text-white/50 mb-1">{{ t('Tag Name') }}</label>
                        <input
                            v-model="newTagName"
                            type="text"
                            maxlength="50"
                            class="w-full px-3 py-2 rounded-lg border border-black/10 dark:border-white/10 bg-white dark:bg-white/5 text-[#1a1a1a] dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/50"
                            :placeholder="t('Enter tag name')"
                        />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-[#6e6a65] dark:text-white/50 mb-1">{{ t('Color') }}</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="color in colorPresets"
                                :key="color"
                                class="w-8 h-8 rounded-full border-2 transition-transform hover:scale-110"
                                :class="newTagColor === color ? 'border-[#1a1a1a] dark:border-white scale-110' : 'border-transparent'"
                                :style="{ backgroundColor: color }"
                                @click="newTagColor = color"
                            ></button>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button
                            class="px-4 py-2 rounded-lg bg-primary-500 text-white text-sm font-medium hover:bg-primary-600 transition-colors disabled:opacity-60"
                            :disabled="isSaving || !newTagName.trim()"
                            @click="saveTag"
                        >
                            {{ isSaving ? t('Saving...') : (isCreating ? t('Create') : t('Save')) }}
                        </button>
                        <button
                            class="px-4 py-2 rounded-lg bg-black/5 dark:bg-white/5 text-[#6e6a65] dark:text-white/50 text-sm font-medium hover:bg-black/10 dark:hover:bg-white/10 transition-colors disabled:opacity-60"
                            :disabled="isSaving"
                            @click="cancelEdit"
                        >
                            {{ t('Cancel') }}
                        </button>
                    </div>
                    <p v-if="saveError" class="mt-2 text-xs text-red-500">{{ saveError }}</p>
                </div>

                <!-- Tags List -->
                <div v-if="chat.tags.value.length === 0 && !isCreating" class="text-center py-8">
                    <p class="text-sm text-[#6e6a65] dark:text-white/50">{{ t('No tags yet') }}</p>
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="tag in chat.tags.value"
                        :key="tag.id"
                        class="flex items-center gap-3 p-3 rounded-lg bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 transition-colors"
                    >
                        <div class="w-4 h-4 rounded-full shrink-0" :style="{ backgroundColor: tag.color }"></div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-[#1a1a1a] dark:text-white truncate">{{ tag.name }}</div>
                            <div class="text-xs text-[#6e6a65] dark:text-white/50">
                                {{ tag.conversations_count || 0 }} {{ t('conversations') }}
                            </div>
                        </div>
                        <div class="flex gap-1">
                            <button
                                class="p-1.5 rounded-lg text-[#6e6a65] dark:text-white/50 hover:text-[#1a1a1a] dark:hover:text-white hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                                @click="startEdit(tag)"
                            >
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button
                                class="p-1.5 rounded-lg text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
                                @click="deleteTag(tag)"
                            >
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-black/5 dark:border-white/10">
                <button
                    v-if="!isCreating && !editingTag"
                    class="w-full px-4 py-2 rounded-lg bg-primary-500 text-white text-sm font-medium hover:bg-primary-600 transition-colors"
                    @click="startCreate"
                >
                    {{ t('Create New Tag') }}
                </button>
            </div>
        </div>
    </div>
</template>
