<script setup>
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { Extension } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyle } from '@tiptap/extension-text-style';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const ResizableImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            width: {
                default: '100%',
                parseHTML: (element) => String(element.style.width || element.getAttribute('data-width') || '100%'),
                renderHTML: (attributes) => ({
                    'data-width': String(attributes.width || '100%'),
                    style: `width: ${String(attributes.width || '100%')};`,
                }),
            },
        };
    },
});

const FontSize = Extension.create({
    name: 'fontSize',
    addOptions() {
        return {
            types: ['textStyle'],
        };
    },
    addGlobalAttributes() {
        return [
            {
                types: this.options.types,
                attributes: {
                    fontSize: {
                        default: null,
                        parseHTML: (element) => element.style.fontSize || null,
                        renderHTML: (attributes) => {
                            if (!attributes.fontSize) {
                                return {};
                            }

                            return {
                                style: `font-size: ${String(attributes.fontSize)};`,
                            };
                        },
                    },
                },
            },
        ];
    },
    addCommands() {
        return {
            setFontSize: (fontSize) => ({ chain }) => chain().setMark('textStyle', { fontSize }).run(),
            unsetFontSize: () => ({ chain }) => chain().setMark('textStyle', { fontSize: null }).removeEmptyTextStyle().run(),
        };
    },
});

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Start writing...',
    },
    uploadUrl: {
        type: String,
        required: true,
    },
    persistKey: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue', 'uploading', 'ready']);

const isUploading = ref(false);
const isDragActive = ref(false);
const imageSelectionVersion = ref(0);
const surfaceRef = ref(null);
const hasScrolledContent = ref(false);
const FONT_SIZE_OPTIONS = [
    { label: '14', value: '14px' },
    { label: '16', value: '16px' },
    { label: '18', value: '18px' },
    { label: '24', value: '24px' },
    { label: '32', value: '32px' },
];

const editorStateStorageKey = computed(() => (
    props.persistKey ? `creation.editor.state.${props.persistKey}` : ''
));

const saveEditorState = (editor) => {
    if (!editor || !editorStateStorageKey.value || typeof window === 'undefined') {
        return;
    }

    const payload = {
        content: editor.getHTML(),
        selection: {
            from: editor.state.selection?.from || 0,
            to: editor.state.selection?.to || 0,
        },
        savedAt: Date.now(),
    };

    window.localStorage.setItem(editorStateStorageKey.value, JSON.stringify(payload));
};

const restoreEditorState = () => {
    if (!editorStateStorageKey.value || typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.localStorage.getItem(editorStateStorageKey.value);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object') {
            return null;
        }

        return parsed;
    } catch (error) {
        return null;
    }
};

const setUploading = (value) => {
    isUploading.value = value;
    emit('uploading', value);
};

