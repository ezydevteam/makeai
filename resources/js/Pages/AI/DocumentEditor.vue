<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import UserLayout from '@/Layouts/UserLayout.vue'
import RichEditor from '@/Components/RichEditor.vue'

defineOptions({ layout: UserLayout })

const props = defineProps<{
    document: {
        id: number
        title: string
        content: string
        tool_slug?: string | null
        word_count?: number | null
        folder_id?: number | null
        updated_at?: string | null
    }
}>()

const routeTo = (name: string, params?: unknown): string => route(name, params)

const form = useForm({
    title: props.document.title || '',
    content: props.document.content || '',
    folder_id: props.document.folder_id || null,
})

const saveDocument = () => {
    form.patch(routeTo('documents.update', props.document.id), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="`Edit ${document.title}`" />

    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-2 text-sm mb-2">
                    <Link :href="routeTo('ai.tools.index')" class="text-gray-500 hover:text-primary-500 transition-colors">AI Tools</Link>
                    <i class="ti-chevron-right text-gray-400 text-xs"></i>
                    <span class="text-gray-500">Documents</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Document</h1>
            </div>

            <div class="flex items-center gap-2">
                <Link
                    v-if="document.tool_slug"
                    :href="routeTo('ai.tools.show', document.tool_slug)"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-xl hover:border-primary-300 dark:hover:border-primary-700 transition-colors"
                >
                    Back to Tool
                </Link>
                <button
                    type="button"
                    :disabled="form.processing"
                    class="px-5 py-2 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 rounded-xl disabled:opacity-50 transition-colors inline-flex items-center gap-2"
                    @click="saveDocument"
                >
                    <i class="ti-device-floppy"></i>
                    {{ form.processing ? 'Saving' : 'Save' }}
                </button>
            </div>
        </div>

        <div class="bg-white dark:bg-surface-950 border border-gray-100 dark:border-surface-800 rounded-2xl p-4 sm:p-6 shadow-sm">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Title</label>
            <input
                v-model="form.title"
                type="text"
                class="w-full px-4 py-3 mb-5 bg-gray-50 dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-xl text-gray-900 dark:text-white focus:border-primary-500 focus:ring-primary-500/20"
                placeholder="Document title"
            />
            <p v-if="form.errors.title" class="text-sm text-danger-500 -mt-3 mb-4">{{ form.errors.title }}</p>

            <RichEditor v-model="form.content" />
            <p v-if="form.errors.content" class="text-sm text-danger-500 mt-3">{{ form.errors.content }}</p>

            <div class="flex flex-wrap items-center justify-between gap-3 mt-5 pt-5 border-t border-gray-100 dark:border-surface-800 text-xs text-gray-500">
                <div class="flex flex-wrap items-center gap-3">
                    <span v-if="document.tool_slug">Tool: {{ document.tool_slug }}</span>
                    <span v-if="document.word_count !== null && document.word_count !== undefined">{{ document.word_count }} words</span>
                </div>
                <span v-if="form.recentlySuccessful" class="text-primary-600 dark:text-primary-400">Saved</span>
            </div>
        </div>
    </div>
</template>
