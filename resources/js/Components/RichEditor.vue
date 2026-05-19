<script setup lang="ts">
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { Extension, Mark, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import TiptapLink from '@tiptap/extension-link';
import { TextAlign } from '@tiptap/extension-text-align';
import { TextStyle } from '@tiptap/extension-text-style';
import { Color } from '@tiptap/extension-color';
import { Highlight } from '@tiptap/extension-highlight';
import { TaskList } from '@tiptap/extension-task-list';
import { TaskItem } from '@tiptap/extension-task-item';
import { Table } from '@tiptap/extension-table';
import { TableRow } from '@tiptap/extension-table-row';
import { TableHeader } from '@tiptap/extension-table-header';
import { TableCell } from '@tiptap/extension-table-cell';
import { Image } from '@tiptap/extension-image';
import { useTranslate } from '@/Composables/useTranslate';

interface AiAssistAction {
    key: string;
    label: string;
    description?: string;
}

type ImageAlignment = 'left' | 'center' | 'right' | 'float-left' | 'float-right';
type ExportFormat = 'html' | 'text' | 'markdown' | 'pdf' | 'doc';

interface SlashCommand {
    key: string;
    label: string;
    group: string;
    action: () => void;
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
            width: {
                default: null,
                parseHTML: (element) => element.getAttribute('width'),
                renderHTML: (attributes) => attributes.width ? { width: attributes.width } : {},
            },
            alt: {
                default: null,
                parseHTML: (element) => element.getAttribute('alt'),
                renderHTML: (attributes) => attributes.alt ? { alt: attributes.alt } : {},
            },
            title: {
                default: null,
                parseHTML: (element) => element.getAttribute('title'),
                renderHTML: (attributes) => attributes.title ? { title: attributes.title } : {},
            },
            'data-align': {
                default: 'left',
                parseHTML: (element) => element.getAttribute('data-align') || 'left',
                renderHTML: (attributes) => ({
                    'data-align': attributes['data-align'] || 'left',
                    style: imageAlignmentStyle(attributes['data-align']),
                }),
            },
        }
    },
})

const Subscript = Mark.create({
    name: 'subscript',
    excludes: 'superscript',
    parseHTML: () => [{ tag: 'sub' }],
    renderHTML: ({ HTMLAttributes }) => ['sub', mergeAttributes(HTMLAttributes), 0],
    addCommands() {
        return {
            toggleSubscript: () => ({ commands }: any) => commands.toggleMark(this.name),
        } as any
    },
})

const Superscript = Mark.create({
    name: 'superscript',
    excludes: 'subscript',
    parseHTML: () => [{ tag: 'sup' }],
    renderHTML: ({ HTMLAttributes }) => ['sup', mergeAttributes(HTMLAttributes), 0],
    addCommands() {
        return {
            toggleSuperscript: () => ({ commands }: any) => commands.toggleMark(this.name),
        } as any
    },
})

