<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    enrollment: { type: Object, required: true },
});

const roadmap = computed(() => props.enrollment?.roadmap || {});
const sections = computed(() => roadmap.value.sections || []);
const nodes = computed(() => roadmap.value.nodes || []);
const textBlocks = computed(() => roadmap.value.text_blocks || []);
const edges = computed(() => roadmap.value.edges || []);
const isOwner = computed(() => Boolean(props.enrollment?.is_owner));
const isMentor = computed(() => Boolean(props.enrollment?.is_mentor));
const canManage = computed(() => Boolean(props.enrollment?.can_manage));
const backHref = computed(() => {
    if (canManage.value) {
        return route('dooplab.roadmaps.index', {
            roadmap: roadmap.value.uuid,
        });
    }

    return route('dooplab.roadmaps.enrollments.index');
});
const backLabel = computed(() => canManage.value ? 'Back Roadmap' : 'Back Paths');

const selectedNode = ref(null);
const canvasWrapperRef = ref(null);

const clampValue = (value, min, max) => {
    return Math.min(max, Math.max(min, value));
};

const nodeMap = computed(() => {
    return new Map(nodes.value.map((n) => [String(n.uuid), n]));
});

const MIN_BOARD_WIDTH = 1600;
const MIN_BOARD_HEIGHT = 1000;
const MIN_ZOOM_SCALE = 0.4;
const MAX_ZOOM_SCALE = 2;
const ZOOM_STEP = 0.1;
const WHEEL_ZOOM_SENSITIVITY = 0.0022;
const MAX_WHEEL_DELTA = 42;

const zoomScale = ref(1);
const zoomGestureStartScale = ref(1);
const pinchStartDistance = ref(0);
const pinchStartScale = ref(1);

const boardWidth = computed(() => {
    const maxX = Math.max(MIN_BOARD_WIDTH, ...nodes.value.map((n) => Number(n.x || 0) + Number(n.width || 0)),
        ...sections.value.map((s) => Number(s.x || 0) + Number(s.width || 0)),
        ...textBlocks.value.map((t) => Number(t.x || 0) + Number(t.width || 0)));
    return maxX + 180;
});

const boardHeight = computed(() => {
    const maxY = Math.max(MIN_BOARD_HEIGHT, ...nodes.value.map((n) => Number(n.y || 0) + Number(n.height || 0)),
        ...sections.value.map((s) => Number(s.y || 0) + Number(s.height || 0)),
        ...textBlocks.value.map((t) => Number(t.y || 0) + Number(t.height || 0)));
    return maxY + 180;
});

const visualBoardWidth = computed(() => Math.round(boardWidth.value * zoomScale.value));
const visualBoardHeight = computed(() => Math.round(boardHeight.value * zoomScale.value));
const zoomPercent = computed(() => `${Math.round(zoomScale.value * 100)}%`);
const canvasStageStyle = computed(() => ({
    width: `${visualBoardWidth.value}px`,
    height: `${visualBoardHeight.value}px`,
}));
const roadmapBoardStyle = computed(() => ({
    width: `${boardWidth.value}px`,
    height: `${boardHeight.value}px`,
    transform: `scale(${zoomScale.value})`,
}));

const resolveCanvasFocalPoint = (clientX = null, clientY = null) => {
    const wrapper = canvasWrapperRef.value;
    if (!wrapper) return null;

    const rect = wrapper.getBoundingClientRect();
    return {
        clientX: Number(clientX ?? rect.left + (wrapper.clientWidth / 2)),
        clientY: Number(clientY ?? rect.top + (wrapper.clientHeight / 2)),
    };
};

const setZoomScale = (value) => {
    const nextScale = Math.round(clampValue(Number(value || 1), MIN_ZOOM_SCALE, MAX_ZOOM_SCALE) * 100) / 100;
    zoomScale.value = nextScale;
    return nextScale;
};

