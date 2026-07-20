import { marked } from 'marked'
import DOMPurify from 'dompurify'

/**
 * Render markdown to HTML safely. All AI/user-derived markdown MUST go
 * through this (never raw marked.parse into v-html) — model output can
 * echo back HTML from user documents, which would otherwise execute.
 */
export function renderMarkdown(content: string): string {
    const html = marked.parse(content || '', { async: false }) as string

    return DOMPurify.sanitize(html, {
        USE_PROFILES: { html: true },
        FORBID_TAGS: ['style', 'form', 'input', 'button'],
        FORBID_ATTR: ['style'],
        ADD_ATTR: ['target', 'rel'],
    })
}