const FontTools = Extension.create({
    name: 'fontTools',
    addGlobalAttributes() {
        return [
            {
                types: ['textStyle'],
                attributes: {
                    fontFamily: {
                        default: null,
                        parseHTML: (element) => element.style.fontFamily?.replace(/['"]/g, ''),
                        renderHTML: (attributes) => attributes.fontFamily ? { style: `font-family: ${attributes.fontFamily};` } : {},
                    },
                    fontSize: {
                        default: null,
                        parseHTML: (element) => element.style.fontSize,
                        renderHTML: (attributes) => attributes.fontSize ? { style: `font-size: ${attributes.fontSize};` } : {},
                    },
                },
            },
            {
                types: ['paragraph', 'heading'],
                attributes: {
                    lineHeight: {
                        default: null,
                        parseHTML: (element) => element.style.lineHeight,
                        renderHTML: (attributes) => attributes.lineHeight ? { style: `line-height: ${attributes.lineHeight};` } : {},
                    },
                },
            },
        ]
    },
    addCommands() {
        return {
            setFontFamily: (fontFamily: string) => ({ chain }: any) => chain().setMark('textStyle', { fontFamily }).run(),
            setFontSize: (fontSize: string) => ({ chain }: any) => chain().setMark('textStyle', { fontSize }).run(),
            setLineHeight: (lineHeight: string) => ({ commands }: any) => commands.updateAttributes('paragraph', { lineHeight }) || commands.updateAttributes('heading', { lineHeight }),
        } as any
    },
})

const Details = Node.create({
    name: 'details',
    group: 'block',
    content: 'block+',
    defining: true,
    addAttributes() {
        return {
            summary: {
                default: 'Details',
                parseHTML: (element) => element.querySelector('summary')?.textContent || 'Details',
                renderHTML: () => ({}),
            },
        }
    },
    parseHTML: () => [{ tag: 'details' }],
    renderHTML: ({ node, HTMLAttributes }) => ['details', mergeAttributes(HTMLAttributes, { open: '' }), ['summary', node.attrs.summary || 'Details'], ['div', 0]],
    addCommands() {
        return {
            insertDetails: (summary = 'Details') => ({ commands }: any) => commands.insertContent({
                type: this.name,
                attrs: { summary },
                content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Hidden content' }] }],
            }),
        } as any
    },
})

const VideoEmbed = Node.create({
    name: 'videoEmbed',
    group: 'block',
    atom: true,
    addAttributes() {
        return {
            src: { default: null },
            title: { default: 'Embedded video' },
        }
    },
    parseHTML: () => [{ tag: 'iframe[data-video-embed]' }],
    renderHTML: ({ HTMLAttributes }) => ['iframe', mergeAttributes(HTMLAttributes, {
        'data-video-embed': 'true',
        class: 'rich-video-embed',
        frameborder: '0',
        allowfullscreen: 'true',
        allow: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture',
    })],
    addCommands() {
        return {
            setVideoEmbed: (attrs: { src: string; title?: string }) => ({ commands }: any) => commands.insertContent({ type: this.name, attrs }),
        } as any
    },
})

const FileAttachment = Node.create({
    name: 'fileAttachment',
    group: 'block',
    atom: true,
    addAttributes() {
        return {
            href: { default: null },
            name: { default: 'Download file' },
        }
    },
    parseHTML: () => [{ tag: 'a[data-file-attachment]' }],
    renderHTML: ({ HTMLAttributes }) => ['a', mergeAttributes(HTMLAttributes, {
        'data-file-attachment': 'true',
        class: 'rich-file-attachment',
        download: '',
    }), HTMLAttributes.name || 'Download file'],
    addCommands() {
        return {
            setFileAttachment: (attrs: { href: string; name: string }) => ({ commands }: any) => commands.insertContent({ type: this.name, attrs }),
        } as any
    },
})

const props = withDefaults(defineProps<{
    modelValue: string;
    variant?: 'full' | 'comment' | 'minimal';
    aiAssist?: boolean;
    aiAssistActions?: AiAssistAction[];
    aiAssistLoadingKey?: string | null;
    aiAssistLabel?: string;
    aiAssistLoadingLabel?: string;
    imageUploadUrl?: string | null;
    attachmentUploadUrl?: string | null;
}>(), {
    variant: 'full',
    aiAssist: false,
    aiAssistActions: () => [],
    aiAssistLoadingKey: null,
    aiAssistLabel: 'AI Assist',
    aiAssistLoadingLabel: 'Working...',
    imageUploadUrl: null,
    attachmentUploadUrl: null,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'ai-assist': [action: string];
}>();

const { t } = useTranslate();
const isSourceMode = ref(false);
const sourceContent = ref(props.modelValue);
const overflowOpen = ref(false);
const aiAssistOpen = ref(false);
const formatOpen = ref(false);
const linkModalOpen = ref(false);
const imageModalOpen = ref(false);
const linkUrl = ref('');
const linkTitle = ref('');
const linkTarget = ref<'_self' | '_blank'>('_self');
const linkColor = ref('#2563eb');
const imageUrl = ref('');
const uploadedImageData = ref('');
const imageWidth = ref('');
const imageAlignment = ref<ImageAlignment>('left');
const imageAlt = ref('');
const imageCaption = ref('');
const imageFileName = ref('');
const imageError = ref('');
const imageUploading = ref(false);
const imageEditing = ref(false);
const videoModalOpen = ref(false);
const videoUrl = ref('');
const videoError = ref('');
const attachmentModalOpen = ref(false);
const attachmentUrl = ref('');
const attachmentName = ref('');
const attachmentFileName = ref('');
const attachmentUploading = ref(false);
const attachmentError = ref('');
const exportOpen = ref(false);
const slashOpen = ref(false);
const slashQuery = ref('');
const slashIndex = ref(0);
const aiSidebarOpen = ref(false);
const selectedText = ref('');
const toolbarAiMaxHeight = ref(320);
const versionHistory = ref<Array<{ id: number; html: string; savedAt: Date; words: number }>>([]);
let autosaveTimer: number | undefined;
const linkTooltip = ref({
    visible: false,
    url: '',
    x: 0,
    y: 0,
});

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            link: false, // configure manually
            codeBlock: {},
            heading: {
                levels: [1, 2, 3, 4, 5, 6],
            },
        }),
        TiptapLink.configure({
            openOnClick: false,
            autolink: true,
            linkOnPaste: true,
            HTMLAttributes: {
                class: 'text-primary-600 underline cursor-pointer',
            },
        }),
        TextAlign.configure({
            types: ['heading', 'paragraph'],
            alignments: ['left', 'center', 'right', 'justify'],
        }),
        TextStyle,
        FontTools,
        Color,
        Highlight.configure({ multicolor: true }),
        Subscript,
        Superscript,
        TaskList,
        TaskItem.configure({
            nested: true,
        }),
        Table.configure({
            resizable: true,
        }),
        TableRow,
        TableHeader,
        TableCell,
        RichImage.configure({
            inline: true,
            allowBase64: true,
        }),
        VideoEmbed,
        FileAttachment,
        Details,
    ],
    editorProps: {
        handleKeyDown: (_view, event) => {
            if (slashOpen.value) {
                if (event.key === 'ArrowDown') {
                    event.preventDefault()
                    slashIndex.value = Math.min(slashIndex.value + 1, filteredSlashCommands.value.length - 1)
                    return true
                }

                if (event.key === 'ArrowUp') {
                    event.preventDefault()
                    slashIndex.value = Math.max(slashIndex.value - 1, 0)
                    return true
                }

                if (event.key === 'Enter') {
                    event.preventDefault()
                    runSlashCommand(filteredSlashCommands.value[slashIndex.value])
                    return true
                }

                if (event.key === 'Escape') {
                    event.preventDefault()
                    closeSlash()
                    return true
                }
            }

            if (event.key === '/' && props.variant === 'full') {
                slashOpen.value = true
                slashQuery.value = ''
                slashIndex.value = 0
                return false
            }

            if (event.key !== ' ' || !editor.value?.isActive('link')) {
                return false
            }

            const { state } = editor.value
            const { selection } = state

            if (!selection.empty) {
                return false
            }

            const linkMark = (state.storedMarks || selection.$from.marks())
                .find((mark) => mark.type.name === 'link')
            const nextHasSameLink = selection.$from.nodeAfter?.marks
                .some((mark) => mark.type.name === 'link' && mark.attrs.href === linkMark?.attrs.href)

            if (!linkMark || nextHasSameLink) {
                return false
            }

            event.preventDefault()
            editor.value.chain().focus().unsetMark('link').insertContent(' ').run()

            return true
        },
        handleClick: (view, pos, event) => {
            const target = event.target as HTMLElement | null
            const link = target?.closest('a') as HTMLAnchorElement | null
            const image = target?.closest('img') as HTMLImageElement | null

            if (link) {
                event.preventDefault()
                editor.value?.commands.setTextSelection(pos)
                openLinkModal(link)
                return true
            }

            if (image) {
                const position = view.posAtDOM(image, 0)
                editor.value?.commands.setNodeSelection(position)
                openImageModal(true)
                return true
            }

            return false
        },
        handleDrop: (_view, event) => {
            if (props.variant !== 'full') return false
            const file = event.dataTransfer?.files?.[0]
            if (!file?.type.startsWith('image/')) return false

            event.preventDefault()
            insertImageFile(file)
            return true
        },
        handlePaste: (_view, event) => {
            if (props.variant !== 'full') return false
            const file = Array.from(event.clipboardData?.files || []).find((item) => item.type.startsWith('image/'))
            if (!file) return false

            event.preventDefault()
            insertImageFile(file)
            return true
        },
        handleDOMEvents: {
            mouseover: (_view, event) => {
                const target = event.target as HTMLElement | null
                const link = target?.closest('a') as HTMLAnchorElement | null

                if (!link?.href) return false

                linkTooltip.value = {
                    visible: true,
                    url: link.getAttribute('href') || link.href,
                    x: event.clientX,
                    y: event.clientY,
                }

                return false
            },
            mousemove: (_view, event) => {
                if (!linkTooltip.value.visible) return false

                linkTooltip.value.x = event.clientX
                linkTooltip.value.y = event.clientY

                return false
            },
            mouseout: (_view, event) => {
                const target = event.target as HTMLElement | null

                if (target?.closest('a')) {
                    linkTooltip.value.visible = false
                }

                return false
            },
        },
    },
    onUpdate: ({ editor }) => {
        const html = editor.getHTML();
        emit('update:modelValue', html);
        sourceContent.value = html;
        if (slashOpen.value) {
            updateSlashQuery()
        }
    },
    onSelectionUpdate: ({ editor }) => {
        const { from, to } = editor.state.selection;
        selectedText.value = editor.state.doc.textBetween(from, to, '\n').trim();
    },
});

watch(() => props.modelValue, (value) => {
    if (isSourceMode.value) return;
    if (editor.value?.getHTML() === value) return;
    editor.value?.commands.setContent(value, { emitUpdate: false });
});

const toggleMode = () => {
    if (isSourceMode.value) {
        editor.value?.commands.setContent(sourceContent.value, { emitUpdate: false });
    } else {
        sourceContent.value = editor.value?.getHTML() || '';
    }
    isSourceMode.value = !isSourceMode.value;
    formatOpen.value = false;
    overflowOpen.value = false;
    aiAssistOpen.value = false;
};

const handleSourceInput = (e: Event) => {
    const target = e.target as HTMLTextAreaElement;
    sourceContent.value = target.value;
    emit('update:modelValue', target.value);
};

const textStats = computed(() => {
    const text = editor.value?.state.doc.textBetween(0, editor.value.state.doc.content.size, ' ') || ''
    const words = text.trim() ? text.trim().split(/\s+/).length : 0

    return {
        words,
        characters: text.length,
        readingMinutes: Math.max(1, Math.ceil(words / 225)),
    }
})

const saveVersionSnapshot = () => {
    const html = editor.value?.getHTML() || sourceContent.value || ''
    if (!html.trim()) return

    const latest = versionHistory.value[0]
    if (latest?.html === html) return

    versionHistory.value = [
        { id: Date.now(), html, savedAt: new Date(), words: textStats.value.words },
        ...versionHistory.value,
    ].slice(0, 20)
}

const restoreVersion = (html: string) => {
    editor.value?.commands.setContent(html)
    sourceContent.value = html
}

const getSelectedText = () => {
    if (!editor.value) return '';

    const { from, to } = editor.value.state.selection;

    return editor.value.state.doc.textBetween(from, to, '\n').trim();
};

const replaceSelection = (html: string) => {
    editor.value?.chain().focus().insertContent(html).run();
};

const insertAtCursor = (html: string) => {
    editor.value?.chain().focus().insertContent(html).run();
};

defineExpose({
    getSelectedText,
    replaceSelection,
    insertAtCursor,
});

const extractColor = (style: string | undefined) => {
    return style?.match(/color:\s*([^;]+)/i)?.[1]?.trim() || '#2563eb'
}

