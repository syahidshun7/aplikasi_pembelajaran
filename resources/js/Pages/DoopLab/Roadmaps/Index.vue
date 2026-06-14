<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    roadmaps: { type: Array, default: () => [] },
    activeRoadmap: { type: Object, default: null },
    roadmapLabPermissions: { type: Object, default: () => ({}) },
    availableGuides: { type: Array, default: () => [] },
    availableQuests: { type: Array, default: () => [] },
    assignableUsers: { type: Array, default: () => [] },
    enrolledUsers: { type: Array, default: () => [] },
    studentsOverview: { type: Array, default: () => [] },
});

const page = usePage();
const flashMessage = computed(() => String(page.props?.flash?.message || ''));
const pageUrl = computed(() => String(page.url || ''));
const workspaceMode = ref(false);

const syncWorkspaceModeFromUrl = () => {
    const rawUrl = pageUrl.value;
    const queryString = rawUrl.includes('?') ? rawUrl.split('?')[1] : '';
    const params = new URLSearchParams(queryString);
    workspaceMode.value = params.get('workspace') === '1';
};

const isWorkspaceMode = computed(() => {
    return workspaceMode.value;
});

const activeRoadmap = computed(() => props.activeRoadmap || null);
const hasActiveRoadmap = computed(() => Boolean(activeRoadmap.value?.uuid));

const draftSections = ref([]);
const draftNodes = ref([]);
const draftTextBlocks = ref([]);

const sections = computed(() => draftSections.value);
const nodes = computed(() => draftNodes.value);
const textBlocks = computed(() => draftTextBlocks.value);
const edges = computed(() => Array.isArray(activeRoadmap.value?.edges) ? activeRoadmap.value.edges : []);

const boardRef = ref(null);
const dragState = ref(null);
const showCreateForm = ref(false);
const layoutSaving = ref(false);
const dirtySectionUuids = ref(new Set());
const dirtyNodeUuids = ref(new Set());
const dirtyTextBlockUuids = ref(new Set());
const inlineTitleDraft = ref(null);
const selectedItem = ref(null);
const hoveredEdgeUuid = ref('');
const connectMode = ref(false);
const connectFromUuid = ref('');
const resourcePicker = ref({ type: 'guide', id: '' });

const colorPresets = [
    { bg: 'transparent', text: '#e6f6ff', label: 'Clear' },
    { bg: '#dbeafe', text: '#1e3a8a' },
    { bg: '#bfdbfe', text: '#1e3a8a' },
    { bg: '#bbf7d0', text: '#14532d' },
    { bg: '#fde68a', text: '#78350f' },
    { bg: '#fed7aa', text: '#7c2d12' },
    { bg: '#fecaca', text: '#7f1d1d' },
    { bg: '#fbcfe8', text: '#831843' },
    { bg: '#e9d5ff', text: '#581c87' },
    { bg: '#a7f3d0', text: '#064e3b' },
    { bg: '#cffafe', text: '#155e75' },
    { bg: '#1e293b', text: '#f1f5f9' },
    { bg: '#0f172a', text: '#f8fafc' },
    { bg: '#ffffff', text: '#0f172a' },
    { bg: '#f1f5f9', text: '#0f172a' },
];

const hasPendingLayoutChanges = computed(() => {
    return dirtySectionUuids.value.size > 0 || dirtyNodeUuids.value.size > 0 || dirtyTextBlockUuids.value.size > 0;
});

const clampValue = (value, min, max) => {
    return Math.min(max, Math.max(min, value));
};

const getBoardDimensions = () => {
    const boardElement = boardRef.value;
    const width = Number(boardElement?.clientWidth || boardWidth.value || 0);
    const height = Number(boardElement?.clientHeight || boardHeight.value || 0);

    return {
        width: Math.max(0, Math.round(width)),
        height: Math.max(0, Math.round(height)),
    };
};

const nodeMap = computed(() => {
    const pairs = nodes.value.map((item) => [String(item.uuid), item]);
    return new Map(pairs);
});

const boardWidth = computed(() => {
    const sectionMax = sections.value.map((section) => Number(section.x || 0) + Number(section.width || 0));
    const nodeMax = nodes.value.map((node) => Number(node.x || 0) + Number(node.width || 0));
    const textMax = textBlocks.value.map((textBlock) => Number(textBlock.x || 0) + Number(textBlock.width || 0));
    const maxValue = Math.max(1000, ...sectionMax, ...nodeMax, ...textMax);
    return maxValue + 120;
});

const boardHeight = computed(() => {
    const sectionMax = sections.value.map((section) => Number(section.y || 0) + Number(section.height || 0));
    const nodeMax = nodes.value.map((node) => Number(node.y || 0) + Number(node.height || 0));
    const textMax = textBlocks.value.map((textBlock) => Number(textBlock.y || 0) + Number(textBlock.height || 0));
    const maxValue = Math.max(680, ...sectionMax, ...nodeMax, ...textMax);
    return maxValue + 120;
});

const edgePaths = computed(() => {
    return edges.value
        .map((edge) => {
            const fromNode = nodeMap.value.get(String(edge.from_node_uuid || ''));
            const toNode = nodeMap.value.get(String(edge.to_node_uuid || ''));
            if (!fromNode || !toNode) return null;

            const sx = Number(fromNode.x || 0) + Number(fromNode.width || 0) / 2;
            const sy = Number(fromNode.y || 0) + Number(fromNode.height || 0) / 2;
            const tx = Number(toNode.x || 0) + Number(toNode.width || 0) / 2;
            const ty = Number(toNode.y || 0) + Number(toNode.height || 0) / 2;
            const curve = Number(edge.curvature || 0.35);
            const dx = tx - sx;
            const c1x = sx + (dx * curve);
            const c2x = tx - (dx * curve);
            const midX = (sx + tx) / 2;
            const midY = (sy + ty) / 2;

            return {
                uuid: String(edge.uuid || ''),
                strokeColor: String(edge.stroke_color || '#334155'),
                d: `M ${sx} ${sy} C ${c1x} ${sy}, ${c2x} ${ty}, ${tx} ${ty}`,
                midX,
                midY,
                fromTitle: String(fromNode.title || ''),
                toTitle: String(toNode.title || ''),
            };
        })
        .filter(Boolean);
});

const roadmapForm = useForm({
    title: '',
    description: '',
    is_published: false,
});

const roadmapEditUuid = ref('');
const enrollForm = useForm({
    roadmap_uuid: '',
    user_ids: [],
});
const studentAssignForm = useForm({
    user_id: '',
    roadmap_uuids: [],
});
const showAssignModal = ref(false);
const showManageModal = ref(false);
const manageTargetUserId = ref('');

const sectionForm = useForm({
    title: '',
    x: 24,
    y: 24,
    width: 500,
    height: 260,
    bg_color: '#dbeafe',
    text_color: '#1e3a8a',
    sort_order: 0,
    workspace: 1,
});
const sectionEditUuid = ref('');

const nodeForm = useForm({
    title: '',
    section_uuid: '',
    x: 64,
    y: 64,
    width: 180,
    height: 72,
    bg_color: '#93c5fd',
    text_color: '#0f172a',
    sort_order: 0,
    workspace: 1,
});
const nodeEditUuid = ref('');

const textBlockForm = useForm({
    content: '',
    x: 120,
    y: 120,
    width: 320,
    height: 120,
    bg_color: 'transparent',
    text_color: '#e6f6ff',
    sort_order: 0,
    workspace: 1,
});
const textBlockEditUuid = ref('');

const edgeForm = useForm({
    from_node_uuid: '',
    to_node_uuid: '',
    stroke_color: '#334155',
    curvature: 0.35,
    workspace: 1,
});

const pickRoadmap = (roadmapUuid, workspace = false) => {
    const payload = { roadmap: roadmapUuid };
    if (workspace) payload.workspace = 1;

    router.get(route('dooplab.roadmaps.index'), payload, {
        preserveState: true,
        preserveScroll: true,
    });
};

const openWorkspace = () => {
    if (!hasActiveRoadmap.value) return;
    workspaceMode.value = true;
    pickRoadmap(activeRoadmap.value.uuid, true);
};

const backToRoadmapTable = () => {
    workspaceMode.value = false;
    if (hasActiveRoadmap.value) {
        pickRoadmap(activeRoadmap.value.uuid, false);
        return;
    }

    router.get(route('dooplab.roadmaps.index'), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const startEditRoadmap = () => {
    if (!hasActiveRoadmap.value) return;
    roadmapEditUuid.value = String(activeRoadmap.value.uuid || '');
    roadmapForm.title = String(activeRoadmap.value.title || '');
    roadmapForm.description = String(activeRoadmap.value.description || '');
    roadmapForm.is_published = Boolean(activeRoadmap.value.is_published);
};

const resetRoadmapForm = () => {
    roadmapEditUuid.value = '';
    roadmapForm.reset();
    roadmapForm.is_published = false;
};

const submitRoadmap = () => {
    if (roadmapEditUuid.value !== '') {
        roadmapForm.patch(route('dooplab.roadmaps.update', roadmapEditUuid.value));
        return;
    }

    roadmapForm.post(route('dooplab.roadmaps.store'));
};

const submitEnrollment = () => {
    if (!hasActiveRoadmap.value) return;
    enrollForm.roadmap_uuid = String(activeRoadmap.value.uuid || '');
    enrollForm.post(route('dooplab.roadmaps.enrollments.store'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            enrollForm.user_ids = [];
        },
    });
};

const unassignUser = (enrollmentUuid) => {
    if (!enrollmentUuid) return;
    router.delete(route('dooplab.roadmaps.enrollments.destroy', enrollmentUuid), {
        preserveState: true,
        preserveScroll: true,
    });
};

const manageEnrollment = (enrollmentUuid) => {
    if (!enrollmentUuid) return;
    router.get(route('dooplab.roadmaps.enrollments.show', enrollmentUuid));
};

const selectedStudentOverview = computed(() => {
    const sourceId = Number(manageTargetUserId.value || studentAssignForm.user_id || 0);
    const studentId = sourceId;
    if (!studentId) return null;
    return props.studentsOverview.find((item) => Number(item.user_id) === studentId) || null;
});

const openAssignModal = () => {
    showAssignModal.value = true;
};

const openRoadmapModal = (mode = 'create') => {
    if (mode === 'edit' && hasActiveRoadmap.value) {
        startEditRoadmap();
    } else {
        resetRoadmapForm();
    }
    showCreateForm.value = true;
};

const closeRoadmapModal = () => {
    showCreateForm.value = false;
    resetRoadmapForm();
};

const closeAssignModal = () => {
    showAssignModal.value = false;
    studentAssignForm.user_id = '';
    studentAssignForm.roadmap_uuids = [];
};

const openManageModal = (userId) => {
    manageTargetUserId.value = String(userId || '');
    showManageModal.value = true;
};

const closeManageModal = () => {
    showManageModal.value = false;
    manageTargetUserId.value = '';
};

const submitStudentAssign = () => {
    const studentId = Number(studentAssignForm.user_id || 0);
    if (!studentId || !studentAssignForm.roadmap_uuids.length) return;

    router.post(route('dooplab.roadmaps.enrollments.store'), {
        roadmap_uuid: String(studentAssignForm.roadmap_uuids[0]),
        user_ids: [studentId],
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            studentAssignForm.roadmap_uuids = [];
        },
    });
};

const deleteRoadmap = (roadmapUuid) => {
    if (!window.confirm('Hapus roadmap ini?')) return;
    router.delete(route('dooplab.roadmaps.destroy', roadmapUuid));
};

