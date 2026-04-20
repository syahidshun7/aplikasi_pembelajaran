<script setup>
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { Extension, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyle } from '@tiptap/extension-text-style';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const IMAGE_ALIGN_OPTIONS = ['left', 'center', 'right'];

const normalizeImageWidth = (value) => {
    const parsed = Number.parseInt(String(value || '').replace('%', ''), 10);
    const safe = Number.isFinite(parsed) ? Math.min(100, Math.max(25, parsed)) : 100;
    return `${safe}%`;
};

const parseWidthFromStyle = (styleValue = '') => {
    const widthMatch = String(styleValue || '').match(/width\s*:\s*([^;]+)/i);
    if (!widthMatch?.[1]) {
        return '100%';
    }

    return normalizeImageWidth(widthMatch[1]);
};

const parseImageAlign = (element) => {
    const explicitAlign = String(element?.getAttribute('data-align') || '').trim().toLowerCase();
    if (IMAGE_ALIGN_OPTIONS.includes(explicitAlign)) {
        return explicitAlign;
    }

    const className = String(element?.getAttribute('class') || '');
    if (className.includes('creation-image--right')) {
        return 'right';
    }
    if (className.includes('creation-image--left')) {
        return 'left';
    }
    if (className.includes('creation-image--center')) {
        return 'center';
    }

    const styleText = String(element?.getAttribute('style') || '').toLowerCase();
    if (styleText.includes('margin-left: auto') && styleText.includes('margin-right: auto')) {
        return 'center';
    }
    if (styleText.includes('margin-left: auto')) {
        return 'right';
    }

    return 'left';
};

const normalizeImageAlign = (value) => {
    const normalized = String(value || '').trim().toLowerCase();
    if (IMAGE_ALIGN_OPTIONS.includes(normalized)) {
        return normalized;
    }

    return 'left';
};

const buildImageInlineStyle = ({ width, align }) => {
    const styleChunks = [
        `width: ${normalizeImageWidth(width)};`,
        'display: block;',
    ];

    if (align === 'center') {
        styleChunks.push('margin-left: auto;', 'margin-right: auto;');
    } else if (align === 'right') {
        styleChunks.push('margin-left: auto;', 'margin-right: 0;');
    } else {
        styleChunks.push('margin-left: 0;', 'margin-right: auto;');
    }

    return styleChunks.join(' ');
};

const ResizableImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            width: {
                default: '100%',
                parseHTML: (element) => normalizeImageWidth(
                    element?.getAttribute('data-width')
                    || element?.style?.width
                    || parseWidthFromStyle(element?.getAttribute('style')),
                ),
            },
            align: {
                default: 'left',
                parseHTML: (element) => parseImageAlign(element),
            },
        };
    },
    renderHTML({ HTMLAttributes }) {
        const width = normalizeImageWidth(HTMLAttributes.width);
        const align = normalizeImageAlign(HTMLAttributes.align);
        const inheritedClass = String(HTMLAttributes.class || '').trim();
        const mergedClass = ['creation-image', `creation-image--${align}`, inheritedClass]
            .filter(Boolean)
            .join(' ');
        const existingStyle = String(HTMLAttributes.style || '').trim().replace(/\s+/g, ' ');
        const computedStyle = buildImageInlineStyle({ width, align });
        const mergedStyle = [existingStyle, computedStyle].filter(Boolean).join(' ');

        return ['img', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
            class: mergedClass,
            style: mergedStyle,
            'data-width': width,
            'data-align': align,
        })];
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
    restoreAfter: {
        type: Number,
        default: 0,
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

        const savedAt = Number(parsed.savedAt || 0);
        const restoreAfter = Number(props.restoreAfter || 0);

        if (restoreAfter > 0 && (!savedAt || savedAt < restoreAfter)) {
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
        Table.configure({
            resizable: true,
            HTMLAttributes: {
                class: 'doc-table',
            },
        }),
        TableRow,
        TableHeader,
        TableCell,
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
        id: 'bold',
        icon: 'fi fi-rr-bold',
        title: 'Bold',
        active: editor.value?.isActive('bold'),
        action: () => editor.value?.chain().focus().toggleBold().run(),
    },
    {
        id: 'italic',
        icon: 'fi fi-rr-italic',
        title: 'Italic',
        active: editor.value?.isActive('italic'),
        action: () => editor.value?.chain().focus().toggleItalic().run(),
    },
    {
        id: 'heading',
        icon: 'fi fi-rr-heading',
        title: 'Heading',
        active: editor.value?.isActive('heading', { level: 2 }),
        action: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run(),
    },
    {
        id: 'bullet',
        icon: 'fi fi-rr-list',
        title: 'Bullet list',
        active: editor.value?.isActive('bulletList'),
        action: () => editor.value?.chain().focus().toggleBulletList().run(),
    },
    {
        id: 'ordered',
        label: 'OL',
        title: 'Numbered list',
        active: editor.value?.isActive('orderedList'),
        action: () => editor.value?.chain().focus().toggleOrderedList().run(),
    },
    {
        id: 'quote',
        icon: 'fi fi-rr-quote-right',
        title: 'Quote',
        active: editor.value?.isActive('blockquote'),
        action: () => editor.value?.chain().focus().toggleBlockquote().run(),
    },
    {
        id: 'link',
        icon: 'fi fi-rr-link',
        title: 'Link',
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
        id: 'align-left',
        icon: 'fi fi-rr-align-left',
        title: 'Align left',
        active: editor.value?.isActive({ textAlign: 'left' }),
        action: () => editor.value?.chain().focus().setTextAlign('left').run(),
    },
    {
        id: 'align-center',
        icon: 'fi fi-rr-align-center',
        title: 'Align center',
        active: editor.value?.isActive({ textAlign: 'center' }),
        action: () => editor.value?.chain().focus().setTextAlign('center').run(),
    },
    {
        id: 'align-right',
        label: 'R',
        title: 'Align right',
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

const selectedImageAlign = computed(() => {
    imageSelectionVersion.value;
    return normalizeImageAlign(editor.value?.getAttributes('image')?.align || 'left');
});

const setSelectedImageWidth = (width) => {
    if (!editor.value || !editor.value.isActive('image')) {
        return;
    }

    const normalizedWidth = normalizeImageWidth(width);
    editor.value.chain().focus().updateAttributes('image', { width: normalizedWidth }).run();
};

const setSelectedImageAlign = (align) => {
    if (!editor.value || !editor.value.isActive('image')) {
        return;
    }

    editor.value.chain().focus().updateAttributes('image', {
        align: normalizeImageAlign(align),
    }).run();
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

const isTableSelected = computed(() => {
    imageSelectionVersion.value;
    return Boolean(editor.value?.isActive('table'));
});

const tableActions = computed(() => ([
    {
        id: 'insert',
        label: 'TBL+',
        title: 'Insert table',
        active: isTableSelected.value,
        disabled: false,
        action: () => editor.value?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(),
    },
    {
        id: 'row',
        label: '+ROW',
        title: 'Add row',
        active: false,
        disabled: !isTableSelected.value,
        action: () => editor.value?.chain().focus().addRowAfter().run(),
    },
    {
        id: 'row-delete',
        label: '-ROW',
        title: 'Delete row',
        active: false,
        disabled: !isTableSelected.value,
        action: () => editor.value?.chain().focus().deleteRow().run(),
    },
    {
        id: 'col',
        label: '+COL',
        title: 'Add column',
        active: false,
        disabled: !isTableSelected.value,
        action: () => editor.value?.chain().focus().addColumnAfter().run(),
    },
    {
        id: 'col-delete',
        label: '-COL',
        title: 'Delete column',
        active: false,
        disabled: !isTableSelected.value,
        action: () => editor.value?.chain().focus().deleteColumn().run(),
    },
    {
        id: 'header',
        label: 'HEAD',
        title: 'Toggle header row',
        active: editor.value?.isActive('tableHeader'),
        disabled: !isTableSelected.value,
        action: () => editor.value?.chain().focus().toggleHeaderRow().run(),
    },
    {
        id: 'merge',
        label: 'MERGE',
        title: 'Merge or split cell',
        active: false,
        disabled: !isTableSelected.value,
        action: () => editor.value?.chain().focus().mergeOrSplit().run(),
    },
    {
        id: 'delete',
        label: 'TBL-',
        title: 'Delete table',
        active: false,
        disabled: !isTableSelected.value,
        action: () => editor.value?.chain().focus().deleteTable().run(),
    },
]));

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
                :key="item.id"
                type="button"
                class="doc-editor__tool"
                :class="{ 'doc-editor__tool--active': item.active }"
                :title="item.title"
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

            <div class="doc-editor__table-controls">
                <button
                    v-for="tableAction in tableActions"
                    :key="tableAction.id"
                    type="button"
                    class="doc-editor__table-chip"
                    :class="{ 'doc-editor__table-chip--active': tableAction.active }"
                    :disabled="tableAction.disabled"
                    :title="tableAction.title"
                    @click="tableAction.action"
                >
                    {{ tableAction.label }}
                </button>
            </div>

            <div v-if="isImageSelected" class="doc-editor__image-controls">
                <span class="doc-editor__group-label">Image</span>
                <button type="button" class="doc-editor__align-chip" :class="{ 'doc-editor__align-chip--active': selectedImageAlign === 'left' }" @click="setSelectedImageAlign('left')">
                    L
                </button>
                <button type="button" class="doc-editor__align-chip" :class="{ 'doc-editor__align-chip--active': selectedImageAlign === 'center' }" @click="setSelectedImageAlign('center')">
                    C
                </button>
                <button type="button" class="doc-editor__align-chip" :class="{ 'doc-editor__align-chip--active': selectedImageAlign === 'right' }" @click="setSelectedImageAlign('right')">
                    R
                </button>
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
    border: 1px solid rgba(148, 163, 184, 0.45);
    background: #ffffff;
    display: flex;
    flex-direction: column;
    min-height: 0;
    width: 100%;
    overflow: visible;
}

.doc-editor--drag {
    border-color: rgba(59, 130, 246, 0.62);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.18);
}

.doc-editor__toolbar {
    position: sticky;
    top: 0;
    z-index: 6;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
    border-bottom: 1px solid rgba(226, 232, 240, 1);
    padding: 0.85rem;
    background: rgba(248, 250, 252, 0.96);
    box-shadow: 0 1px 0 rgba(226, 232, 240, 0.8);
}

.doc-editor--scrolled .doc-editor__toolbar {
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08), 0 1px 0 rgba(226, 232, 240, 1);
}

