<script setup lang="ts">
import { computed, ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { Extension, Mark, Node, mergeAttributes } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import TiptapLink from '@tiptap/extension-link'
import { TextAlign } from '@tiptap/extension-text-align'
import { TextStyle } from '@tiptap/extension-text-style'
import { Color } from '@tiptap/extension-color'
import { Highlight } from '@tiptap/extension-highlight'
import { TaskList } from '@tiptap/extension-task-list'
import { TaskItem } from '@tiptap/extension-task-item'
import { Table } from '@tiptap/extension-table'
import { TableRow } from '@tiptap/extension-table-row'
import { TableHeader } from '@tiptap/extension-table-header'
import { TableCell } from '@tiptap/extension-table-cell'
import { Image } from '@tiptap/extension-image'
import { Placeholder } from '@tiptap/extension-placeholder'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'
import AppColorPicker from '@/Components/UI/AppColorPicker.vue'
import AppSelect, { type SelectOption } from '@/Components/UI/AppSelect.vue'
import { RICH_EDITOR_FONT_OPTIONS } from '@/config/fontFamilies'
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight'
import { common, createLowlight } from 'lowlight'
import { Document, Paragraph, TextRun, HeadingLevel, Packer } from 'docx'

const lowlight = createLowlight(common)

interface AiAssistAction {
    key: string
    label: string
    description?: string
}

type ImageAlignment = 'left' | 'center' | 'right' | 'float-left' | 'float-right'
type ExportFormat = 'html' | 'text' | 'markdown' | 'pdf' | 'docx'

interface SlashCommand {
    key: string
    label: string
    group: string
    action: () => void
}

interface TablePickerCell {
    row: number
    col: number
    active: boolean
}

const imageAlignmentStyle = (align: ImageAlignment | null | undefined) => {
    if (align === 'center') return 'display: block; margin-inline: auto;'
    if (align === 'right') return 'display: block; margin-inline-start: auto; margin-inline-end: 0;'
    if (align === 'float-left') return 'float: inline-start; margin-inline-end: 1rem; margin-block: 0.25rem 1rem;'
    if (align === 'float-right') return 'float: inline-end; margin-inline-start: 1rem; margin-block: 0.25rem 1rem;'
    return 'display: block; margin-inline-start: 0; margin-inline-end: auto;'
}

const RichImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            width: { default: null, parseHTML: (el) => el.getAttribute('width'), renderHTML: (a) => a.width ? { width: a.width } : {} },
            alt: { default: null, parseHTML: (el) => el.getAttribute('alt'), renderHTML: (a) => a.alt ? { alt: a.alt } : {} },
            title: { default: null, parseHTML: (el) => el.getAttribute('title'), renderHTML: (a) => a.title ? { title: a.title } : {} },
            'data-align': {
                default: 'left',
                parseHTML: (el) => el.getAttribute('data-align') || 'left',
                renderHTML: (a) => ({ 'data-align': a['data-align'] || 'left', style: imageAlignmentStyle(a['data-align']) }),
            },
        }
    },
})

const Subscript = Mark.create({
    name: 'subscript', excludes: 'superscript',
    parseHTML: () => [{ tag: 'sub' }],
    renderHTML: ({ HTMLAttributes }) => ['sub', mergeAttributes(HTMLAttributes), 0],
    addCommands() { return { toggleSubscript: () => ({ commands }: any) => commands.toggleMark(this.name) } as any },
})

const Superscript = Mark.create({
    name: 'superscript', excludes: 'subscript',
    parseHTML: () => [{ tag: 'sup' }],
    renderHTML: ({ HTMLAttributes }) => ['sup', mergeAttributes(HTMLAttributes), 0],
    addCommands() { return { toggleSuperscript: () => ({ commands }: any) => commands.toggleMark(this.name) } as any },
})