const startEditSection = (section) => {
    sectionEditUuid.value = String(section.uuid || '');
    sectionForm.title = String(section.title || '');
    sectionForm.x = Number(section.x || 24);
    sectionForm.y = Number(section.y || 24);
    sectionForm.width = Number(section.width || 500);
    sectionForm.height = Number(section.height || 260);
    sectionForm.bg_color = String(section.bg_color || '#dbeafe');
    sectionForm.text_color = String(section.text_color || '#1e3a8a');
    sectionForm.sort_order = Number(section.sort_order || 0);
};

const resetSectionForm = () => {
    sectionEditUuid.value = '';
    sectionForm.reset();
    sectionForm.x = 24;
    sectionForm.y = 24;
    sectionForm.width = 500;
    sectionForm.height = 260;
    sectionForm.bg_color = '#dbeafe';
    sectionForm.text_color = '#1e3a8a';
    sectionForm.sort_order = 0;
};

const submitSection = () => {
    if (!hasActiveRoadmap.value) return;
    if (sectionEditUuid.value !== '') {
        sectionForm.patch(route('dooplab.roadmaps.sections.update', sectionEditUuid.value), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => resetSectionForm(),
        });
        return;
    }

    sectionForm.post(route('dooplab.roadmaps.sections.store', activeRoadmap.value.uuid), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => resetSectionForm(),
    });
};

const deleteSection = (sectionUuid) => {
    const targetUuid = String(sectionUuid || '');
    if (!targetUuid) return;

    draftSections.value = draftSections.value.filter((item) => String(item.uuid) !== targetUuid);
    if (dirtySectionUuids.value.has(targetUuid)) {
        const next = new Set(dirtySectionUuids.value);
        next.delete(targetUuid);
        dirtySectionUuids.value = next;
    }

    router.delete(route('dooplab.roadmaps.sections.destroy', targetUuid), {
        data: { workspace: 1 },
        preserveState: true,
        preserveScroll: true,
        only: ['activeRoadmap', 'roadmaps'],
    });
};

const startEditNode = (node) => {
    nodeEditUuid.value = String(node.uuid || '');
    nodeForm.title = String(node.title || '');
    const matchedSection = sections.value.find((item) => Number(item.id) === Number(node.section_id || 0));
    nodeForm.section_uuid = String(matchedSection?.uuid || '');
    nodeForm.x = Number(node.x || 64);
    nodeForm.y = Number(node.y || 64);
    nodeForm.width = Number(node.width || 180);
    nodeForm.height = Number(node.height || 72);
    nodeForm.bg_color = String(node.bg_color || '#93c5fd');
    nodeForm.text_color = String(node.text_color || '#0f172a');
    nodeForm.sort_order = Number(node.sort_order || 0);
};

const resetNodeForm = () => {
    nodeEditUuid.value = '';
    nodeForm.reset();
    nodeForm.section_uuid = '';
    nodeForm.x = 64;
    nodeForm.y = 64;
    nodeForm.width = 180;
    nodeForm.height = 72;
    nodeForm.bg_color = '#93c5fd';
    nodeForm.text_color = '#0f172a';
    nodeForm.sort_order = 0;
};

const submitNode = () => {
    if (!hasActiveRoadmap.value) return;
    if (nodeEditUuid.value !== '') {
        nodeForm.patch(route('dooplab.roadmaps.nodes.update', nodeEditUuid.value), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => resetNodeForm(),
        });
        return;
    }

    nodeForm.post(route('dooplab.roadmaps.nodes.store', activeRoadmap.value.uuid), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => resetNodeForm(),
    });
};

const deleteNode = (nodeUuid) => {
    const targetUuid = String(nodeUuid || '');
    if (!targetUuid) return;

    draftNodes.value = draftNodes.value.filter((item) => String(item.uuid) !== targetUuid);
    if (dirtyNodeUuids.value.has(targetUuid)) {
        const next = new Set(dirtyNodeUuids.value);
        next.delete(targetUuid);
        dirtyNodeUuids.value = next;
    }

    router.delete(route('dooplab.roadmaps.nodes.destroy', targetUuid), {
        data: { workspace: 1 },
        preserveState: true,
        preserveScroll: true,
        only: ['activeRoadmap', 'roadmaps'],
    });
};

const startEditTextBlock = (textBlock) => {
    textBlockEditUuid.value = String(textBlock.uuid || '');
    textBlockForm.content = String(textBlock.content || '');
    textBlockForm.x = Number(textBlock.x || 120);
    textBlockForm.y = Number(textBlock.y || 120);
    textBlockForm.width = Number(textBlock.width || 320);
    textBlockForm.height = Number(textBlock.height || 120);
    textBlockForm.bg_color = String(textBlock.bg_color || 'transparent');
    textBlockForm.text_color = String(textBlock.text_color || '#e6f6ff');
    textBlockForm.sort_order = Number(textBlock.sort_order || 0);
};

const resetTextBlockForm = () => {
    textBlockEditUuid.value = '';
    textBlockForm.reset();
    textBlockForm.content = '';
    textBlockForm.x = 120;
    textBlockForm.y = 120;
    textBlockForm.width = 320;
    textBlockForm.height = 120;
    textBlockForm.bg_color = 'transparent';
    textBlockForm.text_color = '#e6f6ff';
    textBlockForm.sort_order = 0;
    textBlockForm.workspace = 1;
};

const submitTextBlock = () => {
    if (!hasActiveRoadmap.value) return;
    if (textBlockEditUuid.value !== '') {
        textBlockForm.patch(route('dooplab.roadmaps.text-blocks.update', textBlockEditUuid.value), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => resetTextBlockForm(),
        });
        return;
    }

    textBlockForm.post(route('dooplab.roadmaps.text-blocks.store', activeRoadmap.value.uuid), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => resetTextBlockForm(),
    });
};

const deleteTextBlock = (textBlockUuid) => {
    const targetUuid = String(textBlockUuid || '');
    if (!targetUuid) return;

    draftTextBlocks.value = draftTextBlocks.value.filter((item) => String(item.uuid) !== targetUuid);
    if (dirtyTextBlockUuids.value.has(targetUuid)) {
        const next = new Set(dirtyTextBlockUuids.value);
        next.delete(targetUuid);
        dirtyTextBlockUuids.value = next;
    }

    router.delete(route('dooplab.roadmaps.text-blocks.destroy', targetUuid), {
        data: { workspace: 1 },
        preserveState: true,
        preserveScroll: true,
        only: ['activeRoadmap', 'roadmaps'],
    });
};

const submitEdge = () => {
    if (!hasActiveRoadmap.value) return;
    edgeForm.post(route('dooplab.roadmaps.edges.store', activeRoadmap.value.uuid), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            edgeForm.reset();
            edgeForm.stroke_color = '#334155';
            edgeForm.curvature = 0.35;
        },
    });
};

const deleteEdge = (edgeUuid) => {
    const targetUuid = String(edgeUuid || '');
    if (!targetUuid) return;

    hoveredEdgeUuid.value = '';

    router.delete(route('dooplab.roadmaps.edges.destroy', targetUuid), {
        data: { workspace: 1 },
        preserveState: true,
        preserveScroll: true,
        only: ['activeRoadmap', 'roadmaps'],
    });
};

const showEdgeDelete = (edgeUuid) => {
    hoveredEdgeUuid.value = String(edgeUuid || '');
};

const hideEdgeDelete = () => {
    hoveredEdgeUuid.value = '';
};

const cloneSection = (section) => ({
    ...section,
    x: Number(section?.x || 0),
    y: Number(section?.y || 0),
    width: Number(section?.width || 0),
    height: Number(section?.height || 0),
    font_size: Number(section?.font_size || 20),
    text_align: String(section?.text_align || 'left'),
    text_valign: String(section?.text_valign || 'top'),
    sort_order: Number(section?.sort_order || 0),
});

const cloneNode = (node) => ({
    ...node,
    x: Number(node?.x || 0),
    y: Number(node?.y || 0),
    width: Number(node?.width || 0),
    height: Number(node?.height || 0),
    font_size: Number(node?.font_size || 28),
    text_align: String(node?.text_align || 'center'),
    text_valign: String(node?.text_valign || 'middle'),
    resource_items: Array.isArray(node?.resource_items) ? [...node.resource_items] : [],
    section_id: Number(node?.section_id || 0),
    sort_order: Number(node?.sort_order || 0),
});

const cloneTextBlock = (textBlock) => ({
    ...textBlock,
    content: String(textBlock?.content || ''),
    x: Number(textBlock?.x || 0),
    y: Number(textBlock?.y || 0),
    width: Number(textBlock?.width || 320),
    height: Number(textBlock?.height || 120),
    font_size: Number(textBlock?.font_size || 16),
    text_align: String(textBlock?.text_align || 'left'),
    text_valign: String(textBlock?.text_valign || 'top'),
    sort_order: Number(textBlock?.sort_order || 0),
});

const findSectionUuidById = (sectionId) => {
    const matched = sections.value.find((item) => Number(item.id) === Number(sectionId || 0));
    return String(matched?.uuid || '');
};

const markLayoutDirty = (type, uuid) => {
    const normalizedUuid = String(uuid || '');
    if (!normalizedUuid) return;

    if (type === 'section') {
        const next = new Set(dirtySectionUuids.value);
        next.add(normalizedUuid);
        dirtySectionUuids.value = next;
        return;
    }

    if (type === 'text') {
        const next = new Set(dirtyTextBlockUuids.value);
        next.add(normalizedUuid);
        dirtyTextBlockUuids.value = next;
        return;
    }

    const next = new Set(dirtyNodeUuids.value);
    next.add(normalizedUuid);
    dirtyNodeUuids.value = next;
};

const updateItemFontSize = (type, uuid, delta) => {
    const sourceList = type === 'section' ? draftSections.value : (type === 'text' ? draftTextBlocks.value : draftNodes.value);
    const target = sourceList.find((item) => String(item.uuid) === String(uuid || ''));
    if (!target) return;

    const defaultSize = type === 'section' ? 20 : (type === 'text' ? 16 : 28);
    const currentSize = Number(target.font_size || defaultSize);
    const nextSize = clampValue(currentSize + Number(delta || 0), 8, 120);

    if (nextSize === currentSize) return;

    target.font_size = nextSize;
    markLayoutDirty(type, target.uuid);
};

const updateItemAlign = (type, uuid, prop, value) => {
    const sourceList = type === 'section' ? draftSections.value : (type === 'text' ? draftTextBlocks.value : draftNodes.value);
    const target = sourceList.find((item) => String(item.uuid) === String(uuid || ''));
    if (!target) return;

    if (target[prop] === value) return;

    target[prop] = value;
    markLayoutDirty(type, target.uuid);
};

const resolveVerticalJustify = (value, fallback = 'top') => {
    const normalized = String(value || fallback);
    if (normalized === 'middle') return 'center';
    if (normalized === 'bottom') return 'flex-end';
    return 'flex-start';
};

const addNodeResource = (resourceType, resourceId) => {
    if (!selectedItem.value || selectedItem.value.type !== 'node') return;
    const type = String(resourceType || '');
    const id = Number(resourceId || 0);
    if (!type || !id) return;

    const target = draftNodes.value.find((item) => String(item.uuid) === String(selectedItem.value.uuid));
    if (!target) return;
    target.resource_items = Array.isArray(target.resource_items) ? target.resource_items : [];
    if (target.resource_items.some((r) => r.type === type && Number(r.id) === id)) return;
    target.resource_items.push({ type, id });
    markLayoutDirty('node', target.uuid);
};