.doc-editor__tool {
    display: inline-flex;
    height: 2rem;
    width: 2rem;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(203, 213, 225, 1);
    background: #ffffff;
    color: #334155;
    transition: 160ms ease;
}

.doc-editor__tool-label {
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
    letter-spacing: 0.04em;
}

.doc-editor__tool:hover,
.doc-editor__tool--active {
    border-color: rgba(37, 99, 235, 0.55);
    color: #1d4ed8;
    background: rgba(239, 246, 255, 0.95);
}

.doc-editor__table-controls {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    margin-left: 0.35rem;
    padding-left: 0.35rem;
    border-left: 1px solid rgba(203, 213, 225, 0.95);
}

.doc-editor__table-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.95rem;
    height: 2rem;
    padding: 0 0.35rem;
    border: 1px solid rgba(203, 213, 225, 1);
    background: #ffffff;
    color: #334155;
    font-size: 9px;
    letter-spacing: 0.03em;
    transition: 160ms ease;
}

.doc-editor__table-chip:hover,
.doc-editor__table-chip--active {
    border-color: rgba(37, 99, 235, 0.55);
    color: #1d4ed8;
    background: rgba(239, 246, 255, 0.95);
}

.doc-editor__table-chip:disabled {
    cursor: not-allowed;
    opacity: 0.45;
    border-color: rgba(203, 213, 225, 0.85);
    background: rgba(248, 250, 252, 0.8);
    color: #94a3b8;
}

