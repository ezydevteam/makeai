/**
 * Shared client types for the AI Assistant widget.
 *
 * These mirror the `aiAssistant` Inertia prop shared by
 * Addons\AiAssistant\AddonServiceProvider. The prop is NULL whenever the
 * assistant must not be offered to the current visitor (addon disabled, or the
 * visitor fails the admin's show_to rule) — so every consumer must treat it as
 * nullable and render nothing when it is absent.
 */

export type AssistantScope = 'user' | 'admin'

export type PanelId = 'home' | 'chat' | 'help' | 'message'

export interface AssistantEndpoints {
    chat: string
    extract: string
    feedback: string
    transcript: string
    conversations: string
    help_articles: string
    help_search: string
    /** Base path — append `/{slug}` for one article. */
    help_article: string
    message: string
    csat: string
}

/** Which tabs the admin has enabled for this surface. */
export interface AssistantPanels {
    home: boolean
    chat: boolean
    help: boolean
    message: boolean
}

/** Non-empty social links only; any subset of these keys may be present. */
export interface AssistantChannels {
    whatsapp?: string
    telegram?: string
    facebook?: string
    instagram?: string
    x?: string
    website?: string
}

/**
 * Privacy / terms links for the note under the chat input. The whole object is null when
 * the note must not render at all (disabled, admin surface, or neither link configured);
 * an individual field is null when only one of the two is set.
 */
export interface AssistantLegal {
    terms_url: string | null
    privacy_url: string | null
}

/** A knowledge-base article as listed/searched in the Help panel. */
export interface HelpArticle {
    title: string
    slug: string
    excerpt?: string
}

/** A single article opened in the Help reader. */
export interface HelpArticleDetail {
    title: string
    slug: string
    body: string
    related: HelpArticle[]
}

/** A past conversation in the chat history list. */
export interface ConversationSummary {
    session_id: string
    title: string
    message_count: number
    last_message_at: string | null
}

/** A slash command offered to this surface. Already scope-filtered server-side. */
export interface SlashCommand {
    name: string
    usage: string
    description: string
    /** Handled entirely in the browser — never POSTed to the server (e.g. /clear). */
    client_only: boolean
}

/** A knowledge-base citation attached to an assistant answer. */
export interface AssistantSource {
    title: string
    slug: string
    /**
     * Resolved link to the cited source, or absent when it cannot be linked — a MakeAI
     * documentation citation on an install with no public docs site, or a citation persisted
     * before the server began sending urls. The widget renders those as plain labels.
     */
    url?: string
    /** Knowledge Base articles only; MakeAI documentation pages have no ulid. */
    ulid?: string
}

export interface AssistantSettings {
    scope: AssistantScope
    endpoints: AssistantEndpoints
    panels: AssistantPanels
    channels: AssistantChannels
    commands: SlashCommand[]
    is_guest: boolean
    position: string
    accent_color: string | null
    allow_file_upload: boolean
    allowed_file_types: string
    auto_open: boolean
    greeting_on_first_visit: boolean
    assistant_name: string | null
    avatar_url: string | null
    designation: string | null
    greeting_message: string | null
    /** First name of a signed-in visitor, for a personalised greeting; null for guests. */
    greeting_name: string | null
    enable_emoji: boolean
    enable_csat: boolean
    legal: AssistantLegal | null
}

export interface AssistantMessageItem {
    role: 'user' | 'assistant'
    content: string
    messageHash?: string
    sources?: AssistantSource[]
    /** Epoch ms; used for the relative timestamp. Absent on legacy cached items. */
    createdAt?: number
}

export interface AssistantAttachment {
    name: string
    text: string
}

/**
 * SSE frames emitted by the assistant chat endpoint. The old protocol used
 * plaintext "READY" / "ERROR:" sentinels that were indistinguishable from model
 * output; everything is now a typed JSON frame.
 */
export type AssistantFrame =
    | { type: 'ready' }
    | { type: 'token'; content: string }
    | { type: 'sources'; sources: AssistantSource[] }
    | { type: 'error'; code: string; message: string }
    | { type: 'done' }
