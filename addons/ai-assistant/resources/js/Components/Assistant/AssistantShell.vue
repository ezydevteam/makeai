<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AssistantHeader from './AssistantHeader.vue'
import AssistantHero from './AssistantHero.vue'
import AssistantNav from './AssistantNav.vue'
import AssistantHistory from './AssistantHistory.vue'
import ChatPanel from './panels/ChatPanel.vue'
import HelpPanel from './panels/HelpPanel.vue'
import MessagePanel from './panels/MessagePanel.vue'
import HomePanel from './panels/HomePanel.vue'
import { fetchConversations } from '../../Composables/useAssistantPanels'
import type { AssistantSettings, ConversationSummary, PanelId } from '../../types'

const props = withDefaults(defineProps<{
    settings: AssistantSettings
    sessionId: string
    /**
     * Header-button mode: the panel is a full-height rail pinned to the right edge rather
     * than a floating card, so it fills its container and squares off the edges that now
     * meet the viewport.
     */
    fullHeight?: boolean
}>(), {
    fullHeight: false,
})

defineEmits<{
    (e: 'close'): void
}>()

// The panel to land on: Home if enabled, otherwise Chat. Admin surface is chat-only.
const active = ref<PanelId>(props.settings.panels.home ? 'home' : 'chat')

// A deep-link target so Home → "read this article" can drop the user straight into the
// Help reader rather than the Help list.
const helpArticleSlug = ref<string | null>(null)

/*
 | The ACTIVE conversation.
 |
 | The widget used to be pinned to one session id for the life of the browser session, so
 | there was only ever a single thread. Now the shell owns it: picking a past conversation
 | swaps in its session id, and "New chat" mints a fresh one. The server keys a conversation
 | on (session_id, scope), so swapping the id is all it takes to switch threads — ChatPanel
 | is remounted via :key and reloads that thread's transcript.
 */
const activeSession = ref<string>(props.sessionId)

/*
 | Conversation list lives HERE, not in AssistantHistory, because the shell needs it to
 | decide what the chat tab opens on: the recent-chats list when the user has history, or
 | straight into a fresh chat when they don't. Fetching it in both places would mean two
 | requests and a race over which answer wins.
 */
const conversations = ref<ConversationSummary[]>([])
const canSave = ref(true)
const loadingConversations = ref(true)

// Whether the history list is currently covering the chat body.
const showHistory = ref(false)

/*
 | Has the user actually picked a conversation (or started a new one) yet?
 |
 | This is what decides whether the Chat tab shows the recent-chats list or drops straight
 | into the thread. Before they've chosen: with history → show the list; without → straight
 | into a new chat. After they've chosen: the Chat tab returns them to THAT conversation, so
 | tabbing away to Help and back doesn't yank them out of a thread they're mid-way through.
 |
 | It also decides the back arrow: there is only something to go "back to chat" to once a
 | conversation is open behind the list.
 */
const enteredChat = ref(false)

// Guests get a temporary thread with no history list, so the controls are pointless there.
const historyAvailable = computed(() => !props.settings.is_guest)

// Home gets the hero treatment: artwork to the top, transparent name-only header.
const isHome = computed(() => active.value === 'home' && !showHistory.value)

async function loadConversations() {
    if (!historyAvailable.value) {
        loadingConversations.value = false
        canSave.value = false
        return
    }

    loadingConversations.value = true
    const result = await fetchConversations(props.settings.endpoints.conversations)
    conversations.value = result.conversations
    canSave.value = result.can_save
    loadingConversations.value = false
}

onMounted(async () => {
    await loadConversations()

    // Land on the recent-chats list when the user HAS history and chat is the opening
    // panel; go straight into the chat when they don't. (Matches MagicAI: recent chats
    // first, direct chat when there's nothing to show.)
    if (active.value === 'chat' && conversations.value.length > 0) {
        showHistory.value = true
    }
})

function newSessionId(): string {
    return 'ai_' + Math.random().toString(36).slice(2) + Date.now().toString(36)
}

function startNewChat() {
    activeSession.value = newSessionId()
    showHistory.value = false
    enteredChat.value = true
    active.value = 'chat'
}

function openConversation(sessionId: string) {
    activeSession.value = sessionId
    showHistory.value = false
    enteredChat.value = true
    active.value = 'chat'
}

function onDeleted(sessionId: string) {
    conversations.value = conversations.value.filter(c => c.session_id !== sessionId)
}