.doc-editor__image-controls {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    margin-left: 0.35rem;
    padding-left: 0.35rem;
    border-left: 1px solid rgba(203, 213, 225, 0.95);
}

.doc-editor__group-label {
    font-size: 9px;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.08em;
}

.doc-editor__align-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border: 1px solid rgba(203, 213, 225, 1);
    background: #ffffff;
    color: #334155;
    font-size: 10px;
    transition: 160ms ease;
}

.doc-editor__align-chip:hover,
.doc-editor__align-chip--active {
    border-color: rgba(37, 99, 235, 0.55);
    color: #1d4ed8;
    background: rgba(239, 246, 255, 0.95);
}

.doc-editor__size-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.5rem;
    height: 2rem;
    border: 1px solid rgba(203, 213, 225, 1);
    background: #ffffff;
    color: #334155;
    font-size: 9px;
    transition: 160ms ease;
}

.doc-editor__size-chip:hover,
.doc-editor__size-chip--active {
    border-color: rgba(37, 99, 235, 0.55);
    color: #1d4ed8;
    background: rgba(239, 246, 255, 0.95);
}

.doc-editor__size-range {
    width: 88px;
    accent-color: #2563eb;
}

.doc-editor__select-shell {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    margin-left: 0.35rem;
    padding-left: 0.35rem;
    border-left: 1px solid rgba(203, 213, 225, 0.95);
}

.doc-editor__select-label {
    font-size: 10px;
    text-transform: uppercase;
    color: #64748b;
}

.doc-editor__select {
    height: 2rem;
    min-width: 4.25rem;
    border: 1px solid rgba(203, 213, 225, 1);
    background: #ffffff;
    color: #0f172a;
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
    color: #1d4ed8;
}

.doc-editor__status-dot {
    width: 8px;
    height: 8px;
    background: #2563eb;
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
    scrollbar-color: rgba(148, 163, 184, 0.95) rgba(241, 245, 249, 0.9);
}

:deep(.doc-editor__content) {
    min-height: 100%;
    padding: 1.5rem;
    box-sizing: border-box;
    outline: none;
    color: #0f172a;
    font-family: "Georgia", "Times New Roman", serif;
    font-size: 18px;
    line-height: 1.78;
    background: #ffffff;
}

:deep(.doc-editor__content p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    color: #94a3b8;
    float: left;
    height: 0;
    pointer-events: none;
}

:deep(.doc-editor__content h2) {
    margin-top: 1.4rem;
    margin-bottom: 0.8rem;
    font-size: 1.5rem;
    line-height: 1.35;
    color: #0f172a;
}

:deep(.doc-editor__content p) {
    margin-bottom: 1rem;
}

:deep(.doc-editor__content ul),
:deep(.doc-editor__content ol) {
    padding-left: 1.5rem;
    margin-bottom: 1rem;
}

:deep(.doc-editor__content ul) {
    list-style: disc;
}

:deep(.doc-editor__content ol) {
    list-style: decimal;
}

:deep(.doc-editor__content blockquote) {
    border-left: 3px solid rgba(37, 99, 235, 0.62);
    margin: 1.25rem 0;
    padding: 0.7rem 0.9rem;
    color: #334155;
    background: rgba(248, 250, 252, 0.9);
}

:deep(.doc-editor__content a) {
    color: #1d4ed8;
    text-decoration: underline;
}