// Actions
const openLinkModal = (link?: HTMLAnchorElement) => {
    const attrs = editor.value?.getAttributes('link') || {}
    linkUrl.value = link?.getAttribute('href') || attrs.href || ''
    linkTitle.value = link?.getAttribute('title') || attrs.title || ''
    linkTarget.value = (link?.getAttribute('target') || attrs.target) === '_blank' ? '_blank' : '_self'
    linkColor.value = extractColor(link?.getAttribute('style') || attrs.style)
    linkModalOpen.value = true
    overflowOpen.value = false
    aiAssistOpen.value = false
    linkTooltip.value.visible = false
}

const closeLinkModal = () => {
    linkModalOpen.value = false
}

const applyLink = () => {
    const url = linkUrl.value.trim()

    if (!url) {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run()
        closeLinkModal()
        return
    }

    editor.value?.chain().focus().extendMarkRange('link').setLink({
        href: url,
        title: linkTitle.value.trim() || undefined,
        target: linkTarget.value === '_blank' ? '_blank' : undefined,
        rel: linkTarget.value === '_blank' ? 'noopener noreferrer' : undefined,
        style: `color: ${linkColor.value};`,
    } as any).run()
    closeLinkModal()
}

const removeLink = () => {
    editor.value?.chain().focus().extendMarkRange('link').unsetLink().run()
    closeLinkModal()
}

const openImageModal = (editing = false) => {
    const attrs = editor.value?.getAttributes('image') || {}

    imageEditing.value = editing
    imageUrl.value = editing ? (attrs.src || '') : ''
    uploadedImageData.value = ''
    imageWidth.value = editing ? String(attrs.width || '') : ''
    imageAlignment.value = ['left', 'center', 'right', 'float-left', 'float-right'].includes(attrs['data-align']) ? attrs['data-align'] : 'left'
    imageAlt.value = editing ? String(attrs.alt || '') : ''
    imageCaption.value = ''
    imageFileName.value = ''
    imageError.value = ''
    imageModalOpen.value = true
    overflowOpen.value = false
    aiAssistOpen.value = false
}

const closeImageModal = () => {
    imageModalOpen.value = false
}

const imageAttributes = (src: string) => {
    const width = imageWidth.value.trim()

    return {
        src,
        width: width ? width.replace(/[^0-9.%pxrem]/gi, '') : undefined,
        'data-align': imageAlignment.value,
        alt: imageAlt.value.trim() || undefined,
        title: imageCaption.value.trim() || undefined,
    }
}

const insertImage = (src: string) => {
    const attrs = imageAttributes(src)

    if (imageEditing.value && editor.value?.isActive('image')) {
        editor.value?.chain().focus().updateAttributes('image', attrs).run()
    } else {
        editor.value?.chain().focus().setImage(attrs as any).run()
    }

    closeImageModal()
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

    const body = new FormData()
    body.append('file', file)

    const response = await fetch(uploadUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body,
    })
    const payload = await response.json().catch(() => ({}))

    if (!response.ok || !payload.url) {
        throw new Error(payload.message || t('Upload failed.'))
    }

    return String(payload.url)
}

const insertImageFile = async (file: File) => {
    if (!file.type.startsWith('image/')) {
        imageError.value = t('Choose a valid image file.')
        return
    }

    imageUploading.value = true
    imageError.value = ''

    try {
        const src = await uploadFile(file, props.imageUploadUrl)
        insertImage(src)
    } catch (error) {
        imageError.value = error instanceof Error ? error.message : t('Image upload failed.')
    } finally {
        imageUploading.value = false
    }
}

const removeImage = () => {
    if (editor.value?.isActive('image')) {
        editor.value.chain().focus().deleteSelection().run()
    }

    closeImageModal()
}

const applyImageUrl = () => {
    const url = uploadedImageData.value || imageUrl.value.trim()

    if (!url) {
        imageError.value = t('Add an image URL or upload an image.')
        return
    }

    if (uploadedImageData.value) {
        insertImage(uploadedImageData.value)
        return
    }

    insertImage(url)
}

const handleImageUpload = (event: Event) => {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]

    if (!file) return

    if (!file.type.startsWith('image/')) {
        imageError.value = t('Choose a valid image file.')
        return
    }

    imageFileName.value = file.name
    uploadedImageData.value = ''
    imageError.value = ''
    imageUploading.value = true

    uploadFile(file, props.imageUploadUrl)
        .then((url) => {
            uploadedImageData.value = url
        })
        .catch((error) => {
            imageError.value = error instanceof Error ? error.message : t('Image upload failed.')
        })
        .finally(() => {
            imageUploading.value = false
        })
}

const activeFormatLabel = computed(() => {
    if (editor.value?.isActive('heading', { level: 1 })) return 'H1'
    if (editor.value?.isActive('heading', { level: 2 })) return 'H2'
    if (editor.value?.isActive('heading', { level: 3 })) return 'H3'
    if (editor.value?.isActive('heading', { level: 4 })) return 'H4'
    if (editor.value?.isActive('heading', { level: 5 })) return 'H5'
    if (editor.value?.isActive('heading', { level: 6 })) return 'H6'
    if (editor.value?.isActive('code')) return 'Perforated'

    return 'Paragraph'
})

const isHeadingActive = computed(() => {
    return [1, 2, 3, 4, 5, 6].some((level) => editor.value?.isActive('heading', { level }))
})

const setParagraph = () => {
    editor.value?.chain().focus().setParagraph().run()
    formatOpen.value = false
}

const setHeading = (level: 1 | 2 | 3 | 4 | 5 | 6) => {
    editor.value?.chain().focus().toggleHeading({ level }).run()
    formatOpen.value = false
}

const toggleCodeText = () => {
    if (isHeadingActive.value) return

    editor.value?.chain().focus().toggleCode().run()
    formatOpen.value = false
}

const toggleSubscript = () => {
    ;(editor.value?.chain().focus() as any).toggleSubscript().run()
}

const toggleSuperscript = () => {
    ;(editor.value?.chain().focus() as any).toggleSuperscript().run()
}

const insertDetails = () => {
    ;(editor.value?.chain().focus() as any).insertDetails(t('Details')).run()
}

const setFontFamily = (fontFamily: string) => {
    ;(editor.value?.chain().focus() as any).setFontFamily(fontFamily).run()
}

const setFontSize = (fontSize: string) => {
    ;(editor.value?.chain().focus() as any).setFontSize(fontSize).run()
}

const setLineHeight = (lineHeight: string) => {
    ;(editor.value?.chain().focus() as any).setLineHeight(lineHeight).run()
}

const clearFormatting = () => {
    editor.value?.chain().focus().unsetAllMarks().clearNodes().run()
}

const normalizeVideoUrl = (url: string) => {
    const trimmed = url.trim()
    const youtube = trimmed.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]+)/)
    const vimeo = trimmed.match(/vimeo\.com\/(\d+)/)

    if (youtube?.[1]) return `https://www.youtube.com/embed/${youtube[1]}`
    if (vimeo?.[1]) return `https://player.vimeo.com/video/${vimeo[1]}`

    return trimmed
}

const insertVideo = () => {
    const src = normalizeVideoUrl(videoUrl.value)

    if (!/^https?:\/\/.+/i.test(src)) {
        videoError.value = t('Add a valid YouTube, Vimeo, or iframe URL.')
        return
    }

    ;(editor.value?.chain().focus() as any).setVideoEmbed({ src, title: t('Embedded video') }).run()
    videoUrl.value = ''
    videoError.value = ''
    videoModalOpen.value = false
}

const handleAttachmentUpload = async (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0]
    if (!file) return

    attachmentUploading.value = true
    attachmentError.value = ''
    attachmentFileName.value = file.name
    attachmentName.value = attachmentName.value || file.name

    try {
        attachmentUrl.value = await uploadFile(file, props.attachmentUploadUrl)
    } catch (error) {
        attachmentError.value = error instanceof Error ? error.message : t('File upload failed.')
    } finally {
        attachmentUploading.value = false
    }
}