const removeNodeResource = (resourceType, resourceId) => {
    if (!selectedItem.value || selectedItem.value.type !== 'node') return;
    const target = draftNodes.value.find((item) => String(item.uuid) === String(selectedItem.value.uuid));
    if (!target) return;
    target.resource_items = (target.resource_items || []).filter((r) => !(r.type === resourceType && Number(r.id) === Number(resourceId)));
    markLayoutDirty('node', target.uuid);
};

const getResourceMeta = (resource) => {
    if (!resource) return null;
    if (resource.type === 'guide') {
        const found = props.availableGuides.find((g) => Number(g.id) === Number(resource.id));
        if (!found) return { label: '\ud83d\udcd6 Guide', href: '' };
        return { label: `\ud83d\udcd6 ${found.title}`, href: route('guides.user.show', found.uuid) };
    }
    if (resource.type === 'quest') {
        const found = props.availableQuests.find((q) => Number(q.id) === Number(resource.id));
        if (!found) return { label: '\u2694\ufe0f Quest', href: '' };
        return { label: `\u2694\ufe0f ${found.title}`, href: route('quests.show', found.uuid) };
    }
    return null;
};

const openResource = (resource) => {
    const meta = getResourceMeta(resource);
    if (!meta?.href) return;
    window.open(meta.href, '_blank');
};

const isInlineEditing = (type, uuid) => {
    if (!inlineTitleDraft.value) return false;
    return inlineTitleDraft.value.type === type && inlineTitleDraft.value.uuid === String(uuid || '');
};

const getItemLabel = (item) => {
    if (!item) return '';
    if (selectedItem.value?.type === 'text') return `Text: ${String(item.content || '').slice(0, 36) || '(empty)'}`;
    return `${selectedItem.value?.type === 'section' ? 'Sec' : 'Node'}: ${item.title || '(untitled)'}`;
};

const applyItemColor = (type, uuid, palette) => {
    const sourceList = type === 'section' ? draftSections.value : (type === 'text' ? draftTextBlocks.value : draftNodes.value);
    const target = sourceList.find((item) => String(item.uuid) === String(uuid || ''));
    if (!target) return;

    const nextBg = String(palette.bg || target.bg_color || '');
    const nextText = String(palette.text || target.text_color || '');

    if (target.bg_color === nextBg && target.text_color === nextText) {
        return;
    }

    target.bg_color = nextBg;
    target.text_color = nextText;
    markLayoutDirty(type, target.uuid);
};

const selectItem = (type, item) => {
    if (!item?.uuid) {
        selectedItem.value = null;
        return;
    }
    selectedItem.value = { type, uuid: String(item.uuid) };
};

const clearSelectedItem = () => {
    selectedItem.value = null;
};

const toggleConnectMode = () => {
    connectMode.value = !connectMode.value;
    connectFromUuid.value = '';
};

const cancelConnectMode = () => {
    connectMode.value = false;
    connectFromUuid.value = '';
};

const handleNodeClickForConnect = (node) => {
    if (!connectMode.value) return false;
    if (!node?.uuid) return false;

    const nodeUuid = String(node.uuid);

    if (connectFromUuid.value === '') {
        connectFromUuid.value = nodeUuid;
        return true;
    }

    if (connectFromUuid.value === nodeUuid) {
        connectFromUuid.value = '';
        return true;
    }

    edgeForm.from_node_uuid = connectFromUuid.value;
    edgeForm.to_node_uuid = nodeUuid;
    edgeForm.stroke_color = '#334155';
    edgeForm.curvature = 0.35;
    edgeForm.workspace = 1;

    edgeForm.post(route('dooplab.roadmaps.edges.store', activeRoadmap.value.uuid), {
        preserveState: true,
        preserveScroll: true,
        only: ['activeRoadmap'],
        onSuccess: () => {
            edgeForm.reset();
            edgeForm.stroke_color = '#334155';
            edgeForm.curvature = 0.35;
            edgeForm.workspace = 1;
            connectFromUuid.value = '';
        },
        onError: () => {
            connectFromUuid.value = '';
        },
    });

    return true;
};

const isItemSelected = (type, uuid) => {
    if (!selectedItem.value) return false;
    return selectedItem.value.type === type && selectedItem.value.uuid === String(uuid || '');
};

const selectedItemRecord = computed(() => {
    if (!selectedItem.value) return null;
    const sourceList = selectedItem.value.type === 'section'
        ? draftSections.value
        : (selectedItem.value.type === 'text' ? draftTextBlocks.value : draftNodes.value);
    return sourceList.find((item) => String(item.uuid) === String(selectedItem.value.uuid)) || null;
});

const startInlineTitleEdit = (type, item) => {
    if (!item?.uuid) return;

    inlineTitleDraft.value = {
        type,
        uuid: String(item.uuid),
        value: type === 'text' ? String(item.content || '') : String(item.title || ''),
    };
};

const cancelInlineTitleEdit = () => {
    inlineTitleDraft.value = null;
};

const commitInlineTitleEdit = () => {
    if (!inlineTitleDraft.value) return;

    const draft = inlineTitleDraft.value;
    const sourceList = draft.type === 'section' ? draftSections.value : (draft.type === 'text' ? draftTextBlocks.value : draftNodes.value);
    const target = sourceList.find((item) => String(item.uuid) === String(draft.uuid));

    if (target) {
        const nextTitle = String(draft.value || '').trim();
        const currentTitle = draft.type === 'text' ? String(target.content || '') : String(target.title || '');

        if (nextTitle !== '' && nextTitle !== currentTitle) {
            if (draft.type === 'text') {
                target.content = nextTitle;
            } else {
                target.title = nextTitle;
            }
            markLayoutDirty(draft.type, target.uuid);
        }
    }

    inlineTitleDraft.value = null;
};

const clearLayoutDirty = () => {
    dirtySectionUuids.value = new Set();
    dirtyNodeUuids.value = new Set();
    dirtyTextBlockUuids.value = new Set();
};

const persistSectionPosition = (section) => {
    return new Promise((resolve, reject) => {
        router.patch(route('dooplab.roadmaps.sections.update', section.uuid), {
            title: section.title,
            x: Number(section.x || 0),
            y: Number(section.y || 0),
            width: Number(section.width || 500),
            height: Number(section.height || 260),
            bg_color: section.bg_color,
            text_color: section.text_color,
            font_size: Number(section.font_size || 20),
            text_align: String(section.text_align || 'left'),
            text_valign: String(section.text_valign || 'top'),
            sort_order: Number(section.sort_order || 0),
            workspace: 1,
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: (errors) => reject(errors),
            onCancel: () => reject(new Error('cancelled')),
        });
    });
};

const persistNodePosition = (node) => {
    return new Promise((resolve, reject) => {
        router.patch(route('dooplab.roadmaps.nodes.update', node.uuid), {
            title: node.title,
            section_uuid: findSectionUuidById(node.section_id),
            x: Number(node.x || 0),
            y: Number(node.y || 0),
            width: Number(node.width || 180),
            height: Number(node.height || 72),
            bg_color: node.bg_color,
            text_color: node.text_color,
            font_size: Number(node.font_size || 28),
            text_align: String(node.text_align || 'center'),
            text_valign: String(node.text_valign || 'middle'),
            resource_items: (node.resource_items || []).map((r) => ({ type: String(r.type), id: Number(r.id) })),
            sort_order: Number(node.sort_order || 0),
            workspace: 1,
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: (errors) => reject(errors),
            onCancel: () => reject(new Error('cancelled')),
        });
    });
};

const persistTextBlockPosition = (textBlock) => {
    return new Promise((resolve, reject) => {
        router.patch(route('dooplab.roadmaps.text-blocks.update', textBlock.uuid), {
            content: textBlock.content,
            x: Number(textBlock.x || 0),
            y: Number(textBlock.y || 0),
            width: Number(textBlock.width || 320),
            height: Number(textBlock.height || 120),
            bg_color: textBlock.bg_color,
            text_color: textBlock.text_color,
            font_size: Number(textBlock.font_size || 16),
            text_align: String(textBlock.text_align || 'left'),
            text_valign: String(textBlock.text_valign || 'top'),
            sort_order: Number(textBlock.sort_order || 0),
            workspace: 1,
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: (errors) => reject(errors),
            onCancel: () => reject(new Error('cancelled')),
        });
    });
};

const saveLayoutChanges = async () => {
    if (!hasActiveRoadmap.value || !hasPendingLayoutChanges.value || layoutSaving.value) return;

    layoutSaving.value = true;

    const sectionUuids = [...dirtySectionUuids.value];
    const nodeUuids = [...dirtyNodeUuids.value];
    const textBlockUuids = [...dirtyTextBlockUuids.value];

    try {
        for (const uuid of sectionUuids) {
            const section = draftSections.value.find((item) => String(item.uuid) === uuid);
            if (!section) continue;
            await persistSectionPosition(section);
        }

        for (const uuid of nodeUuids) {
            const node = draftNodes.value.find((item) => String(item.uuid) === uuid);
            if (!node) continue;
            await persistNodePosition(node);
        }

        for (const uuid of textBlockUuids) {
            const textBlock = draftTextBlocks.value.find((item) => String(item.uuid) === uuid);
            if (!textBlock) continue;
            await persistTextBlockPosition(textBlock);
        }

        clearLayoutDirty();
    } catch {
    } finally {
        layoutSaving.value = false;
    }
};

const stopDragListeners = () => {
    window.removeEventListener('pointermove', onDragMove);
    window.removeEventListener('pointerup', onDragEnd);
    window.removeEventListener('pointercancel', onDragEnd);
};

const onDragMove = (event) => {
    if (!dragState.value) return;
    if (Number(event.pointerId || 0) !== Number(dragState.value.pointerId || 0)) return;

    if (dragState.value.mode === 'resize') {
        const deltaX = event.clientX - dragState.value.startClientX;
        const deltaY = event.clientY - dragState.value.startClientY;

        const nextWidth = clampValue(
            Math.round(dragState.value.originWidth + deltaX),
            Number(dragState.value.minWidth || 0),
            Number(dragState.value.maxWidth || 0),
        );

        const nextHeight = clampValue(
            Math.round(dragState.value.originHeight + deltaY),
            Number(dragState.value.minHeight || 0),
            Number(dragState.value.maxHeight || 0),
        );

        if (dragState.value.type === 'section') {
            const item = draftSections.value.find((section) => String(section.uuid) === dragState.value.uuid);
            if (!item) return;
            item.width = nextWidth;
            item.height = nextHeight;
            return;
        }

        if (dragState.value.type === 'text') {
            const item = draftTextBlocks.value.find((textBlock) => String(textBlock.uuid) === dragState.value.uuid);
            if (!item) return;
            item.width = nextWidth;
            item.height = nextHeight;
            return;
        }

        const item = draftNodes.value.find((node) => String(node.uuid) === dragState.value.uuid);
        if (!item) return;
        item.width = nextWidth;
        item.height = nextHeight;
        return;
    }

    const deltaX = event.clientX - dragState.value.startClientX;
    const deltaY = event.clientY - dragState.value.startClientY;
    const nextX = clampValue(
        Math.round(dragState.value.originX + deltaX),
        0,
        Number(dragState.value.maxX || 0),
    );
    const nextY = clampValue(
        Math.round(dragState.value.originY + deltaY),
        0,
        Number(dragState.value.maxY || 0),
    );

    if (dragState.value.type === 'section') {
        const item = draftSections.value.find((section) => String(section.uuid) === dragState.value.uuid);
        if (!item) return;
        item.x = nextX;
        item.y = nextY;
        return;
    }

    if (dragState.value.type === 'text') {
        const item = draftTextBlocks.value.find((textBlock) => String(textBlock.uuid) === dragState.value.uuid);
        if (!item) return;
        item.x = nextX;
        item.y = nextY;
        return;
    }

    const item = draftNodes.value.find((node) => String(node.uuid) === dragState.value.uuid);
    if (!item) return;
    item.x = nextX;
    item.y = nextY;
};

