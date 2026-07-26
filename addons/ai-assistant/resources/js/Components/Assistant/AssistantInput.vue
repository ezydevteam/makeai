<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { csrfHeaders } from '../../Composables/useAssistantApi'
import { toastAssistantError, toastAssistantFailure } from '../../Composables/useAssistantErrors'
import { useTranslate } from '@/Composables/useTranslate'
import type { AssistantAttachment, SlashCommand } from '../../types'

const props = defineProps<{
    disabled: boolean
    isStreaming: boolean
    allowUpload: boolean
    allowEmoji: boolean
    allowedTypes: string
    extractEndpoint: string
    commands: SlashCommand[]
}>()

const emit = defineEmits<{
    (e: 'send', text: string, file?: AssistantAttachment): void
    (e: 'stop'): void
}>()

const { t } = useTranslate()

const input = ref('')
const textarea = ref<HTMLTextAreaElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const uploadError = ref<string | null>(null)
const attachedFile = ref<AssistantAttachment | null>(null)

/* ── Slash-command autocomplete ────────────────────────────────
 |
 | `commands` is already scope-filtered server-side and shared inline on the Inertia
 | prop, so the menu is available on the first keystroke with no round-trip. An empty
 | array (the admin disabled slash commands) simply turns the feature off.
 */
const menuDismissed = ref(false)
const activeIndex = ref(0)

/** The command name being typed, e.g. "/do" → "do". Null once a space is typed. */
const commandQuery = computed<string | null>(() => {
    if (props.commands.length === 0) return null
    const match = /^\/([a-z0-9_-]*)$/i.exec(input.value)
    return match ? match[1].toLowerCase() : null
})

const filteredCommands = computed<SlashCommand[]>(() => {
    const query = commandQuery.value
    if (query === null) return []
    if (query === '') return props.commands

    return props.commands.filter(c => c.name.toLowerCase().startsWith(query))
})

const menuOpen = computed(() => !menuDismissed.value && filteredCommands.value.length > 0)

watch(input, () => {
    menuDismissed.value = false
    activeIndex.value = 0
    uploadError.value = null
})

watch(filteredCommands, (list) => {
    if (activeIndex.value > list.length - 1) activeIndex.value = 0
})

function moveActive(delta: number) {
    const count = filteredCommands.value.length
    if (count === 0) return
    activeIndex.value = (activeIndex.value + delta + count) % count
}

function applyCommand(command: SlashCommand) {
    input.value = `/${command.name} `
    menuDismissed.value = true

    nextTick(() => {
        textarea.value?.focus()
        autoResize()
    })
}

/* ── File upload ───────────────────────────────────────────── */

const acceptFilter = computed(() => {
    if (!props.allowedTypes) return ''
    return props.allowedTypes
        .split(',')
        .map((ext) => {
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

    uploadError.value = null

    const extension = file.name.split('.').pop()?.toLowerCase() ?? ''
    const allowedList = props.allowedTypes
        .split(',')
        .map(ext => ext.trim().toLowerCase().replace(/^\./, ''))

    if (!allowedList.includes(extension)) {
        uploadError.value = t('File type not allowed.')
        target.value = ''
        return
    }

    if (file.size > 10 * 1024 * 1024) {
        uploadError.value = t('File is too large. Max size is 10MB.')
        target.value = ''
        return
    }

    uploading.value = true

    const formData = new FormData()
    formData.append('file', file)

    try {
        const response = await fetch(props.extractEndpoint, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                ...csrfHeaders(),
            },
            credentials: 'same-origin',
            body: formData,
        })

        if (!response.ok) {
            // Reads the server's own wording — demo mode's block, the size/type rejection,
            // a rate limit — and toasts it as well as showing it under the input.
            throw new Error(await toastAssistantError(response, t('Failed to upload and parse file.')))
        }

        const data = await response.json() as { success?: boolean; filename?: string; text?: string; error?: string }

        if (!data.success || !data.filename) {
            throw new Error(toastAssistantFailure(data.error || t('Failed to upload and parse file.')))
        }

        attachedFile.value = { name: data.filename, text: data.text ?? '' }
    } catch (err: unknown) {
        uploadError.value = err instanceof Error
            ? err.message
            : toastAssistantFailure(t('Something went wrong during file upload.'))
    } finally {
        uploading.value = false
        target.value = ''
    }
}

function removeAttachedFile() {
    attachedFile.value = null
}

/* ── Send / stop ───────────────────────────────────────────── */

const canSend = computed(() => Boolean(input.value.trim()) || Boolean(attachedFile.value))

function onSubmit() {
    if (props.disabled || uploading.value) return

    const text = input.value.trim()
    if (!text && !attachedFile.value) return

    const finalMsg = text || `${t('Analyze attached file')}: ${attachedFile.value?.name}`
    emit('send', finalMsg, attachedFile.value ?? undefined)

    input.value = ''
    attachedFile.value = null
    menuDismissed.value = false

    if (textarea.value) {
        textarea.value.style.height = 'auto'
    }
}

function onKeydown(e: KeyboardEvent) {
    if (menuOpen.value) {
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault()
                moveActive(1)
                return
            case 'ArrowUp':
                e.preventDefault()
                moveActive(-1)
                return
            case 'Enter':
            case 'Tab': {
                const command = filteredCommands.value[activeIndex.value]
                if (!command) break
                e.preventDefault()
                applyCommand(command)
                return
            }
            case 'Escape':
                e.preventDefault()
                menuDismissed.value = true
                return
        }
    }

    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault()
        onSubmit()
    }
}

