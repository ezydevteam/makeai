import { marked } from 'marked'
import DOMPurify from 'dompurify'

/**
 * Markdown rendering for the assistant widget.
 *
 * This replaces the four hand-rolled regexes (bold / italic / inline code /
 * newline) that used to live — duplicated — in AssistantMessage.vue and
 * AssistantMessages.vue. They mangled any real answer: no fenced code blocks,
 * no lists, no links, no headings. `marked` is already a dependency of this
 * repo (see package.json), so it is used here rather than growing the regex pile.
 *
 * Everything still goes through DOMPurify before it reaches v-html — model output
 * can echo back HTML from a user's document or an uploaded file.
 */

const CODE_BLOCK_RE = /<pre><code(?:\s+class="language-([^"]*)")?>([\s\S]*?)<\/code><\/pre>/g

/** Entities marked emits inside a code block, decoded back to the raw source. */
function decodeEntities(html: string): string {
    return html
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&#39;/g, "'")
        .replace(/&amp;/g, '&')
}

/** UTF-8 safe base64 (btoa alone throws on non-latin1; `unescape` is deprecated). */
function encodeBase64(value: string): string {
    const bytes = new TextEncoder().encode(value)
    let binary = ''
    for (const byte of bytes) {
        binary += String.fromCharCode(byte)
    }
    return btoa(binary)
}

function decodeBase64(value: string): string {
    const binary = atob(value)
    const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0))
    return new TextDecoder().decode(bytes)
}

function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
}

/**
 * Fenced code blocks get a header strip with the language and a copy button. The
 * button carries the raw source in `data-code`; the click is handled by delegation
 * (see `handleMarkdownCopy`) so no listener has to be attached per render.
 */
function withCodeBlocks(html: string): string {
    return html.replace(CODE_BLOCK_RE, (_match, lang: string | undefined, code: string) => {
        const raw = decodeEntities(code)
        const label = escapeHtml((lang || 'code').toLowerCase())

        return (
            '<div class="ai-code-block">' +
            `<div class="ai-code-head"><span class="ai-code-lang">${label}</span>` +
            `<button type="button" class="ai-code-copy" data-code="${encodeBase64(raw)}">Copy</button></div>` +
            `<pre><code>${code}</code></pre>` +
            '</div>'
        )
    })
}

/** Links open in a new tab and never leak the opener. Applied after sanitising. */
function hardenLinks(html: string): string {
    if (typeof document === 'undefined') return html

    const holder = document.createElement('div')
    holder.innerHTML = html
    holder.querySelectorAll('a[href]').forEach((anchor) => {
        anchor.setAttribute('target', '_blank')
        anchor.setAttribute('rel', 'noopener noreferrer nofollow')
    })

    return holder.innerHTML
}

/**
 * Render assistant/user markdown to sanitised HTML.
 * NEVER pass raw `marked.parse()` output to v-html — always come through here.
 */
export function renderMarkdown(content: string): string {
    if (!content) return ''

    const parsed = marked.parse(content, { async: false, breaks: true, gfm: true }) as string

    const clean = DOMPurify.sanitize(withCodeBlocks(parsed), {
        USE_PROFILES: { html: true },
        // <button> is allowed only so the code-block copy control survives; DOMPurify
        // still strips scripts, event handlers and javascript: URLs.
        FORBID_TAGS: ['style', 'form', 'input', 'textarea', 'select'],
        FORBID_ATTR: ['style'],
        ADD_ATTR: ['data-code', 'target', 'rel'],
    })

    return hardenLinks(clean)
}

/**
 * Delegated click handler for the code-block copy button. Bind it with
 * `@click="handleMarkdownCopy"` on the element that carries the rendered v-html.
 */
export function handleMarkdownCopy(event: Event): void {
    const target = event.target as HTMLElement | null
    const button = target?.closest<HTMLElement>('.ai-code-copy')
    if (!button) return

    const encoded = button.getAttribute('data-code')
    if (!encoded) return

    let code: string
    try {
        code = decodeBase64(encoded)
    } catch {
        return
    }

    void copyText(code).then((ok) => {
        if (!ok) return
        const previous = button.textContent
        button.textContent = 'Copied'
        window.setTimeout(() => {
            button.textContent = previous ?? 'Copy'
        }, 1500)
    })
}

/** Clipboard write with a document.execCommand fallback for non-secure contexts. */
export async function copyText(text: string): Promise<boolean> {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text)
            return true
        }
    } catch {
        // fall through to the legacy path
    }

    try {
        const holder = document.createElement('textarea')
        holder.value = text
        holder.setAttribute('readonly', '')
        holder.style.position = 'fixed'
        holder.style.left = '-9999px'
        document.body.appendChild(holder)
        holder.select()
        const ok = document.execCommand('copy')
        document.body.removeChild(holder)
        return ok
    } catch {
        return false
    }
}