const onDragEnd = (event) => {
    if (!dragState.value) {
        stopDragListeners();
        return;
    }

    if (event && Number(event.pointerId || 0) !== Number(dragState.value.pointerId || 0)) return;

    if (
        dragState.value.sourceElement
        && typeof dragState.value.sourceElement.releasePointerCapture === 'function'
        && dragState.value.sourceElement.hasPointerCapture?.(dragState.value.pointerId)
    ) {
        dragState.value.sourceElement.releasePointerCapture(dragState.value.pointerId);
    }

    const currentDrag = { ...dragState.value };
    dragState.value = null;
    stopDragListeners();

    if (currentDrag.type === 'section') {
        const item = draftSections.value.find((section) => String(section.uuid) === currentDrag.uuid);
        if (!item) return;
        if (currentDrag.mode === 'resize') {
            if (Number(item.width) === Number(currentDrag.originWidth) && Number(item.height) === Number(currentDrag.originHeight)) return;
            markLayoutDirty('section', item.uuid);
            return;
        }
        if (Number(item.x) === Number(currentDrag.originX) && Number(item.y) === Number(currentDrag.originY)) return;
        markLayoutDirty('section', item.uuid);
        return;
    }

    if (currentDrag.type === 'text') {
        const item = draftTextBlocks.value.find((textBlock) => String(textBlock.uuid) === currentDrag.uuid);
        if (!item) return;
        if (currentDrag.mode === 'resize') {
            if (Number(item.width) === Number(currentDrag.originWidth) && Number(item.height) === Number(currentDrag.originHeight)) return;
            markLayoutDirty('text', item.uuid);
            return;
        }
        if (Number(item.x) === Number(currentDrag.originX) && Number(item.y) === Number(currentDrag.originY)) return;
        markLayoutDirty('text', item.uuid);
        return;
    }

    const item = draftNodes.value.find((node) => String(node.uuid) === currentDrag.uuid);
    if (!item) return;
    if (currentDrag.mode === 'resize') {
        if (Number(item.width) === Number(currentDrag.originWidth) && Number(item.height) === Number(currentDrag.originHeight)) return;
        markLayoutDirty('node', item.uuid);
        return;
    }
    if (Number(item.x) === Number(currentDrag.originX) && Number(item.y) === Number(currentDrag.originY)) return;
    markLayoutDirty('node', item.uuid);
};

const startDrag = (type, item, event, mode = 'move') => {
    if (!item?.uuid) return;
    if (connectMode.value && type === 'node' && mode === 'move') return;
    if (!event.isPrimary) return;
    if (event.pointerType === 'mouse' && event.button !== 0) return;

    const itemWidth = Number(item.width || (type === 'section' ? 500 : (type === 'text' ? 320 : 180)));
    const itemHeight = Number(item.height || (type === 'section' ? 260 : (type === 'text' ? 120 : 72)));
    const boardSize = getBoardDimensions();
    const maxX = Math.max(0, boardSize.width - Math.round(itemWidth));
    const maxY = Math.max(0, boardSize.height - Math.round(itemHeight));
    const maxWidth = Math.max(
        mode === 'resize' ? Number(item.x || 0) : 0,
        boardSize.width - Math.round(Number(item.x || 0)),
    );
    const maxHeight = Math.max(
        mode === 'resize' ? Number(item.y || 0) : 0,
        boardSize.height - Math.round(Number(item.y || 0)),
    );
    const sourceElement = event.currentTarget instanceof HTMLElement ? event.currentTarget : null;

    event.preventDefault();

    if (sourceElement?.setPointerCapture) {
        sourceElement.setPointerCapture(event.pointerId);
    }

    dragState.value = {
        type,
        mode,
        uuid: String(item.uuid),
        pointerId: Number(event.pointerId || 0),
        sourceElement,
        startClientX: Number(event.clientX || 0),
        startClientY: Number(event.clientY || 0),
        originX: Number(item.x || 0),
        originY: Number(item.y || 0),
        originWidth: Number(item.width || (type === 'section' ? 500 : (type === 'text' ? 320 : 180))),
        originHeight: Number(item.height || (type === 'section' ? 260 : (type === 'text' ? 120 : 72))),
        minWidth: type === 'section' ? 200 : 120,
        minHeight: type === 'section' ? 160 : (type === 'text' ? 60 : 50),
        maxWidth,
        maxHeight,
        maxX,
        maxY,
    };

    window.addEventListener('pointermove', onDragMove);
    window.addEventListener('pointerup', onDragEnd);
    window.addEventListener('pointercancel', onDragEnd);
};

const isDragging = (type, uuid) => {
    return dragState.value?.type === type && dragState.value?.uuid === String(uuid || '');
};

watch(activeRoadmap, () => {
    const incomingSections = Array.isArray(activeRoadmap.value?.sections)
        ? activeRoadmap.value.sections.map(cloneSection)
        : [];
    const incomingNodes = Array.isArray(activeRoadmap.value?.nodes)
        ? activeRoadmap.value.nodes.map(cloneNode)
        : [];
    const incomingTextBlocks = Array.isArray(activeRoadmap.value?.text_blocks)
        ? activeRoadmap.value.text_blocks.map(cloneTextBlock)
        : [];

    const dirtySecUuids = dirtySectionUuids.value;
    const dirtyNdUuids = dirtyNodeUuids.value;
    const dirtyTxtUuids = dirtyTextBlockUuids.value;

    draftSections.value = incomingSections.map((incoming) => {
        if (dirtySecUuids.has(String(incoming.uuid))) {
            const existing = draftSections.value.find((s) => String(s.uuid) === String(incoming.uuid));
            if (existing) {
                return {
                    ...incoming,
                    x: existing.x,
                    y: existing.y,
                    width: existing.width,
                    height: existing.height,
                    font_size: existing.font_size,
                    text_align: existing.text_align,
                    text_valign: existing.text_valign,
                    resource_items: existing.resource_items,
                };
            }
        }
        return incoming;
    });

    draftNodes.value = incomingNodes.map((incoming) => {
        if (dirtyNdUuids.has(String(incoming.uuid))) {
            const existing = draftNodes.value.find((n) => String(n.uuid) === String(incoming.uuid));
            if (existing) {
                return {
                    ...incoming,
                    x: existing.x,
                    y: existing.y,
                    width: existing.width,
                    height: existing.height,
                    font_size: existing.font_size,
                    text_align: existing.text_align,
                    text_valign: existing.text_valign,
                };
            }
        }
        return incoming;
    });

    draftTextBlocks.value = incomingTextBlocks.map((incoming) => {
        if (dirtyTxtUuids.has(String(incoming.uuid))) {
            const existing = draftTextBlocks.value.find((t) => String(t.uuid) === String(incoming.uuid));
            if (existing) {
                return {
                    ...incoming,
                    content: existing.content,
                    x: existing.x,
                    y: existing.y,
                    width: existing.width,
                    height: existing.height,
                    font_size: existing.font_size,
                    text_align: existing.text_align,
                    text_valign: existing.text_valign,
                };
            }
        }
        return incoming;
    });

    resetSectionForm();
    resetNodeForm();
    resetTextBlockForm();
    inlineTitleDraft.value = null;
    selectedItem.value = null;
    edgeForm.reset();
    edgeForm.stroke_color = '#334155';
    edgeForm.curvature = 0.35;
    edgeForm.workspace = 1;
}, { deep: true, immediate: true });

watch(pageUrl, () => {
    syncWorkspaceModeFromUrl();
}, { immediate: true });

