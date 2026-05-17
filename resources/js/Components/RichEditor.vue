<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import TiptapLink from '@tiptap/extension-link';
import Underline from '@tiptap/extension-underline';
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

const props = withDefaults(defineProps<{
    modelValue: string;
    variant?: 'full' | 'comment' | 'minimal';
}>(), {
    variant: 'full'
});

const emit = defineEmits(['update:modelValue']);

const isSourceMode = ref(false);
const sourceContent = ref(props.modelValue);
const linkUrl = ref('');
const showLinkPrompt = ref(false);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            link: false, // configure manually
            codeBlock: {},
        }),
        TiptapLink.configure({
            openOnClick: false,
            HTMLAttributes: {
                class: 'text-primary-600 underline cursor-pointer',
            },
        }),
        Underline,
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
        TextStyle,
        Color,
        Highlight.configure({ multicolor: true }),
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
        Image.configure({
            inline: true,
            allowBase64: true,
        }),
    ],
    onUpdate: ({ editor }) => {
        const html = editor.getHTML();
        emit('update:modelValue', html);
        sourceContent.value = html;
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
};

const handleSourceInput = (e: Event) => {
    const target = e.target as HTMLTextAreaElement;
    sourceContent.value = target.value;
    emit('update:modelValue', target.value);
};