const uploadImage = async (file, insertAt = null) => {
    if (!file) {
        return false;
    }

    const formData = new FormData();
    formData.append('image', file);
    setUploading(true);

    try {
        const response = await window.axios.post(props.uploadUrl, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        const url = String(response.data?.url || '').trim();
        if (!url || !editor.value) {
            return false;
        }

        if (typeof insertAt === 'number' && Number.isFinite(insertAt)) {
            editor.value.chain().focus().insertContentAt(insertAt, {
                type: 'image',
                attrs: { src: url, width: '100%' },
            }).run();
        } else {
            editor.value.chain().focus().setImage({ src: url, width: '100%' }).run();
        }

        saveEditorState(editor.value);
        return true;
    } catch (error) {
        console.error('image upload failed', error);
        return false;
    } finally {
        setUploading(false);
    }
};

const editor = useEditor({
    immediatelyRender: false,
    content: props.modelValue || '<p></p>',
    extensions: [
        StarterKit,
        ResizableImage,
        TextStyle,
        FontSize,
        TextAlign.configure({
            types: ['heading', 'paragraph', 'blockquote'],
        }),
        Link.configure({
            openOnClick: false,
            autolink: true,
        }),
        Placeholder.configure({
            placeholder: props.placeholder,
            emptyEditorClass: 'is-editor-empty',
        }),
    ],
    editorProps: {
        attributes: {
            class: 'doc-editor__content',
        },
        handlePaste(view, event) {
            const imageItem = Array.from(event?.clipboardData?.items || [])
                .find((item) => String(item.type || '').startsWith('image/'));

            if (!imageItem) {
                return false;
            }

            const file = imageItem.getAsFile();
            if (!file) {
                return false;
            }

            event.preventDefault();
            uploadImage(file, view.state.selection.from);
            return true;
        },
        handleDrop(view, event) {
            const file = Array.from(event?.dataTransfer?.files || [])
                .find((item) => String(item.type || '').startsWith('image/'));

            if (!file) {
                return false;
            }

            event.preventDefault();
            const coordinates = view.posAtCoords({
                left: event.clientX,
                top: event.clientY,
            });
            uploadImage(file, coordinates?.pos ?? view.state.selection.from);
            return true;
        },
    },
    onCreate({ editor }) {
        const restored = restoreEditorState();
        if (restored?.content) {
            editor.commands.setContent(String(restored.content || '<p></p>'), false);

            const from = Number(restored.selection?.from || 0);
            const to = Number(restored.selection?.to || from);
            const maxPosition = editor.state.doc.content.size;
            const safeFrom = Math.min(Math.max(from, 0), maxPosition);
            const safeTo = Math.min(Math.max(to, safeFrom), maxPosition);

            editor.commands.setTextSelection({ from: safeFrom, to: safeTo });
        }

        emit('update:modelValue', editor.getHTML());
        emit('ready', editor);
    },
    onUpdate({ editor }) {
        const html = editor.getHTML();
        emit('update:modelValue', html);
        saveEditorState(editor);
        imageSelectionVersion.value += 1;
    },
    onSelectionUpdate({ editor }) {
        saveEditorState(editor);
        imageSelectionVersion.value += 1;
    },
});

watch(
    () => props.modelValue,
    (value) => {
        if (!editor.value) {
            return;
        }

        const incoming = String(value || '<p></p>');
        if (incoming !== editor.value.getHTML()) {
            editor.value.commands.setContent(incoming, false);
        }
    },
);

const toolbarItems = computed(() => ([
    {
        icon: 'fi fi-rr-bold',
        active: editor.value?.isActive('bold'),
        action: () => editor.value?.chain().focus().toggleBold().run(),
    },
    {
        icon: 'fi fi-rr-italic',
        active: editor.value?.isActive('italic'),
        action: () => editor.value?.chain().focus().toggleItalic().run(),
    },
    {
        icon: 'fi fi-rr-heading',
        active: editor.value?.isActive('heading', { level: 2 }),
        action: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run(),
    },
    {
        icon: 'fi fi-rr-list',
        active: editor.value?.isActive('bulletList'),
        action: () => editor.value?.chain().focus().toggleBulletList().run(),
    },
    {
        icon: 'fi fi-rr-quote-right',
        active: editor.value?.isActive('blockquote'),
        action: () => editor.value?.chain().focus().toggleBlockquote().run(),
    },
    {
        icon: 'fi fi-rr-link',
        active: editor.value?.isActive('link'),
        action: () => {
            const previousUrl = editor.value?.getAttributes('link')?.href || '';
            const nextUrl = window.prompt('Paste link URL', previousUrl);

            if (nextUrl === null || !editor.value) {
                return;
            }

            if (String(nextUrl).trim() === '') {
                editor.value.chain().focus().unsetLink().run();
                return;
            }

            editor.value.chain().focus().extendMarkRange('link').setLink({ href: String(nextUrl).trim() }).run();
        },
    },
    {
        icon: 'fi fi-rr-align-left',
        active: editor.value?.isActive({ textAlign: 'left' }),
        action: () => editor.value?.chain().focus().setTextAlign('left').run(),
    },
    {
        icon: 'fi fi-rr-align-center',
        active: editor.value?.isActive({ textAlign: 'center' }),
        action: () => editor.value?.chain().focus().setTextAlign('center').run(),
    },
    {
        icon: '',
        label: 'R',
        active: editor.value?.isActive({ textAlign: 'right' }),
        action: () => editor.value?.chain().focus().setTextAlign('right').run(),
    },
]));

const isImageSelected = computed(() => {
    imageSelectionVersion.value;
    return Boolean(editor.value?.isActive('image'));
});

const selectedImageWidth = computed(() => {
    imageSelectionVersion.value;
    return String(editor.value?.getAttributes('image')?.width || '100%');
});

const selectedImageWidthValue = computed(() => {
    const parsed = Number.parseInt(String(selectedImageWidth.value).replace('%', ''), 10);
    return Number.isFinite(parsed) ? Math.min(100, Math.max(25, parsed)) : 100;
});

const setSelectedImageWidth = (width) => {
    if (!editor.value || !editor.value.isActive('image')) {
        return;
    }

    const normalizedWidth = `${Math.min(100, Math.max(25, Number(width || 100)))}%`;
    editor.value.chain().focus().updateAttributes('image', { width: normalizedWidth }).run();
};

const currentFontSize = computed(() => {
    imageSelectionVersion.value;
    return String(editor.value?.getAttributes('textStyle')?.fontSize || '18px');
});

const updateFontSize = (value) => {
    if (!editor.value) {
        return;
    }

    const normalized = String(value || '').trim();
    if (normalized === '') {
        editor.value.chain().focus().unsetFontSize().run();
        return;
    }

    editor.value.chain().focus().setFontSize(normalized).run();
};

const handleDragEnter = (event) => {
    if (Array.from(event?.dataTransfer?.types || []).includes('Files')) {
        isDragActive.value = true;
    }
};

const handleDragLeave = () => {
    isDragActive.value = false;
};

const handleDrop = () => {
    isDragActive.value = false;
};

const syncSurfaceScrollState = () => {
    hasScrolledContent.value = Number(surfaceRef.value?.scrollTop || 0) > 4;
};

onBeforeUnmount(() => {
    if (editor.value) {
        saveEditorState(editor.value);
        editor.value.destroy();
    }
});
</script>

<template>
    <div
        class="doc-editor"
        :class="{
            'doc-editor--drag': isDragActive,
            'doc-editor--scrolled': hasScrolledContent,
        }"
        @dragenter.prevent="handleDragEnter"
        @dragover.prevent="handleDragEnter"
        @dragleave.prevent="handleDragLeave"
        @drop.prevent="handleDrop"
    >
        <div class="doc-editor__toolbar">
            <button
                v-for="item in toolbarItems"
                :key="item.icon || item.label"
                type="button"
                class="doc-editor__tool"
                :class="{ 'doc-editor__tool--active': item.active }"
                @click="item.action"
            >
                <i v-if="item.icon" :class="item.icon" />
                <span v-else class="doc-editor__tool-label">{{ item.label }}</span>
            </button>

            <label class="doc-editor__select-shell">
                <span class="doc-editor__select-label">Size</span>
                <select class="doc-editor__select" :value="currentFontSize" @change="updateFontSize($event.target.value)">
                    <option v-for="option in FONT_SIZE_OPTIONS" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </label>

            <div v-if="isImageSelected" class="doc-editor__image-controls">
                <button type="button" class="doc-editor__size-chip" :class="{ 'doc-editor__size-chip--active': selectedImageWidth === '40%' }" @click="setSelectedImageWidth(40)">
                    40%
                </button>
                <button type="button" class="doc-editor__size-chip" :class="{ 'doc-editor__size-chip--active': selectedImageWidth === '70%' }" @click="setSelectedImageWidth(70)">
                    70%
                </button>
                <button type="button" class="doc-editor__size-chip" :class="{ 'doc-editor__size-chip--active': selectedImageWidth === '100%' }" @click="setSelectedImageWidth(100)">
                    100%
                </button>
                <input
                    :value="selectedImageWidthValue"
                    type="range"
                    min="25"
                    max="100"
                    step="5"
                    class="doc-editor__size-range"
                    @input="setSelectedImageWidth($event.target.value)"
                >
            </div>

            <div v-if="isUploading" class="doc-editor__status">
                <span class="doc-editor__status-dot" />
                Uploading image...
            </div>
        </div>

        <div ref="surfaceRef" class="doc-editor__surface" @scroll.passive="syncSurfaceScrollState">
            <EditorContent v-if="editor" :editor="editor" />
        </div>

        <div v-if="isDragActive" class="doc-editor__overlay">
            Drop image to upload
        </div>
    </div>