onUnmounted(() => {
    stopDragListeners();
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="DoopLab Roadmap Lab" />

        <div class="lab-root">
            <Teleport to="body">
                <div class="lab-aurora">
                    <img src="/images/Gerbang_lab_pixel_art_website (3).jpeg" alt="" class="hidden md:block" />
                </div>
            </Teleport>
            <div class="lab-shell">
            <div class="lab-topbar">
                <div>
                    <p class="lab-eyebrow">DOOPLAB ROADMAP</p>
                    <h1 class="lab-title">Roadmap Lab</h1>
                    <p class="lab-subtitle">Bangun mentoring path yang terstruktur untuk student.</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('dooplab.dashboard')" class="lab-btn lab-btn--ghost">Back Dashboard</Link>
                </div>
            </div>

            <div v-if="flashMessage" class="lab-flash">
                {{ flashMessage }}
            </div>

            <div v-if="!isWorkspaceMode" class="space-y-4">
                <div class="panel space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="title">Roadmaps</h2>
                        <div class="flex gap-2">
                            <button class="btn-primary" type="button" @click="openRoadmapModal('create')">+ Buat Baru</button>
                        </div>
                    </div>

                    <div v-if="roadmaps.length" class="table-wrap">
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>S</th>
                                    <th>N</th>
                                    <th>E</th>
                                    <th>Updated</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in roadmaps"
                                    :key="`row-${item.uuid}`"
                                    :class="{ 'is-active': activeRoadmap?.uuid === item.uuid }"
                                >
                                    <td class="cell-title" @click="pickRoadmap(item.uuid, true)">{{ item.title }}</td>
                                    <td>{{ item.sections_count }}</td>
                                    <td>{{ item.nodes_count }}</td>
                                    <td>{{ item.edges_count }}</td>
                                    <td>{{ item.updated_at ? new Date(item.updated_at).toLocaleDateString() : '-' }}</td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn-secondary" type="button" @click="pickRoadmap(item.uuid, true)">Open</button>
                                            <button class="btn-secondary" type="button" @click="pickRoadmap(item.uuid, false); openRoadmapModal('edit')">Edit</button>
                                            <button class="btn-danger" type="button" @click="deleteRoadmap(item.uuid)">Del</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-[8px] text-slate-400 uppercase">Belum ada roadmap. Klik + Buat Baru.</p>

                </div>

                <div class="panel space-y-4">
                    <h2 class="title">Students</h2>
                    <div class="flex justify-end">
                        <button class="btn-primary" type="button" @click="openAssignModal">+ Assign Roadmap</button>
                    </div>

                    <div v-if="studentsOverview.length" class="space-y-2">
                        <div class="table-wrap">
                            <table class="mini-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Roadmaps</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="student in studentsOverview" :key="`stu-row-${student.user_id}`">
                                        <td class="cell-title" @click="openManageModal(student.user_id)">{{ student.name }}</td>
                                        <td>{{ student.email }}</td>
                                        <td>{{ student.enrollments.length }}</td>
                                        <td>
                                            <button class="btn-secondary" type="button" @click="openManageModal(student.user_id)">Manage</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="space-y-4">
                <div class="panel space-y-2">
                    <div class="visual-preview-head">
                        <h2 class="title">Visual Preview</h2>
                        <div class="visual-preview-actions">
                            <span v-if="hasPendingLayoutChanges" class="visual-save-state">Unsaved Layout</span>
                            <button class="btn-secondary" type="button" @click="backToRoadmapTable">Kembali</button>
                            <button
                                type="button"
                                class="btn-primary btn-save-icon"
                                :disabled="!hasPendingLayoutChanges || layoutSaving"
                                title="Save Layout"
                                @click="saveLayoutChanges"
                            >
                                <span aria-hidden="true">&#128190;</span>
                                {{ layoutSaving ? 'Saving...' : 'Save' }}
                            </button>
                        </div>
                    </div>
                    <div class="workspace-toolbar">
                        <form class="workspace-toolbar__form" @submit.prevent="submitSection">
                            <input v-model="sectionForm.title" type="text" required placeholder="Section title" class="field field--mini">
                            <button class="btn-primary" :disabled="sectionForm.processing" type="submit">{{ sectionEditUuid ? 'Upd' : '+Sec' }}</button>
                            <button v-if="sectionEditUuid" class="btn-secondary" type="button" @click="resetSectionForm">x</button>
                        </form>
                        <form class="workspace-toolbar__form" @submit.prevent="submitNode">
                            <input v-model="nodeForm.title" type="text" required placeholder="Node title" class="field field--mini">
                            <select v-model="nodeForm.section_uuid" class="field field--mini">
                                <option value="">No Sec</option>
                                <option v-for="section in sections" :key="section.uuid" :value="section.uuid">{{ section.title }}</option>
                            </select>
                            <button class="btn-primary" :disabled="nodeForm.processing" type="submit">{{ nodeEditUuid ? 'Upd' : '+Node' }}</button>
                            <button v-if="nodeEditUuid" class="btn-secondary" type="button" @click="resetNodeForm">x</button>
                        </form>
                        <form class="workspace-toolbar__form" @submit.prevent="submitTextBlock">
                            <textarea v-model="textBlockForm.content" required placeholder="Text area" class="field field--mini text-block-input" rows="1"></textarea>
                            <button class="btn-primary" :disabled="textBlockForm.processing" type="submit">{{ textBlockEditUuid ? 'Upd' : '+Text' }}</button>
                            <button v-if="textBlockEditUuid" class="btn-secondary" type="button" @click="resetTextBlockForm">x</button>
                        </form>
                        <div class="workspace-toolbar__form">
                            <button
                                type="button"
                                class="btn-primary"
                                :class="{ 'btn-primary--active': connectMode }"
                                @click="toggleConnectMode"
                            >
                                {{ connectMode ? '× Cancel Connect' : '🔗 Connect' }}
                            </button>
                            <span v-if="connectMode" class="workspace-ribbon__pill">
                                {{ connectFromUuid ? 'Klik node tujuan' : 'Klik node asal' }}
                            </span>
                        </div>
                    </div>

                    <div class="workspace-ribbon">
                        <div class="workspace-ribbon__group">
                            <span class="workspace-ribbon__label">Selected</span>
                            <span class="workspace-ribbon__pill" v-if="selectedItemRecord">
                                {{ getItemLabel(selectedItemRecord) }}
                            </span>
                            <span class="workspace-ribbon__pill workspace-ribbon__pill--muted" v-else>
                                Klik item di canvas
                            </span>
                            <button v-if="selectedItemRecord" type="button" class="btn-secondary" @click="clearSelectedItem">x</button>
                        </div>
                        <div class="workspace-ribbon__group" v-if="selectedItemRecord">
                            <span class="workspace-ribbon__label">Font</span>
                            <button type="button" class="btn-secondary" @click="updateItemFontSize(selectedItem.type, selectedItem.uuid, -2)">A-</button>
                            <span class="workspace-ribbon__value">{{ selectedItemRecord.font_size || (selectedItem.type === 'section' ? 20 : (selectedItem.type === 'text' ? 16 : 28)) }}px</span>
                            <button type="button" class="btn-secondary" @click="updateItemFontSize(selectedItem.type, selectedItem.uuid, 2)">A+</button>
                        </div>
                        <div class="workspace-ribbon__group" v-if="selectedItemRecord">
                            <span class="workspace-ribbon__label">H</span>
                            <button type="button" class="btn-secondary" :class="{ 'btn-secondary--active': selectedItemRecord.text_align === 'left' }" @click="updateItemAlign(selectedItem.type, selectedItem.uuid, 'text_align', 'left')">L</button>
                            <button type="button" class="btn-secondary" :class="{ 'btn-secondary--active': selectedItemRecord.text_align === 'center' }" @click="updateItemAlign(selectedItem.type, selectedItem.uuid, 'text_align', 'center')">C</button>
                            <button type="button" class="btn-secondary" :class="{ 'btn-secondary--active': selectedItemRecord.text_align === 'right' }" @click="updateItemAlign(selectedItem.type, selectedItem.uuid, 'text_align', 'right')">R</button>
                        </div>
                        <div class="workspace-ribbon__group" v-if="selectedItemRecord">
                            <span class="workspace-ribbon__label">V</span>
                            <button type="button" class="btn-secondary" :class="{ 'btn-secondary--active': selectedItemRecord.text_valign === 'top' }" @click="updateItemAlign(selectedItem.type, selectedItem.uuid, 'text_valign', 'top')">T</button>
                            <button type="button" class="btn-secondary" :class="{ 'btn-secondary--active': selectedItemRecord.text_valign === 'middle' }" @click="updateItemAlign(selectedItem.type, selectedItem.uuid, 'text_valign', 'middle')">M</button>
                            <button type="button" class="btn-secondary" :class="{ 'btn-secondary--active': selectedItemRecord.text_valign === 'bottom' }" @click="updateItemAlign(selectedItem.type, selectedItem.uuid, 'text_valign', 'bottom')">B</button>
                        </div>
                        <div class="workspace-ribbon__group" v-if="selectedItemRecord">
                            <span class="workspace-ribbon__label">Color</span>
                            <button
                                v-for="(preset, idx) in colorPresets"
                                :key="`ribbon-color-${idx}`"
                                type="button"
                                class="color-dot"
                                :class="{ 'color-dot--clear': preset.bg === 'transparent' }"
                                :style="{ background: preset.bg === 'transparent' ? 'transparent' : preset.bg }"
                                :title="preset.label || preset.bg"
                                @click="applyItemColor(selectedItem.type, selectedItem.uuid, preset)"
                            />
                        </div>
                        <div class="workspace-ribbon__group" v-if="selectedItemRecord && selectedItem.type === 'node'">
                            <span class="workspace-ribbon__label">Resource</span>
                            <select v-model="resourcePicker.type" class="field field--mini">
                                <option value="guide">Guide</option>
                                <option value="quest">Quest</option>
                            </select>
                            <select
                                v-if="resourcePicker.type === 'guide'"
                                v-model="resourcePicker.id"
                                class="field field--mini"
                            >
                                <option value="" disabled>Pilih guide</option>
                                <option v-for="guide in availableGuides" :key="`g-${guide.id}`" :value="guide.id">
                                    {{ guide.title }}
                                </option>
                            </select>
                            <select
                                v-if="resourcePicker.type === 'quest'"
                                v-model="resourcePicker.id"
                                class="field field--mini"
                            >
                                <option value="" disabled>Pilih quest</option>
                                <option v-for="quest in availableQuests" :key="`q-${quest.id}`" :value="quest.id">
                                    {{ quest.title }}
                                </option>
                            </select>
                            <button type="button" class="btn-primary" @click="addNodeResource(resourcePicker.type, resourcePicker.id); resourcePicker.id = ''">+</button>
                            <div v-if="selectedItemRecord.resource_items?.length" class="workspace-ribbon__resources">
                                <span
                                    v-for="(res, idx) in selectedItemRecord.resource_items"
                                    :key="`res-${idx}`"
                                    class="workspace-ribbon__resource-tag"
                                >
                                    {{ getResourceMeta(res)?.label || `${res.type}#${res.id}` }}
                                    <button type="button" @click="removeNodeResource(res.type, res.id)">×</button>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="hasActiveRoadmap" class="panel overflow-auto canvas-wrapper">
                    <div ref="boardRef" class="roadmap-board" :style="{ width: `${boardWidth}px`, height: `${boardHeight}px` }" @pointerdown.self="clearSelectedItem">
                        <svg class="edge-layer" :width="boardWidth" :height="boardHeight">
                            <defs>
                                <marker id="arrowHead" markerWidth="8" markerHeight="8" refX="6" refY="4" orient="auto" markerUnits="strokeWidth">
                                    <path d="M0,0 L8,4 L0,8 Z" fill="#334155" />
                                </marker>
                            </defs>
                            <path
                                v-for="item in edgePaths"
                                :key="item.uuid"
                                :d="item.d"
                                :stroke="item.strokeColor"
                                stroke-width="2"
                                fill="none"
                                marker-end="url(#arrowHead)"
                            />
                            <path
                                v-for="item in edgePaths"
                                :key="`hit-${item.uuid}`"
                                :d="item.d"
                                stroke="transparent"
                                stroke-width="14"
                                fill="none"
                                class="edge-hit"
                                @mouseenter="showEdgeDelete(item.uuid)"
                                @mouseleave="hideEdgeDelete"
                            />
                        </svg>

                        <button
                            v-for="item in edgePaths"
                            v-show="hoveredEdgeUuid === item.uuid"
                            :key="`edge-del-${item.uuid}`"
                            type="button"
                            class="edge-delete-btn"
                            :style="{ left: `${item.midX}px`, top: `${item.midY}px` }"
                            @mouseenter="showEdgeDelete(item.uuid)"
                            @mouseleave="hideEdgeDelete"
                            @click.stop="deleteEdge(item.uuid)"
                        >
                            ✕
                        </button>

                        <div
                            v-for="section in sections"
                            :key="section.uuid"
                            class="section-box"
                            :class="{ 'is-dragging': isDragging('section', section.uuid), 'is-selected': isItemSelected('section', section.uuid) }"
                            :style="{
                                left: `${section.x}px`,
                                top: `${section.y}px`,
                                width: `${section.width}px`,
                                height: `${section.height}px`,
                                background: section.bg_color,
                                color: section.text_color,
                                justifyContent: resolveVerticalJustify(section.text_valign, 'top'),
                            }"
                            @click.stop="selectItem('section', section)"
                            @pointerdown="startDrag('section', section, $event)"
                        >
                            <p
                                v-if="!isInlineEditing('section', section.uuid)"
                                class="section-title"
                                @pointerdown.stop
                                @dblclick.stop="startInlineTitleEdit('section', section)"
                                :style="{ fontSize: `${section.font_size || 20}px`, textAlign: section.text_align || 'left' }"
                            >
                                {{ section.title }}
                            </p>
                            <input
                                v-else
                                v-model="inlineTitleDraft.value"
                                type="text"
                                maxlength="180"
                                class="inline-title-input section-title-input"
                                autofocus
                                :style="{ fontSize: `${section.font_size || 20}px`, textAlign: section.text_align || 'left' }"
                                @pointerdown.stop
                                @click.stop
                                @blur="commitInlineTitleEdit"
                                @keydown.enter.prevent="commitInlineTitleEdit"
                                @keydown.esc.prevent="cancelInlineTitleEdit"
                            >
                            <div class="item-actions" @pointerdown.stop>
                                <button type="button" class="item-actions__del" @click="deleteSection(section.uuid)">Del</button>
                            </div>
                            <div class="resize-handle" @pointerdown.stop="startDrag('section', section, $event, 'resize')"></div>
                        </div>

                        <div
                            v-for="textBlock in textBlocks"
                            :key="textBlock.uuid"
                            class="text-block-box"
                            :class="{ 'is-dragging': isDragging('text', textBlock.uuid), 'is-selected': isItemSelected('text', textBlock.uuid) }"
                            :style="{
                                left: `${textBlock.x}px`,
                                top: `${textBlock.y}px`,
                                width: `${textBlock.width}px`,
                                height: `${textBlock.height}px`,
                                background: textBlock.bg_color,
                                color: textBlock.text_color,
                                justifyContent: resolveVerticalJustify(textBlock.text_valign, 'top'),
                            }"
                            @click.stop="selectItem('text', textBlock)"
                            @pointerdown="startDrag('text', textBlock, $event)"
                        >
                            <p
                                v-if="!isInlineEditing('text', textBlock.uuid)"
                                class="text-block-content"
                                @dblclick.stop="startInlineTitleEdit('text', textBlock)"
                                @pointerdown.stop
                                :style="{ fontSize: `${textBlock.font_size || 16}px`, textAlign: textBlock.text_align || 'left' }"
                            >
                                {{ textBlock.content }}
                            </p>
                            <textarea
                                v-else
                                v-model="inlineTitleDraft.value"
                                maxlength="3000"
                                class="inline-title-input text-block-textarea"
                                autofocus
                                :style="{ fontSize: `${textBlock.font_size || 16}px`, textAlign: textBlock.text_align || 'left' }"
                                @pointerdown.stop
                                @click.stop
                                @blur="commitInlineTitleEdit"
                                @keydown.esc.prevent="cancelInlineTitleEdit"
                            ></textarea>
                            <div class="item-actions" @pointerdown.stop>
                                <button type="button" class="item-actions__edit" @click="startEditTextBlock(textBlock)">Edit</button>
                                <button type="button" class="item-actions__del" @click="deleteTextBlock(textBlock.uuid)">Del</button>
                            </div>
                            <div class="resize-handle" @pointerdown.stop="startDrag('text', textBlock, $event, 'resize')"></div>
                        </div>

                        <div
                            v-for="node in nodes"
                            :key="node.uuid"
                            class="node-box"
                            :class="{
                                'is-dragging': isDragging('node', node.uuid),
                                'is-selected': isItemSelected('node', node.uuid),
                                'is-connect-target': connectMode,
                                'is-connect-source': connectMode && connectFromUuid === String(node.uuid),
                            }"
                            :style="{
                                left: `${node.x}px`,
                                top: `${node.y}px`,
                                width: `${node.width}px`,
                                height: `${node.height}px`,
                                background: node.bg_color,
                                color: node.text_color,
                                justifyContent: resolveVerticalJustify(node.text_valign, 'middle'),
                            }"
                            @click.stop="handleNodeClickForConnect(node) || selectItem('node', node)"
                            @pointerdown="startDrag('node', node, $event)"
                        >
                            <div
                                v-if="!isInlineEditing('node', node.uuid)"
                                class="node-title"
                                @pointerdown.stop
                                @dblclick.stop="startInlineTitleEdit('node', node)"
                                :style="{ fontSize: `${node.font_size || 28}px`, textAlign: node.text_align || 'center' }"
                            >
                                {{ node.title }}
                            </div>
                            <input
                                v-else
                                v-model="inlineTitleDraft.value"
                                type="text"
                                maxlength="180"
                                class="inline-title-input node-title-input"
                                autofocus
                                :style="{ fontSize: `${node.font_size || 28}px`, textAlign: node.text_align || 'center' }"
                                @pointerdown.stop
                                @click.stop
                                @blur="commitInlineTitleEdit"
                                @keydown.enter.prevent="commitInlineTitleEdit"
                                @keydown.esc.prevent="cancelInlineTitleEdit"
                            >
                            <button
                                v-for="(res, idx) in (node.resource_items || [])"
                                :key="`node-res-${node.uuid}-${idx}`"
                                type="button"
                                class="node-resource-badge"
                                :title="getResourceMeta(res)?.label"
                                @click.stop="openResource(res)"
                            >
                                {{ getResourceMeta(res)?.label }}
                            </button>
                            <div class="item-actions" @pointerdown.stop>
                                <button type="button" class="item-actions__del" @click="deleteNode(node.uuid)">Del</button>
                            </div>
                            <div class="resize-handle" @pointerdown.stop="startDrag('node', node, $event, 'resize')"></div>
                        </div>
                    </div>
                </div>

                <div v-if="!hasActiveRoadmap" class="panel text-slate-400 uppercase text-[8px]">
                    Belum ada roadmap aktif. Buat roadmap baru dulu.
                </div>
            </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <div v-if="showAssignModal" class="modal-backdrop" @click.self="closeAssignModal">
        <div class="modal-card">
            <div class="modal-head">
                <h3>Assign Roadmap ke Student</h3>
                <button type="button" class="btn-secondary" @click="closeAssignModal">×</button>
            </div>
            <div class="modal-body space-y-3">
                <label class="modal-label">Student</label>
                <select v-model="studentAssignForm.user_id" class="field">
                    <option value="" disabled>Pilih student</option>
                    <option v-for="user in assignableUsers" :key="`modal-u-${user.id}`" :value="user.id">
                        {{ user.name }} ({{ user.email }})
                    </option>
                </select>
                <label class="modal-label">Roadmap (Ctrl+klik untuk multi)</label>
                <select v-model="studentAssignForm.roadmap_uuids" multiple class="field" style="height:120px">
                    <option v-for="rm in roadmaps" :key="`modal-rm-${rm.uuid}`" :value="rm.uuid">
                        {{ rm.title }}
                    </option>
                </select>
                <div class="flex justify-end gap-2 pt-2">
                    <button class="btn-secondary" type="button" @click="closeAssignModal">Batal</button>
                    <button class="btn-primary" type="button" :disabled="!studentAssignForm.user_id || !studentAssignForm.roadmap_uuids.length" @click="submitStudentAssign(); closeAssignModal()">Assign</button>
                </div>
            </div>
        </div>
    </div>

    <div v-if="showCreateForm" class="modal-backdrop" @click.self="closeRoadmapModal">
        <div class="modal-card">
            <div class="modal-head">
                <h3>{{ roadmapEditUuid ? 'Edit Roadmap' : 'Buat Roadmap Baru' }}</h3>
                <button type="button" class="btn-secondary" @click="closeRoadmapModal">×</button>
            </div>
            <div class="modal-body space-y-3">
                <input v-model="roadmapForm.title" type="text" required placeholder="Roadmap title" class="field">
                <textarea v-model="roadmapForm.description" placeholder="Description (opsional)" class="field h-16 resize-none" />
                <label class="flex items-center gap-2 text-[10px] text-slate-300 uppercase font-['Inter']">
                    <input v-model="roadmapForm.is_published" type="checkbox">
                    Publish
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button class="btn-secondary" type="button" @click="closeRoadmapModal">Batal</button>
                    <button class="btn-primary" :disabled="roadmapForm.processing" type="button" @click="submitRoadmap(); closeRoadmapModal()">{{ roadmapEditUuid ? 'Update' : 'Create' }}</button>
                </div>
            </div>
        </div>
    </div>

    <div v-if="showManageModal" class="modal-backdrop" @click.self="closeManageModal">
        <div class="modal-card">
            <div class="modal-head">
                <h3>Manage Roadmaps</h3>
                <button type="button" class="btn-secondary" @click="closeManageModal">×</button>
            </div>
            <div class="modal-body space-y-3">
                <p v-if="selectedStudentOverview" class="text-[10px] text-slate-200">
                    {{ selectedStudentOverview.name }} &mdash; {{ selectedStudentOverview.email }}
                </p>
                <div v-if="selectedStudentOverview?.enrollments?.length" class="space-y-2">
                    <div v-for="enr in selectedStudentOverview.enrollments" :key="enr.enrollment_uuid" class="modal-enroll-row">
                        <span>{{ enr.roadmap_title }}</span>
                        <div class="flex gap-2">
                            <button type="button" class="btn-secondary" @click="manageEnrollment(enr.enrollment_uuid)">Manage</button>
                            <button type="button" class="btn-danger" @click="unassignUser(enr.enrollment_uuid)">Unassign</button>
                        </div>
                    </div>
                </div>
                <p v-else class="text-[8px] text-slate-400 uppercase">Belum ada roadmap di-assign.</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.panel {
    background: rgba(10, 16, 30, 0.72);
    border: 1px solid rgba(51, 65, 85, 0.8);
    padding: 0.75rem;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}