const insertAttachment = () => {
    const href = attachmentUrl.value.trim()
    const name = attachmentName.value.trim() || t('Download file')

    if (!href) {
        attachmentError.value = t('Upload a file or paste a file URL.')
        return
    }

    ;(editor.value?.chain().focus() as any).setFileAttachment({ href, name }).run()
    attachmentModalOpen.value = false
    attachmentUrl.value = ''
    attachmentName.value = ''
    attachmentFileName.value = ''
    attachmentError.value = ''
}

const htmlToPlainText = (html: string) => {
    const element = document.createElement('div')
    element.innerHTML = html
    return element.textContent || ''
}

const htmlToMarkdown = (html: string) => html
    .replace(/<h1[^>]*>(.*?)<\/h1>/gis, '# $1\n\n')
    .replace(/<h2[^>]*>(.*?)<\/h2>/gis, '## $1\n\n')
    .replace(/<h3[^>]*>(.*?)<\/h3>/gis, '### $1\n\n')
    .replace(/<strong[^>]*>(.*?)<\/strong>/gis, '**$1**')
    .replace(/<b[^>]*>(.*?)<\/b>/gis, '**$1**')
    .replace(/<em[^>]*>(.*?)<\/em>/gis, '*$1*')
    .replace(/<i[^>]*>(.*?)<\/i>/gis, '*$1*')
    .replace(/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/gis, '[$2]($1)')
    .replace(/<li[^>]*>(.*?)<\/li>/gis, '- $1\n')
    .replace(/<br\s*\/?>/gis, '\n')
    .replace(/<\/p>/gis, '\n\n')
    .replace(/<[^>]+>/g, '')
    .replace(/\n{3,}/g, '\n\n')
    .trim()

const downloadTextFile = (filename: string, content: string, type = 'text/plain') => {
    const blob = new Blob([content], { type })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    link.click()
    URL.revokeObjectURL(url)
}

const exportContent = async (format: ExportFormat) => {
    const html = editor.value?.getHTML() || sourceContent.value || ''
    exportOpen.value = false

    if (format === 'html') downloadTextFile('content.html', html, 'text/html')
    if (format === 'text') downloadTextFile('content.txt', htmlToPlainText(html))
    if (format === 'markdown') downloadTextFile('content.md', htmlToMarkdown(html), 'text/markdown')
    if (format === 'doc') downloadTextFile('content.doc', `<html><body>${html}</body></html>`, 'application/msword')
    if (format === 'pdf') {
        const win = window.open('', '_blank')
        if (!win) return
        win.document.write(`<html><head><title>${t('Export')}</title></head><body>${html}</body></html>`)
        win.document.close()
        win.print()
    }
}

const copyContent = async (format: 'html' | 'markdown') => {
    const html = editor.value?.getHTML() || sourceContent.value || ''
    await navigator.clipboard.writeText(format === 'html' ? html : htmlToMarkdown(html))
    exportOpen.value = false
}

const runAiAssist = (action: AiAssistAction) => {
    emit('ai-assist', action.key);
    aiAssistOpen.value = false;
    aiSidebarOpen.value = false;
}

const updateToolbarAiMaxHeight = () => {
    const editorHeight = editor.value?.view.dom.getBoundingClientRect().height ?? 320;
    toolbarAiMaxHeight.value = Math.floor(Math.max(180, Math.min(360, editorHeight - 16)));
}

const toggleAiAssistMenu = () => {
    updateToolbarAiMaxHeight();
    aiAssistOpen.value = !aiAssistOpen.value;
}

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

const documentAiActions = computed<AiAssistAction[]>(() => props.aiAssistActions.length
    ? props.aiAssistActions
    : [
        { key: 'summarize_document', label: t('Summarize'), description: t('Summarize the full document.') },
        { key: 'generate_title', label: t('Generate title'), description: t('Create a title from this content.') },
        { key: 'generate_meta_description', label: t('Generate meta description'), description: t('Create SEO description.') },
        { key: 'continue_writing', label: t('Continue writing'), description: t('Continue from the cursor.') },
    ])

const defaultAiActions = computed<AiAssistAction[]>(() => selectedText.value ? selectedAiActions.value : documentAiActions.value)

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
    { key: 'table', label: t('Table'), group: t('Advanced'), action: () => editor.value?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run() },
    { key: 'quote', label: t('Blockquote'), group: t('Text'), action: () => editor.value?.chain().focus().toggleBlockquote().run() },
    { key: 'code', label: t('Code block'), group: t('Code'), action: () => editor.value?.chain().focus().toggleCodeBlock().run() },
    { key: 'hr', label: t('Horizontal rule'), group: t('Advanced'), action: () => editor.value?.chain().focus().setHorizontalRule().run() },
    { key: 'details', label: t('Details'), group: t('Advanced'), action: () => (editor.value?.chain().focus() as any).insertDetails(t('Details')).run() },
])

const filteredSlashCommands = computed(() => {
    const query = slashQuery.value.toLowerCase()

    return slashCommands.value.filter((command) => `${command.label} ${command.group}`.toLowerCase().includes(query)).slice(0, 10)
})

const updateSlashQuery = () => {
    const text = editor.value?.state.doc.textBetween(Math.max(0, editor.value.state.selection.from - 40), editor.value.state.selection.from, '\n') || ''
    const match = text.match(/\/([\w\s-]*)$/)
    slashQuery.value = match?.[1] || ''
    slashIndex.value = 0
}

const closeSlash = () => {
    slashOpen.value = false
    slashQuery.value = ''
    slashIndex.value = 0
}

const runSlashCommand = (command?: SlashCommand) => {
    if (!command) return
    editor.value?.commands.deleteRange({ from: Math.max(0, editor.value.state.selection.from - slashQuery.value.length - 1), to: editor.value.state.selection.from })
    command.action()
    closeSlash()
}

const closeOverflow = (event: MouseEvent) => {
    const target = event.target as HTMLElement | null
    if (!target?.closest('[data-rich-editor-format]')) {
        formatOpen.value = false
    }
    if (!target?.closest('[data-rich-editor-overflow]')) {
        overflowOpen.value = false
    }
    if (!target?.closest('[data-rich-editor-ai-assist]')) {
        aiAssistOpen.value = false
    }
    if (!target?.closest('[data-rich-editor-export]')) {
        exportOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', closeOverflow)
    window.addEventListener('resize', updateToolbarAiMaxHeight)
    saveVersionSnapshot()
    autosaveTimer = window.setInterval(saveVersionSnapshot, 30000)
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeOverflow)
    window.removeEventListener('resize', updateToolbarAiMaxHeight)
    if (autosaveTimer) window.clearInterval(autosaveTimer)
    editor.value?.destroy();
});
</script>