const FontTools = Extension.create({
    name: 'fontTools',
    addGlobalAttributes() {
        return [
            {
                types: ['textStyle'],
                attributes: {
                    fontFamily: { default: null, parseHTML: (el) => el.style.fontFamily?.replace(/['"]/g, ''), renderHTML: (a) => a.fontFamily ? { style: `font-family: ${a.fontFamily};` } : {} },
                    fontSize: { default: null, parseHTML: (el) => el.style.fontSize, renderHTML: (a) => a.fontSize ? { style: `font-size: ${a.fontSize};` } : {} },
                },
            },
            {
                types: ['paragraph', 'heading'],
                attributes: { lineHeight: { default: null, parseHTML: (el) => el.style.lineHeight, renderHTML: (a) => a.lineHeight ? { style: `line-height: ${a.lineHeight};` } : {} } },
            },
        ]
    },
    addCommands() {
        return {
            setFontFamily: (fontFamily: string) => ({ chain }: any) => chain().setMark('textStyle', { fontFamily }).run(),
            setFontSize: (fontSize: string) => ({ chain }: any) => chain().setMark('textStyle', { fontSize }).run(),
            setLineHeight: (lineHeight: string) => ({ chain }: any) => chain().setMark('textStyle', { lineHeight }).run(),
        } as any
    },
})

const Details = Node.create({
    name: 'details', group: 'block', content: 'block+', defining: true,
    addAttributes() {
        return { summary: { default: 'Details', parseHTML: (el) => el.querySelector('summary')?.textContent || 'Details', renderHTML: () => ({}) } }
    },
    parseHTML: () => [{ tag: 'details' }],
    renderHTML: ({ node, HTMLAttributes }) => ['details', mergeAttributes(HTMLAttributes, { open: '' }), ['summary', node.attrs.summary || 'Details'], ['div', 0]],
    addCommands() {
        return { insertDetails: (summary = 'Details') => ({ commands }: any) => commands.insertContent({ type: this.name, attrs: { summary }, content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Hidden content' }] }] }) } as any
    },
})

const VideoEmbed = Node.create({
    name: 'videoEmbed', group: 'block', atom: true,
    addAttributes() { return { src: { default: null }, title: { default: 'Embedded video' } } },
    parseHTML: () => [{ tag: 'iframe[data-video-embed]' }],
    renderHTML: ({ HTMLAttributes }) => ['iframe', mergeAttributes(HTMLAttributes, { 'data-video-embed': 'true', class: 'rich-video-embed', frameborder: '0', allowfullscreen: 'true', allow: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture' })],
    addCommands() { return { setVideoEmbed: (attrs: { src: string; title?: string }) => ({ commands }: any) => commands.insertContent({ type: this.name, attrs }) } as any },
})

const FileAttachment = Node.create({
    name: 'fileAttachment', group: 'block', atom: true,
    addAttributes() { return { href: { default: null }, name: { default: 'Download file' } } },
    parseHTML: () => [{ tag: 'a[data-file-attachment]' }],
    renderHTML: ({ HTMLAttributes }) => ['a', mergeAttributes(HTMLAttributes, { 'data-file-attachment': 'true', class: 'rich-file-attachment', download: '' }), HTMLAttributes.name || 'Download file'],
    addCommands() { return { setFileAttachment: (attrs: { href: string; name: string }) => ({ commands }: any) => commands.insertContent({ type: this.name, attrs }) } as any },
})

const TableCellBg = Extension.create({
    name: 'tableCellBg',
    addGlobalAttributes() {
        return [{
            types: ['tableCell', 'tableHeader'],
            attributes: { cellBg: { default: null, parseHTML: (el) => el.style.backgroundColor, renderHTML: (a) => a.cellBg ? { style: `background-color: ${a.cellBg};` } : {} } },
        }]
    },
    addCommands() {
        return {
            setCellBg: (color: string) => ({ chain }: any) => chain().updateAttributes('tableCell', { cellBg: color }).updateAttributes('tableHeader', { cellBg: color }).run(),
        } as any
    },
})

const props = withDefaults(defineProps<{
    modelValue: string
    variant?: 'full' | 'comment' | 'minimal'
    aiAssist?: boolean
    aiAssistActions?: AiAssistAction[]
    aiAssistLoadingKey?: string | null
    // Set by the parent when the last AI-assist run failed. The parent clears the
    // loading key on both success and failure, so without this flag the editor can't
    // tell them apart and would flash a success indicator on top of the parent's error.
    aiAssistError?: boolean
    aiAssistLabel?: string
    aiAssistLoadingLabel?: string
    imageUploadUrl?: string | null
    attachmentUploadUrl?: string | null
}>(), {
    variant: 'full',
    aiAssist: false,
    aiAssistActions: () => [],
    aiAssistLoadingKey: null,
    aiAssistError: false,
    aiAssistLabel: 'AI Assist',
    aiAssistLoadingLabel: 'Working...',
    imageUploadUrl: null,
    attachmentUploadUrl: null,
})

const emit = defineEmits<{
    'update:modelValue': [value: string]
    'ai-assist': [action: string]
}>()

const { t } = useTranslate()
const isSourceMode = ref(false)
const sourceContent = ref(props.modelValue)
const overflowOpen = ref(false)
const aiAssistOpen = ref(false)
const aiAssistSuccess = ref(false)
let wasAiLoading = false

watch(() => props.aiAssistLoadingKey, (newVal) => {
    if (newVal) {
        wasAiLoading = true
        aiAssistSuccess.value = false
    } else if (wasAiLoading) {
        wasAiLoading = false
        // Only flash the success check when the run actually succeeded. The parent owns
        // the success/error toasts; the editor just reflects the outcome on the button.
        if (props.aiAssistError) {
            return
        }
        aiAssistSuccess.value = true
        setTimeout(() => {
            aiAssistSuccess.value = false
        }, 2000)
    }
})
const formatOpen = ref(false)
const linkModalOpen = ref(false)
const linkEditing = ref(false)
const imageModalOpen = ref(false)
const emojiOpen = ref(false)
const tablePickerOpen = ref(false)
const tablePickerRows = ref(0)
const tablePickerCols = ref(0)
const imageInputRef = ref<HTMLInputElement | null>(null)
const attachmentInputRef = ref<HTMLInputElement | null>(null)
const linkUrl = ref('')
const linkTitle = ref('')
const linkTarget = ref<'_self' | '_blank'>('_self')
const linkColor = ref('#2563eb')
const imageUrl = ref('')
const uploadedImageData = ref('')
const imageWidth = ref('')
const imageAlignment = ref<ImageAlignment>('left')
const imageAlt = ref('')
const imageCaption = ref('')
const imageFileName = ref('')
const imageError = ref('')
const imageUploading = ref(false)
const imageEditing = ref(false)
const videoModalOpen = ref(false)
const videoUrl = ref('')
const videoError = ref('')
const attachmentModalOpen = ref(false)
const attachmentUrl = ref('')
const attachmentName = ref('')
const attachmentFileName = ref('')
const attachmentUploading = ref(false)
const attachmentError = ref('')
const exportOpen = ref(false)
const slashOpen = ref(false)
const slashQuery = ref('')
const slashIndex = ref(0)
const aiSidebarOpen = ref(false)
const selectedText = ref('')
const toolbarAiMaxHeight = ref(320)
const versionHistory = ref<Array<{ id: number; html: string; savedAt: Date; words: number }>>([])
const codeLanguageModalOpen = ref(false)
const codeLanguageQuery = ref('')
const activeCodeBlockPos = ref(0)
let autosaveTimer: number | undefined
const linkTooltip = ref({ visible: false, url: '', x: 0, y: 0 })
const exporting = ref(false)
const restoreVersionSelection = ref<string | null>(null)

const fontOptions: SelectOption[] = RICH_EDITOR_FONT_OPTIONS
const linkTargetOptions = computed<SelectOption[]>(() => [
    { value: '_self', label: t('Same tab') },
    { value: '_blank', label: t('New tab') },
])
const imageAlignmentOptions = computed<SelectOption[]>(() => [
    { value: 'left', label: t('Left') },
    { value: 'center', label: t('Center') },
    { value: 'right', label: t('Right') },
    { value: 'float-left', label: t('Float left') },
    { value: 'float-right', label: t('Float right') },
])
const versionHistoryOptions = computed<SelectOption[]>(() =>
    versionHistory.value.map((version) => ({
        value: version.html,
        label: `${version.savedAt.toLocaleTimeString()} - ${version.words} ${t('words')}`,
    })),
)

const codeLanguages = [
    'javascript', 'typescript', 'python', 'html', 'css', 'json', 'php', 'ruby', 'go', 'rust',
    'sql', 'bash', 'yaml', 'markdown', 'java', 'c', 'cpp', 'csharp', 'swift', 'kotlin',
    'dart', 'scala', 'r', 'perl', 'lua', 'graphql', 'xml', 'dockerfile', 'toml', 'diff',
]

const filteredLanguages = computed(() => {
    const q = codeLanguageQuery.value.toLowerCase()
    return codeLanguages.filter((l) => l.includes(q))
})

const emojiGroups: Array<{ label: string; emojis: string[] }> = [
    { label: 'Smileys', emojis: ['😀', '😃', '😄', '😁', '😅', '😂', '🤣', '😊', '😇', '🙂', '😉', '😌', '😍', '🥰', '😘', '😗', '😋', '😛', '😜', '🤪', '😝', '🤑', '🤗', '🤭', '🤫', '🤔', '🤐', '🤨', '😐', '😑', '😶', '😏', '😒', '🙄', '😬', '😮', '😯', '😲', '😳', '🥺'] },
    { label: 'Gestures', emojis: ['👍', '👎', '👏', '🙌', '🤝', '💪', '✌️', '🤞', '🤟', '🤘', '👌', '🤏', '✋', '👋', '🖐', '👆', '👇', '👉', '👈', '🖖', '🤙', '💅', '🙏', '💃', '🕺'] },
    { label: 'Objects', emojis: ['💻', '🖥', '⌨️', '🖱', '📱', '💡', '🔥', '⭐', '❤️', '💚', '💙', '💜', '🧡', '💛', '🤍', '🖤', '💔', '💯', '✅', '❌', '⚠️', '🚀', '💎', '🎯', '📌', '📎', '✂️', '📝', '📊', '📈', '🏆', '🎉', '🎊', '🔔', '🔕'] },
    { label: 'Nature', emojis: ['🌈', '☀️', '🌙', '⭐', '🌍', '🌸', '🌺', '🌻', '🌹', '🍀', '🌲', '🌊', '🔥', '💧', '❄️', '⛄'] },
    { label: 'Symbols', emojis: ['➡️', '⬅️', '⬆️', '⬇️', '↗️', '↘️', '↙️', '↖️', '↔️', '🔄', '🔃', '🔁', '🔂', '®️', '©️', '™️', '♻️', '🔱', '〽️', '🔰', '➕', '➖', '➗', '✖️'] },
]

function cleanPastedHTML(html: string): string {
    const parser = new DOMParser()
    const doc = parser.parseFromString(html, 'text/html')
    const allElements = doc.body.getElementsByTagName('*')
    for (let i = 0; i < allElements.length; i++) {
        const el = allElements[i]
        const attrs = Array.from(el.attributes)
        for (const attr of attrs) {
            if (!['href', 'src', 'alt', 'title', 'target', 'rel'].includes(attr.name)) {
                el.removeAttribute(attr.name)
            }
        }
    }
    return doc.body.innerHTML
}

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            link: false,
            codeBlock: false,
            heading: { levels: [1, 2, 3, 4, 5, 6] },
        }),
        CodeBlockLowlight.configure({ lowlight }),
        TiptapLink.configure({
            openOnClick: false, autolink: true, linkOnPaste: true,
            HTMLAttributes: { class: 'text-primary-600 underline cursor-pointer' },
        }),
        TextAlign.configure({ types: ['heading', 'paragraph'], alignments: ['left', 'center', 'right', 'justify'] }),
        TextStyle,
        FontTools,
        Color,
        Highlight.configure({ multicolor: true }),
        Subscript,
        Superscript,
        TaskList,
        TaskItem.configure({ nested: true }),
        Table.configure({ resizable: true }),
        TableRow,
        TableHeader,
        TableCell,
        TableCellBg,
        RichImage.configure({ inline: true, allowBase64: true }),
        VideoEmbed,
        FileAttachment,
        Details,
        Placeholder.configure({
            placeholder: ({ node }) => {
                if (node.type.name === 'heading') return t('Heading...')
                return t('Type / for commands...')
            },
        }),
    ],
    editorProps: {
        transformPastedHTML(html) {
            if (props.variant === 'comment' || props.variant === 'minimal') {
                return cleanPastedHTML(html)
            }
            return html
        },
        handleKeyDown: (_view, event) => {
            if (slashOpen.value) {
                if (event.key === 'ArrowDown') { event.preventDefault(); slashIndex.value = Math.min(slashIndex.value + 1, filteredSlashCommands.value.length - 1); return true }
                if (event.key === 'ArrowUp') { event.preventDefault(); slashIndex.value = Math.max(slashIndex.value - 1, 0); return true }
                if (event.key === 'Enter') { event.preventDefault(); runSlashCommand(filteredSlashCommands.value[slashIndex.value]); return true }
                if (event.key === 'Escape') { event.preventDefault(); closeSlash(); return true }
            }
            if (event.key === '/' && props.variant === 'full') { slashOpen.value = true; slashQuery.value = ''; slashIndex.value = 0; return false }
            return false
        },
        handleClick: (view, pos, event) => {
            const target = event.target as HTMLElement | null
            const link = target?.closest('a') as HTMLAnchorElement | null
            const image = target?.closest('img') as HTMLImageElement | null
            if (link) { event.preventDefault(); editor.value?.commands.setTextSelection(pos); openLinkModal(link); return true }
            if (image) { const position = view.posAtDOM(image, 0); editor.value?.commands.setNodeSelection(position); openImageModal(true); return true }
            return false
        },
        handleDrop: (_view, event) => {
            if (props.variant !== 'full') return false
            const file = event.dataTransfer?.files?.[0]
            if (!file?.type.startsWith('image/')) return false
            event.preventDefault(); insertImageFile(file); return true
        },
        handlePaste: (_view, event) => {
            if (props.variant !== 'full') return false
            const file = Array.from(event.clipboardData?.files || []).find((item) => item.type.startsWith('image/'))
            if (!file) return false
            event.preventDefault(); insertImageFile(file); return true
        },
        handleDOMEvents: {
            mouseover: (_view, event) => {
                const target = event.target as HTMLElement | null
                const link = target?.closest('a') as HTMLAnchorElement | null
                if (!link?.href) return false
                linkTooltip.value = { visible: true, url: link.getAttribute('href') || link.href, x: event.clientX, y: event.clientY }
                return false
            },
            mousemove: (_view, event) => {
                if (!linkTooltip.value.visible) return false
                linkTooltip.value.x = event.clientX; linkTooltip.value.y = event.clientY
                return false
            },
            mouseout: (_view, event) => {
                if ((event.target as HTMLElement)?.closest('a')) linkTooltip.value.visible = false
                return false
            },
        },
    },
    onUpdate: ({ editor }) => {
        const html = editor.getHTML()
        emit('update:modelValue', html)
        sourceContent.value = html
        if (slashOpen.value) updateSlashQuery()
    },
    onSelectionUpdate: ({ editor }) => {
        const { from, to } = editor.state.selection
        selectedText.value = editor.state.doc.textBetween(from, to, '\n').trim()
    },
})

watch(() => props.modelValue, (value) => {
    if (isSourceMode.value) return
    if (editor.value?.getHTML() === value) return
    editor.value?.commands.setContent(value, { emitUpdate: false })
})

// ---- Mode toggle ----
const toggleMode = () => {
    if (isSourceMode.value) { editor.value?.commands.setContent(sourceContent.value, { emitUpdate: false }) }
    else { sourceContent.value = editor.value?.getHTML() || '' }
    isSourceMode.value = !isSourceMode.value; formatOpen.value = false; overflowOpen.value = false; aiAssistOpen.value = false
}
const handleSourceInput = (e: Event) => { sourceContent.value = (e.target as HTMLTextAreaElement).value; emit('update:modelValue', (e.target as HTMLTextAreaElement).value) }

// ---- Stats ----
const textStats = computed(() => {
    const text = editor.value?.state.doc.textBetween(0, editor.value.state.doc.content.size, ' ') || ''
    const words = text.trim() ? text.trim().split(/\s+/).length : 0
    return { words, characters: text.length, readingMinutes: Math.max(1, Math.ceil(words / 225)) }
})

// ---- Version history ----
const saveVersionSnapshot = () => {
    const html = editor.value?.getHTML() || sourceContent.value || ''
    if (!html.trim()) return
    if (versionHistory.value[0]?.html === html) return
    versionHistory.value = [{ id: Date.now(), html, savedAt: new Date(), words: textStats.value.words }, ...versionHistory.value].slice(0, 20)
}
const restoreVersion = (html: string) => { editor.value?.commands.setContent(html); sourceContent.value = html }
const handleRestoreVersion = (value: string | number | null | (string | number)[]) => {
    if (typeof value !== 'string' || !value) return
    restoreVersion(value)
    nextTick(() => {
        restoreVersionSelection.value = null
    })
}

// ---- Expose ----
const getSelectedText = () => editor.value ? editor.value.state.doc.textBetween(editor.value.state.selection.from, editor.value.state.selection.to, '\n').trim() : ''
const replaceSelection = (html: string) => editor.value?.chain().focus().insertContent(html).run()
const insertAtCursor = (html: string) => editor.value?.chain().focus().insertContent(html).run()
defineExpose({ getSelectedText, replaceSelection, insertAtCursor })

// ---- Actions ----
const extractColor = (style: string | undefined) => style?.match(/color:\s*([^;]+)/i)?.[1]?.trim() || '#2563eb'

const openLinkModal = (link?: HTMLAnchorElement) => {
    const attrs = editor.value?.getAttributes('link') || {}
    const href = link?.getAttribute('href') || attrs.href || ''
    const title = link?.getAttribute('title') || attrs.title || ''
    const target = link?.getAttribute('target') || attrs.target
    const style = link?.getAttribute('style') || attrs.style
    const selectionText = getSelectedText()

    linkEditing.value = Boolean(href)
    linkUrl.value = href
    linkTitle.value = title || (!href ? selectionText : '')
    linkTarget.value = target === '_blank' ? '_blank' : '_self'
    linkColor.value = extractColor(style)
    linkModalOpen.value = true; overflowOpen.value = false; aiAssistOpen.value = false; linkTooltip.value.visible = false
}
const closeLinkModal = () => { linkModalOpen.value = false; linkEditing.value = false }
const applyLink = () => {
    const url = linkUrl.value.trim()
    if (!url) { editor.value?.chain().focus().extendMarkRange('link').unsetLink().run(); closeLinkModal(); return }
    editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url, title: linkTitle.value.trim() || undefined, target: linkTarget.value === '_blank' ? '_blank' : undefined, rel: linkTarget.value === '_blank' ? 'noopener noreferrer' : undefined, style: `color: ${linkColor.value};` } as any).run()
    closeLinkModal()
}
const removeLink = () => { editor.value?.chain().focus().extendMarkRange('link').unsetLink().run(); closeLinkModal() }

const openImageModal = (editing = false) => {
    const attrs = editor.value?.getAttributes('image') || {}
    imageEditing.value = editing; imageUrl.value = editing ? (attrs.src || '') : ''; uploadedImageData.value = ''
    imageWidth.value = editing ? String(attrs.width || '') : ''
    imageAlignment.value = ['left', 'center', 'right', 'float-left', 'float-right'].includes(attrs['data-align']) ? attrs['data-align'] : 'left'
    imageAlt.value = editing ? String(attrs.alt || '') : ''; imageCaption.value = ''; imageFileName.value = ''; imageError.value = ''
    imageModalOpen.value = true; overflowOpen.value = false; aiAssistOpen.value = false
}
const closeImageModal = () => { imageModalOpen.value = false }
const imageAttributes = (src: string) => ({ src, width: imageWidth.value.trim() ? imageWidth.value.trim().replace(/[^0-9.%pxrem]/gi, '') : undefined, 'data-align': imageAlignment.value, alt: imageAlt.value.trim() || undefined, title: imageCaption.value.trim() || undefined })
const insertImage = (src: string) => {
    if (imageEditing.value && editor.value?.isActive('image')) { editor.value?.chain().focus().updateAttributes('image', imageAttributes(src)).run() }
    else { editor.value?.chain().focus().setImage(imageAttributes(src) as any).run() }
    closeImageModal()
}
const removeImage = () => { if (editor.value?.isActive('image')) { editor.value.chain().focus().deleteSelection().run() }; closeImageModal() }
const applyImageUrl = () => {
    const url = uploadedImageData.value || imageUrl.value.trim()
    if (!url) { imageError.value = t('Add an image URL or upload an image.'); return }
    insertImage(url)
}

const csrfToken = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''
const uploadFile = async (file: File, uploadUrl: string | null | undefined) => {
    if (!uploadUrl) {
        return await new Promise<string>((resolve, reject) => {
            const reader = new FileReader()
            reader.onload = () => resolve(String(reader.result || ''))
            reader.onerror = () => reject(new Error(t('Upload failed.')))
            reader.readAsDataURL(file)
        })
    }
    const body = new FormData(); body.append('file', file)
    const response = await fetch(uploadUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() }, body })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok || !payload.url) throw new Error(payload.message || t('Upload failed.'))
    return String(payload.url)
}
const insertImageFile = async (file: File) => {
    if (!file.type.startsWith('image/')) { imageError.value = t('Choose a valid image file.'); return }
    imageUploading.value = true; imageError.value = ''
    try { const src = await uploadFile(file, props.imageUploadUrl); insertImage(src) }
    catch (error) { imageError.value = error instanceof Error ? error.message : t('Image upload failed.') }
    finally { imageUploading.value = false }
}
const handleImageUpload = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0]
    if (!file) return
    if (!file.type.startsWith('image/')) { imageError.value = t('Choose a valid image file.'); return }
    imageFileName.value = file.name; uploadedImageData.value = ''; imageError.value = ''; imageUploading.value = true
    uploadFile(file, props.imageUploadUrl).then((url) => { uploadedImageData.value = url }).catch((error) => { imageError.value = error instanceof Error ? error.message : t('Image upload failed.') }).finally(() => { imageUploading.value = false })
}
const openImageFilePicker = () => {
    imageInputRef.value?.click()
}