// Actions
const setLink = () => {
    const previousUrl = editor.value?.getAttributes('link').href
    const url = window.prompt('URL', previousUrl)

    if (url === null) {
        return
    }

    if (url === '') {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run()
        return
    }

    editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

const addImage = () => {
    const url = window.prompt('Image URL')
    if (url) {
        editor.value?.chain().focus().setImage({ src: url }).run()
    }
}

onBeforeUnmount(() => {
    editor.value?.destroy();
});
</script>

<template>
    <div class="border border-gray-100 dark:border-surface-800 rounded-2xl overflow-hidden focus-within:ring-2 focus-within:ring-primary-500/20 transition-all bg-white dark:bg-surface-900">
        <!-- Toolbar -->
        <div v-if="editor && !isSourceMode" class="flex flex-wrap items-center gap-1.5 p-2 bg-gray-50 dark:bg-surface-800/50 border-b border-gray-100 dark:border-surface-800">
            
            <!-- Minimal Tools -->
            <button type="button" @click="editor.chain().focus().toggleBold().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('bold') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Bold">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h4.5a3.75 3.75 0 110 7.5h-4.5v-7.5zM6.75 11.25h5.25a3.75 3.75 0 110 7.5h-5.25v-7.5z" /></svg>
            </button>
            <button type="button" @click="editor.chain().focus().toggleItalic().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('italic') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Italic">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.25h13.5M7.5 18.75h13.5M10.5 5.25l-3 13.5" /></svg>
            </button>
            <button v-if="variant !== 'minimal'" type="button" @click="editor.chain().focus().toggleUnderline().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('underline') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors font-serif font-bold text-sm leading-none flex items-center justify-center w-8" title="Underline">
                <span class="underline underline-offset-2">U</span>
            </button>
            <button v-if="variant !== 'minimal'" type="button" @click="editor.chain().focus().toggleStrike().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('strike') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors font-serif font-bold text-sm leading-none flex items-center justify-center w-8" title="Strikethrough">
                <span class="line-through">S</span>
            </button>

            <div class="w-px h-5 bg-gray-200 dark:bg-surface-700 mx-1"></div>
            
            <button type="button" @click="setLink" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('link') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Link">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>
            </button>

            <!-- Comment + Full Tools -->
            <template v-if="variant !== 'minimal'">
                <div class="w-px h-5 bg-gray-200 dark:bg-surface-700 mx-1"></div>
                <button type="button" @click="editor.chain().focus().toggleBulletList().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('bulletList') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Bullet List">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                </button>
                <button type="button" @click="editor.chain().focus().toggleOrderedList().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('orderedList') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Numbered List">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75v.008m0 5.242v.008m0 5.242v.008" /></svg>
                </button>
                <button type="button" @click="editor.chain().focus().toggleCodeBlock().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('codeBlock') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors font-mono font-bold text-xs" title="Code Block">
                    &lt;/&gt;
                </button>
            </template>

            <!-- Full Tools Only -->
            <template v-if="variant === 'full'">
                <div class="w-px h-5 bg-gray-200 dark:bg-surface-700 mx-1"></div>
                <button type="button" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('heading', { level: 1 }) }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors font-black text-xs">H1</button>
                <button type="button" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('heading', { level: 2 }) }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors font-black text-xs">H2</button>
                <button type="button" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('heading', { level: 3 }) }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors font-black text-xs">H3</button>

                <div class="w-px h-5 bg-gray-200 dark:bg-surface-700 mx-1"></div>
                <button type="button" @click="editor.chain().focus().setTextAlign('left').run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive({ textAlign: 'left' }) }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Align Left">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h11.25m-11.25 5.25h16.5" /></svg>
                </button>
                <button type="button" @click="editor.chain().focus().setTextAlign('center').run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive({ textAlign: 'center' }) }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Align Center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M6.75 12h10.5m-13.5 5.25h16.5" /></svg>
                </button>

                <div class="w-px h-5 bg-gray-200 dark:bg-surface-700 mx-1"></div>
                
                <label class="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors cursor-pointer flex items-center relative group" title="Text Color">
                    <div class="w-5 h-5 rounded-md border border-gray-200 dark:border-surface-600 flex items-center justify-center font-serif text-xs font-bold" :style="{ color: editor?.getAttributes('textStyle').color || 'currentColor' }">A</div>
                    <input type="color" @input="(e) => editor?.chain().focus().setColor((e.target as HTMLInputElement).value).run()" :value="editor?.getAttributes('textStyle').color || '#000000'" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
                </label>

                <label class="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors cursor-pointer flex items-center relative group" title="Highlight">
                    <div class="w-5 h-5 rounded-md border border-gray-200 dark:border-surface-600 flex items-center justify-center font-serif text-xs font-bold bg-yellow-200 text-black">H</div>
                    <input type="color" @input="(e) => editor?.chain().focus().toggleHighlight({ color: (e.target as HTMLInputElement).value }).run()" :value="editor?.getAttributes('highlight').color || '#ffff00'" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
                </label>

                <div class="w-px h-5 bg-gray-200 dark:bg-surface-700 mx-1"></div>
                
                <button type="button" @click="editor.chain().focus().toggleTaskList().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('taskList') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Task List">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </button>
                <button type="button" @click="editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Insert Table">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-1.5A1.125 1.125 0 0118 18.375M20.625 4.5H3.375m17.25 0c.621 0 1.125.504 1.125 1.125M20.625 4.5h-1.5C18.504 4.5 18 5.004 18 5.625m3.75 0v1.5c0 .621-.504 1.125-1.125 1.125M3.375 4.5c-.621 0-1.125.504-1.125 1.125M3.375 4.5h1.5C5.496 4.5 6 5.004 6 5.625m-3.75 0v1.5c0 .621.504 1.125 1.125 1.125m0 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m1.5-3.75C5.496 8.25 6 7.746 6 7.125v-1.5M4.875 8.25C5.496 8.25 6 8.754 6 9.375v1.5m0-5.25v5.25m0-5.25C6 5.004 6.504 4.5 7.125 4.5h1.5c.621 0 1.125.504 1.125 1.125m1.125 2.625h1.5m-1.5 0A1.125 1.125 0 0110.5 7.125v-1.5m1.125 2.625a1.125 1.125 0 001.125 1.125h1.5a1.125 1.125 0 001.125-1.125m-3.75 0C10.5 8.754 11.004 9.25 11.625 9.25h1.5c.621 0 1.125-.504 1.125-1.125m-3.75 0v-1.5c0-.621.504-1.125 1.125-1.125h1.5c.621 0 1.125.504 1.125 1.125m-3.75 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5m0 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5" /></svg>
                </button>
                <button type="button" @click="addImage" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Add Image">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                </button>
                <button type="button" @click="editor.chain().focus().toggleBlockquote().run()" :class="{ 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400': editor.isActive('blockquote') }" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors" title="Blockquote">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3" /></svg>
                </button>
            </template>

            <!-- Always visible mode toggle & history -->
            <div class="flex-1"></div>
            <div class="flex items-center gap-1 border-l border-gray-200 dark:border-surface-700 pl-2">
                <button type="button" @click="editor.chain().focus().undo().run()" :disabled="!editor.can().undo()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors disabled:opacity-30 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                </button>
                <button type="button" @click="editor.chain().focus().redo().run()" :disabled="!editor.can().redo()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-400 transition-colors disabled:opacity-30 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l6-6m0 0l-6-6m6 6H9a6 6 0 000 12h3" /></svg>
                </button>
                <button type="button" @click="toggleMode" class="ml-1 px-3 py-1.5 bg-white dark:bg-surface-800 text-primary-600 border border-primary-100 dark:border-primary-900/30 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-sm hover:bg-primary-50 dark:hover:bg-surface-700">
                    {{ isSourceMode ? 'Visual' : 'Source' }}
                </button>
            </div>
        </div>
        
        <!-- Table Context Menu -->
        <div v-if="editor && editor.isActive('table') && !isSourceMode" class="flex flex-wrap items-center gap-1.5 p-1.5 bg-indigo-50 dark:bg-indigo-900/20 border-b border-indigo-100 dark:border-indigo-900/30">
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
        <div class="relative bg-white dark:bg-surface-900 min-h-[400px]">
            <EditorContent v-if="!isSourceMode" :editor="editor" class="prose-container" />
            <textarea v-else :value="sourceContent" @input="handleSourceInput" class="w-full h-full min-h-[400px] p-6 text-sm font-mono bg-gray-900 text-gray-300 focus:ring-0 border-none outline-none resize-none" spellcheck="false"></textarea>
        </div>
    </div>
</template>

<style>
.prose-container .ProseMirror {
    padding: 1.5rem;
    min-height: 400px;
    outline: none !important;
}

/* Base formatting */
.prose-container .ProseMirror h1 { font-size: 1.875rem; font-weight: 800; margin-bottom: 1.5rem; line-height: 1.2; }
.prose-container .ProseMirror h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 1.25rem; line-height: 1.3; }
.prose-container .ProseMirror h3 { font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem; }
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

/* Dark mode */
.dark .prose-container .ProseMirror { color: #f4f4f5; }
.dark .prose-container .ProseMirror blockquote { border-left-color: #3f3f46; color: #a1a1aa; }
.dark .prose-container .ProseMirror code { background: #27272a; color: #e4e4e7; }
.dark .prose-container .ProseMirror table td, .dark .prose-container .ProseMirror table th { border-color: #3f3f46; }
.dark .prose-container .ProseMirror table th { background-color: #27272a; }
</style>
