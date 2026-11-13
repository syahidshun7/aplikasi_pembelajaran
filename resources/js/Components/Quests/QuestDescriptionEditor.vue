<script setup>
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Tulis tujuan, instruksi, dan langkah pengerjaan quest...',
    },
    light: {
        type: Boolean,
        default: false,
    },
    compact: Boolean,
    ariaLabel: String,
});

const emit = defineEmits(['update:modelValue']);
const resizeArea = ref(null);
const writingHeight = ref(null);
let resizeGesture = null;

const resizeWritingArea = (height) => {
    writingHeight.value = Math.max(64, Math.round(height));
};

const startResize = (event) => {
    if (!event.isPrimary || event.button !== 0) return;

    resizeGesture = {
        pointerId: event.pointerId,
        startY: event.clientY,
        startHeight: resizeArea.value.offsetHeight,
    };
    event.currentTarget.setPointerCapture(event.pointerId);
};

const moveResize = (event) => {
    if (resizeGesture?.pointerId !== event.pointerId) return;

    resizeWritingArea(resizeGesture.startHeight + event.clientY - resizeGesture.startY);
};

const stopResize = (event) => {
    if (resizeGesture?.pointerId === event.pointerId) resizeGesture = null;
};

const editor = useEditor({
    immediatelyRender: false,
    content: props.modelValue || '<p></p>',
    extensions: [
        StarterKit.configure({ heading: { levels: [2, 3] } }),
        Link.configure({ openOnClick: false, autolink: true }),
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    editorProps: {
        attributes: {
            class: 'quest-description-editor__content',
            ...(props.ariaLabel ? { 'aria-label': props.ariaLabel } : {}),
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
    <div class="quest-description-editor" :class="{ 'quest-description-editor--light': light, 'quest-description-editor--compact': compact }">
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
        <div v-if="compact && editor" class="quest-description-editor__work-area">
            <div
                ref="resizeArea"
                class="quest-description-editor__resize-area"
                :style="writingHeight === null ? undefined : { height: writingHeight + 'px' }"
            >
                <EditorContent :editor="editor" />
            </div>
            <button
                type="button"
                class="quest-description-editor__resize-handle"
                aria-label="Ubah tinggi area tulis"
                @pointerdown="startResize"
                @pointermove="moveResize"
                @lostpointercapture="stopResize"
                @keydown.down.prevent="resizeWritingArea(resizeArea.offsetHeight + 24)"
                @keydown.up.prevent="resizeWritingArea(resizeArea.offsetHeight - 24)"
            >
                <span aria-hidden="true"></span>
            </button>
        </div>
        <EditorContent
            v-else-if="editor"
            :editor="editor"
        />
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

.quest-description-editor--light{border:1px solid #dce6e8;border-radius:8px;background:#fff;overflow:hidden}
.quest-description-editor--light:focus-within{border-color:#008b83}
.quest-description-editor--light .quest-description-editor__toolbar{background:#f6f8f9;border-bottom-color:#dce6e8}
.quest-description-editor--light .quest-description-editor__toolbar button{background:#fff;border-color:#dce6e8;border-radius:5px;color:#19333d;font-family:inherit}
.quest-description-editor--light .quest-description-editor__toolbar button:hover,.quest-description-editor--light .quest-description-editor__toolbar button.is-active{background:#e5f5f1;border-color:#9ccbc6;color:#007f78}
.quest-description-editor--light .quest-description-editor__divider{background:#dce6e8}
.quest-description-editor--light :deep(.quest-description-editor__content){min-height:160px;color:#19333d;font-family:inherit;font-weight:400}
.quest-description-editor--light :deep(.quest-description-editor__content h2),.quest-description-editor--light :deep(.quest-description-editor__content h3){color:#19333d}
.quest-description-editor--light :deep(.quest-description-editor__content a){color:#007f78}

.quest-description-editor--compact {
    border: 1px solid var(--panel-border);
    background: var(--panel);
}
.quest-description-editor--compact:focus-within { border-color: var(--accent); }
.quest-description-editor--compact .quest-description-editor__toolbar {
    padding: 0.25rem;
    border-color: var(--panel-border);
    background: var(--bg);
}
.quest-description-editor--compact .quest-description-editor__toolbar button {
    border-color: var(--panel-border);
    background: var(--panel);
    color: var(--text);
    font-family: ui-monospace, monospace;
}
.quest-description-editor--compact .quest-description-editor__toolbar button:hover,
.quest-description-editor--compact .quest-description-editor__toolbar button.is-active {
    border-color: var(--accent);
    background: color-mix(in srgb, var(--accent) 14%, var(--panel));
}
.quest-description-editor--compact .quest-description-editor__divider { background: var(--panel-border); }
.quest-description-editor__resize-area {
    min-height: 4rem;
    overflow: auto;
    resize: vertical;
}
.quest-description-editor__work-area { position: relative; }
.quest-description-editor__resize-handle { display: none; }

@media (any-pointer: coarse) {
    .quest-description-editor__resize-area {
        min-height: 6.75rem;
        resize: none;
        padding-bottom: 2.75rem;
        box-sizing: border-box;
    }
    .quest-description-editor__resize-handle {
        position: absolute;
        right: 0;
        bottom: 0;
        display: grid;
        width: 44px;
        height: 44px;
        place-items: center;
        color: var(--text);
        background: var(--panel);
        border-top: 1px solid var(--panel-border);
        border-left: 1px solid var(--panel-border);
        touch-action: none;
        user-select: none;
        cursor: ns-resize;
    }
    .quest-description-editor__resize-handle span {
        width: 12px;
        height: 12px;
        border-right: 2px solid currentColor;
        border-bottom: 2px solid currentColor;
    }
}
.quest-description-editor--compact:not(.quest-description-editor--light) {
    border-color: color-mix(in srgb, var(--panel-border) 78%, var(--text));
}
.quest-description-editor--compact:not(.quest-description-editor--light):focus-within {
    border-color: var(--accent);
}
.quest-description-editor--compact:not(.quest-description-editor--light) .quest-description-editor__resize-area {
    background: var(--bg);
}
.quest-description-editor--compact :deep(.quest-description-editor__content) {
    min-height: 4rem;
    padding: 0.4rem 0.6rem;
    color: var(--text);
    font-family: ui-sans-serif, system-ui, sans-serif;
}
.quest-description-editor--compact :deep(.quest-description-editor__content :is(h2, h3)) { color: var(--text); }
.quest-description-editor--compact :deep(.quest-description-editor__content a) { color: var(--accent); }
</style>
