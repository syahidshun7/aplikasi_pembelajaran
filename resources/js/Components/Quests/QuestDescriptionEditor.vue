<script setup>
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    immediatelyRender: false,
    content: props.modelValue || '<p></p>',
    extensions: [
        StarterKit.configure({ heading: { levels: [2, 3] } }),
        Link.configure({ openOnClick: false, autolink: true }),
        Placeholder.configure({ placeholder: 'Tulis tujuan, instruksi, dan langkah pengerjaan quest...' }),
    ],
    editorProps: {
        attributes: {
            class: 'quest-description-editor__content',
        },
    },
    onUpdate: ({ editor: activeEditor }) => emit('update:modelValue', activeEditor.getHTML()),
});

watch(
    () => props.modelValue,
    (value) => {
        if (!editor.value) return;
        const content = value || '<p></p>';
        if (content !== editor.value.getHTML()) {
            editor.value.commands.setContent(content, false);
        }
    },
);

const toggleLink = () => {
    if (!editor.value) return;
    const previousUrl = editor.value.getAttributes('link').href || '';
    const url = window.prompt('Masukkan URL tautan:', previousUrl);
    if (url === null) return;
    if (url.trim() === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }
    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url.trim() }).run();
};

onBeforeUnmount(() => editor.value?.destroy());
</script>

<template>
    <div class="quest-description-editor">
        <div v-if="editor" class="quest-description-editor__toolbar" aria-label="Format description">
            <button type="button" title="Bold" :class="{ 'is-active': editor.isActive('bold') }" @click="editor.chain().focus().toggleBold().run()"><strong>B</strong></button>
            <button type="button" title="Italic" :class="{ 'is-active': editor.isActive('italic') }" @click="editor.chain().focus().toggleItalic().run()"><em>I</em></button>
            <span class="quest-description-editor__divider"></span>
            <button type="button" title="Heading" :class="{ 'is-active': editor.isActive('heading', { level: 2 }) }" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">H</button>
            <button type="button" title="Bullet list" :class="{ 'is-active': editor.isActive('bulletList') }" @click="editor.chain().focus().toggleBulletList().run()">• ≡</button>
            <button type="button" title="Numbered list" :class="{ 'is-active': editor.isActive('orderedList') }" @click="editor.chain().focus().toggleOrderedList().run()">1 ≡</button>
            <span class="quest-description-editor__divider"></span>
            <button type="button" title="Tambah atau ubah tautan" :class="{ 'is-active': editor.isActive('link') }" @click="toggleLink">↗</button>
            <button type="button" title="Hapus format" @click="editor.chain().focus().clearNodes().unsetAllMarks().run()">⌫</button>
        </div>
        <EditorContent v-if="editor" :editor="editor" />
    </div>
</template>

<style scoped>
.quest-description-editor {
    border: 2px solid #334155;
    background: #0d1117;
}

.quest-description-editor:focus-within { border-color: #22d3ee; }

.quest-description-editor__toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    padding: 0.5rem;
    border-bottom: 1px solid #334155;
    background: #111827;
}

.quest-description-editor__toolbar button {
    min-width: 2rem;
    border: 1px solid #475569;
    padding: 0.28rem 0.45rem;
    background: #0d1117;
    color: #94a3b8;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.78rem;
}

.quest-description-editor__toolbar button:hover,
.quest-description-editor__toolbar button.is-active {
    border-color: #22d3ee;
    background: #164e63;
    color: #cffafe;
}

.quest-description-editor__divider { width: 1px; height: 1.5rem; background: #334155; }

:deep(.quest-description-editor__content) {
    min-height: 11rem;
    padding: 0.85rem;
    outline: none;
    color: #e2e8f0;
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-size: 0.9rem;
    line-height: 1.65;
}

:deep(.quest-description-editor__content p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    float: left;
    color: #64748b;
    pointer-events: none;
    height: 0;
}

:deep(.quest-description-editor__content h2) { margin: 0.8rem 0 0.35rem; color: #67e8f9; font-size: 1.1rem; }
:deep(.quest-description-editor__content h3) { margin: 0.7rem 0 0.3rem; color: #a5f3fc; font-size: 1rem; }
:deep(.quest-description-editor__content p) { margin: 0.4rem 0; }
:deep(.quest-description-editor__content ul), :deep(.quest-description-editor__content ol) { margin: 0.5rem 0; padding-left: 1.5rem; }
:deep(.quest-description-editor__content ul) { list-style: disc; }
:deep(.quest-description-editor__content ol) { list-style: decimal; }
:deep(.quest-description-editor__content a) { color: #67e8f9; text-decoration: underline; }
</style>