/** Must match the textarea's max-h class, or the box and the scrollbar disagree. */
const MAX_INPUT_HEIGHT = 140

function autoResize() {
    const el = textarea.value
    if (!el) return

    el.style.height = 'auto'
    el.style.height = Math.min(el.scrollHeight, MAX_INPUT_HEIGHT) + 'px'

    // Only allow scrolling once the typed content genuinely exceeds the cap. Without this a
    // long placeholder — which wraps but doesn't grow the box — left a scrollbar sitting in
    // an empty field.
    el.style.overflowY = el.scrollHeight > MAX_INPUT_HEIGHT ? 'auto' : 'hidden'
}

/* ── Emoji picker ───────────────────────────────────────────
 |
 | A small curated set — enough for chat sentiment without pulling in an emoji library
 | onto every page the widget loads on. Inserts at the caret and keeps focus.
 */
const EMOJIS = [
    '😀', '😁', '😂', '🙂', '😉', '😊', '😍', '😎', '🤔', '😴',
    '👍', '👎', '🙏', '👏', '🙌', '💪', '🔥', '✨', '🎉', '❤️',
    '✅', '❌', '⚠️', '❓', '❗', '💡', '📎', '📌', '🚀', '⭐',
]
const emojiOpen = ref(false)

function toggleEmoji() {
    emojiOpen.value = !emojiOpen.value
}

function insertEmoji(emoji: string) {
    const el = textarea.value
    if (!el) {
        input.value += emoji
    } else {
        const start = el.selectionStart ?? input.value.length
        const end = el.selectionEnd ?? input.value.length
        input.value = input.value.slice(0, start) + emoji + input.value.slice(end)
        nextTick(() => {
            el.focus()
            const caret = start + emoji.length
            el.setSelectionRange(caret, caret)
            autoResize()
        })
    }
    emojiOpen.value = false
}
</script>