const setZoomScaleAtPoint = (value, clientX = null, clientY = null) => {
    const wrapper = canvasWrapperRef.value;
    const focalPoint = resolveCanvasFocalPoint(clientX, clientY);
    if (!wrapper || !focalPoint) return setZoomScale(value);

    const rect = wrapper.getBoundingClientRect();
    const oldScale = Math.max(MIN_ZOOM_SCALE, Number(zoomScale.value || 1));
    const offsetX = focalPoint.clientX - rect.left;
    const offsetY = focalPoint.clientY - rect.top;
    const canvasX = (wrapper.scrollLeft + offsetX) / oldScale;
    const canvasY = (wrapper.scrollTop + offsetY) / oldScale;
    const nextScale = setZoomScale(value);

    requestAnimationFrame(() => {
        const maxScrollLeft = Math.max(0, Math.round(boardWidth.value * nextScale) - wrapper.clientWidth);
        const maxScrollTop = Math.max(0, Math.round(boardHeight.value * nextScale) - wrapper.clientHeight);
        wrapper.scrollLeft = clampValue(Math.round((canvasX * nextScale) - offsetX), 0, maxScrollLeft);
        wrapper.scrollTop = clampValue(Math.round((canvasY * nextScale) - offsetY), 0, maxScrollTop);
    });

    return nextScale;
};

const zoomInCanvas = () => {
    setZoomScaleAtPoint(zoomScale.value + ZOOM_STEP);
};

const zoomOutCanvas = () => {
    setZoomScaleAtPoint(zoomScale.value - ZOOM_STEP);
};

const resetCanvasZoom = () => {
    setZoomScaleAtPoint(1);
};

const fitCanvasToWidth = () => {
    const wrapperWidth = Number(canvasWrapperRef.value?.clientWidth || 0);
    if (!wrapperWidth || !boardWidth.value) {
        resetCanvasZoom();
        return;
    }

    setZoomScaleAtPoint((wrapperWidth - 28) / boardWidth.value);
};

const normalizeWheelDelta = (event) => {
    let delta = Number(event.deltaY || 0);
    if (event.deltaMode === 1) delta *= 16;
    if (event.deltaMode === 2) delta *= Number(canvasWrapperRef.value?.clientHeight || 800);

    return clampValue(delta, -MAX_WHEEL_DELTA, MAX_WHEEL_DELTA);
};

const zoomCanvasFromWheel = (event) => {
    if (!event.ctrlKey && !event.metaKey && !event.altKey) return false;

    event.preventDefault();

    const delta = normalizeWheelDelta(event);
    const nextScale = zoomScale.value * Math.exp(-delta * WHEEL_ZOOM_SENSITIVITY);
    setZoomScaleAtPoint(nextScale, event.clientX, event.clientY);

    return true;
};

const onCanvasGestureStart = (event) => {
    event.preventDefault();
    zoomGestureStartScale.value = zoomScale.value;
};

const onCanvasGestureChange = (event) => {
    event.preventDefault();
    setZoomScaleAtPoint(zoomGestureStartScale.value * Number(event.scale || 1), event.clientX, event.clientY);
};

const getTouchDistance = (touches) => {
    if (!touches || touches.length < 2) return 0;

    const [first, second] = touches;
    return Math.hypot(second.clientX - first.clientX, second.clientY - first.clientY);
};

const getTouchCenter = (touches) => {
    if (!touches || touches.length < 2) return resolveCanvasFocalPoint();

    const [first, second] = touches;
    return {
        clientX: (first.clientX + second.clientX) / 2,
        clientY: (first.clientY + second.clientY) / 2,
    };
};

const onCanvasTouchStart = (event) => {
    if (event.touches.length !== 2) return;

    pinchStartDistance.value = getTouchDistance(event.touches);
    pinchStartScale.value = zoomScale.value;
};