/* panel biasa tidak boleh overflow, tapi canvas-wrapper butuh overflow:auto */
.panel:not(.canvas-wrapper) {
    overflow: hidden;
}

.title {
    font-size: 9px;
    color: #93c5fd;
    text-transform: uppercase;
}

.field {
    width: 100%;
    border: 1px solid #334155;
    background: #020617;
    color: #cbd5e1;
    padding: 0.5rem;
    font-size: 11px;
    font-family: Inter, sans-serif;
}

.btn-primary,
.btn-secondary,
.btn-danger {
    border: 1px solid;
    padding: 0.4rem 0.55rem;
    text-transform: uppercase;
    font-size: 8px;
}

.btn-primary {
    border-color: #22d3ee;
    color: #67e8f9;
}

.btn-primary--active {
    background: rgba(34, 211, 238, 0.15);
    border-color: #67e8f9;
}

.btn-secondary {
    border-color: #64748b;
    color: #cbd5e1;
}

.btn-secondary--active {
    border-color: #22d3ee;
    color: #67e8f9;
}

.btn-danger {
    border-color: #ef4444;
    color: #fca5a5;
}

.roadmap-card-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.6rem;
}

.roadmap-card {
    border: 1px solid #334155;
    background: rgba(15, 23, 42, 0.55);
    padding: 0.65rem;
    cursor: pointer;
    transition: border-color 0.15s;
}

.roadmap-card:hover {
    border-color: #22d3ee;
}

.roadmap-card.is-active {
    border-color: #22d3ee;
    background: rgba(8, 145, 178, 0.18);
}

.roadmap-card__title {
    font-family: Inter, sans-serif;
    font-size: 12px;
    font-weight: 600;
    color: #e2e8f0;
    margin-bottom: 0.25rem;
}

.roadmap-card__meta {
    font-family: Inter, sans-serif;
    font-size: 10px;
    color: #64748b;
    margin-bottom: 0.4rem;
}

.roadmap-card__actions {
    display: flex;
    gap: 0.35rem;
}

.toolbar-panel {
    padding: 0.6rem;
}

.toolbar-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.toolbar-state {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.workspace-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: flex-start;
    margin-top: 0.4rem;
}

.workspace-toolbar__form {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
    min-width: 0;
    flex: 1 1 auto;
}

.text-block-input {
    min-width: 120px;
    width: 100%;
    max-width: 220px;
    height: 39px;
    resize: none;
}

.visual-preview-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.visual-preview-actions {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    min-width: 0;
}

.visual-preview-actions .btn-primary,
.visual-preview-actions .btn-secondary {
    white-space: normal;
    word-break: break-word;
    min-width: 0;
    flex-shrink: 1;
}

.visual-save-state {
    color: #fcd34d;
    font-size: 8px;
    text-transform: uppercase;
}