:deep(.doc-editor__content img.creation-image) {
    display: block;
    max-width: 100%;
    border: 1px solid rgba(203, 213, 225, 1);
    margin: 1rem 0;
}

:deep(.doc-editor__content img.creation-image--left) {
    margin-left: 0;
    margin-right: auto;
}

:deep(.doc-editor__content img.creation-image--center) {
    margin-left: auto;
    margin-right: auto;
}

:deep(.doc-editor__content img.creation-image--right) {
    margin-left: auto;
    margin-right: 0;
}

:deep(.doc-editor__content .tableWrapper) {
    margin: 1.2rem 0;
    overflow-x: auto;
}

:deep(.doc-editor__content table),
:deep(.doc-editor__content table.doc-table) {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    border: 1px solid rgba(100, 116, 139, 0.85);
}

:deep(.doc-editor__content table th),
:deep(.doc-editor__content table td),
:deep(.doc-editor__content table.doc-table th),
:deep(.doc-editor__content table.doc-table td) {
    border: 1px solid rgba(100, 116, 139, 0.85);
    padding: 0.45rem 0.55rem;
    vertical-align: top;
}

:deep(.doc-editor__content table th),
:deep(.doc-editor__content table.doc-table th) {
    background: rgba(241, 245, 249, 0.96);
    color: #0f172a;
    font-weight: 700;
}

:deep(.doc-editor__content pre) {
    margin: 1rem 0;
    padding: 0.8rem;
    border: 1px solid rgba(203, 213, 225, 1);
    background: rgba(248, 250, 252, 0.9);
    overflow-x: auto;
}

.doc-editor__overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(59, 130, 246, 0.08);
    color: #1e3a8a;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    pointer-events: none;
}

@media (max-width: 1099px) {
    .doc-editor__toolbar {
        flex-wrap: nowrap;
        align-items: stretch;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    .doc-editor__toolbar > * {
        flex: 0 0 auto;
    }

    .doc-editor__tool,
    .doc-editor__align-chip,
    .doc-editor__size-chip,
    .doc-editor__table-chip {
        height: 2.2rem;
        min-height: 2.2rem;
    }

    .doc-editor__size-range {
        width: 72px;
    }

    .doc-editor__status {
        margin-left: 0;
        padding-left: 0.35rem;
        border-left: 1px solid rgba(203, 213, 225, 0.95);
    }

    .doc-editor__surface {
        min-height: max(50vh, 360px);
    }

    :deep(.doc-editor__content) {
        padding: 1rem;
        font-size: 16px;
        line-height: 1.72;
    }

    :deep(.doc-editor__content h2) {
        margin-top: 1.1rem;
        margin-bottom: 0.7rem;
        font-size: 1.3rem;
    }

    :deep(.doc-editor__content .tableWrapper) {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    :deep(.doc-editor__content table),
    :deep(.doc-editor__content table.doc-table) {
        width: max-content;
        min-width: 560px;
        table-layout: auto;
    }
}

@media (max-width: 640px) {
    .doc-editor__toolbar {
        gap: 0.4rem;
        padding: 0.6rem;
    }

    .doc-editor__select-label,
    .doc-editor__group-label {
        display: none;
    }

    .doc-editor__table-controls,
    .doc-editor__image-controls,
    .doc-editor__select-shell {
        margin-left: 0.15rem;
        padding-left: 0.2rem;
    }

    .doc-editor__table-chip {
        min-width: 2.65rem;
        padding: 0 0.25rem;
        font-size: 8px;
    }

    .doc-editor__select {
        min-width: 3.6rem;
        font-size: 9px;
    }

    .doc-editor__surface {
        min-height: max(56vh, 410px);
    }

    :deep(.doc-editor__content) {
        padding: 0.85rem;
        font-size: 15px;
    }

    :deep(.doc-editor__content table),
    :deep(.doc-editor__content table.doc-table) {
        min-width: 500px;
    }
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