<template>
    <div class="border border-gray-100 dark:border-surface-800 rounded-2xl overflow-hidden focus-within:ring-2 focus-within:ring-primary-500/20 transition-all bg-white dark:bg-surface-900">
        <!-- Toolbar -->
        <div v-if="editor && !isSourceMode" class="relative flex flex-nowrap items-center gap-1.5 p-2 bg-gray-50 dark:bg-surface-800/50 border-b border-gray-100 dark:border-surface-800">
            <div v-if="variant === 'full'" data-rich-editor-format class="relative shrink-0">
                <button type="button" @click.stop="formatOpen = !formatOpen" class="inline-flex h-8 min-w-24 items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-3 text-xs font-bold text-gray-700 transition-colors hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700" title="Format">
                    <span>{{ activeFormatLabel }}</span>
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                </button>
                <div v-if="formatOpen" class="absolute start-0 top-10 z-40 w-48 rounded-xl border border-gray-200 bg-white p-2 shadow-lg dark:border-surface-700 dark:bg-surface-900">
                    <button type="button" @click="setHeading(1)" :class="{ 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300': editor.isActive('heading', { level: 1 }) }" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-start text-sm font-medium text-gray-700 transition-colors hover:bg-primary-50 dark:text-gray-300 dark:hover:bg-surface-800">
                        <span>Heading 1</span>
                        <span class="text-xs font-black">H1</span>
                    </button>
                    <button type="button" @click="setParagraph" :class="{ 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300': editor.isActive('paragraph') }" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-start text-sm font-medium text-gray-700 transition-colors hover:bg-primary-50 dark:text-gray-300 dark:hover:bg-surface-800">
                        <span>Paragraph</span>
                        <span class="text-xs font-black">P</span>
                    </button>
                    <button type="button" @click="setHeading(2)" :class="{ 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300': editor.isActive('heading', { level: 2 }) }" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-start text-sm font-medium text-gray-700 transition-colors hover:bg-primary-50 dark:text-gray-300 dark:hover:bg-surface-800">
                        <span>Heading 2</span>
                        <span class="text-xs font-black">H2</span>
                    </button>
                    <button type="button" @click="setHeading(3)" :class="{ 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300': editor.isActive('heading', { level: 3 }) }" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-start text-sm font-medium text-gray-700 transition-colors hover:bg-primary-50 dark:text-gray-300 dark:hover:bg-surface-800">
                        <span>Heading 3</span>
                        <span class="text-xs font-black">H3</span>
                    </button>
                    <button type="button" @click="setHeading(4)" :class="{ 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300': editor.isActive('heading', { level: 4 }) }" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-start text-sm font-medium text-gray-700 transition-colors hover:bg-primary-50 dark:text-gray-300 dark:hover:bg-surface-800">
                        <span>Heading 4</span>
                        <span class="text-xs font-black">H4</span>
                    </button>
                    <button type="button" @click="setHeading(5)" :class="{ 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300': editor.isActive('heading', { level: 5 }) }" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-start text-sm font-medium text-gray-700 transition-colors hover:bg-primary-50 dark:text-gray-300 dark:hover:bg-surface-800">
                        <span>Heading 5</span>
                        <span class="text-xs font-black">H5</span>
                    </button>
                    <button type="button" @click="setHeading(6)" :class="{ 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300': editor.isActive('heading', { level: 6 }) }" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-start text-sm font-medium text-gray-700 transition-colors hover:bg-primary-50 dark:text-gray-300 dark:hover:bg-surface-800">
                        <span>Heading 6</span>
                        <span class="text-xs font-black">H6</span>
                    </button>
                    <div class="my-2 h-px bg-gray-100 dark:bg-surface-800"></div>
                    <button type="button" @click="toggleCodeText" :disabled="isHeadingActive" :class="{ 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300': editor.isActive('code') }" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-start text-sm font-medium text-gray-700 transition-colors hover:bg-primary-50 disabled:cursor-not-allowed disabled:opacity-40 dark:text-gray-300 dark:hover:bg-surface-800">
                        <span>Perforated</span>
                        <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs dark:bg-surface-800 italic">p</code>
                    </button>
                </div>
            </div>
            <button type="button" @click="editor.chain().focus().toggleBold().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('bold') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Bold">
                <span class="font-bold">B</span>
            </button>
            <button type="button" @click="editor.chain().focus().toggleItalic().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('italic') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Italic">
                <span class="font-bold italic">I</span>
            </button>
            <button v-if="variant !== 'minimal'" type="button" @click="editor.chain().focus().toggleUnderline().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('underline') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors font-serif font-bold text-sm leading-none flex items-center justify-center w-8" title="Underline">
                <span class="underline underline-offset-2">U</span>
            </button>

            <template v-if="variant === 'full'">
                <button type="button" @click="toggleSubscript" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('subscript') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors text-xs font-bold" title="Subscript">
                    X<sub>2</sub>
                </button>
                <button type="button" @click="toggleSuperscript" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('superscript') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors text-xs font-bold" title="Superscript">
                    X<sup>2</sup>
                </button>
                <label class="relative flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Text Color">
                    <span class="font-serif text-xs font-bold" :style="{ color: editor?.getAttributes('textStyle').color || 'currentColor' }">A</span>
                    <input type="color" @input="(e) => editor?.chain().focus().setColor((e.target as HTMLInputElement).value).run()" :value="editor?.getAttributes('textStyle').color || '#000000'" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                </label>
                <label class="relative flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Highlight">
                    <span class="rounded bg-yellow-200 px-1 font-serif text-xs font-bold text-black">H</span>
                    <input type="color" @input="(e) => editor?.chain().focus().toggleHighlight({ color: (e.target as HTMLInputElement).value }).run()" :value="editor?.getAttributes('highlight').color || '#ffff00'" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                </label>
                <button type="button" @click="clearFormatting" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors text-xs font-bold" :title="t('Clear formatting')">Tx</button>
            </template>

            <template v-if="variant !== 'minimal'">
                <div class="w-px h-5 bg-gray-200 dark:bg-surface-700 mx-1"></div>
                <button type="button" @click="editor.chain().focus().toggleBulletList().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('bulletList') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Bullet List">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                </button>
                <button type="button" @click="editor.chain().focus().toggleOrderedList().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('orderedList') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Numbered List">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75v.008m0 5.242v.008m0 5.242v.008" /></svg>
                </button>
            </template>

            <div class="w-px h-5 bg-gray-200 dark:bg-surface-700 mx-1"></div>
            <button type="button" @click="openLinkModal()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('link') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" :title="t('Link')">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>
            </button>

            <template v-if="variant === 'full'">
                <button type="button" @click="openImageModal()" class="hidden sm:flex h-8 w-8 items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" :title="t('Add image')">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 19.5h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5Z" /></svg>
                </button>
            </template>

            <!-- Always visible mode toggle & history -->
            <div class="flex-1"></div>
            <div class="flex shrink-0 items-center gap-1 border-l border-gray-200 dark:border-surface-700 pl-2">
                <button type="button" @click="editor.chain().focus().undo().run()" :disabled="!editor.can().undo()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors disabled:opacity-30 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                </button>
                <button type="button" @click="editor.chain().focus().redo().run()" :disabled="!editor.can().redo()" class="hidden sm:inline-flex p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors disabled:opacity-30 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l6-6m0 0l-6-6m6 6H9a6 6 0 000 12h3" /></svg>
                </button>
                <div v-if="aiAssist && defaultAiActions.length" data-rich-editor-ai-assist class="relative">
                    <button type="button" @click.stop="toggleAiAssistMenu" :disabled="Boolean(aiAssistLoadingKey)" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-violet-200 bg-violet-50 px-3 text-xs font-bold text-violet-700 transition-colors hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-violet-900/40 dark:bg-violet-900/20 dark:text-violet-300 dark:hover:bg-violet-900/30" :title="aiAssistLabel">
                        {{ aiAssistLoadingKey ? aiAssistLoadingLabel : '✨AI' }}
                    </button>
                    <div v-if="aiAssistOpen" class="absolute end-0 top-10 z-40 w-64 overflow-y-auto overscroll-contain rounded-xl border border-gray-200 bg-white p-2 shadow-lg dark:border-surface-700 dark:bg-surface-900" :style="{ maxHeight: `${toolbarAiMaxHeight}px` }">
                        <button v-for="action in defaultAiActions" :key="action.key" type="button" @click="runAiAssist(action)" :disabled="Boolean(aiAssistLoadingKey)" class="flex w-full flex-col rounded-lg px-3 py-2 text-start text-sm transition-colors hover:bg-violet-50 disabled:cursor-not-allowed disabled:opacity-60 dark:hover:bg-violet-900/20">
                            <span class="font-medium text-gray-800 dark:text-gray-100">{{ aiAssistLoadingKey === action.key ? aiAssistLoadingLabel : action.label }}</span>
                            <span v-if="action.description" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ action.description }}</span>
                        </button>
                        <button type="button" @click="aiSidebarOpen = true; aiAssistOpen = false" class="mt-2 w-full rounded-lg border border-violet-100 bg-violet-50 px-3 py-2 text-xs font-bold text-violet-700 hover:bg-violet-100 dark:border-violet-900/30 dark:bg-violet-900/20 dark:text-violet-300">{{ t('Open AI sidebar') }}</button>
                    </div>
                </div>
                <div v-if="variant === 'full'" data-rich-editor-export class="relative">
                    <button type="button" @click.stop="exportOpen = !exportOpen" class="inline-flex h-8 items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 text-xs font-bold text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300" :title="t('Export')">
                        {{ t('Export') }}
                    </button>
                    <div v-if="exportOpen" class="absolute end-0 top-10 z-40 w-52 rounded-xl border border-gray-200 bg-white p-2 shadow-lg dark:border-surface-700 dark:bg-surface-900">
                        <button type="button" @click="exportContent('html')" class="w-full rounded-lg px-3 py-2 text-start text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200">{{ t('Download HTML') }}</button>
                        <button type="button" @click="exportContent('markdown')" class="w-full rounded-lg px-3 py-2 text-start text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200">{{ t('Download Markdown') }}</button>
                        <button type="button" @click="exportContent('text')" class="w-full rounded-lg px-3 py-2 text-start text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200">{{ t('Download Plain Text') }}</button>
                        <button type="button" @click="exportContent('doc')" class="w-full rounded-lg px-3 py-2 text-start text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200">{{ t('Download DOC') }}</button>
                        <button type="button" @click="exportContent('pdf')" class="w-full rounded-lg px-3 py-2 text-start text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200">{{ t('Print PDF') }}</button>
                        <div class="my-2 h-px bg-gray-100 dark:bg-surface-800"></div>
                        <button type="button" @click="copyContent('html')" class="w-full rounded-lg px-3 py-2 text-start text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200">{{ t('Copy HTML') }}</button>
                        <button type="button" @click="copyContent('markdown')" class="w-full rounded-lg px-3 py-2 text-start text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200">{{ t('Copy Markdown') }}</button>
                    </div>
                </div>
                <button v-if="variant === 'full'" type="button" data-rich-editor-overflow @click.stop="overflowOpen = !overflowOpen" class="p-2 rounded-lg bg-white dark:bg-surface-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-surface-700 hover:bg-primary-50 dark:hover:bg-surface-700 transition-colors" title="More tools">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8a2 2 0 110-4 2 2 0 010 4Zm0 6a2 2 0 110-4 2 2 0 010 4Zm0 6a2 2 0 110-4 2 2 0 010 4Z" /></svg>
                </button>
            </div>

            <div v-if="variant === 'full' && overflowOpen" data-rich-editor-overflow class="absolute end-2 top-12 z-30 w-72 rounded-xl border border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-900 p-3 shadow-lg">
                <div class="grid grid-cols-3 gap-2 border-b border-gray-100 pb-3 dark:border-surface-800">
                    <select @change="setFontFamily(($event.target as HTMLSelectElement).value)" class="rounded-lg border border-gray-200 bg-gray-50 px-2 py-2 text-xs dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200">
                        <option value="Inter">Inter</option>
                        <option value="Roboto">Roboto</option>
                        <option value="Georgia">Georgia</option>
                        <option value="'Courier New'">Courier New</option>
                        <option value="'Plus Jakarta Sans'">Plus Jakarta</option>
                    </select>
                    <select @change="setFontSize(($event.target as HTMLSelectElement).value)" class="rounded-lg border border-gray-200 bg-gray-50 px-2 py-2 text-xs dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200">
                        <option v-for="size in [12,14,16,18,20,24,30,36,48,60,72]" :key="size" :value="`${size}px`">{{ size }}px</option>
                    </select>
                    <select @change="setLineHeight(($event.target as HTMLSelectElement).value)" class="rounded-lg border border-gray-200 bg-gray-50 px-2 py-2 text-xs dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200">
                        <option value="1.2">1.2</option>
                        <option value="1.5">1.5</option>
                        <option value="1.75">1.75</option>
                        <option value="2">2</option>
                    </select>
                </div>
                <div class="mt-3 grid grid-cols-4 gap-2">
                    <button type="button" @click="editor.chain().focus().toggleStrike().run(); overflowOpen = false" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('strike') }" class="h-9 rounded-lg text-sm font-bold text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700" title="Strikethrough"><span class="line-through">S</span></button>
                    <button type="button" @click="editor.chain().focus().setTextAlign('left').run(); overflowOpen = false" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive({ textAlign: 'left' }) }" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700" title="Align Left">
                        <svg class="mx-auto w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h11.25m-11.25 5.25h16.5" /></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().setTextAlign('center').run(); overflowOpen = false" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive({ textAlign: 'center' }) }" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700" title="Align Center">
                        <svg class="mx-auto w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M6.75 12h10.5m-13.5 5.25h16.5" /></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().setTextAlign('right').run(); overflowOpen = false" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive({ textAlign: 'right' }) }" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700" title="Align Right">
                        <svg class="mx-auto w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M9 12h11.25M3.75 17.25h16.5" /></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().setTextAlign('justify').run(); overflowOpen = false" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive({ textAlign: 'justify' }) }" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700" title="Justify">
                        <svg class="mx-auto w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().toggleCodeBlock().run(); overflowOpen = false" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('codeBlock') }" class="h-9 rounded-lg font-mono text-xs font-bold text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700">&lt;/&gt;</button>

                    <button type="button" @click="editor.chain().focus().toggleTaskList().run(); overflowOpen = false" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('taskList') }" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700" title="Task List">
                        <svg class="mx-auto w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(); overflowOpen = false" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700" title="Insert Table">
                        <svg class="mx-auto w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 5.25h17.25m-17.25 6.75h17.25m-17.25 6.75h17.25M8.25 5.25v13.5m7.5-13.5v13.5" /></svg>
                    </button>
                    <button type="button" @click="openImageModal(); overflowOpen = false" class="sm:hidden h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700" :title="t('Add image')">
                        <svg class="mx-auto w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 19.5h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5Z" /></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().toggleBlockquote().run(); overflowOpen = false" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('blockquote') }" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700" title="Blockquote">
                        <span class="text-base font-black">“”</span>
                    </button>
                    <button type="button" @click="editor.chain().focus().setHorizontalRule().run(); overflowOpen = false" class="h-9 rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700" title="Horizontal Rule">HR</button>
                    <button type="button" @click="videoModalOpen = true; overflowOpen = false" class="h-9 rounded-lg text-xs font-bold text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700" title="Video">Vid</button>
                    <button type="button" @click="attachmentModalOpen = true; overflowOpen = false" class="h-9 rounded-lg text-xs font-bold text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700" title="Attachment">File</button>
                    <button type="button" @click="insertDetails(); overflowOpen = false" class="h-9 rounded-lg text-xs font-bold text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700" title="Details">Det</button>
                </div>
                <div class="mt-3 grid grid-cols-1 gap-2 border-t border-gray-100 dark:border-surface-800 pt-3">
                    <button type="button" @click="toggleMode" class="col-span-2 rounded-lg border border-primary-100 dark:border-primary-900/30 bg-primary-50 dark:bg-primary-900/20 px-3 py-2 text-xs font-bold text-primary-700 dark:text-primary-400 hover:bg-primary-100 dark:hover:bg-primary-900/30">
                        Source
                    </button>
                </div>
            </div>
        </div>

        <!-- Table Context Menu -->
        <div v-if="editor && editor.isActive('table') && !isSourceMode" class="flex flex-nowrap items-center gap-1.5 overflow-x-auto p-1.5 bg-indigo-50 dark:bg-indigo-900/20 border-b border-indigo-100 dark:border-indigo-900/30">
            <span class="text-[10px] font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest px-2">Table:</span>
            <button type="button" @click="editor.chain().focus().addColumnBefore().run()" class="px-2 py-1 bg-white dark:bg-surface-800 border border-indigo-200 dark:border-indigo-900/50 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/40 text-xs text-indigo-700 dark:text-indigo-300 transition-colors">Add Col Before</button>
            <button type="button" @click="editor.chain().focus().addColumnAfter().run()" class="px-2 py-1 bg-white dark:bg-surface-800 border border-indigo-200 dark:border-indigo-900/50 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/40 text-xs text-indigo-700 dark:text-indigo-300 transition-colors">Add Col After</button>
            <button type="button" @click="editor.chain().focus().deleteColumn().run()" class="px-2 py-1 bg-white dark:bg-surface-800 border border-red-200 dark:border-red-900/50 rounded hover:bg-red-50 dark:hover:bg-red-900/40 text-xs text-red-700 dark:text-red-400 transition-colors">Del Col</button>
            <div class="w-px h-4 bg-indigo-200 dark:bg-indigo-800 mx-1"></div>
            <button type="button" @click="editor.chain().focus().addRowBefore().run()" class="px-2 py-1 bg-white dark:bg-surface-800 border border-indigo-200 dark:border-indigo-900/50 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/40 text-xs text-indigo-700 dark:text-indigo-300 transition-colors">Add Row Before</button>
            <button type="button" @click="editor.chain().focus().addRowAfter().run()" class="px-2 py-1 bg-white dark:bg-surface-800 border border-indigo-200 dark:border-indigo-900/50 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/40 text-xs text-indigo-700 dark:text-indigo-300 transition-colors">Add Row After</button>
            <button type="button" @click="editor.chain().focus().deleteRow().run()" class="px-2 py-1 bg-white dark:bg-surface-800 border border-red-200 dark:border-red-900/50 rounded hover:bg-red-50 dark:hover:bg-red-900/40 text-xs text-red-700 dark:text-red-400 transition-colors">Del Row</button>
            <div class="w-px h-4 bg-indigo-200 dark:bg-indigo-800 mx-1"></div>
            <button type="button" @click="editor.chain().focus().mergeCells().run()" class="px-2 py-1 bg-white dark:bg-surface-800 border border-indigo-200 dark:border-indigo-900/50 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/40 text-xs text-indigo-700 dark:text-indigo-300 transition-colors">Merge</button>
            <button type="button" @click="editor.chain().focus().splitCell().run()" class="px-2 py-1 bg-white dark:bg-surface-800 border border-indigo-200 dark:border-indigo-900/50 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/40 text-xs text-indigo-700 dark:text-indigo-300 transition-colors">Split</button>
            <div class="w-px h-4 bg-indigo-200 dark:bg-indigo-800 mx-1"></div>
            <button type="button" @click="editor.chain().focus().deleteTable().run()" class="px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900/50 rounded hover:bg-red-100 dark:hover:bg-red-900/40 text-xs font-bold text-red-700 dark:text-red-400 transition-colors">Delete Table</button>
        </div>

        <!-- Source Mode Header -->
        <div v-if="isSourceMode" class="flex items-center justify-between px-4 py-2 bg-gray-50 dark:bg-surface-800/50 border-b border-gray-100 dark:border-surface-800">
            <div class="text-[10px] font-black text-amber-600 uppercase tracking-widest px-2">Source Mode</div>
            <button type="button" @click="toggleMode" class="px-3 py-1.5 bg-white dark:bg-surface-800 text-primary-600 border border-primary-100 dark:border-primary-900/30 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-sm hover:bg-primary-50 dark:hover:bg-surface-700">
                Visual
            </button>
        </div>

        <!-- Editor Surface -->
        <div class="relative bg-white dark:bg-surface-900" :class="variant === 'full' ? 'min-h-[400px]' : 'min-h-[140px]'">
            <EditorContent v-if="!isSourceMode" :editor="editor" class="prose-container" :class="`rich-editor-${variant}`" />
            <textarea v-else :value="sourceContent" @input="handleSourceInput" class="w-full h-full min-h-[400px] p-6 text-sm font-mono bg-gray-900 text-gray-300 focus:ring-0 border-none outline-none resize-none" spellcheck="false"></textarea>

            <div v-if="slashOpen && filteredSlashCommands.length" class="absolute left-6 top-14 z-40 w-72 rounded-xl border border-gray-200 bg-white p-2 shadow-lg dark:border-surface-700 dark:bg-surface-900">
                <div class="px-3 py-2 text-[10px] font-black uppercase tracking-widest text-gray-400">{{ t('Slash commands') }}</div>
                <button
                    v-for="(command, index) in filteredSlashCommands"
                    :key="command.key"
                    type="button"
                    @click="runSlashCommand(command)"
                    :class="{ 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300': index === slashIndex }"
                    class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-start text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200"
                >
                    <span>{{ command.label }}</span>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">{{ command.group }}</span>
                </button>
            </div>
        </div>

        <div v-if="editor" class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 bg-gray-50 px-4 py-2 text-xs text-gray-500 dark:border-surface-800 dark:bg-surface-800/50 dark:text-gray-400">
            <div class="flex flex-wrap items-center gap-3">
                <span>{{ textStats.words }} {{ t('words') }}</span>
                <span>{{ textStats.characters }} {{ t('characters') }}</span>
                <span>{{ textStats.readingMinutes }} {{ t('min read') }}</span>
            </div>
            <div v-if="variant === 'full'" class="flex flex-wrap items-center gap-2">
                <span>{{ t('Versions') }}: {{ versionHistory.length }}/20</span>
                <select v-if="versionHistory.length > 1" @change="restoreVersion(($event.target as HTMLSelectElement).value)" class="rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs dark:border-surface-700 dark:bg-surface-900">
                    <option value="">{{ t('Restore version') }}</option>
                    <option v-for="version in versionHistory" :key="version.id" :value="version.html">
                        {{ version.savedAt.toLocaleTimeString() }} - {{ version.words }} {{ t('words') }}
                    </option>
                </select>
            </div>
        </div>

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
                    <span class="block text-sm font-bold text-violet-700 dark:text-violet-300">{{ aiAssistLoadingKey === action.key ? aiAssistLoadingLabel : action.label }}</span>
                    <span v-if="action.description" class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ action.description }}</span>
                </button>
            </div>
        </aside>

        <div v-if="linkTooltip.visible" class="pointer-events-none fixed z-50 max-w-xs truncate rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-medium text-white shadow-lg" :style="{ left: `${linkTooltip.x + 12}px`, top: `${linkTooltip.y + 14}px` }">
            {{ linkTooltip.url }}
        </div>

        <div v-if="linkModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]" @click.self="closeLinkModal">
            <div class="w-full max-w-[540px] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg dark:border-surface-700 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Edit link') }}</h3>
                </div>
                <div class="space-y-4 p-5">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('URL') }}
                        <input v-model="linkUrl" type="url" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition-colors focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Link title') }}
                        <input v-model="linkTitle" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition-colors focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Link target') }}
                        <select v-model="linkTarget" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition-colors focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            <option value="_self">{{ t('Same tab') }}</option>
                            <option value="_blank">{{ t('New tab') }}</option>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Link color') }}
                        <div class="mt-2 flex items-center gap-3">
                            <input v-model="linkColor" type="color" class="h-10 w-12 cursor-pointer rounded-lg border border-gray-200 bg-white p-1 dark:border-surface-700 dark:bg-surface-800">
                            <input v-model="linkColor" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition-colors focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </div>
                    </label>
                </div>
                <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50 px-5 py-4 dark:border-surface-800 dark:bg-surface-950">
                    <button type="button" @click="removeLink" class="rounded-lg px-4 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 dark:hover:bg-red-900/20">{{ t('Remove link') }}</button>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="closeLinkModal" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-800">{{ t('Cancel') }}</button>
                        <button type="button" @click="applyLink" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-500">{{ t('Apply') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="imageModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]" @click.self="closeImageModal">
            <div class="w-full max-w-[540px] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg dark:border-surface-700 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Add image') }}</h3>
                </div>
                <div class="space-y-4 p-5">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Upload image') }}
                        <input type="file" accept="image/*" @change="handleImageUpload" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition-colors file:me-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>
                    <div class="relative text-center text-xs font-medium uppercase tracking-wide text-gray-400">
                        <span class="bg-white px-3 dark:bg-surface-900">{{ t('or') }}</span>
                        <div class="absolute inset-x-0 top-1/2 -z-0 h-px bg-gray-100 dark:bg-surface-800"></div>
                    </div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Image URL') }}
                        <input v-model="imageUrl" type="url" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition-colors focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Alt text') }}
                            <input v-model="imageAlt" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition-colors focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </label>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Caption') }}
                            <input v-model="imageCaption" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition-colors focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </label>
                    </div>
                    <p v-if="uploadedImageData" class="rounded-lg bg-primary-50 px-3 py-2 text-sm text-primary-700 dark:bg-primary-900/20 dark:text-primary-300">{{ t('Uploaded image is ready to insert.') }}</p>
                    <p v-if="imageUploading" class="rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-700 dark:bg-blue-900/20 dark:text-blue-300">{{ t('Uploading image...') }}</p>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Image width') }}
                            <input v-model="imageWidth" type="text" :placeholder="t('Auto, 480, 80%, 32rem')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition-colors focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </label>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Image alignment') }}
                            <select v-model="imageAlignment" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition-colors focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                <option value="left">{{ t('Left') }}</option>
                                <option value="center">{{ t('Center') }}</option>
                                <option value="right">{{ t('Right') }}</option>
                                <option value="float-left">{{ t('Float left') }}</option>
                                <option value="float-right">{{ t('Float right') }}</option>
                            </select>
                        </label>
                    </div>
                    <p v-if="imageFileName" class="rounded-lg bg-primary-50 px-3 py-2 text-sm text-primary-700 dark:bg-primary-900/20 dark:text-primary-300">{{ imageFileName }}</p>
                    <p v-if="imageError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">{{ imageError }}</p>
                </div>
                <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50 px-5 py-4 dark:border-surface-800 dark:bg-surface-950">
                    <button v-if="imageEditing" type="button" @click="removeImage" class="rounded-lg px-4 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 dark:hover:bg-red-900/20">{{ t('Remove image') }}</button>
                    <span v-else></span>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="closeImageModal" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-800">{{ t('Cancel') }}</button>
                        <button type="button" @click="applyImageUrl" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-500">{{ imageEditing ? t('Apply') : t('Insert image') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="videoModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]" @click.self="videoModalOpen = false">
            <div class="w-full max-w-[540px] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg dark:border-surface-700 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Embed video') }}</h3>
                </div>
                <div class="space-y-4 p-5">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('YouTube or Vimeo URL') }}
                        <input v-model="videoUrl" type="url" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition-colors focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>
                    <p v-if="videoError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">{{ videoError }}</p>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-4 dark:border-surface-800 dark:bg-surface-950">
                    <button type="button" @click="videoModalOpen = false" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-800">{{ t('Cancel') }}</button>
                    <button type="button" @click="insertVideo" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-500">{{ t('Insert video') }}</button>
                </div>
            </div>
        </div>

        <div v-if="attachmentModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-[2px]" @click.self="attachmentModalOpen = false">
            <div class="w-full max-w-[540px] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg dark:border-surface-700 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Attach file') }}</h3>
                </div>
                <div class="space-y-4 p-5">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Upload file') }}
                        <input type="file" @change="handleAttachmentUpload" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition-colors file:me-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('File URL') }}
                        <input v-model="attachmentUrl" type="url" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition-colors focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Display name') }}
                        <input v-model="attachmentName" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition-colors focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>
                    <p v-if="attachmentUploading" class="rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-700 dark:bg-blue-900/20 dark:text-blue-300">{{ t('Uploading file...') }}</p>
                    <p v-if="attachmentFileName" class="rounded-lg bg-primary-50 px-3 py-2 text-sm text-primary-700 dark:bg-primary-900/20 dark:text-primary-300">{{ attachmentFileName }}</p>
                    <p v-if="attachmentError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">{{ attachmentError }}</p>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-4 dark:border-surface-800 dark:bg-surface-950">
                    <button type="button" @click="attachmentModalOpen = false" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-800">{{ t('Cancel') }}</button>
                    <button type="button" @click="insertAttachment" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-500">{{ t('Insert file') }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