.btn-save-icon {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.workspace-ribbon {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
    align-items: center;
    margin-top: 0.4rem;
    padding-top: 0.4rem;
    border-top: 1px solid rgba(51, 65, 85, 0.55);
    min-width: 0;
    overflow: hidden;
}

.workspace-ribbon__group {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.workspace-ribbon__label {
    font-family: Inter, sans-serif;
    font-size: 10px;
    color: #93c5fd;
}

.workspace-ribbon__pill {
    font-family: Inter, sans-serif;
    font-size: 10px;
    color: #cbd5e1;
    padding: 0.2rem 0.45rem;
    border: 1px solid rgba(71, 85, 105, 0.7);
    border-radius: 999px;
}

.workspace-ribbon__pill--muted {
    color: #94a3b8;
}

.workspace-ribbon__value {
    font-family: Inter, sans-serif;
    font-size: 10px;
    color: #e2e8f0;
    min-width: 44px;
    text-align: center;
}

.workspace-ribbon__resources {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.workspace-ribbon__resource-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-family: Inter, sans-serif;
    font-size: 10px;
    color: #cbd5e1;
    border: 1px solid rgba(71, 85, 105, 0.7);
    border-radius: 999px;
    padding: 0.2rem 0.45rem;
}

.workspace-ribbon__resource-tag button {
    border: none;
    background: transparent;
    color: #fca5a5;
    cursor: pointer;
    padding: 0;
}

.field--mini {
    border: 1px solid #334155;
    background: #020617;
    color: #cbd5e1;
    padding: 0.3rem 0.4rem;
    font-size: 10px;
    font-family: Inter, sans-serif;
    max-width: 120px;
    min-width: 0;
    flex: 1 1 80px;
    width: 100%;
}

.enroll-multi {
    min-width: min(220px, 100%);
    max-width: 280px;
    width: 100%;
    height: 76px;
}

.enrolled-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    max-width: 420px;
}

.enrolled-list__tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-family: Inter, sans-serif;
    font-size: 10px;
    color: #cbd5e1;
    border: 1px solid rgba(71, 85, 105, 0.7);
    border-radius: 999px;
    padding: 0.2rem 0.45rem;
}

.enrolled-list__tag button {
    border: none;
    background: transparent;
    color: #fca5a5;
    cursor: pointer;
    padding: 0;
}

.table-wrap {
    overflow-x: auto;
    width: 100%;
    -webkit-overflow-scrolling: touch;
}

.mini-table {
    width: 100%;
    min-width: 480px;
    border-collapse: collapse;
    font-family: Inter, sans-serif;
    font-size: 11px;
}

.mini-table th {
    text-align: left;
    font-size: 9px;
    color: #93c5fd;
    text-transform: uppercase;
    padding: 6px 8px;
    border-bottom: 1px solid rgba(51, 65, 85, 0.6);
}

.mini-table td {
    padding: 6px 8px;
    color: #cbd5e1;
    border-bottom: 1px solid rgba(51, 65, 85, 0.3);
}

.mini-table tr.is-active td {
    background: rgba(8, 145, 178, 0.12);
}

.mini-table .cell-title {
    color: #e2e8f0;
    font-weight: 600;
    cursor: pointer;
}

.mini-table .cell-title:hover {
    color: #67e8f9;
}

.table-actions {
    display: flex;
    gap: 4px;
}

.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(2, 6, 23, 0.7);
    backdrop-filter: blur(2px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
    padding: 16px;
}

.modal-card {
    width: min(480px, 100%);
    background: rgba(15, 23, 42, 0.96);
    border: 1px solid rgba(34, 211, 238, 0.3);
    border-radius: 8px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    overflow: hidden;
}

.modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid rgba(51, 65, 85, 0.6);
}

.modal-head h3 {
    font-family: Inter, sans-serif;
    font-size: 13px;
    font-weight: 600;
    color: #e2e8f0;
    margin: 0;
}

.modal-body {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.modal-label {
    font-family: Inter, sans-serif;
    font-size: 10px;
    color: #94a3b8;
    text-transform: uppercase;
}

.modal-enroll-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 10px;
    border: 1px solid rgba(51, 65, 85, 0.6);
    border-radius: 4px;
    font-family: Inter, sans-serif;
    font-size: 11px;
    color: #cbd5e1;
}

.roadmap-board {
    position: relative;
    background-image:
        linear-gradient(to right, rgba(148, 163, 184, 0.12) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(148, 163, 184, 0.12) 1px, transparent 1px);
    background-size: 24px 24px;
    background-color: #0b1220;
    border-radius: 8px;
    border: 1px solid #1e293b;
}

.edge-layer {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 1;
}

.edge-hit {
    cursor: pointer;
    pointer-events: stroke;
}

.edge-delete-btn {
    position: absolute;
    transform: translate(-50%, -50%);
    width: 20px;
    height: 20px;
    border-radius: 999px;
    border: 1px solid rgba(185, 28, 28, 0.65);
    background: rgba(255, 255, 255, 0.94);
    color: #b91c1c;
    font-size: 11px;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 6;
    padding: 0;
}

.section-box {
    position: absolute;
    border-radius: 22px;
    border: 1px solid rgba(15, 23, 42, 0.12);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.3);
    padding: 12px;
    display: flex;
    flex-direction: column;
    cursor: grab;
    user-select: none;
    touch-action: none;
    z-index: 0;
}

.section-title {
    font-size: 20px;
    line-height: 1.2;
    font-weight: 700;
    font-family: Inter, sans-serif;
    width: 100%;
}

.node-box {
    position: absolute;
    border-radius: 12px;
    border: 1px solid rgba(15, 23, 42, 0.16);
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.14);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 10px;
    cursor: grab;
    user-select: none;
    touch-action: none;
    z-index: 2;
}

.text-block-box {
    position: absolute;
    border-radius: 0;
    border: 1px solid transparent;
    box-shadow: none;
    display: flex;
    flex-direction: column;
    padding: 12px;
    cursor: grab;
    user-select: none;
    touch-action: none;
    z-index: 1;
    white-space: pre-wrap;
}

.text-block-box:hover {
    border-color: rgba(87, 214, 255, 0.28);
    background-color: rgba(2, 8, 23, 0.12);
}

.text-block-content {
    width: 100%;
    margin: 0;
    line-height: 1.45;
    font-family: Inter, sans-serif;
    font-weight: 700;
    text-shadow: 0 1px 0 rgba(2, 8, 23, 0.22);
    cursor: text;
    pointer-events: auto;
}

.text-block-textarea {
    width: 100%;
    min-height: 100%;
    border: 1px solid rgba(87, 214, 255, 0.5);
    background: rgba(2, 8, 23, 0.78) !important;
    color: inherit !important;
    resize: none;
    line-height: 1.45;
    border-radius: 0;
    box-shadow: inset 0 0 0 1px rgba(87, 214, 255, 0.12);
    caret-color: #facc15;
    text-shadow: none;
}

.text-block-textarea::selection {
    background: rgba(87, 214, 255, 0.35);
    color: #f8fafc;
}

.section-box.is-dragging,
.node-box.is-dragging,
.text-block-box.is-dragging {
    cursor: grabbing;
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.22);
}

.section-box.is-selected,
.node-box.is-selected,
.text-block-box.is-selected {
    outline: 2px solid rgba(34, 211, 238, 0.9);
    outline-offset: 1px;
    border-color: rgba(87, 214, 255, 0.45);
}

.node-box.is-connect-target {
    cursor: pointer;
}

.node-box.is-connect-source {
    outline: 2px solid rgba(34, 197, 94, 0.95);
    outline-offset: 1px;
}

.item-actions {
    position: absolute;
    top: 4px;
    right: 4px;
    display: flex;
    gap: 4px;
    opacity: 0;
    transition: opacity 0.15s;
    z-index: 5;
}

.section-box:hover .item-actions,
.node-box:hover .item-actions,
.text-block-box:hover .item-actions {
    opacity: 1;
}

.item-actions button {
    font-family: Inter, sans-serif;
    font-size: 9px;
    padding: 2px 6px;
    border: 1px solid rgba(87, 214, 255, 0.45);
    background: rgba(2, 8, 23, 0.92);
    color: #67e8f9;
    cursor: pointer;
    border-radius: 4px;
}

.item-actions__edit {
    color: #67e8f9 !important;
    border-color: rgba(87, 214, 255, 0.55) !important;
}

.item-actions__del {
    color: #fca5a5 !important;
    border-color: rgba(239, 68, 68, 0.6) !important;
}

.color-dot {
    width: 16px;
    height: 16px;
    border: 1px solid rgba(15, 23, 42, 0.2);
    border-radius: 4px;
    cursor: pointer;
    padding: 0;
}

.color-dot--clear {
    position: relative;
    border-color: rgba(87, 214, 255, 0.55);
    background:
        linear-gradient(45deg, rgba(148, 163, 184, 0.16) 25%, transparent 25% 50%, rgba(148, 163, 184, 0.16) 50% 75%, transparent 75%),
        rgba(2, 8, 23, 0.45) !important;
    background-size: 8px 8px !important;
}

.color-dot--clear::after {
    content: '';
    position: absolute;
    left: 2px;
    right: 2px;
    top: 7px;
    height: 1px;
    background: #f87171;
    transform: rotate(-35deg);
}

.resize-handle {
    position: absolute;
    right: 0;
    bottom: 0;
    width: 14px;
    height: 14px;
    cursor: nwse-resize;
    opacity: 0;
    transition: opacity 0.15s;
    background:
        linear-gradient(135deg, transparent 0 50%, rgba(15, 23, 42, 0.5) 50% 60%, transparent 60% 70%, rgba(15, 23, 42, 0.5) 70% 80%, transparent 80%);
    z-index: 4;
    touch-action: none;
}

.text-block-box .resize-handle {
    right: 2px;
    bottom: 2px;
    width: 22px;
    height: 22px;
    border: 0;
    background:
        linear-gradient(135deg, transparent 0 52%, rgba(103, 232, 249, 0.95) 52% 58%, transparent 58% 66%, rgba(103, 232, 249, 0.95) 66% 72%, transparent 72% 80%, rgba(103, 232, 249, 0.95) 80% 86%, transparent 86%);
    box-shadow: none;
}

.section-box:hover .resize-handle,
.node-box:hover .resize-handle,
.text-block-box:hover .resize-handle,
.text-block-box.is-selected .resize-handle {
    opacity: 1;
}

.node-title {
    font-family: Inter, sans-serif;
    font-size: 28px;
    line-height: 1.2;
    font-weight: 700;
    text-align: center;
    width: 100%;
}

.node-resource-badge {
    font-family: Inter, sans-serif;
    font-size: 9px;
    padding: 2px 8px;
    border-radius: 999px;
    border: 1px solid rgba(15, 23, 42, 0.2);
    background: rgba(255, 255, 255, 0.7);
    color: #0f172a;
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 90%;
    align-self: center;
}

.inline-title-input {
    width: 100%;
    border: 1px solid rgba(15, 23, 42, 0.4);
    background: rgba(255, 255, 255, 0.85);
    color: inherit;
    font-family: Inter, sans-serif;
    font-weight: 700;
    border-radius: 8px;
    padding: 4px 8px;
    outline: none;
}

.section-title-input {
    font-size: 20px;
    line-height: 1.2;
}

.node-title-input {
    font-size: 28px;
    line-height: 1.2;
    text-align: center;
}

</style>

<style scoped>
.lab-root {
    --bg: #070d18;
    --panel: rgba(13, 21, 35, 0.88);
    --panel-soft: rgba(13, 21, 35, 0.72);
    --line: rgba(87, 214, 255, 0.24);
    --line-soft: rgba(148, 163, 184, 0.2);
    --text: #e2e8f0;
    --muted: #93a4ba;
    --cyan: #57d6ff;
    --cyan-2: #25b8ff;
    --danger: #ff6b7a;
    position: relative;
    min-height: calc(100vh - 64px);
    padding: clamp(8px, 2vw, 24px);
    color: var(--text);
    font-family: "Press Start 2P", Inter, system-ui, sans-serif;
    font-size: 10px;
    /* overflow-x:clip mencegah page-level horizontal scroll tanpa memblokir scroll di canvas-wrapper */
    overflow-x: clip;
    max-width: 100%;
    box-sizing: border-box;
}