// ---- Formatting ----
const activeFormatLabel = computed(() => {
    if (editor.value?.isActive('heading', { level: 1 })) return 'H1'
    if (editor.value?.isActive('heading', { level: 2 })) return 'H2'
    if (editor.value?.isActive('heading', { level: 3 })) return 'H3'
    if (editor.value?.isActive('heading', { level: 4 })) return 'H4'
    if (editor.value?.isActive('heading', { level: 5 })) return 'H5'
    if (editor.value?.isActive('heading', { level: 6 })) return 'H6'
    if (editor.value?.isActive('code')) return 'Code'
    return 'P'
})
const isHeadingActive = computed(() => [1, 2, 3, 4, 5, 6].some((level) => editor.value?.isActive('heading', { level })))
const setParagraph = () => { editor.value?.chain().focus().setParagraph().run(); formatOpen.value = false }
const setHeading = (level: 1 | 2 | 3 | 4 | 5 | 6) => { editor.value?.chain().focus().toggleHeading({ level }).run(); formatOpen.value = false }
const toggleCodeText = () => { if (isHeadingActive.value) return; editor.value?.chain().focus().toggleCode().run(); formatOpen.value = false }
const toggleSubscript = () => { (editor.value?.chain().focus() as any).toggleSubscript().run() }
const toggleSuperscript = () => { (editor.value?.chain().focus() as any).toggleSuperscript().run() }
const insertDetails = () => { (editor.value?.chain().focus() as any).insertDetails(t('Details')).run() }
const setFontFamily = (fontFamily: string) => { (editor.value?.chain().focus() as any).setFontFamily(fontFamily).run() }
const setFontSize = (fontSize: string) => { (editor.value?.chain().focus() as any).setFontSize(fontSize).run() }
const setLineHeight = (lineHeight: string) => { (editor.value?.chain().focus() as any).setLineHeight(lineHeight).run() }
const clearFormatting = () => { editor.value?.chain().focus().unsetAllMarks().clearNodes().run() }

// ---- Insertions ----
const normalizeVideoUrl = (url: string) => {
    const youtube = url.trim().match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]+)/)
    const vimeo = url.trim().match(/vimeo\.com\/(\d+)/)
    if (youtube?.[1]) return `https://www.youtube.com/embed/${youtube[1]}`
    if (vimeo?.[1]) return `https://player.vimeo.com/video/${vimeo[1]}`
    return url.trim()
}
const insertVideo = () => {
    const src = normalizeVideoUrl(videoUrl.value)
    if (!/^https?:\/\/.+/i.test(src)) { videoError.value = t('Add a valid YouTube, Vimeo, or iframe URL.'); return }
    (editor.value?.chain().focus() as any).setVideoEmbed({ src, title: t('Embedded video') }).run()
    videoUrl.value = ''; videoError.value = ''; videoModalOpen.value = false
}
const handleAttachmentUpload = async (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0]
    if (!file) return
    attachmentUploading.value = true; attachmentError.value = ''; attachmentFileName.value = file.name; attachmentName.value = attachmentName.value || file.name
    try { attachmentUrl.value = await uploadFile(file, props.attachmentUploadUrl) }
    catch (error) { attachmentError.value = error instanceof Error ? error.message : t('File upload failed.') }
    finally { attachmentUploading.value = false }
}
const openAttachmentFilePicker = () => {
    attachmentInputRef.value?.click()
}
const insertAttachment = () => {
    const href = attachmentUrl.value.trim(); const name = attachmentName.value.trim() || t('Download file')
    if (!href) { attachmentError.value = t('Upload a file or paste a file URL.'); return }
    (editor.value?.chain().focus() as any).setFileAttachment({ href, name }).run()
    attachmentModalOpen.value = false; attachmentUrl.value = ''; attachmentName.value = ''; attachmentFileName.value = ''; attachmentError.value = ''
}

// ---- Table picker ----
const tablePickerGrid = computed<TablePickerCell[]>(() => {
    const cells: TablePickerCell[] = []
    for (let r = 1; r <= 8; r++) {
        for (let c = 1; c <= 8; c++) {
            cells.push({ row: r, col: c, active: r <= tablePickerRows.value && c <= tablePickerCols.value })
        }
    }
    return cells
})
const openTablePicker = () => { tablePickerRows.value = 3; tablePickerCols.value = 3; tablePickerOpen.value = true; overflowOpen.value = false }
const hoverTableCell = (r: number, c: number) => { tablePickerRows.value = r; tablePickerCols.value = c }
const insertTable = (r: number, c: number) => { editor.value?.chain().focus().insertTable({ rows: r, cols: c, withHeaderRow: true }).run(); tablePickerOpen.value = false }