<template>
    <div class="ai-input-wrapper relative border-t border-gray-200 dark:border-surface-800 shrink-0 flex flex-col bg-white px-3 py-2.5 dark:bg-surface-900">
        <!-- Slash command menu -->
        <div
            v-if="menuOpen"
            id="ai-command-menu"
            role="listbox"
            class="absolute bottom-full left-3 right-3 mb-1 max-h-56 overflow-y-auto rounded-xl border border-gray-200 bg-white py-1 shadow-lg dark:border-surface-700 dark:bg-surface-800"
        >
            <button
                v-for="(command, idx) in filteredCommands"
                :id="`ai-command-${command.name}`"
                :key="command.name"
                type="button"
                role="option"
                :aria-selected="idx === activeIndex"
                class="flex w-full items-start gap-2 px-3 py-1.5 text-left transition-colors"
                :class="idx === activeIndex ? 'bg-gray-100 dark:bg-surface-700' : 'hover:bg-gray-50 dark:hover:bg-surface-700/60'"
                @mouseenter="activeIndex = idx"
                @click="applyCommand(command)"
            >
                <span class="shrink-0 font-mono text-xs text-[var(--ai-accent,#1F75FE)]">{{ command.usage }}</span>
                <span class="truncate text-xs text-gray-500 dark:text-gray-400">{{ command.description }}</span>
            </button>
        </div>

        <!-- Upload error -->
        <div v-if="uploadError" class="mb-2 flex items-center gap-1.5 text-xs text-red-600 dark:text-red-400">
            <i class="ti ti-alert-circle text-sm shrink-0"></i>
            <span class="min-w-0 flex-1">{{ uploadError }}</span>
            <button type="button" class="shrink-0 text-gray-400 hover:text-gray-600" @click="uploadError = null">
                <i class="ti ti-x text-[10px]"></i>
            </button>
        </div>

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

        <!-- One row: the message, then emoji / attach / send on the right. The send button
             only appears once there's something to send (or a stream to stop), so the bar
             stays clean and the right side never shows a dead grey chip. -->
        <div class="flex items-end gap-1 rounded-2xl border border-gray-200 bg-gray-100 py-1.5 pl-3 pr-1.5 transition-all focus-within:border-transparent focus-within:ring-2 focus-within:ring-[var(--ai-accent,#1F75FE)] dark:border-surface-700 dark:bg-surface-800">
            <textarea
                ref="textarea"
                v-model="input"
                :placeholder="commands.length ? t('Type a message or / for commands...') : t('Type a message...')"
                :disabled="disabled || uploading"
                rows="1"
                role="combobox"
                aria-autocomplete="list"
                aria-controls="ai-command-menu"
                :aria-expanded="menuOpen"
                :aria-activedescendant="menuOpen ? `ai-command-${filteredCommands[activeIndex]?.name}` : undefined"
                class="chat-textarea min-w-0 flex-1 resize-none self-center border-0 bg-transparent py-1.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-0 dark:text-gray-100 dark:placeholder-gray-500"
                @keydown="onKeydown"
                @input="autoResize"
            />

            <!-- Emoji picker -->
            <div v-if="allowEmoji" class="relative shrink-0">
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-600 disabled:opacity-40 dark:hover:bg-surface-700 dark:hover:text-gray-300"
                    :disabled="disabled || uploading"
                    :title="t('Emoji')"
                    :aria-label="t('Emoji')"
                    @click="toggleEmoji"
                >
                    <i class="ti ti-mood-smile text-[18px]"></i>
                </button>

                <div
                    v-if="emojiOpen"
                    class="absolute bottom-full right-0 z-10 mb-2 grid w-56 grid-cols-8 gap-0.5 rounded-xl border border-gray-200 bg-white p-2 shadow-lg dark:border-surface-700 dark:bg-surface-800"
                >
                    <button
                        v-for="emoji in EMOJIS"
                        :key="emoji"
                        type="button"
                        class="flex h-6 w-6 items-center justify-center rounded text-base hover:bg-gray-100 dark:hover:bg-surface-700"
                        @click="insertEmoji(emoji)"
                    >
                        {{ emoji }}
                    </button>
                </div>
            </div>

            <!-- Attach -->
            <button
                v-if="allowUpload"
                type="button"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-600 disabled:opacity-40 dark:hover:bg-surface-700 dark:hover:text-gray-300"
                :disabled="disabled || uploading"
                :title="t('Attach a file')"
                :aria-label="t('Attach a file')"
                @click="triggerFileInput"
            >
                <i v-if="!uploading" class="ti ti-paperclip text-[18px]"></i>
                <i v-else class="ti ti-loader text-[18px] animate-spin"></i>
            </button>

            <input
                ref="fileInput"
                type="file"
                class="hidden"
                :accept="acceptFilter"
                @change="onFileSelected"
            />

            <!-- Stop generating -->
            <button
                v-if="isStreaming"
                type="button"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-white transition-colors"
                style="background: var(--ai-accent, #1F75FE);"
                :title="t('Stop generating')"
                :aria-label="t('Stop generating')"
                @click="emit('stop')"
            >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                    <rect x="6" y="6" width="12" height="12" rx="2" />
                </svg>
            </button>

            <!-- Send — only rendered once there's actually something to send. -->
            <transition name="ai-pop">
                <button
                    v-if="!isStreaming && canSend"
                    type="button"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-white transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                    style="background: var(--ai-accent, #1F75FE);"
                    :disabled="disabled || uploading"
                    :title="t('Send')"
                    :aria-label="t('Send')"
                    @click="onSubmit"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                    </svg>
                </button>
            </transition>
        </div>
    </div>
</template>

<style scoped>
/* No scrollbar by default — autoResize() turns it on only when the typed content actually
   outgrows the box. This also covers the first paint, before any JS has run. The height cap
   lives here (not a max-h class) so it stays in lockstep with MAX_INPUT_HEIGHT. */
.chat-textarea {
    max-height: 140px;
    overflow-y: hidden;
    scrollbar-width: thin;
}

/* The send button appears as soon as there's something to send. */
.ai-pop-enter-active,
.ai-pop-leave-active {
    transition: transform 0.12s ease-out, opacity 0.12s ease-out;
}
.ai-pop-enter-from,
.ai-pop-leave-to {
    opacity: 0;
    transform: scale(0.8);
}

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