.lab-aurora {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: -1;
}
.lab-aurora img {
    width: 100%; height: 100%;
    object-fit: cover; object-position: center;
    image-rendering: pixelated;
    opacity: 0.15;
    transform: translateZ(0);
    will-change: auto;
}

.lab-shell {
    position: relative;
    z-index: 1;
    display: grid;
    gap: 14px;
    min-width: 0;
    max-width: 100%;
}

/* Semua direct child grid item tidak boleh overflow */
.lab-shell > * {
    min-width: 0;
}

.lab-topbar,
.panel {
    border: 2px solid var(--line);
    background: linear-gradient(165deg, var(--panel), var(--panel-soft));
    box-shadow: 4px 4px 0 rgba(1, 6, 14, 0.9), inset 0 0 0 1px rgba(87, 214, 255, 0.08);
    border-radius: 0;
}

.lab-topbar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    flex-wrap: wrap;
}

.lab-topbar > div:first-child {
    min-width: 0;
    flex: 1 1 0;
}

.lab-eyebrow {
    margin: 0;
    font-size: 7px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--cyan);
}

.lab-title {
    margin: 2px 0 0;
    font-size: clamp(14px, 2vw, 18px);
    line-height: 1.05;
    font-weight: 700;
    color: #f1f5f9;
}

.lab-subtitle {
    margin: 6px 0 0;
    font-size: 9px;
    color: var(--muted);
    overflow-wrap: break-word;
    word-break: break-word;
}

.lab-btn {
    border: 1px solid var(--line-soft);
    color: #d6e4f8;
    padding: 6px 10px;
    font-size: 8px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    transition: all 0.15s ease;
    border-radius: 0;
    box-shadow: 2px 2px 0 rgba(1, 6, 14, 0.9);
}

.lab-btn--ghost {
    background: rgba(255, 255, 255, 0.02);
}

.lab-btn--ghost:hover {
    border-color: var(--cyan);
    color: #fff;
}

.lab-flash {
    padding: 8px 10px;
    border: 1px solid rgba(87, 246, 185, 0.35);
    background: rgba(16, 185, 129, 0.12);
    color: #a7f3d0;
    font-size: 8px;
    border-radius: 0;
}

.panel {
    padding: 14px;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}

.title {
    font-size: 9px;
    color: #b8d9ff;
    letter-spacing: 0.11em;
}

.field {
    border-radius: 0;
    border: 1px solid rgba(148, 163, 184, 0.35);
    background: rgba(2, 8, 23, 0.88);
    color: #d9e7f8;
    font-size: 10px;
    padding: 6px 8px;
}

.field:focus {
    outline: none;
    border-color: var(--cyan);
    box-shadow: 0 0 0 2px rgba(87, 214, 255, 0.2);
}

.btn-primary,
.btn-secondary,
.btn-danger {
    border-radius: 0;
    font-size: 8px;
    font-weight: 700;
    letter-spacing: 0.08em;
    transition: all 0.14s ease;
    box-shadow: 2px 2px 0 rgba(1, 6, 14, 0.9);
}

.btn-primary {
    border-color: rgba(87, 214, 255, 0.6);
    background: linear-gradient(180deg, rgba(87, 214, 255, 0.22), rgba(37, 184, 255, 0.12));
    color: #dcf4ff;
}

.btn-primary:hover { border-color: var(--cyan); }

.btn-secondary {
    border-color: rgba(148, 163, 184, 0.45);
    background: rgba(148, 163, 184, 0.06);
    color: #d6e4f8;
}

.btn-danger {
    border-color: rgba(255, 107, 122, 0.7);
    background: rgba(255, 107, 122, 0.08);
    color: #fecdd3;
}

.table-wrap {
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 0;
    overflow: auto;
    width: 100%;
}

.mini-table th {
    font-size: 10px;
    letter-spacing: 0.08em;
    color: #9ec7f5;
    background: rgba(8, 15, 30, 0.65);
}

.mini-table td {
    font-size: 12px;
    color: #d3deec;
}

.mini-table tr:hover td {
    background: rgba(87, 214, 255, 0.06);
}

.mini-table tr.is-active td {
    background: rgba(87, 214, 255, 0.12);
}

.table-actions .btn-secondary,
.table-actions .btn-danger {
    font-size: 9px;
    padding: 6px 10px;
}

.workspace-toolbar,
.workspace-ribbon {
    gap: 8px;
}

.visual-preview-head {
    margin-bottom: 8px;
}

.visual-preview-actions .btn-secondary,
.visual-preview-actions .btn-primary {
    padding: 7px 10px;
}

.btn-save-icon span {
    font-size: 12px;
    line-height: 1;
}

.roadmap-board {
    border: 1px solid rgba(87, 214, 255, 0.18);
    border-radius: 0;
    background-color: #091224;
    box-shadow: inset 0 0 0 1px rgba(87, 214, 255, 0.06);
}

.section-box,
.node-box,
.text-block-box {
    transition: box-shadow 0.14s ease, transform 0.14s ease;
    border-radius: 0 !important;
}

.section-box,
.node-box {
    box-shadow: 3px 3px 0 rgba(1, 6, 14, 0.7) !important;
}

.section-box:hover,
.node-box:hover {
    box-shadow: 0 12px 24px rgba(2, 10, 27, 0.35);
}

.modal-backdrop {
    backdrop-filter: blur(2px);
    background: rgba(2, 8, 23, 0.82);
}

.modal-card {
    width: min(560px, 96vw);
    border-radius: 0;
    border: 4px solid #3d415f;
    background: #1a1c2c;
    box-shadow: 8px 8px 0 rgba(0, 0, 0, 0.5);
    padding: 20px;
}

.modal-head {
    padding: 0 0 12px;
    border-bottom: 2px solid #3d415f;
    margin-bottom: 14px;
}

.modal-head h3 {
    font-size: 13px;
    color: #fff;
    font-weight: 700;
}

.modal-body {
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.modal-label {
    font-size: 10px;
    color: #8ea8bb;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.modal-enroll-row {
    border-radius: 0;
    border: 2px solid #3d415f;
    background: #0d1117;
    padding: 8px 10px;
    font-size: 12px;
    color: #cbd5e1;
}

.workspace-ribbon__pill,
.workspace-ribbon__resource-tag,
.enrolled-list__tag,
.node-resource-badge,
.node-status-badge,
.item-actions button,
.color-dot,
.inline-title-input,
.field--mini,
.modal-enroll-row,
.workspace-ribbon select,
.workspace-ribbon input,
.workspace-toolbar button,
.toolbar-state button {
    border-radius: 0 !important;
}

.lab-root {
    font-size: 11px;
}

.lab-root p,
.lab-root span,
.lab-root label,
.lab-root a,
.lab-root button,
.lab-root input,
.lab-root select,
.lab-root textarea,
.lab-root th,
.lab-root td {
    font-size: 11px;
}

.modal-card h3 {
    font-size: 13px !important;
}

.modal-card .field,
.modal-card .field--mini,
.modal-card .btn-primary,
.modal-card .btn-secondary,
.modal-card .btn-danger {
    font-size: 11px !important;
}

.modal-card .field,
.modal-card .field--mini,
.modal-card select,
.modal-card input,
.modal-card textarea {
    border: 2px solid #3d415f !important;
    background: #0d1117 !important;
    color: #cbd5e1 !important;
    font-family: "Press Start 2P", monospace !important;
    font-size: 11px !important;
    padding: 10px !important;
}

.modal-card .btn-primary,
.modal-card .btn-secondary,
.modal-card .btn-danger {
    border-bottom-width: 4px !important;
    border-right-width: 4px !important;
    border-color: #3d415f !important;
    font-family: "Press Start 2P", monospace !important;
    font-size: 9px !important;
    padding: 10px 14px !important;
    text-transform: uppercase !important;
    font-weight: bold !important;
    transition: all 0.15s !important;
}

.modal-card .btn-primary:active,
.modal-card .btn-secondary:active,
.modal-card .btn-danger:active {
    transform: translate(4px, 4px) !important;
    border-bottom-width: 0 !important;
    border-right-width: 0 !important;
}

.modal-card .btn-primary {
    background: #009999 !important;
    border-color: #006666 !important;
    color: #fff !important;
}

.modal-card .btn-secondary {
    background: rgba(26, 28, 44, 0.95) !important;
    color: #cbd5e1 !important;
}

.modal-card .btn-danger {
    background: rgba(239, 68, 68, 0.15) !important;
    border-color: #7f1d1d !important;
    color: #fca5a5 !important;
}

@media (max-width: 768px) {
    .modal-card { padding: 14px !important; width: min(100%, 96vw) !important; }
    .modal-card h3 { font-size: 12px !important; }
    .modal-label { font-size: 11px !important; }
    .modal-card .field,
    .modal-card .field--mini,
    .modal-card select,
    .modal-card input,
    .modal-card textarea { font-size: 12px !important; padding: 10px !important; }
    .modal-card .btn-primary,
    .modal-card .btn-secondary,
    .modal-card .btn-danger { font-size: 11px !important; padding: 10px 12px !important; }
    .modal-enroll-row { font-size: 11px !important; }
}

@media (max-width: 480px) {
    .modal-card {
        padding: 12px !important;
        max-height: calc(100dvh - 24px);
        overflow-y: auto;
    }

    .modal-body {
        overflow-y: auto;
        max-height: calc(100dvh - 120px);
    }

    .modal-head h3 {
        font-size: 11px !important;
    }

    .modal-enroll-row {
        flex-direction: column;
        gap: 8px;
        align-items: flex-start;
    }
}

@media (max-width: 900px) {
    .lab-topbar {
        flex-direction: column;
        align-items: flex-start;
    }

    .table-actions {
        flex-wrap: wrap;
    }

    .workspace-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .workspace-toolbar__form {
        flex-wrap: wrap;
    }

    .workspace-toolbar .field--mini {
        max-width: 100%;
        flex: 1 1 120px;
    }

    .workspace-ribbon {
        flex-direction: column;
        align-items: flex-start;
    }

    .workspace-ribbon__group {
        flex-wrap: wrap;
    }

    .visual-preview-head {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .visual-preview-actions {
        width: 100%;
        flex-wrap: wrap;
    }
}

@media (max-width: 600px) {
    .lab-root {
        padding: 8px;
    }

    .lab-topbar {
        padding: 10px 12px;
    }

    .lab-title {
        font-size: 12px !important;
    }

    .lab-subtitle {
        font-size: 8px;
    }

    .mini-table {
        font-size: 10px;
    }

    .mini-table th {
        font-size: 8px;
        padding: 5px 6px;
    }

    .mini-table td {
        padding: 5px 6px;
        font-size: 10px;
    }

    .btn-primary,
    .btn-secondary,
    .btn-danger {
        font-size: 8px;
        padding: 6px 8px;
    }
}

@media (max-width: 480px) {
    .workspace-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    .workspace-toolbar__form {
        width: 100%;
    }
    .workspace-ribbon {
        gap: 0.4rem;
    }
    .workspace-ribbon__group {
        flex-wrap: wrap;
    }
    .field--mini {
        max-width: 100%;
        flex: 1;
    }
}

/* Canvas — scroll container mandiri, tidak mendorong halaman */
.canvas-wrapper {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
}

@media (max-width: 768px) {
    .canvas-wrapper {
        max-height: 60vh;
    }
}
</style>