// ---- Code language ----
const setCodeLanguage = (lang: string) => {
    editor.value?.chain().focus().toggleCodeBlock({ language: lang }).run()
    codeLanguageModalOpen.value = false; codeLanguageQuery.value = ''
}

// ---- Emoji ----
const insertEmoji = (emoji: string) => { editor.value?.chain().focus().insertContent(emoji).run(); emojiOpen.value = false }

// ---- Export ----
const htmlToPlainText = (html: string) => { const el = document.createElement('div'); el.innerHTML = html; return el.textContent || '' }
const htmlToMarkdown = (html: string) => {
    let md = html
        .replace(/<h1[^>]*>(.*?)<\/h1>/gis, '# $1\n\n')
        .replace(/<h2[^>]*>(.*?)<\/h2>/gis, '## $1\n\n')
        .replace(/<h3[^>]*>(.*?)<\/h3>/gis, '### $1\n\n')
        .replace(/<strong[^>]*>(.*?)<\/strong>/gis, '**$1**')
        .replace(/<b[^>]*>(.*?)<\/b>/gis, '**$1**')
        .replace(/<em[^>]*>(.*?)<\/em>/gis, '*$1*')
        .replace(/<i[^>]*>(.*?)<\/i>/gis, '*$1*')
        .replace(/<s[^>]*>(.*?)<\/s>/gis, '~~$1~~')
        .replace(/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/gis, '[$2]($1)')
        .replace(/<li[^>]*>(.*?)<\/li>/gis, '- $1\n')
        .replace(/<br\s*\/?>/gis, '\n').replace(/<\/p>/gis, '\n\n').replace(/<[^>]+>/g, '')
        .replace(/\n{3,}/g, '\n\n').trim()
    return md
}
const downloadTextFile = (filename: string, content: string, type = 'text/plain') => {
    const blob = new Blob([content], { type }); const url = URL.createObjectURL(blob)
    const link = document.createElement('a'); link.href = url; link.download = filename; link.click(); URL.revokeObjectURL(url)
}

const htmlToDocxBlob = async (html: string): Promise<Blob> => {
    const el = document.createElement('div'); el.innerHTML = html
    const paragraphs: Paragraph[] = []
    const walk = (node: ChildNode) => {
        if (node.nodeType === 3) {
            const text = node.textContent || ''
            if (text.trim()) paragraphs.push(new Paragraph({ children: [new TextRun(text)] }))
            return
        }
        if (node.nodeType !== 1) return
        const tag = (node as HTMLElement).tagName.toUpperCase()
        if (tag === 'H1') { paragraphs.push(new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun((node as HTMLElement).textContent || '')] })); return }
        if (tag === 'H2') { paragraphs.push(new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun((node as HTMLElement).textContent || '')] })); return }
        if (tag === 'H3') { paragraphs.push(new Paragraph({ heading: HeadingLevel.HEADING_3, children: [new TextRun((node as HTMLElement).textContent || '')] })); return }
        if (tag === 'P') { const t = (node as HTMLElement).textContent || ''; if (t.trim()) paragraphs.push(new Paragraph({ children: [new TextRun(t)] })); return }
        if (tag === 'BR') { paragraphs.push(new Paragraph({ children: [] })); return }
        node.childNodes.forEach(walk)
    }
    el.childNodes.forEach(walk)
    if (paragraphs.length === 0) paragraphs.push(new Paragraph({ children: [new TextRun(htmlToPlainText(html))] }))
    const doc = new Document({ sections: [{ properties: {}, children: paragraphs }] })
    return await Packer.toBlob(doc)
}