/**
 * Opening the history view refreshes the list first, so a thread the user just finished
 * shows up (and its title/timestamp are current) rather than being a snapshot from mount.
 */
/** Open the full history list (chat header icon, or Home's "See all"). */
async function openHistory() {
    active.value = 'chat'
    showHistory.value = true
    await loadConversations()
}

// When only one panel is enabled the nav bar is pointless — the widget should look exactly
// like the original single-panel chat.
const singlePanel = computed(() => {
    const p = props.settings.panels
    return [p.home, p.chat, p.help].filter(Boolean).length <= 1
})

function go(panel: PanelId) {
    helpArticleSlug.value = null
    active.value = panel

    // The Chat tab shows the recent-chats list until the user has actually opened a
    // conversation; after that it returns them to the one they're in.
    showHistory.value = panel === 'chat'
        && !enteredChat.value
        && conversations.value.length > 0
}

function openArticle(slug: string) {
    helpArticleSlug.value = slug
    active.value = 'help'
}
</script>

<template>
    <!--
        FIXED height, not max-height. With `max-h` the window grew and shrank as content
        loaded (empty chat → tall article → short form), so the whole widget jumped around
        its anchor. A fixed frame means only the panel body scrolls; the chrome never moves.
        It still yields on short viewports so the widget can't overflow the screen.
    -->
    <div
        class="ai-window relative isolate flex w-[380px] max-w-[100vw] flex-col overflow-hidden border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900 sm:w-[420px]"
        :class="fullHeight
            ? 'ai-window--rail h-full rounded-none border-y-0 border-r-0'
            : 'h-[600px] max-h-[calc(100vh-7rem)] rounded-2xl'"
    >
        <!-- On Home the artwork runs to the very top, behind a transparent header. It's
             rendered here rather than inside HomePanel so it can sit under the header, and
             so it stays put while the panel body scrolls. -->
        <AssistantHero v-if="isHome" />

        <AssistantHeader
            :settings="settings"
            :minimal="isHome"
            :show-history="historyAvailable && active === 'chat' && !showHistory"
            @close="$emit('close')"
            @history="openHistory"
            @new-chat="startNewChat"
        />

        <!-- min-h-0 is what actually lets the body scroll instead of stretching the frame.
             relative z-10 keeps the panels above the Home hero artwork. -->
        <div class="relative z-10 flex min-h-0 flex-1 flex-col">
            <!-- Conversation history takes over the body while open. -->
            <AssistantHistory
                v-if="showHistory"
                :settings="settings"
                :current-session-id="activeSession"
                :conversations="conversations"
                :can-save="canSave"
                :loading="loadingConversations"
                :can-go-back="enteredChat"
                @open="openConversation"
                @new="startNewChat"
                @close="showHistory = false"
                @deleted="onDeleted"
            />

            <template v-else>
                <!-- Chat is kept alive with v-show so a stream isn't torn down when the user
                     peeks at Help. The :key means switching conversation remounts it, which
                     reloads that thread's transcript from the server. -->
                <ChatPanel
                    v-show="active === 'chat'"
                    :key="activeSession"
                    :settings="settings"
                    :session-id="activeSession"
                />

                <HomePanel
                    v-if="active === 'home'"
                    :settings="settings"
                    :conversations="conversations"
                    :loading-conversations="loadingConversations"
                    @go="go"
                    @open-article="openArticle"
                    @open-conversation="openConversation"
                    @show-history="openHistory"
                    @new-chat="startNewChat"
                />

                <HelpPanel
                    v-else-if="active === 'help'"
                    :key="helpArticleSlug ?? 'help-list'"
                    :settings="settings"
                    :initial-slug="helpArticleSlug"
                    @leave-message="go('message')"
                />

                <MessagePanel
                    v-else-if="active === 'message'"
                    :settings="settings"
                />
            </template>
        </div>

        <AssistantNav
            v-if="!singlePanel"
            :panels="settings.panels"
            :active="active"
            @change="go"
        />
    </div>
</template>

<style scoped>
.ai-window {
    animation: ai-slide-up 0.2s ease-out;
}

/* The rail comes in from the edge it's pinned to, not upward from a bubble. */
.ai-window--rail {
    animation: ai-slide-in 0.22s ease-out;
}

@keyframes ai-slide-in {
    from {
        opacity: 0;
        transform: translateX(16px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes ai-slide-up {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
