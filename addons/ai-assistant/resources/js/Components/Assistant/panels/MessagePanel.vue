<script setup lang="ts">
import { computed, ref } from 'vue'
import AssistantSocialRow from '../AssistantSocialRow.vue'
import { submitMessage } from '../../../Composables/useAssistantPanels'
import { useTranslate } from '@/Composables/useTranslate'
import type { AssistantSettings } from '../../../types'

const props = defineProps<{
    settings: AssistantSettings
}>()

const { t } = useTranslate()

const hasChannels = computed(() => Object.keys(props.settings.channels ?? {}).length > 0)

const email = ref('')
const name = ref('')
const message = ref('')
const website = ref('') // honeypot — must stay empty

const sending = ref(false)
const sent = ref(false)
const successMessage = ref('')
const errors = ref<Record<string, string[]>>({})
const formError = ref<string | null>(null)

async function submit() {
    if (sending.value) return

    formError.value = null
    errors.value = {}

    if (!email.value.trim() || !message.value.trim()) {
        formError.value = t('Please enter your email and a message.')
        return
    }

    sending.value = true

    const result = await submitMessage(props.settings.endpoints.message, {
        email: email.value.trim(),
        message: message.value.trim(),
        name: name.value.trim() || undefined,
        website: website.value,
    })

    sending.value = false

    if (result.ok) {
        sent.value = true
        successMessage.value = result.message
        return
    }

    errors.value = result.errors ?? {}
    if (!Object.keys(errors.value).length) formError.value = result.message
}

function reset() {
    sent.value = false
    email.value = ''
    name.value = ''
    message.value = ''
}
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-y-auto px-4 py-4">
        <!-- Success state -->
        <div v-if="sent" class="flex flex-1 flex-col items-center justify-center text-center">
            <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                <i class="ti ti-check text-2xl text-green-600 dark:text-green-400"></i>
            </div>
            <p class="text-sm text-gray-700 dark:text-gray-200">{{ successMessage }}</p>
            <button
                type="button"
                class="mt-4 text-sm text-[var(--ai-accent,#1F75FE)] hover:underline"
                @click="reset"
            >
                {{ t('Send another message') }}
            </button>
        </div>

        <!-- Form -->
        <form v-else class="space-y-3" @submit.prevent="submit">
            <p class="text-center text-xs text-gray-500 dark:text-gray-400">
                {{ t('Enter your email and message — we’ll reply soon.') }}
            </p>

            <div v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600 dark:bg-red-900/20 dark:text-red-400">
                {{ formError }}
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">{{ t('Name') }}</label>
                <input
                    v-model="name"
                    type="text"
                    :placeholder="t('Your name (optional)')"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--ai-accent,#1F75FE)] dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                />
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">{{ t('Email Address') }}</label>
                <input
                    v-model="email"
                    type="email"
                    placeholder="youremail@example.com"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--ai-accent,#1F75FE)] dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    :class="errors.email ? 'border-red-400' : ''"
                />
                <p v-if="errors.email" class="mt-1 text-xs text-red-500">{{ errors.email[0] }}</p>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">{{ t('Your message') }}</label>
                <textarea
                    v-model="message"
                    rows="5"
                    :placeholder="t('Your message')"
                    class="w-full resize-none rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--ai-accent,#1F75FE)] dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    :class="errors.message ? 'border-red-400' : ''"
                ></textarea>
                <p v-if="errors.message" class="mt-1 text-xs text-red-500">{{ errors.message[0] }}</p>
            </div>

            <!-- Honeypot: hidden from users, tempting to bots. -->
            <input
                v-model="website"
                type="text"
                tabindex="-1"
                autocomplete="off"
                class="hidden"
                aria-hidden="true"
            />

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-1.5 rounded-xl py-2.5 text-sm font-medium text-white transition-opacity disabled:opacity-60"
                style="background: var(--ai-accent, #1F75FE);"
                :disabled="sending"
            >
                <i v-if="sending" class="ti ti-loader animate-spin"></i>
                <i v-else class="ti ti-send"></i>
                {{ sending ? t('Sending…') : t('Send email') }}
            </button>
        </form>

        <!-- Other ways to reach us — offered alongside the form only. Once the message is
             sent, the success state stands on its own; pushing more contact options at that
             point just muddies a completed action. -->
        <div v-if="hasChannels && !sent" class="mt-auto pt-5">
            <p class="mb-1 text-center text-xs text-gray-400">{{ t('Or reach us on') }}</p>
            <AssistantSocialRow :channels="settings.channels" />
        </div>
    </div>
</template>