const exportContent = async (format: ExportFormat) => {
    const html = editor.value?.getHTML() || sourceContent.value || ''
    exportOpen.value = false
    if (format === 'html') downloadTextFile('content.html', html, 'text/html')
    if (format === 'text') downloadTextFile('content.txt', htmlToPlainText(html))
    if (format === 'markdown') downloadTextFile('content.md', htmlToMarkdown(html), 'text/markdown')
    if (format === 'docx') {
        exporting.value = true
        try {
            const blob = await htmlToDocxBlob(html)
            const url = URL.createObjectURL(blob)
            const link = document.createElement('a'); link.href = url; link.download = 'content.docx'; link.click(); URL.revokeObjectURL(url)
        } catch { downloadTextFile('content.docx', `<html><body>${html}</body></html>`, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') }
        finally { exporting.value = false }
    }
    if (format === 'pdf') {
        exporting.value = true
        const wrapper = document.createElement('div')
        wrapper.innerHTML = html
        wrapper.style.cssText = 'font-family: Inter, sans-serif; font-size: 12px; line-height: 1.6; color: #111; padding: 20px; max-width: 700px;'
        try {
            const html2pdf = (await import('html2pdf.js')).default
            await html2pdf().set({ margin: 10, filename: 'content.pdf', image: { type: 'jpeg', quality: 0.95 }, html2canvas: { scale: 2, useCORS: true }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } }).from(wrapper).save()
        } catch { const win = window.open('', '_blank'); if (win) { win.document.write(`<html><head><title>Print</title></head><body>${html}</body></html>`); win.document.close(); win.print() } }
        finally { exporting.value = false }
    }
}
const copyContent = async (format: 'html' | 'markdown') => {
    const html = editor.value?.getHTML() || sourceContent.value || ''
    await navigator.clipboard.writeText(format === 'html' ? html : htmlToMarkdown(html))
    exportOpen.value = false
}

// ---- AI assist ----
const runAiAssist = (action: AiAssistAction) => { emit('ai-assist', action.key); aiAssistOpen.value = false; aiSidebarOpen.value = false }
const updateToolbarAiMaxHeight = () => { const h = editor.value?.view.dom.getBoundingClientRect().height ?? 320; toolbarAiMaxHeight.value = Math.floor(Math.max(180, Math.min(360, h - 16))) }
const toggleAiAssistMenu = () => { updateToolbarAiMaxHeight(); aiAssistOpen.value = !aiAssistOpen.value }
const selectedAiActions = computed<AiAssistAction[]>(() => [
    { key: 'improve_selection', label: t('Improve'), description: t('Polish selected text.') },
    { key: 'shorten_selection', label: t('Shorten'), description: t('Make selected text concise.') },
    { key: 'expand_selection', label: t('Expand'), description: t('Add useful detail.') },
    { key: 'rephrase_selection', label: t('Rephrase'), description: t('Rewrite while preserving meaning.') },
    { key: 'translate_selection', label: t('Translate'), description: t('Translate selected text.') },
    { key: 'change_tone', label: t('Change tone'), description: t('Rewrite with a different tone.') },
    { key: 'summarize_selection', label: t('Summarize'), description: t('Condense selected text.') },
    { key: 'fix_grammar', label: t('Fix grammar'), description: t('Correct grammar and spelling.') },
    { key: 'continue_writing', label: t('Continue writing'), description: t('Continue from this selection.') },
])
const documentAiActions = computed<AiAssistAction[]>(() => props.aiAssistActions.length ? props.aiAssistActions : [
    { key: 'summarize_document', label: t('Summarize'), description: t('Summarize the full document.') },
    { key: 'generate_title', label: t('Generate title'), description: t('Create a title from this content.') },
    { key: 'generate_meta_description', label: t('Generate meta description'), description: t('Create SEO description.') },
    { key: 'continue_writing', label: t('Continue writing'), description: t('Continue from the cursor.') },
])
const defaultAiActions = computed<AiAssistAction[]>(() => selectedText.value ? selectedAiActions.value : documentAiActions.value)

// ---- Slash commands ----
const slashCommands = computed<SlashCommand[]>(() => [
    { key: 'paragraph', label: t('Paragraph'), group: t('Text'), action: () => setParagraph() },
    { key: 'heading-1', label: t('Heading 1'), group: t('Headings'), action: () => setHeading(1) },
    { key: 'heading-2', label: t('Heading 2'), group: t('Headings'), action: () => setHeading(2) },
    { key: 'heading-3', label: t('Heading 3'), group: t('Headings'), action: () => setHeading(3) },
    { key: 'bullet-list', label: t('Bullet list'), group: t('Lists'), action: () => editor.value?.chain().focus().toggleBulletList().run() },
    { key: 'ordered-list', label: t('Ordered list'), group: t('Lists'), action: () => editor.value?.chain().focus().toggleOrderedList().run() },
    { key: 'task-list', label: t('Task list'), group: t('Lists'), action: () => editor.value?.chain().focus().toggleTaskList().run() },
    { key: 'image', label: t('Image'), group: t('Media'), action: () => openImageModal() },
    { key: 'video', label: t('Video embed'), group: t('Embeds'), action: () => { videoModalOpen.value = true } },
    { key: 'table', label: t('Table'), group: t('Advanced'), action: () => openTablePicker() },
    { key: 'quote', label: t('Blockquote'), group: t('Text'), action: () => editor.value?.chain().focus().toggleBlockquote().run() },
    { key: 'code', label: t('Code block'), group: t('Code'), action: () => editor.value?.chain().focus().toggleCodeBlock().run() },
    { key: 'hr', label: t('Horizontal rule'), group: t('Advanced'), action: () => editor.value?.chain().focus().setHorizontalRule().run() },
    { key: 'details', label: t('Details'), group: t('Advanced'), action: () => (editor.value?.chain().focus() as any).insertDetails(t('Details')).run() },
])
const filteredSlashCommands = computed(() => {
    const q = slashQuery.value.toLowerCase()
    return slashCommands.value.filter((c) => `${c.label} ${c.group}`.toLowerCase().includes(q)).slice(0, 10)
})
const updateSlashQuery = () => {
    const text = editor.value?.state.doc.textBetween(Math.max(0, editor.value.state.selection.from - 40), editor.value.state.selection.from, '\n') || ''
    const match = text.match(/\/([\w\s-]*)$/)
    slashQuery.value = match?.[1] || ''; slashIndex.value = 0
}
const closeSlash = () => { slashOpen.value = false; slashQuery.value = ''; slashIndex.value = 0 }
const runSlashCommand = (command?: SlashCommand) => {
    if (!command) return
    editor.value?.commands.deleteRange({ from: Math.max(0, editor.value.state.selection.from - slashQuery.value.length - 1), to: editor.value.state.selection.from })
    command.action(); closeSlash()
}

// ---- Global click handler ----
const closeOverflow = (event: MouseEvent) => {
    const t = event.target as HTMLElement | null
    if (!t?.closest('[data-rich-editor-format]')) formatOpen.value = false
    if (!t?.closest('[data-rich-editor-overflow]')) overflowOpen.value = false
    if (!t?.closest('[data-rich-editor-ai-assist]')) aiAssistOpen.value = false
    if (!t?.closest('[data-rich-editor-export]')) exportOpen.value = false
    if (!t?.closest('[data-rich-editor-emoji]')) emojiOpen.value = false
    if (!t?.closest('[data-rich-editor-code-lang]')) codeLanguageModalOpen.value = false
    if (!t?.closest('[data-rich-editor-table-picker]')) tablePickerOpen.value = false
}

onMounted(() => {
    document.addEventListener('click', closeOverflow)
    window.addEventListener('resize', updateToolbarAiMaxHeight)
    saveVersionSnapshot()
    autosaveTimer = window.setInterval(saveVersionSnapshot, 30000)
})
onBeforeUnmount(() => {
    document.removeEventListener('click', closeOverflow)
    window.removeEventListener('resize', updateToolbarAiMaxHeight)
    if (autosaveTimer) window.clearInterval(autosaveTimer)
    editor.value?.destroy()
})
</script>

<template>
    <div class="border border-gray-100 dark:border-surface-800 rounded-2xl overflow-hidden focus-within:ring-1 focus-within:ring-primary-500/40 transition-all bg-white dark:bg-surface-900">
        <!-- Main Toolbar -->
        <div v-if="editor && !isSourceMode" class="relative flex flex-wrap items-center gap-1 p-2 bg-gray-50 dark:bg-surface-800/50 border-b border-gray-100 dark:border-surface-800 overflow-visible">
            <!-- Undo/Redo -->
            <button type="button" @click="editor.chain().focus().undo().run()" :disabled="!editor.can().undo()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors disabled:opacity-30 disabled:cursor-not-allowed" title="Undo">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
            </button>
            <button type="button" @click="editor.chain().focus().redo().run()" :disabled="!editor.can().redo()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors disabled:opacity-30 disabled:cursor-not-allowed" title="Redo">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l6-6m0 0l-6-6m6 6H9a6 6 0 000 12h3"/></svg>
            </button>
            <div class="w-px h-5 bg-gray-200 dark:!bg-gray-600 mx-1"></div>

            <!-- Format dropdown -->
            <div v-if="variant === 'full'" data-rich-editor-format class="relative shrink-0">
                <button type="button" @click.stop="formatOpen = !formatOpen" class="inline-flex h-8 min-w-20 items-center justify-between gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 text-xs font-bold text-gray-700 transition-colors hover:bg-primary-50 hover:text-primary-700 dark:!border-gray-800 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700" title="Format">
                    <span>{{ activeFormatLabel }}</span>
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                </button>
                <div v-if="formatOpen" class="absolute start-0 top-10 z-40 w-48 rounded-xl border border-gray-200 bg-white p-2 shadow-lg dark:border-surface-700 dark:bg-surface-900">
                    <button v-for="lvl in [2, 3, 4, 5, 6]" :key="lvl" type="button" @click="setHeading(lvl as 1|2|3|4|5|6)" :class="{ 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300': editor.isActive('heading', { level: lvl }) }" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-start text-sm font-medium text-gray-700 transition-colors hover:bg-primary-50 dark:text-gray-300 dark:hover:bg-surface-800">
                        <span>{{ t('Heading') }} {{ lvl }}</span><span class="text-xs font-black">H{{ lvl }}</span>
                    </button>
                    <button type="button" @click="setParagraph" :class="{ 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300': editor.isActive('paragraph') }" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-start text-sm font-medium text-gray-700 transition-colors hover:bg-primary-50 dark:text-gray-300 dark:hover:bg-surface-800">
                        <span>{{ t('Paragraph') }}</span><span class="text-xs font-black">P</span>
                    </button>
                    <div class="my-2 h-px bg-gray-100 dark:bg-surface-800"></div>
                    <button type="button" @click="toggleCodeText" :disabled="isHeadingActive" :class="{ 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300': editor.isActive('code') }" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-start text-sm font-medium text-gray-700 transition-colors hover:bg-primary-50 disabled:cursor-not-allowed disabled:opacity-40 dark:text-gray-300 dark:hover:bg-surface-800">
                        <span>{{ t('Inline Code') }}</span><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs dark:bg-surface-800 italic font-mono">&lt;/&gt;</code>
                    </button>
                </div>
            </div>

            <!-- Bold / Italic / Underline -->
            <button type="button" @click="editor.chain().focus().toggleBold().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('bold') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors font-bold text-sm" title="Bold">B</button>
            <button type="button" @click="editor.chain().focus().toggleItalic().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('italic') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors font-bold italic text-sm" title="Italic">I</button>
            <button v-if="variant !== 'minimal'" type="button" @click="editor.chain().focus().toggleUnderline().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('underline') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors text-sm underline underline-offset-2" title="Underline">U</button>

            <!-- Text Color + Highlight (full variant only) -->
            <template v-if="variant === 'full'">
                <label class="relative flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Text Color">
                    <span class="font-serif text-xs font-bold" :style="{ color: editor?.getAttributes('textStyle').color || 'currentColor' }">A</span>
                    <input type="color" @input="(e) => editor?.chain().focus().setColor((e.target as HTMLInputElement).value).run()" :value="editor?.getAttributes('textStyle').color || '#000000'" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                </label>
                <label class="relative flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Highlight">
                    <span class="rounded bg-yellow-200 px-1 font-serif text-xs font-bold text-black">H</span>
                    <input type="color" @input="(e) => editor?.chain().focus().toggleHighlight({ color: (e.target as HTMLInputElement).value }).run()" :value="editor?.getAttributes('highlight').color || '#ffff00'" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                </label>
            </template>

            <!-- Divisor + Lists -->
            <div v-if="variant !== 'minimal'" class="w-px h-5 bg-gray-200 dark:!bg-gray-600 mx-1"></div>
            <button v-if="variant !== 'minimal'" type="button" @click="editor.chain().focus().toggleBulletList().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('bulletList') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Bullet List">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
            </button>
            <button v-if="variant !== 'minimal'" type="button" @click="editor.chain().focus().toggleOrderedList().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('orderedList') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Numbered List">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75v.008m0 5.242v.008m0 5.242v.008"/></svg>
            </button>
            <button v-if="variant === 'full'" type="button" @click="editor.chain().focus().toggleBlockquote().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('blockquote') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Blockquote"><i class="ti ti-quote-open text-sm"></i></button>
            <button v-if="variant === 'full'" type="button" @click="clearFormatting()" class="p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 transition-colors text-xs font-bold" title="Clear formatting">Tx</button>

            <!-- Divisor + Link -->
            <div class="w-px h-5 bg-gray-200 dark:!bg-gray-600 mx-1"></div>
            <button type="button" @click="openLinkModal()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('link') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Link">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/></svg>
            </button>

            <!-- Code button for comment/minimal -->
            <button v-if="variant !== 'full'" type="button" @click="toggleCodeText" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('code') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors font-mono text-xs font-bold" title="Inline Code">&lt;/&gt;</button>

            <template v-if="variant === 'full'">
                <button type="button" @click="openImageModal()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Add image">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 19.5h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5Z"/></svg>
                </button>
            </template>

            <!-- Emoji -->
            <div v-if="variant === 'full'" data-rich-editor-emoji class="relative">
                <button type="button" @click.stop="emojiOpen = !emojiOpen" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors text-sm" title="Emoji">😊</button>
                <div v-if="emojiOpen" class="absolute start-0 top-10 z-40 w-80 rounded-xl border border-gray-200 bg-white p-3 shadow-lg dark:border-surface-700 dark:bg-surface-900 overflow-y-auto max-h-72">
                    <div v-for="group in emojiGroups" :key="group.label">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 px-1 py-1">{{ group.label }}</div>
                        <div class="flex flex-wrap gap-0.5 mb-2">
                            <button v-for="emoji in group.emojis" :key="emoji" type="button" @click="insertEmoji(emoji)" class="w-8 h-8 flex items-center justify-center rounded hover:bg-gray-100 dark:hover:bg-surface-700 text-lg leading-none transition-colors">{{ emoji }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AI + Export + Overflow (right side) -->
            <div class="flex-1"></div>
            <div class="flex shrink-0 items-center gap-1 border-l border-gray-200 dark:border-surface-700 pl-2">
                <div v-if="aiAssist && defaultAiActions.length" data-rich-editor-ai-assist class="relative">
                    <button type="button" @click.stop="toggleAiAssistMenu" :disabled="Boolean(aiAssistLoadingKey)" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-violet-200 bg-violet-50 px-2.5 text-xs font-bold text-violet-700 transition-colors hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-violet-900/40 dark:bg-violet-900/20 dark:text-violet-300 dark:hover:bg-violet-900/30" title="AI Assist">
                        <svg v-if="aiAssistLoadingKey" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else-if="aiAssistSuccess" class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span v-else>✨AI</span>
                    </button>
                    <div v-if="aiAssistOpen" class="absolute end-0 top-10 z-40 w-64 overflow-y-auto overscroll-contain rounded-xl border border-gray-200 bg-white p-2 shadow-lg dark:border-surface-700 dark:bg-surface-900" :style="{ maxHeight: `${toolbarAiMaxHeight}px` }">
                        <button v-for="action in defaultAiActions" :key="action.key" type="button" @click="runAiAssist(action)" :disabled="Boolean(aiAssistLoadingKey)" class="flex w-full flex-col rounded-lg px-3 py-2 text-start text-sm transition-colors hover:bg-violet-50 disabled:cursor-not-allowed disabled:opacity-60 dark:hover:bg-violet-900/20">
                            <span class="font-medium text-gray-800 dark:text-gray-100">{{ action.label }}</span>
                            <span v-if="action.description" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ action.description }}</span>
                        </button>
                    </div>
                </div>
                <div v-if="variant === 'full'" data-rich-editor-export class="relative">
                    <button type="button" @click.stop="exportOpen = !exportOpen" :disabled="exporting" class="p-2 rounded-lg bg-white dark:bg-surface-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-surface-700 hover:bg-primary-50 dark:hover:bg-surface-700 transition-colors disabled:opacity-50" title="Export">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </button>
                    <div v-if="exportOpen" class="absolute end-0 top-10 z-40 w-52 rounded-xl border border-gray-200 bg-white p-2 shadow-lg dark:border-surface-700 dark:bg-surface-900">
                        <button type="button" @click="exportContent('html')" class="w-full rounded-lg px-3 py-2 text-start text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200 dark:hover:bg-white/5">{{ t('Download HTML') }}</button>
                        <button type="button" @click="exportContent('markdown')" class="w-full rounded-lg px-3 py-2 text-start text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200 dark:hover:bg-white/5">{{ t('Download Markdown') }}</button>
                        <button type="button" @click="exportContent('text')" class="w-full rounded-lg px-3 py-2 text-start text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200 dark:hover:bg-white/5">{{ t('Download Plain Text') }}</button>
                        <button type="button" @click="exportContent('docx')" class="w-full rounded-lg px-3 py-2 text-start text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200 dark:hover:bg-white/5">{{ t('Download DOCX') }}</button>
                        <button type="button" @click="exportContent('pdf')" class="w-full rounded-lg px-3 py-2 text-start text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200 dark:hover:bg-white/5">{{ t('Download PDF') }}</button>
                        <div class="my-2 h-px bg-gray-200 dark:!bg-gray-700"></div>
                        <button type="button" @click="copyContent('html')" class="w-full rounded-lg px-3 py-2 text-start text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200 dark:hover:bg-white/5">{{ t('Copy HTML') }}</button>
                        <button type="button" @click="copyContent('markdown')" class="w-full rounded-lg px-3 py-2 text-start text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200 dark:hover:bg-white/5">{{ t('Copy Markdown') }}</button>
                    </div>
                </div>
                <button v-if="variant === 'full'" type="button" data-rich-editor-overflow @click.stop="overflowOpen = !overflowOpen" class="p-2 rounded-lg bg-white dark:bg-surface-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-surface-700 hover:bg-primary-50 dark:hover:bg-surface-700 transition-colors" title="More tools">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8a2 2 0 110-4 2 2 0 010 4Zm0 6a2 2 0 110-4 2 2 0 010 4Zm0 6a2 2 0 110-4 2 2 0 010 4Z"/></svg>
                </button>
            </div>

            <!-- Overflow panel -->
            <div v-if="variant === 'full' && overflowOpen" data-rich-editor-overflow class="absolute end-2 top-12 z-30 w-80 rounded-xl border border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-900 p-3 shadow-lg">
                <!-- Font family/size/line-height -->
                <div class="space-y-2 border-b border-gray-100 pb-3 dark:border-surface-800">
                    <AppSelect :options="fontOptions" @update:model-value="(v: any) => setFontFamily(String(v))" placeholder="Font" :size="5" />
                    <div class="grid grid-cols-2 gap-2">
                        <AppSelect :options="[12,14,16,18,20,24,30,36,48,60,72].map(s => ({value: `${s}px`, label: `${s}px`}))" @update:model-value="(v: any) => setFontSize(String(v))" placeholder="Size" :size="5" />
                        <AppSelect :options="[{value:'1.2',label:'1.2'},{value:'1.5',label:'1.5'},{value:'1.75',label:'1.75'},{value:'2',label:'2'}]" @update:model-value="(v: any) => setLineHeight(String(v))" placeholder="Line height" :size="5" />
                    </div>
                </div>
                <!-- Alignment + code + lists -->
                <div class="mt-3 grid grid-cols-4 gap-2">
                    <button type="button" @click="editor.chain().focus().setTextAlign('left').run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive({ textAlign: 'left' }) }" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700 flex items-center justify-center" title="Align Left">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h11.25m-11.25 5.25h16.5"/></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().setTextAlign('center').run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive({ textAlign: 'center' }) }" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700 flex items-center justify-center" title="Align Center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M6.75 12h10.5m-13.5 5.25h16.5"/></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().setTextAlign('right').run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive({ textAlign: 'right' }) }" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700 flex items-center justify-center" title="Align Right">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M9 12h11.25M3.75 17.25h16.5"/></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().setTextAlign('justify').run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive({ textAlign: 'justify' }) }" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700 flex items-center justify-center" title="Justify">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().toggleCodeBlock().run(); overflowOpen = false" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('codeBlock') }" class="h-9 rounded-lg font-mono text-xs font-bold text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700">&lt;/&gt;</button>
                    <button type="button" @click="editor.chain().focus().toggleTaskList().run(); overflowOpen = false" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('taskList') }" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700 flex items-center justify-center" title="Task List">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>
                    <button type="button" @click="openTablePicker(); overflowOpen = false" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700 flex items-center justify-center" title="Insert Table">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 5.25h17.25m-17.25 6.75h17.25m-17.25 6.75h17.25M8.25 5.25v13.5m7.5-13.5v13.5"/></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().sinkListItem('listItem').run(); overflowOpen = false" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700 flex items-center justify-center" title="Indent">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M12 17.25h8.25"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 15.75L3.75 18l3 2.25"/></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().liftListItem('listItem').run(); overflowOpen = false" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700 flex items-center justify-center" title="Outdent">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h8.25"/><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75L18.75 18l-3 2.25"/></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().setHorizontalRule().run(); overflowOpen = false" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700 text-xs font-bold" title="Horizontal Rule">HR</button>
                    <button type="button" @click="videoModalOpen = true; overflowOpen = false" class="h-9 rounded-lg text-xs font-bold text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700" title="Insert Video">Vid</button>
                    <button type="button" @click="attachmentModalOpen = true; overflowOpen = false" class="h-9 rounded-lg text-xs font-bold text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700" title="Attach File">File</button>
                    <button type="button" @click="insertDetails(); overflowOpen = false" class="h-9 rounded-lg text-xs font-bold text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700" title="Insert Details">Det</button>
                    <button type="button" @click="editor.chain().focus().toggleStrike().run(); overflowOpen = false" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('strike') }" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700 flex items-center justify-center text-xs line-through" title="Strikethrough">S</button>
                    <button type="button" @click="toggleSubscript(); overflowOpen = false" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('subscript') }" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700 flex items-center justify-center" title="Subscript">X<sub>2</sub></button>
                    <button type="button" @click="toggleSuperscript(); overflowOpen = false" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('superscript') }" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700 flex items-center justify-center" title="Superscript">X<sup>2</sup></button>
                </div>
                <button type="button" @click="toggleMode" class="mt-3 w-full rounded-lg border border-primary-100 dark:border-primary-900/30 bg-primary-50 dark:bg-primary-900/20 px-3 py-2 text-xs font-bold text-primary-700 dark:text-primary-400 hover:bg-primary-100 dark:hover:bg-primary-900/30" title="Toggle Source Mode">Source</button>
            </div>
        </div>

        <!-- Table Context Bar -->
        <div v-if="editor && editor.isActive('table') && !isSourceMode" class="flex flex-nowrap items-center gap-1 overflow-x-auto p-1.5 bg-indigo-50 dark:bg-indigo-900/20 border-b border-indigo-100 dark:border-indigo-900/30 text-[10px]">
            <span class="font-black text-indigo-600 dark:text-indigo-400 uppercase px-2">Table</span>
            <button @click="editor.chain().focus().addColumnBefore().run()" class="px-2 py-1 bg-white dark:bg-surface-800 border border-indigo-200 dark:border-indigo-900/50 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">+Col L</button>
            <button @click="editor.chain().focus().addColumnAfter().run()" class="px-2 py-1 bg-white dark:bg-surface-800 border border-indigo-200 dark:border-indigo-900/50 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">+Col R</button>
            <button @click="editor.chain().focus().deleteColumn().run()" class="px-2 py-1 bg-white dark:bg-surface-800 border border-red-200 dark:border-red-900/50 rounded hover:bg-red-50 dark:hover:bg-red-900/40 text-red-700 dark:text-red-400">-Col</button>
            <div class="w-px h-4 bg-indigo-200 dark:bg-indigo-800 mx-1"></div>
            <button @click="editor.chain().focus().addRowBefore().run()" class="px-2 py-1 bg-white dark:bg-surface-800 border border-indigo-200 dark:border-indigo-900/50 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">+Row Up</button>
            <button @click="editor.chain().focus().addRowAfter().run()" class="px-2 py-1 bg-white dark:bg-surface-800 border border-indigo-200 dark:border-indigo-900/50 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">+Row Down</button>
            <button @click="editor.chain().focus().deleteRow().run()" class="px-2 py-1 bg-white dark:bg-surface-800 border border-red-200 dark:border-red-900/50 rounded hover:bg-red-50 dark:hover:bg-red-900/40 text-red-700 dark:text-red-400">-Row</button>
            <div class="w-px h-4 bg-indigo-200 dark:bg-indigo-800 mx-1"></div>
            <button @click="editor.chain().focus().mergeCells().run()" class="px-2 py-1 bg-white dark:bg-surface-800 border border-indigo-200 dark:border-indigo-900/50 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">Merge</button>
            <button @click="editor.chain().focus().splitCell().run()" class="px-2 py-1 bg-white dark:bg-surface-800 border border-indigo-200 dark:border-indigo-900/50 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">Split</button>
            <button @click="editor.chain().focus().toggleHeaderRow().run()" class="px-2 py-1 bg-white dark:bg-surface-800 border border-indigo-200 dark:border-indigo-900/50 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">Hdr</button>
            <div class="w-px h-4 bg-indigo-200 dark:bg-indigo-800 mx-1"></div>
            <label class="relative flex h-6 w-8 cursor-pointer items-center justify-center rounded bg-white dark:bg-surface-800 border border-indigo-200 dark:border-indigo-900/50 hover:bg-indigo-50 dark:hover:bg-indigo-900/40" title="Cell BG">
                <span class="text-xs">🎨</span>
                <input type="color" @input="(e) => (editor?.chain().focus() as any).setCellBg((e.target as HTMLInputElement).value)" value="#ffffff" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
            </label>
            <button @click="editor.chain().focus().deleteTable().run()" class="ml-auto px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900/50 rounded hover:bg-red-100 dark:hover:bg-red-900/40 font-bold text-red-700 dark:text-red-400">Delete Table</button>
        </div>

        <!-- Source Mode Header -->
        <div v-if="isSourceMode" class="flex items-center justify-between px-4 py-2 bg-gray-50 dark:bg-surface-800/50 border-b border-gray-100 dark:border-surface-800">
            <div class="text-[10px] font-black text-amber-600 uppercase">Source Mode</div>
            <button type="button" @click="toggleMode" class="px-3 py-1.5 bg-white dark:bg-surface-800 text-primary-600 border border-primary-100 dark:border-primary-900/30 rounded-xl text-[10px] font-black uppercase shadow-sm hover:bg-primary-50 dark:hover:bg-surface-700">Visual</button>
        </div>

        <!-- Editor Surface -->
        <div class="relative bg-white dark:bg-surface-900" :class="variant === 'full' ? 'min-h-[400px]' : 'min-h-[140px]'">
            <EditorContent v-if="!isSourceMode" :editor="editor" class="prose-container" :class="`rich-editor-${variant}`" />
            <textarea v-else :value="sourceContent" @input="handleSourceInput" class="w-full h-full min-h-[400px] p-6 text-sm font-mono bg-gray-900 text-gray-300 focus:ring-0 border-none outline-none resize-none" spellcheck="false"></textarea>

            <!-- Slash command palette -->
            <div v-if="slashOpen && filteredSlashCommands.length" class="absolute left-6 top-14 z-40 w-72 rounded-xl border border-gray-200 bg-white p-2 shadow-lg dark:border-surface-700 dark:bg-surface-900">
                <div class="px-3 py-2 text-[10px] font-black uppercase text-gray-400">{{ t('Slash commands') }}</div>
                <button v-for="(cmd, i) in filteredSlashCommands" :key="cmd.key" @click="runSlashCommand(cmd)" :class="{ 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300': i === slashIndex }" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-start text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200">
                    <span>{{ cmd.label }}</span><span class="text-[10px] font-bold uppercase text-gray-400">{{ cmd.group }}</span>
                </button>
            </div>

            <!-- Code block language selector -->
            <div v-if="editor?.isActive('codeBlock')" data-rich-editor-code-lang class="absolute right-4 top-4 z-30">
                <button type="button" @click.stop="codeLanguageModalOpen = !codeLanguageModalOpen; codeLanguageQuery = ''" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-bold text-gray-600 shadow-sm hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300" title="Code language">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5"/></svg>
                    {{ t('Language') }}
                </button>
                <div v-if="codeLanguageModalOpen" class="absolute right-0 top-8 w-48 rounded-xl border border-gray-200 bg-white p-2 shadow-lg dark:border-surface-700 dark:bg-surface-900">
                    <input v-model="codeLanguageQuery" type="text" :placeholder="t('Search...')" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-2 py-1.5 text-xs mb-2 dark:border-surface-700 dark:bg-surface-800 dark:text-white" autofocus>
                    <div class="max-h-48 overflow-y-auto">
                        <button v-for="lang in filteredLanguages" :key="lang" type="button" @click="setCodeLanguage(lang)" class="w-full rounded-lg px-3 py-1.5 text-start text-xs text-gray-700 font-mono hover:bg-primary-50 dark:text-gray-200 dark:hover:bg-surface-800 transition-colors">{{ lang }}</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status bar -->
        <div v-if="editor" class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 bg-gray-50 px-4 py-2 text-xs text-gray-500 dark:border-surface-800 dark:bg-surface-800/50 dark:text-gray-400">
            <div class="flex flex-wrap items-center gap-3">
                <span>{{ textStats.words }} {{ t('words') }}</span>
                <span>{{ textStats.characters }} {{ t('characters') }}</span>
                <span v-if="variant !== 'minimal' && variant !== 'comment'">{{ textStats.readingMinutes }} {{ t('min read') }}</span>
            </div>
            <div v-if="variant === 'full'" class="flex flex-wrap items-center gap-2">
                <span>{{ t('Versions') }}: {{ versionHistory.length }}/20</span>
                <AppSelect
                    v-if="versionHistory.length > 1"
                    v-model="restoreVersionSelection"
                    :options="versionHistoryOptions"
                    :placeholder="t('Restore version')"
                    :size="6"
                    @update:model-value="handleRestoreVersion"
                />
            </div>
        </div>

        <!-- AI Sidebar -->
        <aside v-if="aiSidebarOpen && aiAssist" class="border-t border-violet-100 bg-violet-50/70 p-4 dark:border-violet-900/30 dark:bg-violet-950/20">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ t('AI editor assistant') }}</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ selectedText ? t('Actions will use selected text.') : t('Actions will use the full document.') }}</p>
                </div>
                <button type="button" @click="aiSidebarOpen = false" class="rounded-lg px-3 py-1.5 text-xs font-bold text-gray-500 hover:bg-white dark:hover:bg-surface-900">{{ t('Close') }}</button>
            </div>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <button v-for="action in defaultAiActions" :key="action.key" type="button" @click="runAiAssist(action)" :disabled="Boolean(aiAssistLoadingKey)" class="rounded-xl border border-violet-100 bg-white px-3 py-3 text-start shadow-sm transition-colors hover:border-violet-300 disabled:opacity-60 dark:border-violet-900/30 dark:bg-surface-900">
                    <span class="block text-sm font-bold text-violet-700 dark:text-violet-300">{{ action.label }}</span>
                    <span v-if="action.description" class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ action.description }}</span>
                </button>
            </div>
        </aside>

        <!-- Link tooltip -->
        <div v-if="linkTooltip.visible" class="pointer-events-none fixed z-50 max-w-xs truncate rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-medium text-white shadow-lg" :style="{ left: `${linkTooltip.x + 12}px`, top: `${linkTooltip.y + 14}px` }">{{ linkTooltip.url }}</div>

        <!-- Table picker modal -->
        <div v-if="tablePickerOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]" @click.self="tablePickerOpen = false">
            <div data-rich-editor-table-picker class="rounded-xl border border-gray-200 bg-white p-4 shadow-lg dark:border-surface-700 dark:bg-surface-900">
                <div class="text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 text-center">{{ tablePickerRows }} × {{ tablePickerCols }}</div>
                <div class="grid grid-cols-8 gap-1">
                    <div v-for="cell in tablePickerGrid" :key="`${cell.row}-${cell.col}`" @mouseenter="hoverTableCell(cell.row, cell.col)" @click="insertTable(cell.row, cell.col)" :class="cell.active ? 'bg-primary-200 border-primary-400 dark:bg-primary-800 dark:border-primary-600' : 'bg-gray-100 border-gray-200 dark:bg-surface-800 dark:border-surface-700'" class="w-6 h-6 rounded border cursor-pointer transition-colors"></div>
                </div>
                <div class="text-[10px] text-gray-400 mt-2 text-center">{{ t('Click to insert') }}</div>
            </div>
        </div>

        <!-- Modals: Link, Image, Video, Attachment -->
        <div v-if="linkModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]" @click.self="closeLinkModal">
            <div class="w-full max-w-[540px] overflow-visible rounded-2xl border border-gray-200 bg-white shadow-lg dark:border-surface-700 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-5 py-3 dark:border-surface-800"><h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ linkEditing ? t('Edit link') : t('Add link') }}</h3></div>
                <div class="space-y-4 p-5">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('URL') }}<input v-model="linkUrl" type="url" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"></label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Link Text') }}<input v-model="linkTitle" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"></label>
                    <AppSelect v-model="linkTarget" :label="t('Link Target')" :options="linkTargetOptions" />
                    <AppColorPicker v-model="linkColor" :label="t('Link Color')" />
                </div>
                <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50 px-5 py-3 dark:border-surface-800 dark:bg-surface-950">
                    <button v-if="linkEditing" type="button" @click="removeLink" class="rounded-lg px-4 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 dark:hover:bg-red-900/20">{{ t('Remove link') }}</button><span v-else></span>
                    <div class="flex items-center gap-2"><button type="button" @click="closeLinkModal" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-800">{{ t('Cancel') }}</button><button type="button" @click="applyLink" class="rounded-lg btn-primary transition-colors">{{ linkEditing ? t('Apply') : t('Add link') }}</button></div>
                </div>
            </div>
        </div>

        <div v-if="imageModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]" @click.self="closeImageModal">
            <div class="w-full max-w-[540px] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg dark:border-surface-700 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-5 py-3 dark:border-surface-800"><h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Add image') }}</h3></div>
                <div class="space-y-4 p-5">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Upload image') }}</label>
                        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-3 py-3 dark:border-surface-700 dark:bg-surface-800">
                            <button type="button" @click="openImageFilePicker" class="inline-flex items-center justify-center rounded-full border border-primary-500 bg-primary-50 px-4 py-2.5 text-sm font-semibold text-primary-700 transition hover:bg-primary-100 dark:border-primary-500/30 dark:bg-primary-500/15 dark:text-primary-300 dark:hover:bg-primary-500/25">
                                {{ t('Choose file') }}
                            </button>
                            <span class="min-w-0 flex-1 truncate text-sm text-gray-500 dark:text-gray-400">
                                {{ imageFileName || t('No file chosen') }}
                            </span>
                            <input ref="imageInputRef" type="file" accept="image/*" @change="handleImageUpload" class="hidden">
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-center text-xs font-medium uppercase text-gray-400">
                        <div class="h-px flex-1 bg-gray-200 dark:!bg-surface-700"></div>
                        <span class="shrink-0">{{ t('or') }}</span>
                        <div class="h-px flex-1 bg-gray-200 dark:!bg-surface-700"></div>
                    </div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Image URL') }}<input v-model="imageUrl" type="url" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"></label>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Alt text') }}<input v-model="imageAlt" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"></label>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Caption') }}<input v-model="imageCaption" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"></label>
                    </div>
                    <p v-if="uploadedImageData" class="rounded-lg bg-primary-50 px-3 py-2 text-sm text-primary-700 dark:bg-primary-900/20 dark:text-primary-300">{{ t('Uploaded image is ready to insert.') }}</p>
                    <p v-if="imageUploading" class="rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-700 dark:bg-blue-900/20 dark:text-blue-300">{{ t('Uploading image...') }}</p>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Image width') }}<input v-model="imageWidth" type="text" :placeholder="t('Auto, 480, 80%, 32rem')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"></label>
                        <AppSelect v-model="imageAlignment" :label="t('Image alignment')" :options="imageAlignmentOptions" />
                    </div>
                    <p v-if="imageError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">{{ imageError }}</p>
                </div>
                <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50 px-5 py-3 dark:border-surface-800 dark:bg-surface-950">
                    <button v-if="imageEditing" type="button" @click="removeImage" class="rounded-lg px-4 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 dark:hover:bg-red-900/20">{{ t('Remove image') }}</button><span v-else></span>
                    <div class="flex items-center gap-2"><button type="button" @click="closeImageModal" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-800">{{ t('Cancel') }}</button><button type="button" @click="applyImageUrl" class="rounded-lg btn-primary transition-colors">{{ imageEditing ? t('Apply') : t('Insert image') }}</button></div>
                </div>
            </div>
        </div>

        <div v-if="videoModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]" @click.self="videoModalOpen = false">
            <div class="w-full max-w-[540px] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg dark:border-surface-700 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-5 py-3 dark:border-surface-800"><h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Embed video') }}</h3></div>
                <div class="space-y-4 p-5"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('YouTube or Vimeo URL') }}<input v-model="videoUrl" type="url" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"></label><p v-if="videoError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">{{ videoError }}</p></div>
                <div class="flex items-center justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-3 dark:border-surface-800 dark:bg-surface-950"><button type="button" @click="videoModalOpen = false" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-800">{{ t('Cancel') }}</button><button type="button" @click="insertVideo" class="rounded-lg btn-primary transition-colors">{{ t('Insert video') }}</button></div>
            </div>
        </div>

        <div v-if="attachmentModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]" @click.self="attachmentModalOpen = false">
            <div class="w-full max-w-[540px] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg dark:border-surface-700 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-5 py-3 dark:border-surface-800"><h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Attach file') }}</h3></div>
                <div class="space-y-4 p-5">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Upload file') }}</label>
                        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-3 py-3 dark:border-surface-700 dark:bg-surface-800">
                            <button type="button" @click="openAttachmentFilePicker" class="inline-flex items-center justify-center rounded-full border border-primary-500 bg-primary-50 px-4 py-2.5 text-sm font-semibold text-primary-700 transition hover:bg-primary-100 dark:border-primary-500/30 dark:bg-primary-500/15 dark:text-primary-300 dark:hover:bg-primary-500/25">
                                {{ t('Choose file') }}
                            </button>
                            <span class="min-w-0 flex-1 truncate text-sm text-gray-500 dark:text-gray-400">
                                {{ attachmentFileName || t('No file chosen') }}
                            </span>
                            <input ref="attachmentInputRef" type="file" @change="handleAttachmentUpload" class="hidden">
                        </div>
                    </div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('File URL') }}<input v-model="attachmentUrl" type="url" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"></label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Display name') }}<input v-model="attachmentName" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"></label>
                    <p v-if="attachmentUploading" class="rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-700 dark:bg-blue-900/20 dark:text-blue-300">{{ t('Uploading file...') }}</p>
                    <p v-if="attachmentFileName" class="rounded-lg bg-primary-50 px-3 py-2 text-sm text-primary-700 dark:bg-primary-900/20 dark:text-primary-300">{{ attachmentFileName }}</p>
                    <p v-if="attachmentError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">{{ attachmentError }}</p>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-3 dark:border-surface-800 dark:bg-surface-950"><button type="button" @click="attachmentModalOpen = false" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-800">{{ t('Cancel') }}</button><button type="button" @click="insertAttachment" class="rounded-lg btn-primary transition-colors">{{ t('Insert file') }}</button></div>
            </div>
        </div>
    </div>