const onCanvasTouchMove = (event) => {
    if (event.touches.length !== 2 || !pinchStartDistance.value) return;

    event.preventDefault();
    const distance = getTouchDistance(event.touches);
    if (!distance) return;
    const touchCenter = getTouchCenter(event.touches);

    setZoomScaleAtPoint(
        pinchStartScale.value * (distance / pinchStartDistance.value),
        touchCenter.clientX,
        touchCenter.clientY,
    );
};

const onCanvasTouchEnd = (event) => {
    if (event.touches.length >= 2) return;

    pinchStartDistance.value = 0;
};

const onCanvasWheel = (event) => {
    zoomCanvasFromWheel(event);
};

const edgePaths = computed(() => {
    return edges.value.map((edge) => {
        const from = nodeMap.value.get(String(edge.from_node_uuid || ''));
        const to = nodeMap.value.get(String(edge.to_node_uuid || ''));
        if (!from || !to) return null;
        const sx = Number(from.x || 0) + Number(from.width || 0) / 2;
        const sy = Number(from.y || 0) + Number(from.height || 0) / 2;
        const tx = Number(to.x || 0) + Number(to.width || 0) / 2;
        const ty = Number(to.y || 0) + Number(to.height || 0) / 2;
        const curve = Number(edge.curvature || 0.35);
        const dx = tx - sx;
        const c1x = sx + dx * curve;
        const c2x = tx - dx * curve;
        return {
            uuid: edge.uuid,
            d: `M ${sx} ${sy} C ${c1x} ${sy}, ${c2x} ${ty}, ${tx} ${ty}`,
            strokeColor: String(edge.stroke_color || '#334155'),
        };
    }).filter(Boolean);
});

const statusColors = {
    locked: { bg: '#64748b', label: '🔒 Locked' },
    unlocked: { bg: '#3b82f6', label: '🔓 Unlocked' },
    submitted: { bg: '#eab308', label: '⏳ Submitted' },
    revision: { bg: '#ef4444', label: '🔄 Revision' },
    approved: { bg: '#22c55e', label: '✅ Approved' },
};

const getNodeStatusColor = (node) => {
    const status = node?.progress?.status || 'locked';
    return statusColors[status]?.bg || '#64748b';
};

const getNodeStatusLabel = (node) => {
    const status = node?.progress?.status || 'locked';
    return statusColors[status]?.label || 'Locked';
};

const resolveVerticalJustify = (value, fallback = 'top') => {
    if (value === 'middle') return 'center';
    if (value === 'bottom') return 'flex-end';
    return 'flex-start';
};

const submitForm = useForm({ student_note: '' });
const reviewForm = useForm({ decision: 'approved', mentor_note: '' });

const submitNode = (node) => {
    submitForm.post(route('dooplab.roadmaps.enrollments.submit', {
        enrollment: props.enrollment.uuid,
        nodeUuid: node.uuid,
    }), {
        preserveScroll: true,
        onSuccess: () => { submitForm.reset(); selectedNode.value = null; },
    });
};

const reviewNode = (node) => {
    reviewForm.post(route('dooplab.roadmaps.enrollments.review', {
        enrollment: props.enrollment.uuid,
        nodeUuid: node.uuid,
    }), {
        preserveScroll: true,
        onSuccess: () => { reviewForm.reset(); selectedNode.value = null; },
    });
};

const unlockNode = (node) => {
    router.post(route('dooplab.roadmaps.enrollments.unlock', {
        enrollment: props.enrollment.uuid,
        nodeUuid: node.uuid,
    }), {}, {
        preserveScroll: true,
        onSuccess: () => { selectedNode.value = null; },
    });
};