.prose-container .ProseMirror {
    padding: 1.5rem;
    min-height: 400px;
    outline: none !important;
}

.prose-container.rich-editor-minimal .ProseMirror {
    min-height: 140px;
    padding: 1rem;
}

.prose-container.rich-editor-comment .ProseMirror {
    min-height: 180px;
    padding: 1rem;
}

/* Base formatting */
.prose-container .ProseMirror h1 { font-size: 1.875rem; font-weight: 800; margin-bottom: 1.5rem; line-height: 1.2; }
.prose-container .ProseMirror h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 1.25rem; line-height: 1.3; }
.prose-container .ProseMirror h3 { font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem; }
.prose-container .ProseMirror h4 { font-size: 1.0625rem; font-weight: 700; margin-bottom: 0.75rem; }
.prose-container .ProseMirror h5 { font-size: 0.9375rem; font-weight: 700; margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; }
.prose-container .ProseMirror p { margin-bottom: 1rem; line-height: 1.6; }
.prose-container .ProseMirror a { color: #4f46e5; text-decoration: underline; }

/* Lists */
.prose-container .ProseMirror ul { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 1rem; }
.prose-container .ProseMirror ol { list-style-type: decimal; padding-left: 1.5rem; margin-bottom: 1rem; }
.prose-container .ProseMirror li p { margin-bottom: 0.25rem; }
.prose-container .ProseMirror ul[data-type="taskList"] { list-style: none; padding: 0; }
.prose-container .ProseMirror ul[data-type="taskList"] li { display: flex; align-items: flex-start; gap: 0.5rem; }
.prose-container .ProseMirror ul[data-type="taskList"] li > label { margin-top: 0.25rem; }

/* Blocks */
.prose-container .ProseMirror blockquote { border-left: 4px solid #e5e7eb; padding-left: 1rem; font-style: italic; color: #4b5563; }
.prose-container .ProseMirror pre { background: #1f2937; color: #f9fafb; padding: 1rem; border-radius: 0.5rem; font-family: monospace; font-size: 0.875rem; overflow-x: auto; }
.prose-container .ProseMirror code { background: #f3f4f6; padding: 0.2rem 0.4rem; border-radius: 0.25rem; font-size: 0.875em; }
.prose-container .ProseMirror pre code { background: none; padding: 0; color: inherit; }

/* Tables */
.prose-container .ProseMirror table { border-collapse: collapse; table-layout: fixed; width: 100%; margin: 0; overflow: hidden; }
.prose-container .ProseMirror table td, .prose-container .ProseMirror table th { min-width: 1em; border: 1px solid #e5e7eb; padding: 0.5rem; vertical-align: top; box-sizing: border-box; position: relative; }
.prose-container .ProseMirror table th { font-weight: bold; text-align: left; background-color: #f9fafb; }

/* Images */
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

/* Dark mode */
.dark .prose-container .ProseMirror { color: #f4f4f5; }
.dark .prose-container .ProseMirror blockquote { border-left-color: #3f3f46; color: #a1a1aa; }
.dark .prose-container .ProseMirror code { background: #27272a; color: #e4e4e7; }
.dark .prose-container .ProseMirror table td, .dark .prose-container .ProseMirror table th { border-color: #3f3f46; }
.dark .prose-container .ProseMirror table th { background-color: #27272a; }
.dark .prose-container .ProseMirror a.rich-file-attachment { border-color: #064e3b; background: rgba(6, 78, 59, 0.25); color: #6ee7b7; }
.dark .prose-container .ProseMirror details { border-color: #3f3f46; background: #18181b; }
.dark .prose-container .ProseMirror details summary { color: #f4f4f5; }
</style>
