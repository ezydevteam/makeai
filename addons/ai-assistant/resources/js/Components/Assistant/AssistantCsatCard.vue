<script setup lang="ts">
import { ref } from 'vue'
import { submitCsat } from '../../Composables/useAssistantPanels'
import { useTranslate } from '@/Composables/useTranslate'

const props = defineProps<{
    endpoint: string
    sessionId: string
}>()

const { t } = useTranslate()

// One rating per session; the flag is persisted by the parent (ChatPanel) so a reopened
// widget doesn't ask again.
const emit = defineEmits<{ (e: 'rated', score: number): void }>()

// Highest → lowest, matching the screenshot order. Score is what the server stores.
const OPTIONS: Array<{ label: string; score: number }> = [
    { label: 'Very Good', score: 5 },
    { label: 'Good', score: 4 },
    { label: 'Average', score: 3 },
    { label: 'Low', score: 2 },
    { label: 'Bad', score: 1 },
]

const submitting = ref(false)
const done = ref(false)

async function choose(score: number) {
    if (submitting.value || done.value) return
    submitting.value = true

    const ok = await submitCsat(props.endpoint, {
        session_id: props.sessionId,
        score,
        context_page: window.location.pathname,
    })

    submitting.value = false

    if (ok) {
        done.value = true
        emit('rated', score)
    }
}
</script>

<template>
    <!-- A standalone card, not a chat bubble: this now lives in the history list, where it
         asks about the conversation the user just finished. -->
    <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-gray-800/60">
        <template v-if="!done">
            <p class="text-sm text-gray-800 dark:text-gray-100">
                <!-- Wording is a translation string, not an admin setting: the site's
                     translator is the right place to change or localise it. -->
                {{ t('Tell us about your experience with our assistant.') }}
            </p>
            <div class="mt-2.5 flex flex-wrap gap-1.5">
                <button
                    v-for="option in OPTIONS"
                    :key="option.score"
                    type="button"
                    class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs text-gray-700 transition-colors hover:border-[var(--ai-accent,#1F75FE)] hover:text-[var(--ai-accent,#1F75FE)] dark:hover:text-[var(--ai-accent,#1F75FE)] disabled:opacity-50 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-200"
                    :disabled="submitting"
                    @click="choose(option.score)"
                >
                    {{ t(option.label) }}
                </button>
            </div>
        </template>

        <p v-else class="flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-200">
            <i class="ti ti-heart-filled text-[var(--ai-accent,#1F75FE)]"></i>
            {{ t('Thanks for your feedback!') }}
        </p>
    </div>
</template>