const lockNode = (node) => {
    router.post(route('dooplab.roadmaps.enrollments.lock', {
        enrollment: props.enrollment.uuid,
        nodeUuid: node.uuid,
    }), {}, {
        preserveScroll: true,
        onSuccess: () => { selectedNode.value = null; },
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Path: ${roadmap.title}`" />
        <div class="relative min-h-screen">
            <Teleport to="body">
                <div class="fixed inset-0 -z-10 pointer-events-none">
                    <img src="/images/Gerbang_lab_pixel_art_website (3).jpeg" class="hidden md:block w-full h-full object-cover opacity-[0.15]" style="image-rendering: pixelated; transform: translateZ(0); will-change: auto;" alt="" />
                </div>
            </Teleport>
        <div class="relative z-10 p-4 md:p-8 text-[10px] font-['Press_Start_2P'] text-[#4ed4d4] space-y-4 overflow-x-hidden">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b-2 border-cyan-900 pb-3">
                <div class="min-w-0">
                    <h1 class="text-sm md:text-lg uppercase tracking-wider break-words">{{ roadmap.title }}</h1>
                    <p class="text-[8px] text-slate-400 uppercase mt-1">
                        {{ canManage ? `Student: ${enrollment.student_name}` : `Mentor: ${enrollment.mentor_name}` }}
                    </p>
                </div>
                <div class="path-actions">
                    <div class="zoom-controls" aria-label="Canvas zoom controls">
                        <button type="button" class="zoom-btn" title="Zoom out" @click="zoomOutCanvas">-</button>
                        <button type="button" class="zoom-value" title="Reset zoom to 100%" @click="resetCanvasZoom">{{ zoomPercent }}</button>
                        <button type="button" class="zoom-btn" title="Zoom in" @click="zoomInCanvas">+</button>
                        <button type="button" class="zoom-fit" title="Fit canvas width" @click="fitCanvasToWidth">Fit</button>
                    </div>
                    <Link :href="backHref" class="back-link">{{ backLabel }}</Link>
                </div>
            </div>

            <div
                ref="canvasWrapperRef"
                class="panel canvas-wrapper overflow-auto"
                @wheel="onCanvasWheel"
                @gesturestart="onCanvasGestureStart"
                @gesturechange="onCanvasGestureChange"
                @touchstart="onCanvasTouchStart"
                @touchmove="onCanvasTouchMove"
                @touchend="onCanvasTouchEnd"
                @touchcancel="onCanvasTouchEnd"
            >
                <div class="canvas-stage" :style="canvasStageStyle">
                <div class="roadmap-board" :style="roadmapBoardStyle">
                    <svg class="edge-layer" :width="boardWidth" :height="boardHeight">
                        <path
                            v-for="item in edgePaths"
                            :key="item.uuid"
                            :d="item.d"
                            :stroke="item.strokeColor"
                            stroke-width="2"
                            fill="none"
                        />
                    </svg>

                    <div
                        v-for="section in sections"
                        :key="`sec-${section.uuid}`"
                        class="section-box"
                        :style="{
                            left: `${section.x}px`,
                            top: `${section.y}px`,
                            width: `${section.width}px`,
                            height: `${section.height}px`,
                            background: section.bg_color,
                            color: section.text_color,
                            justifyContent: resolveVerticalJustify(section.text_valign, 'top'),
                        }"
                    >
                        <p class="section-title" :style="{ fontSize: `${section.font_size || 20}px`, textAlign: section.text_align || 'left' }">{{ section.title }}</p>
                    </div>

                    <div
                        v-for="textBlock in textBlocks"
                        :key="`txt-${textBlock.uuid}`"
                        class="text-block-box"
                        :style="{
                            left: `${textBlock.x}px`,
                            top: `${textBlock.y}px`,
                            width: `${textBlock.width}px`,
                            height: `${textBlock.height}px`,
                            background: textBlock.bg_color,
                            color: textBlock.text_color,
                            justifyContent: resolveVerticalJustify(textBlock.text_valign, 'top'),
                        }"
                    >
                        <p class="text-block-content" :style="{ fontSize: `${textBlock.font_size || 16}px`, textAlign: textBlock.text_align || 'left' }">{{ textBlock.content }}</p>
                    </div>

                    <div
                        v-for="node in nodes"
                        :key="node.uuid"
                        class="node-box"
                        :class="{ 'is-selected': selectedNode?.uuid === node.uuid }"
                        :style="{
                            left: `${node.x}px`,
                            top: `${node.y}px`,
                            width: `${node.width}px`,
                            height: `${node.height}px`,
                            background: node.bg_color,
                            color: node.text_color,
                            justifyContent: resolveVerticalJustify(node.text_valign, 'middle'),
                            borderColor: getNodeStatusColor(node),
                            borderWidth: '3px',
                        }"
                        @click="selectedNode = node"
                    >
                        <div class="node-title" :style="{ fontSize: `${node.font_size || 28}px`, textAlign: node.text_align || 'center' }">{{ node.title }}</div>
                        <span class="node-status-badge" :style="{ background: getNodeStatusColor(node) }">{{ getNodeStatusLabel(node) }}</span>
                    </div>
                </div>
                </div>
            </div>

            <Teleport to="body">
                <div v-if="selectedNode" class="node-modal-backdrop" @click.self="selectedNode = null">
                    <div class="node-modal-card">
                        <div class="node-modal-head">
                            <div>
                                <p>Selected Node</p>
                                <h3>{{ selectedNode.title }}</h3>
                            </div>
                            <button type="button" class="node-modal-close" @click="selectedNode = null">×</button>
                        </div>

                        <div class="node-modal-status">
                            <span class="node-status-badge" :style="{ background: getNodeStatusColor(selectedNode) }">{{ getNodeStatusLabel(selectedNode) }}</span>
                        </div>

                        <div v-if="selectedNode.resource_meta_list?.length" class="node-modal-resource-list">
                            <p>Materi & Quest</p>
                            <div class="node-modal-resource-grid">
                                <a
                                    v-for="resource in selectedNode.resource_meta_list"
                                    :key="`${resource.type}-${resource.href}`"
                                    :href="canManage && resource.submission_inspect_href ? resource.submission_inspect_href : resource.href"
                                    target="_blank"
                                    class="node-modal-resource-link"
                                    :class="{ 'node-modal-resource-link--submission': canManage && resource.submission_inspect_href }"
                                >
                                    <span aria-hidden="true">{{ resource.type === 'guide' ? '📖' : canManage && resource.submission_inspect_href ? '📋' : '⚔️' }}</span>
                                    {{ resource.label }}
                                    <span v-if="canManage && resource.submission_inspect_href" class="text-[8px] opacity-70"> (submission)</span>
                                </a>
                            </div>
                        </div>

                        <div v-if="selectedNode.progress?.mentor_note" class="node-modal-note">
                            Mentor note: {{ selectedNode.progress.mentor_note }}
                        </div>

                        <div v-if="selectedNode.progress?.student_note" class="node-modal-note node-modal-note--student">
                            Catatan student: {{ selectedNode.progress.student_note }}
                        </div>

                        <div v-if="isOwner && (selectedNode.progress?.status === 'unlocked' || selectedNode.progress?.status === 'revision')" class="node-modal-form">
                            <textarea v-model="submitForm.student_note" placeholder="Catatan submit (opsional)" class="field h-20 resize-none" />
                            <button class="btn-primary" :disabled="submitForm.processing" type="button" @click="submitNode(selectedNode)">Submit Node</button>
                        </div>

                        <div v-if="canManage && selectedNode.progress?.status === 'submitted'" class="node-modal-form">
                            <textarea v-model="reviewForm.mentor_note" placeholder="Feedback mentor (opsional)" class="field h-20 resize-none" />
                            <div class="node-modal-actions">
                                <button class="btn-primary" :disabled="reviewForm.processing" type="button" @click="reviewForm.decision = 'approved'; reviewNode(selectedNode)">Approve</button>
                                <button class="btn-danger" :disabled="reviewForm.processing" type="button" @click="reviewForm.decision = 'revision'; reviewNode(selectedNode)">Revisi</button>
                            </div>
                        </div>

                        <div v-if="canManage && selectedNode.progress?.status === 'locked'" class="node-modal-actions">
                            <button class="btn-primary" type="button" @click="unlockNode(selectedNode)">Unlock Node</button>
                        </div>

                        <div v-if="canManage && selectedNode.progress?.status === 'unlocked'" class="node-modal-actions">
                            <button class="btn-danger" type="button" @click="lockNode(selectedNode)">Relock Node</button>
                        </div>
                    </div>
                </div>
            </Teleport>

        </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.panel {
    background: rgba(10, 16, 30, 0.72);
    border: 2px solid rgba(87, 214, 255, 0.24);
    padding: 0.75rem;
    border-radius: 0;
    box-shadow: 4px 4px 0 rgba(1, 6, 14, 0.9);
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
    max-height: calc(100dvh - 160px);
}
.canvas-wrapper {
    touch-action: pan-x pan-y;
}
.path-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: wrap;
    min-width: 0;
}
.back-link,
.zoom-controls button {
    border: 1px solid #334155;
    color: #cbd5e1;
    padding: 0.5rem 0.65rem;
    text-transform: uppercase;
    font-size: 8px;
    border-radius: 0;
    box-shadow: 2px 2px 0 rgba(1, 6, 14, 0.9);
    background: rgba(15, 23, 42, 0.45);
}
.back-link:hover,
.zoom-controls button:hover {
    border-color: #22d3ee;
    color: #fff;
}
.zoom-controls {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-width: 0;
}
.zoom-btn {
    width: 32px;
    min-width: 32px;
}
.zoom-value {
    min-width: 58px;
}
.zoom-fit {
    min-width: 44px;
}
.canvas-stage {
    margin-inline: auto;
    position: relative;
    transition: width 0.16s ease, height 0.16s ease;
}
.field {
    border: 1px solid #334155;
    background: #020617;
    color: #cbd5e1;
    padding: 0.5rem;
    font-size: 11px;
    font-family: Inter, sans-serif;
    border-radius: 0;
}
.node-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: grid;
    place-items: center;
    padding: 1rem;
    background:
        radial-gradient(circle at 50% 24%, rgba(87, 214, 255, 0.1), transparent 30%),
        rgba(2, 6, 23, 0.82);
    backdrop-filter: blur(3px);
}

.node-modal-card {
    width: min(520px, 100%);
    position: relative;
    border: 2px solid rgba(87, 214, 255, 0.45);
    background: #07101d;
    box-shadow:
        8px 8px 0 rgba(1, 6, 14, 0.95),
        inset 0 0 0 1px rgba(255, 255, 255, 0.035),
        0 0 30px rgba(87, 214, 255, 0.16);
    padding: 1.1rem;
    color: #e6f6ff;
}

.node-modal-card::before,
.node-modal-card::after {
    content: '';
    position: absolute;
    width: 18px;
    height: 18px;
    pointer-events: none;
}

.node-modal-card::before {
    top: -2px;
    left: -2px;
    border-top: 4px solid #67e8f9;
    border-left: 4px solid #67e8f9;
}

.node-modal-card::after {
    right: -2px;
    bottom: -2px;
    border-right: 4px solid #facc15;
    border-bottom: 4px solid #facc15;
}

.node-modal-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    border-bottom: 2px solid rgba(87, 214, 255, 0.28);
    padding-bottom: 0.85rem;
    margin-bottom: 0.85rem;
}

.node-modal-head p {
    margin: 0 0 0.4rem;
    color: #facc15;
    font-size: 8px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.node-modal-head h3 {
    margin: 0;
    color: #e6f6ff;
    font-size: 12px;
    line-height: 1.7;
    text-shadow: 2px 2px 0 rgba(87, 214, 255, 0.18);
    text-transform: uppercase;
}

.node-modal-close {
    width: 32px;
    height: 32px;
    flex: 0 0 auto;
    border: 2px solid rgba(87, 214, 255, 0.45);
    background: #020617;
    color: #67e8f9;
    font-size: 16px;
    line-height: 1;
    box-shadow: 3px 3px 0 rgba(1, 6, 14, 0.95);
    transition: transform 120ms ease, border-color 120ms ease, color 120ms ease;
}

.node-modal-close:hover {
    transform: translate(-1px, -1px);
    border-color: #facc15;
    color: #facc15;
}

.node-modal-status,
.node-modal-resource-list,
.node-modal-note,
.node-modal-form,
.node-modal-actions {
    margin-top: 0.75rem;
}

.node-modal-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.node-status-badge {
    border: 1px solid rgba(255, 255, 255, 0.35);
    box-shadow: 3px 3px 0 rgba(1, 6, 14, 0.9);
    color: #020617 !important;
    padding: 0.32rem 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.node-modal-resource-list p {
    margin: 0 0 0.45rem;
    color: #facc15;
    font-size: 8px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.node-modal-resource-grid {
    display: grid;
    gap: 0.45rem;
}

.node-modal-resource-link {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    max-width: 100%;
    border: 1px solid rgba(87, 214, 255, 0.35);
    background: rgba(87, 214, 255, 0.06);
    color: #67e8f9;
    font-size: 8px;
    line-height: 1.8;
    padding: 0.45rem 0.55rem;
    text-decoration: none;
    text-transform: uppercase;
    box-shadow: 3px 3px 0 rgba(1, 6, 14, 0.9);
}

.node-modal-resource-link:hover {
    border-color: #facc15;
    color: #fde68a;
}

.node-modal-resource-link--submission {
    border-color: rgba(250, 204, 21, 0.5);
    background: rgba(250, 204, 21, 0.08);
    color: #fde68a;
}

.node-modal-note {
    border-left: 3px solid #facc15;
    background: rgba(250, 204, 21, 0.08);
    color: #fde68a;
    font-size: 12px;
    line-height: 1.8;
    padding: 0.65rem 0.75rem;
}

.node-modal-note--student {
    border-left-color: #67e8f9;
    background: rgba(103, 232, 249, 0.08);
    color: #a5f3fc;
}

.node-modal-form {
    display: grid;
    gap: 0.75rem;
}

.node-modal-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
}
.btn-primary {
    border: 1px solid #22d3ee;
    color: #67e8f9;
    padding: 0.4rem 0.55rem;
    text-transform: uppercase;
    font-size: 8px;
    border-radius: 0;
    box-shadow: 2px 2px 0 rgba(1, 6, 14, 0.9);
    background: rgba(34, 211, 238, 0.08);
}
.btn-danger {
    border: 1px solid #ef4444;
    color: #fca5a5;
    padding: 0.4rem 0.55rem;
    text-transform: uppercase;
    font-size: 8px;
    border-radius: 0;
    box-shadow: 2px 2px 0 rgba(1, 6, 14, 0.9);
    background: rgba(239, 68, 68, 0.08);
}
.roadmap-board {
    left: 0;
    position: absolute;
    top: 0;
    background-image:
        linear-gradient(to right, rgba(148, 163, 184, 0.12) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(148, 163, 184, 0.12) 1px, transparent 1px);
    background-size: 24px 24px;
    background-color: #0b1220;
    border-radius: 0;
    border: 2px solid rgba(87, 214, 255, 0.24);
    box-shadow: inset 0 0 0 1px rgba(87, 214, 255, 0.06);
    transform-origin: top left;
    will-change: transform;
}
.edge-layer {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 1;
}
.section-box {
    position: absolute;
    border-radius: 0;
    border: 1px solid rgba(15, 23, 42, 0.12);
    box-shadow: 3px 3px 0 rgba(1, 6, 14, 0.55);
    padding: 12px;
    display: flex;
    flex-direction: column;
    z-index: 0;
}
.section-title {
    font-size: 20px;
    line-height: 1.2;
    font-weight: 700;
    font-family: Inter, sans-serif;
    width: 100%;
}
.text-block-box {
    position: absolute;
    border-radius: 0;
    border: 1px solid transparent;
    box-shadow: none;
    padding: 12px;
    display: flex;
    flex-direction: column;
    z-index: 1;
    white-space: pre-wrap;
}
.text-block-content {
    width: 100%;
    margin: 0;
    line-height: 1.45;
    font-family: Inter, sans-serif;
    font-weight: 700;
    text-shadow: 0 1px 0 rgba(2, 8, 23, 0.22);
}
.node-box {
    position: absolute;
    border-radius: 0;
    box-shadow: 3px 3px 0 rgba(1, 6, 14, 0.7);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    cursor: pointer;
    z-index: 2;
    transition: outline 0.15s;
}
.node-box.is-selected {
    outline: 2px solid rgba(34, 211, 238, 0.9);
    outline-offset: 2px;
}
.node-title {
    font-family: Inter, sans-serif;
    font-size: 28px;
    line-height: 1.2;
    font-weight: 700;
    text-align: center;
    width: 100%;
}
.node-status-badge {
    font-family: Inter, sans-serif;
    font-size: 8px;
    padding: 2px 8px;
    border-radius: 0;
    color: #fff;
    margin-top: 4px;
    border: 1px solid rgba(0, 0, 0, 0.25);
}
</style>

<style scoped>
/* Pixel/square theme - font sizing */
.p-4 {
    font-family: "Press Start 2P", Inter, sans-serif !important;
    font-size: 8px !important;
}

h1 { font-size: 11px !important; }
h3 { font-size: 9px !important; }
p, span, a, button, textarea { font-size: 8px; }

.panel h3 { font-size: 9px !important; }
.panel p, .panel span, .panel a { font-size: 8px !important; }
.panel button { font-size: 7px !important; }

.section-title { font-size: 11px !important; }
.node-title { font-size: 12px !important; }
.node-status-badge { font-size: 7px !important; }

.node-modal-card {
    font-size: 11px !important;
}

.node-modal-head p {
    font-size: 10px !important;
}

.node-modal-head h3 {
    font-size: 16px !important;
}

.node-modal-card p,
.node-modal-card span,
.node-modal-card a,
.node-modal-card button,
.node-modal-card textarea {
    font-size: 11px !important;
}

.node-modal-resource-list p,
.node-modal-resource-link {
    font-size: 11px !important;
}

.node-modal-card .node-status-badge {
    font-size: 10px !important;
}

.node-modal-close {
    font-size: 18px !important;
}

@media (max-width: 768px) {
    .path-actions {
        width: 100%;
        justify-content: stretch;
    }

    .zoom-controls {
        width: 100%;
        order: 1;
    }

    .zoom-controls button {
        flex: 1 1 0;
        min-height: 38px;
    }

    .back-link {
        width: 100%;
        text-align: center;
        order: 2;
    }

    .panel {
        max-height: calc(100dvh - 140px);
        padding: 0.5rem;
    }

    .node-modal-card {
        padding: 0.85rem !important;
        width: min(100%, 96vw) !important;
    }

    .node-modal-head h3 {
        font-size: 13px !important;
        line-height: 1.5 !important;
    }

    .node-modal-head p {
        font-size: 9px !important;
    }

    .node-modal-card p,
    .node-modal-card span,
    .node-modal-card a,
    .node-modal-card textarea {
        font-size: 11px !important;
    }

    .node-modal-card button {
        font-size: 10px !important;
        padding: 8px 12px !important;
    }

    .node-modal-resource-link {
        font-size: 10px !important;
    }

    .node-modal-actions {
        flex-direction: column;
    }

    .node-modal-actions button {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .panel {
        max-height: calc(100dvh - 120px);
        padding: 0.4rem;
    }

    .node-modal-backdrop {
        padding: 0.5rem;
        align-items: flex-end;
    }

    .node-modal-card {
        max-height: 85dvh;
        overflow-y: auto;
        padding: 0.75rem !important;
    }

    .node-modal-head h3 {
        font-size: 11px !important;
    }
}
</style>