</template>

<style scoped>
.doc-editor {
    position: relative;
    border: 1px solid rgba(71, 85, 105, 0.8);
    background: rgba(15, 23, 42, 0.78);
    display: flex;
    flex-direction: column;
    min-height: 0;
    width: 100%;
    overflow: visible;
}

.doc-editor--drag {
    border-color: rgba(34, 211, 238, 0.85);
    box-shadow: 0 0 0 2px rgba(34, 211, 238, 0.18);
}

.doc-editor__toolbar {
    position: sticky;
    top: 0;
    z-index: 6;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
    border-bottom: 1px solid rgba(51, 65, 85, 1);
    padding: 0.85rem;
    background: rgba(2, 6, 23, 0.82);
    box-shadow: 0 1px 0 rgba(15, 23, 42, 0.75);
}

.doc-editor--scrolled .doc-editor__toolbar {
    box-shadow: 0 10px 22px rgba(2, 6, 23, 0.28), 0 1px 0 rgba(15, 23, 42, 0.82);
}

.doc-editor__tool {
    display: inline-flex;
    height: 2rem;
    width: 2rem;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(71, 85, 105, 0.8);
    background: rgba(15, 23, 42, 0.8);
    color: #cbd5e1;
    transition: 160ms ease;
}

.doc-editor__tool-label {
    font-size: 12px;
    font-weight: 700;
    line-height: 1;
}

