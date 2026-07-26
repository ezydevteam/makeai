<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import UserLayout from '@themes/default/js/Layouts/UserLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AdSection from '@themes/default/js/Components/AdSection.vue'

const RichEditor = defineAsyncComponent(() => import('@/Components/UI/RichEditor.vue'))

defineOptions({ layout: UserLayout })

const props = defineProps<{
    document: {
        id: number
        title: string
        content: string
        tool_slug?: string | null
        tool_name?: string | null
        word_count?: number | null
        updated_at?: string | null
    }
}>()

const routeTo = (name: string, params?: unknown): string => route(name, params)
const { t } = useTranslate()

const form = useForm({
    title: props.document.title || '',
    content: props.document.content || '',
})

const countWords = (html: string | null | undefined): number => {
    if (!html) return 0

    // Replace closing block tags with a space to prevent words from running together
    const spacedHtml = html.replace(/<\/(p|h1|h2|h3|h4|h5|h6|div|li|tr|th|td|blockquote|pre)>/gi, ' ')

    const tempDiv = document.createElement('div')
    tempDiv.innerHTML = spacedHtml

    let text = tempDiv.textContent || tempDiv.innerText || ''
    text = text.trim()

    if (!text) return 0

    const words = text.split(/\s+/)
    return words.filter((word) => word.length > 0).length
}

const currentWordCount = computed(() => {
    return countWords(form.content)
})

const saveDocument = () => {
    form.patch(routeTo('documents.update', props.document.id), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="t('Edit :title', { title: document.title })" />

    <div class="!max-w-5xl mx-auto px-4 py-6 sm:px-6">
        <!-- Top Ad Slot -->
        <AdSection zone="tool_page_top" class="mx-auto mb-4 w-full max-w-5xl" />

        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-2 text-sm mb-2">
                    <Link :href="routeTo('home')" class="text-gray-500 hover:text-primary-500 transition-colors">
                        <i class="ti ti-home"></i>
                    </Link>
                    <i class="ti ti-chevron-right text-gray-400 text-xs"></i>
                    <Link :href="routeTo('ai.tools.index')" class="text-gray-500 hover:text-primary-500 transition-colors">{{ t('AI Tools') }}</Link>
                    <i class="ti ti-chevron-right text-gray-400 text-xs"></i>
                    <span class="text-gray-500">{{ t('Documents') }}</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex flex-wrap items-center gap-2">
                    <span>{{ t('Edit Document') }}</span>
                    <span v-if="document.tool_name || document.tool_slug" class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-2.5 py-0.5 text-xs font-semibold text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300 shadow-xs">
                        <i class="ti ti-robot text-[13px]"></i>
                        {{ document.tool_name || document.tool_slug }}
                    </span>
                </h1>
            </div>

            <div class="flex items-center gap-2">
                <Link
                    v-if="document.tool_slug"
                    :href="routeTo('ai.tools.show', document.tool_slug)"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-xl hover:border-primary-300 dark:hover:border-primary-700 transition-colors"
                >
                    <i class="ti ti-arrow-left text-base"></i>
                    {{ t('Back to Tool') }}
                </Link>
                <button
                    type="button"
                    :disabled="form.processing"
                    class="px-5 py-2 text-sm font-semibold text-white bg-primary-500 rounded-xl disabled:opacity-50 transition-colors inline-flex items-center gap-2"
                    @click="saveDocument"
                >
                    <i class="ti ti-device-floppy"></i>
                    {{ form.processing ? t('Saving') : t('Save') }}
                </button>
            </div>
        </div>

        <div class="space-y-6">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">{{ t('Title') }}</label>
                <input
                    v-model="form.title"
                    type="text"
                    class="w-full px-4 py-3 bg-gray-50 dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-xl text-gray-900 dark:text-white transition-all focus:!ring-1 focus:ring-primary-500/40"
                    :placeholder="t('Document title')"
                />
                <p v-if="form.errors.title" class="text-sm text-danger-500 mt-2">{{ form.errors.title }}</p>
            </div>

            <!-- Middle Ad Slot -->
            <AdSection zone="tool_page_bottom" class="mx-auto mt-6 w-full max-w-5xl" />

            <div>
                <RichEditor v-model="form.content" variant="comment" />
                <p v-if="form.errors.content" class="text-sm text-danger-500 mt-2">{{ form.errors.content }}</p>
            </div>
        </div>
    </div>
</template>