</template>

<style>
.prose-container .ProseMirror { color: var(--color-text-primary, #1f2937); padding: 1.5rem; min-height: 400px; outline: none !important; }
.prose-container.rich-editor-minimal .ProseMirror { min-height: 140px; padding: 1rem; }
.prose-container.rich-editor-comment .ProseMirror { min-height: 180px; padding: 1rem; }

.prose-container .ProseMirror h1 { font-size: 1.875rem; font-weight: 800; margin-bottom: 1.5rem; line-height: 1.2; }
.prose-container .ProseMirror h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 1.25rem; line-height: 1.3; }
.prose-container .ProseMirror h3 { font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem; }
.prose-container .ProseMirror h4 { font-size: 1.0625rem; font-weight: 700; margin-bottom: 0.75rem; }
.prose-container .ProseMirror h5 { font-size: 0.9375rem; font-weight: 700; margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; }
.prose-container .ProseMirror p { margin-bottom: 1rem; line-height: 1.6; }
.prose-container .ProseMirror a { color: #4f46e5; text-decoration: underline; }

.prose-container .ProseMirror ul { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 1rem; }
.prose-container .ProseMirror ol { list-style-type: decimal; padding-left: 1.5rem; margin-bottom: 1rem; }
.prose-container .ProseMirror li p { margin-bottom: 0.25rem; }
.prose-container .ProseMirror ul[data-type="taskList"] { list-style: none; padding: 0; }
.prose-container .ProseMirror ul[data-type="taskList"] li { display: flex; align-items: flex-start; gap: 0.5rem; }
.prose-container .ProseMirror ul[data-type="taskList"] li > label { margin-top: 0.25rem; }

.prose-container .ProseMirror blockquote { border-left: 4px solid #e5e7eb; padding-left: 1rem; font-style: italic; color: #4b5563; }
.prose-container .ProseMirror pre { background: #1f2937; color: #f9fafb; padding: 1rem; border-radius: 0.5rem; font-family: monospace; font-size: 0.875rem; overflow-x: auto; position: relative; }
.prose-container .ProseMirror pre code { background: none; padding: 0; color: inherit; }
.prose-container .ProseMirror code { background: #f3f4f6; padding: 0.2rem 0.4rem; border-radius: 0.25rem; font-size: 0.875em; }

.prose-container .ProseMirror table { border-collapse: collapse; table-layout: fixed; width: 100%; margin: 0; overflow: hidden; }
.prose-container .ProseMirror table td, .prose-container .ProseMirror table th { min-width: 1em; border: 1px solid #e5e7eb; padding: 0.5rem; vertical-align: top; box-sizing: border-box; position: relative; }
.prose-container .ProseMirror table th { font-weight: bold; text-align: left; background-color: #f9fafb; }

.prose-container .ProseMirror img { max-width: 100%; height: auto; border-radius: 0.5rem; }
.prose-container .ProseMirror img.ProseMirror-selectednode { outline: 3px solid #4f46e5; }
.prose-container .ProseMirror img[data-align="center"] { display: block; margin-inline: auto; }
.prose-container .ProseMirror img[data-align="right"] { display: block; margin-inline-start: auto; margin-inline-end: 0; }
.prose-container .ProseMirror img[data-align="float-left"] { float: inline-start; margin-inline-end: 1rem; margin-block: 0.25rem 1rem; }
.prose-container .ProseMirror img[data-align="float-right"] { float: inline-end; margin-inline-start: 1rem; margin-block: 0.25rem 1rem; }
.prose-container .ProseMirror iframe.rich-video-embed { width: 100%; aspect-ratio: 16 / 9; border-radius: 0.75rem; margin: 1rem 0; background: #111827; }
.prose-container .ProseMirror a.rich-file-attachment { display: inline-flex; align-items: center; gap: 0.5rem; margin: 0.75rem 0; border: 1px solid #d1fae5; border-radius: 0.75rem; background: #ecfdf5; padding: 0.75rem 1rem; color: #047857; font-weight: 700; text-decoration: none; }
.prose-container .ProseMirror details { border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 0.75rem 1rem; margin: 1rem 0; background: #f9fafb; }
.prose-container .ProseMirror details summary { cursor: pointer; font-weight: 700; color: #111827; }
.prose-container .ProseMirror hr { border: 0; border-top: 1px solid #e5e7eb; margin: 1.5rem 0; }

.dark .prose-container .ProseMirror { color: #f4f4f5; }
.dark .prose-container .ProseMirror blockquote { border-left-color: #3f3f46; color: #a1a1aa; }
.dark .prose-container .ProseMirror code { background: #27272a; color: #e4e4e7; }
.dark .prose-container .ProseMirror table td, .dark .prose-container .ProseMirror table th { border-color: #3f3f46; }
.dark .prose-container .ProseMirror table th { background-color: #27272a; }
.dark .prose-container .ProseMirror a.rich-file-attachment { border-color: #064e3b; background: rgba(6, 78, 59, 0.25); color: #6ee7b7; }
.dark .prose-container .ProseMirror details { border-color: #3f3f46; background: #18181b; }
.dark .prose-container .ProseMirror details summary { color: #f4f4f5; }

/* lowlight theme overrides */
.prose-container .ProseMirror pre code .hljs-keyword,
.prose-container .ProseMirror pre code .hljs-selector-tag,
.prose-container .ProseMirror pre code .hljs-title,
.prose-container .ProseMirror pre code .hljs-section,
.prose-container .ProseMirror pre code .hljs-doctag,
.prose-container .ProseMirror pre code .hljs-name,
.prose-container .ProseMirror pre code .hljs-strong { color: #f0f0f0; }
.prose-container .ProseMirror pre code .hljs-comment { color: #6b7280; }
.prose-container .ProseMirror pre code .hljs-string,
.prose-container .ProseMirror pre code .hljs-title.class_,
.prose-container .ProseMirror pre code .hljs-title.class_ .hljs-title,
.prose-container .ProseMirror pre code .hljs-template-variable,
.prose-container .ProseMirror pre code .hljs-type,
.prose-container .ProseMirror pre code .hljs-addition { color: #86efac; }
.prose-container .ProseMirror pre code .hljs-number,
.prose-container .ProseMirror pre code .hljs-literal,
.prose-container .ProseMirror pre code .hljs-variable,
.prose-container .ProseMirror pre code .hljs-template-tag,
.prose-container .ProseMirror pre code .hljs-tag .hljs-attr { color: #fca5a5; }
.prose-container .ProseMirror pre code .hljs-built_in,
.prose-container .ProseMirror pre code .hljs-symbol { color: #93c5fd; }
.prose-container .ProseMirror pre code .hljs-meta { color: #a78bfa; }
.prose-container .ProseMirror pre code .hljs-attr { color: #fcd34d; }
</style>