.doc-editor__tool:hover,
.doc-editor__tool--active {
    border-color: rgba(34, 211, 238, 0.65);
    color: #ecfeff;
    background: rgba(8, 47, 73, 0.85);
}

.doc-editor__image-controls {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    margin-left: 0.35rem;
    padding-left: 0.35rem;
    border-left: 1px solid rgba(51, 65, 85, 0.9);
}

.doc-editor__size-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.5rem;
    height: 2rem;
    border: 1px solid rgba(71, 85, 105, 0.8);
    background: rgba(15, 23, 42, 0.8);
    color: #cbd5e1;
    font-size: 10px;
    transition: 160ms ease;
}

.doc-editor__size-chip:hover,
.doc-editor__size-chip--active {
    border-color: rgba(34, 211, 238, 0.65);
    color: #ecfeff;
    background: rgba(8, 47, 73, 0.85);
}

.doc-editor__size-range {
    width: 88px;
    accent-color: #22d3ee;
}

.doc-editor__select-shell {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    margin-left: 0.35rem;
    padding-left: 0.35rem;
    border-left: 1px solid rgba(51, 65, 85, 0.9);
}

.doc-editor__select-label {
    font-size: 10px;
    text-transform: uppercase;
    color: #94a3b8;
}

.doc-editor__select {
    height: 2rem;
    min-width: 4.25rem;
    border: 1px solid rgba(71, 85, 105, 0.8);
    background: rgba(15, 23, 42, 0.8);
    color: #e2e8f0;
    font-size: 10px;
    padding: 0 0.55rem;
    outline: none;
}

.doc-editor__status {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    margin-left: auto;
    font-size: 10px;
    text-transform: uppercase;
    color: #67e8f9;
}

.doc-editor__status-dot {
    width: 8px;
    height: 8px;
    background: #22d3ee;
    border-radius: 999px;
    animation: editorPulse 1.1s ease-in-out infinite;
}

.doc-editor__surface {
    position: relative;
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    overscroll-behavior: contain;
    scrollbar-width: thin;
    scrollbar-color: rgba(34, 211, 238, 0.45) rgba(15, 23, 42, 0.35);
}

:deep(.doc-editor__content) {
    min-height: 100%;
    padding: 1.5rem;
    box-sizing: border-box;
    outline: none;
    color: #e2e8f0;
    font-family: "Georgia", "Times New Roman", serif;
    font-size: 18px;
    line-height: 1.8;
}

:deep(.doc-editor__content p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    color: #64748b;
    float: left;
    height: 0;
    pointer-events: none;
}

:deep(.doc-editor__content h2) {
    margin-top: 1.4rem;
    margin-bottom: 0.8rem;
    font-size: 1.5rem;
    line-height: 1.35;
    color: #f8fafc;
}

:deep(.doc-editor__content p) {
    margin-bottom: 1rem;
}

:deep(.doc-editor__content ul) {
    padding-left: 1.5rem;
    list-style: disc;
    margin-bottom: 1rem;
}

:deep(.doc-editor__content blockquote) {
    border-left: 3px solid rgba(34, 211, 238, 0.6);
    margin: 1.25rem 0;
    padding-left: 1rem;
    color: #cbd5e1;
}

:deep(.doc-editor__content a) {
    color: #67e8f9;
    text-decoration: underline;
}

:deep(.doc-editor__content img) {
    display: block;
    max-width: 100%;
    border: 1px solid rgba(71, 85, 105, 0.8);
    margin: 1rem 0;
}

.doc-editor__overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(8, 47, 73, 0.2);
    color: #ecfeff;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    pointer-events: none;
}

@keyframes editorPulse {
    0%,
    100% {
        opacity: 0.45;
        transform: scale(0.92);
    }
    50% {
        opacity: 1;
        transform: scale(1);
    }
}
</style>
