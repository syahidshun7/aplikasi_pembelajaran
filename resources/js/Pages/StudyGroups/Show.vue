<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useUserTheme } from '@/Composables/useUserTheme';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    group: {
        type: Object,
        required: true,
    },
    mentors: {
        type: Array,
        default: () => [],
    },
    classmates: {
        type: Array,
        default: () => [],
    },
    classRoadmaps: {
        type: Array,
        default: () => [],
    },
});

const selectedRoadmapUuid = ref(props.classRoadmaps[0]?.uuid || '');
const { themeMode } = useUserTheme();
const selectedNode = ref(null);
const classmatesPage = ref(1);
const canvasWrapperRef = ref(null);
const zoomScale = ref(1);
const zoomGestureStartScale = ref(1);
const pinchStartDistance = ref(0);
const pinchStartScale = ref(1);

const MIN_ZOOM_SCALE = 0.4;
const MAX_ZOOM_SCALE = 2;
const ZOOM_STEP = 0.1;
const WHEEL_ZOOM_SENSITIVITY = 0.0022;
const MAX_WHEEL_DELTA = 42;
const CLASSMATES_PER_PAGE = 20;

const clampValue = (value, min, max) => Math.min(max, Math.max(min, value));

const classmatesTotalPages = computed(() => Math.max(1, Math.ceil(props.classmates.length / CLASSMATES_PER_PAGE)));
const paginatedClassmates = computed(() => {
    const start = (classmatesPage.value - 1) * CLASSMATES_PER_PAGE;
    return props.classmates.slice(start, start + CLASSMATES_PER_PAGE);
});

watch(() => props.classmates.length, () => {
    classmatesPage.value = Math.min(classmatesPage.value, classmatesTotalPages.value);
});

watch(() => props.classRoadmaps, (roadmaps) => {
    if (!selectedRoadmapUuid.value && roadmaps?.length) {
        selectedRoadmapUuid.value = roadmaps[0].uuid;
    }
});

watch(selectedRoadmapUuid, () => {
    zoomScale.value = 1;
    requestAnimationFrame(() => {
        if (!canvasWrapperRef.value) return;
        canvasWrapperRef.value.scrollLeft = 0;
        canvasWrapperRef.value.scrollTop = 0;
    });
});

const selectedRoadmap = computed(() => {
    return props.classRoadmaps.find((roadmap) => roadmap.uuid === selectedRoadmapUuid.value)
        || props.classRoadmaps[0]
        || null;
});

const canvasSize = computed(() => {
    const roadmap = selectedRoadmap.value;
    const items = [
        ...(roadmap?.sections || []),
        ...(roadmap?.nodes || []),
        ...(roadmap?.text_blocks || []),
    ];

    const width = Math.max(900, ...items.map((item) => Number(item.x || 0) + Number(item.width || 0) + 80));
    const height = Math.max(520, ...items.map((item) => Number(item.y || 0) + Number(item.height || 0) + 80));

    return { width, height };
});

const zoomPercent = computed(() => `${Math.round(zoomScale.value * 100)}%`);
const canvasStageStyle = computed(() => ({
    width: `${Math.round(canvasSize.value.width * zoomScale.value)}px`,
    height: `${Math.round(canvasSize.value.height * zoomScale.value)}px`,
}));
const roadmapCanvasStyle = computed(() => ({
    width: `${canvasSize.value.width}px`,
    height: `${canvasSize.value.height}px`,
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
        const maxScrollLeft = Math.max(0, Math.round(canvasSize.value.width * nextScale) - wrapper.clientWidth);
        const maxScrollTop = Math.max(0, Math.round(canvasSize.value.height * nextScale) - wrapper.clientHeight);
        wrapper.scrollLeft = clampValue(Math.round((canvasX * nextScale) - offsetX), 0, maxScrollLeft);
        wrapper.scrollTop = clampValue(Math.round((canvasY * nextScale) - offsetY), 0, maxScrollTop);
    });

    return nextScale;
};

const zoomInCanvas = () => setZoomScaleAtPoint(zoomScale.value + ZOOM_STEP);
const zoomOutCanvas = () => setZoomScaleAtPoint(zoomScale.value - ZOOM_STEP);
const resetCanvasZoom = () => setZoomScaleAtPoint(1);
const fitCanvasToWidth = () => {
    const wrapperWidth = Number(canvasWrapperRef.value?.clientWidth || 0);
    if (!wrapperWidth || !canvasSize.value.width) {
        resetCanvasZoom();
        return;
    }

    setZoomScaleAtPoint((wrapperWidth - 28) / canvasSize.value.width);
};

const normalizeWheelDelta = (event) => {
    let delta = Number(event.deltaY || 0);
    if (event.deltaMode === 1) delta *= 16;
    if (event.deltaMode === 2) delta *= Number(canvasWrapperRef.value?.clientHeight || 620);
    return clampValue(delta, -MAX_WHEEL_DELTA, MAX_WHEEL_DELTA);
};

const onCanvasWheel = (event) => {
    if (!event.ctrlKey && !event.metaKey && !event.altKey) return;
    event.preventDefault();
    const nextScale = zoomScale.value * Math.exp(-normalizeWheelDelta(event) * WHEEL_ZOOM_SENSITIVITY);
    setZoomScaleAtPoint(nextScale, event.clientX, event.clientY);
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
    const [first, second] = event.touches;
    setZoomScaleAtPoint(
        pinchStartScale.value * (distance / pinchStartDistance.value),
        (first.clientX + second.clientX) / 2,
        (first.clientY + second.clientY) / 2,
    );
};

const onCanvasTouchEnd = (event) => {
    if (event.touches.length < 2) pinchStartDistance.value = 0;
};

const nodeByUuid = computed(() => {
    const map = {};
    (selectedRoadmap.value?.nodes || []).forEach((node) => {
        map[node.uuid] = node;
    });
    return map;
});

const edgeLine = (edge) => {
    const fromNode = nodeByUuid.value[edge.from_node_uuid];
    const toNode = nodeByUuid.value[edge.to_node_uuid];
    if (!fromNode || !toNode) return null;

    return {
        x1: Number(fromNode.x || 0) + Number(fromNode.width || 0) / 2,
        y1: Number(fromNode.y || 0) + Number(fromNode.height || 0) / 2,
        x2: Number(toNode.x || 0) + Number(toNode.width || 0) / 2,
        y2: Number(toNode.y || 0) + Number(toNode.height || 0) / 2,
        stroke: edge.stroke_color || '#64748b',
    };
};

const textAlignClass = (align) => {
    if (align === 'left') return 'text-left';
    if (align === 'right') return 'text-right';
    return 'text-center';
};

const resolveVerticalJustify = (value, fallback = 'middle') => {
    const normalized = String(value || fallback);
    if (normalized === 'top') return 'flex-start';
    if (normalized === 'bottom') return 'flex-end';
    return 'center';
};

const openNode = (node) => {
    selectedNode.value = node;
};

const closeNode = () => {
    selectedNode.value = null;
};

const photoUrl = (path) => {
    const value = String(path || '').trim();
    if (value === '') return null;
    if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('/')) return value;
    return `/storage/${value}`;
};

const initials = (name) => {
    const value = String(name || 'U').trim();
    return value.slice(0, 2).toUpperCase();
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`${group.name || 'Study Group'} Detail`" />

        <div class="lobby-detail-page p-0 md:p-4 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
            <div class="mx-auto max-w-6xl space-y-6">
                <div class="flex flex-col gap-3 border-b-4 border-emerald-900 pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-[8px] uppercase text-slate-500">Study_Group_Detail</p>
                        <h1 class="mt-2 text-base uppercase tracking-widest text-white sm:text-xl">{{ group.name }}</h1>
                    </div>
                    <Link
                        :href="route('groups.index')"
                        class="inline-flex items-center justify-center border-2 border-slate-700 bg-slate-900/40 px-3 py-2 text-[9px] uppercase text-slate-300 hover:border-emerald-400 hover:text-white sm:text-[10px]"
                    >
                        [Back_to_Groups]
                    </Link>
                </div>

                <section class="rpg-panel border-cyan-500/50">
                    <div class="grid gap-5 lg:grid-cols-[1.4fr_0.6fr]">
                        <div>
                            <p class="text-[8px] uppercase text-cyan-300">Description</p>
                            <p class="mt-3 whitespace-pre-line font-sans text-[14px] leading-relaxed text-slate-100">
                                {{ group.description || 'Belum ada deskripsi untuk study group ini.' }}
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-center">
                            <div class="border border-slate-700 bg-black/30 p-3">
                                <p class="text-[7px] uppercase text-slate-400">Members</p>
                                <p class="mt-2 text-lg text-yellow-300">{{ group.members_count || 0 }}/{{ group.max_members || 0 }}</p>
                            </div>
                            <div class="border border-slate-700 bg-black/30 p-3">
                                <p class="text-[7px] uppercase text-slate-400">Min_Level</p>
                                <p class="mt-2 text-lg text-emerald-300">{{ group.min_level || 1 }}</p>
                            </div>
                            <div class="border border-slate-700 bg-black/30 p-3">
                                <p class="text-[7px] uppercase text-slate-400">Quests</p>
                                <p class="mt-2 text-lg text-cyan-300">{{ group.quests_count || 0 }}</p>
                            </div>
                            <div class="border border-slate-700 bg-black/30 p-3">
                                <p class="text-[7px] uppercase text-slate-400">Path</p>
                                <p class="mt-2 text-[9px] leading-relaxed text-white">{{ group.job?.name || 'General' }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rpg-panel border-emerald-500/50">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h2 class="text-emerald-300 uppercase">Mentor_In_This_Group</h2>
                        <span class="border border-emerald-900 bg-emerald-900/20 px-2 py-1 text-[8px] uppercase text-emerald-200">
                            {{ mentors.length }} Mentor
                        </span>
                    </div>

                    <div v-if="mentors.length === 0" class="border border-slate-800 bg-black/30 p-4 text-[9px] uppercase text-slate-500">
                        Belum ada mentor yang terdaftar langsung di group ini.
                    </div>

                    <div v-else class="grid gap-3 md:grid-cols-2">
                        <article v-for="mentor in mentors" :key="mentor.id" class="flex items-center gap-4 border border-slate-800 bg-black/40 p-4">
                            <img
                                v-if="photoUrl(mentor.profile_photo)"
                                :src="photoUrl(mentor.profile_photo)"
                                :alt="mentor.name"
                                class="h-14 w-14 border-2 border-emerald-700 object-cover"
                            />
                            <div v-else class="flex h-14 w-14 items-center justify-center border-2 border-emerald-700 bg-emerald-950 text-emerald-200">
                                {{ initials(mentor.name) }}
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-[11px] uppercase text-white">{{ mentor.name }}</p>
                                <p class="mt-1 truncate text-[8px] uppercase text-cyan-300">@{{ mentor.username || 'mentor' }}</p>
                                <p class="mt-2 font-sans text-[12px] text-slate-300">{{ mentor.job_name || 'Mentor' }}</p>
                            </div>
                        </article>
                    </div>
                </section>

                <section
                    class="rpg-panel roadmap-theme-panel border-indigo-500/50"
                    :class="`roadmap-theme--${themeMode}`"
                >
                    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="text-indigo-300 uppercase">Roadmap_Kelas</h2>
                            <p class="mt-2 font-sans text-[12px] leading-relaxed text-slate-300">
                                Kurikulum view-only untuk kelas ini. Klik node untuk membuka Guide atau Quest.
                            </p>
                        </div>
                        <div class="roadmap-toolbar">
                            <div v-if="classRoadmaps.length > 1" class="flex flex-wrap justify-end gap-2">
                                <button
                                    v-for="roadmap in classRoadmaps"
                                    :key="roadmap.uuid"
                                    type="button"
                                    class="roadmap-selector border px-3 py-2 text-[8px] uppercase"
                                    :class="selectedRoadmap?.uuid === roadmap.uuid ? 'roadmap-selector--active border-indigo-400 bg-indigo-500/20 text-indigo-100' : 'roadmap-selector--idle border-slate-700 text-slate-400 hover:border-indigo-400 hover:text-white'"
                                    @click="selectedRoadmapUuid = roadmap.uuid; selectedNode = null"
                                >
                                    {{ roadmap.title }}
                                </button>
                            </div>
                            <div v-if="selectedRoadmap" class="roadmap-zoom-controls" aria-label="Canvas zoom controls">
                                <button type="button" class="roadmap-zoom-btn" title="Zoom out" @click="zoomOutCanvas">-</button>
                                <button type="button" class="roadmap-zoom-value" title="Reset zoom to 100%" @click="resetCanvasZoom">{{ zoomPercent }}</button>
                                <button type="button" class="roadmap-zoom-btn" title="Zoom in" @click="zoomInCanvas">+</button>
                                <button type="button" class="roadmap-zoom-fit" title="Fit canvas width" @click="fitCanvasToWidth">Fit</button>
                            </div>
                        </div>
                    </div>

                    <div v-if="!selectedRoadmap" class="border border-slate-800 bg-black/30 p-4 text-[9px] uppercase text-slate-500">
                        Belum ada roadmap kelas yang aktif.
                    </div>

                    <div v-else>
                        <div class="mb-4 border border-slate-800 bg-black/30 p-4">
                            <p class="break-words text-[11px] uppercase text-white">{{ selectedRoadmap.title }}</p>
                            <p class="mt-2 whitespace-pre-line font-sans text-[13px] leading-relaxed text-slate-300">
                                {{ selectedRoadmap.description || 'Tidak ada deskripsi roadmap.' }}
                            </p>
                        </div>

                        <div
                            ref="canvasWrapperRef"
                            class="roadmap-shell"
                            @wheel="onCanvasWheel"
                            @gesturestart="onCanvasGestureStart"
                            @gesturechange="onCanvasGestureChange"
                            @touchstart="onCanvasTouchStart"
                            @touchmove="onCanvasTouchMove"
                            @touchend="onCanvasTouchEnd"
                            @touchcancel="onCanvasTouchEnd"
                        >
                            <div class="roadmap-canvas-stage" :style="canvasStageStyle">
                            <div
                                class="roadmap-canvas"
                                :style="roadmapCanvasStyle"
                            >
                                <svg class="roadmap-connections pointer-events-none absolute inset-0 h-full w-full">
                                    <template v-for="edge in selectedRoadmap.edges || []" :key="edge.uuid">
                                        <line
                                            v-if="edgeLine(edge)"
                                            :x1="edgeLine(edge).x1"
                                            :y1="edgeLine(edge).y1"
                                            :x2="edgeLine(edge).x2"
                                            :y2="edgeLine(edge).y2"
                                            class="roadmap-edge-halo"
                                            stroke-width="6"
                                            stroke-linecap="round"
                                        />
                                        <line
                                            v-if="edgeLine(edge)"
                                            :x1="edgeLine(edge).x1"
                                            :y1="edgeLine(edge).y1"
                                            :x2="edgeLine(edge).x2"
                                            :y2="edgeLine(edge).y2"
                                            :stroke="edgeLine(edge).stroke"
                                            class="roadmap-edge-line"
                                            stroke-width="3"
                                            stroke-linecap="round"
                                            stroke-dasharray="8 6"
                                        />
                                    </template>
                                </svg>

                                <div
                                    v-for="section in selectedRoadmap.sections || []"
                                    :key="section.uuid"
                                    class="roadmap-section-box"
                                    :style="{
                                        left: `${section.x}px`,
                                        top: `${section.y}px`,
                                        width: `${section.width}px`,
                                        height: `${section.height}px`,
                                        backgroundColor: section.bg_color,
                                        color: section.text_color,
                                        borderColor: section.text_color,
                                        justifyContent: resolveVerticalJustify(section.text_valign, 'top'),
                                    }"
                                >
                                    <p
                                        class="roadmap-section-title"
                                        :style="{ fontSize: `${Number(section.font_size || 20)}px`, textAlign: section.text_align || 'left' }"
                                    >
                                        {{ section.title }}
                                    </p>
                                </div>

                                <div
                                    v-for="textBlock in selectedRoadmap.text_blocks || []"
                                    :key="textBlock.uuid"
                                    class="roadmap-text-block-box"
                                    :style="{
                                        left: `${textBlock.x}px`,
                                        top: `${textBlock.y}px`,
                                        width: `${textBlock.width}px`,
                                        height: `${textBlock.height}px`,
                                        backgroundColor: textBlock.bg_color,
                                        color: textBlock.text_color,
                                        justifyContent: resolveVerticalJustify(textBlock.text_valign, 'top'),
                                    }"
                                >
                                    <p
                                        class="roadmap-text-block-content"
                                        :style="{ fontSize: `${Number(textBlock.font_size || 16)}px`, textAlign: textBlock.text_align || 'left' }"
                                    >
                                        {{ textBlock.content }}
                                    </p>
                                </div>

                                <button
                                    v-for="node in selectedRoadmap.nodes || []"
                                    :key="node.uuid"
                                    type="button"
                                    class="roadmap-node-box"
                                    :style="{
                                        left: `${node.x}px`,
                                        top: `${node.y}px`,
                                        width: `${node.width}px`,
                                        height: `${node.height}px`,
                                        backgroundColor: node.bg_color,
                                        color: node.text_color,
                                        justifyContent: resolveVerticalJustify(node.text_valign, 'middle'),
                                    }"
                                    @click="openNode(node)"
                                >
                                    <span
                                        class="roadmap-node-title"
                                        :style="{ fontSize: `${Number(node.font_size || 28)}px`, textAlign: node.text_align || 'center' }"
                                    >
                                        {{ node.title }}
                                    </span>
                                </button>
                            </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rpg-panel border-slate-700" :class="`classmates-theme--${themeMode}`">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h2 class="text-slate-200 uppercase">Classmates</h2>
                        <span class="text-[8px] uppercase text-slate-500">{{ classmates.length }} Siswa</span>
                    </div>

                    <div v-if="classmates.length === 0" class="text-[9px] uppercase text-slate-500">
                        No_Classmate_Data
                    </div>

                    <div v-else class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <Link
                            v-for="member in paginatedClassmates"
                            :key="member.id"
                            :href="route('profiles.show', member.username)"
                            class="classmate-card group flex items-center gap-3 border border-slate-800 bg-black/30 p-3"
                            :aria-label="`Lihat profil ${member.name}`"
                        >
                            <img
                                v-if="photoUrl(member.profile_photo)"
                                :src="photoUrl(member.profile_photo)"
                                :alt="member.name"
                                class="h-9 w-9 border border-slate-700 object-cover"
                            />
                            <div v-else class="flex h-9 w-9 items-center justify-center border border-slate-700 bg-slate-950 text-[8px] text-slate-300">
                                {{ initials(member.name) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[9px] uppercase text-white">{{ member.name }}</p>
                                <p class="mt-1 truncate text-[8px] uppercase text-slate-500">@{{ member.username }}</p>
                            </div>
                            <i class="fi fi-rr-angle-small-right text-[10px] text-slate-500 transition-transform group-hover:translate-x-1"></i>
                        </Link>
                    </div>

                    <nav
                        v-if="classmatesTotalPages > 1"
                        class="classmates-pagination mt-4 flex items-center justify-between gap-3 border-t border-slate-700 pt-3"
                        aria-label="Pagination classmates"
                    >
                        <button
                            type="button"
                            class="classmates-page-btn"
                            :disabled="classmatesPage === 1"
                            @click="classmatesPage -= 1"
                        >
                            <i class="fi fi-rr-angle-small-left"></i>
                            Prev
                        </button>
                        <span class="text-[8px] uppercase text-slate-400">
                            {{ classmatesPage }} / {{ classmatesTotalPages }}
                        </span>
                        <button
                            type="button"
                            class="classmates-page-btn"
                            :disabled="classmatesPage === classmatesTotalPages"
                            @click="classmatesPage += 1"
                        >
                            Next
                            <i class="fi fi-rr-angle-small-right"></i>
                        </button>
                    </nav>
                </section>
            </div>
        </div>

        <div
            v-if="selectedNode"
            class="fixed inset-0 z-[120] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
            @click.self="closeNode"
        >
            <div class="w-full max-w-lg border-4 border-indigo-500/60 bg-[#111827] p-5 shadow-[0_0_30px_rgba(99,102,241,0.25)]">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[8px] uppercase text-indigo-300">Roadmap_Node</p>
                        <h3 class="mt-2 break-words text-sm uppercase text-white">{{ selectedNode.title }}</h3>
                    </div>
                    <button
                        type="button"
                        class="border border-slate-600 px-2 py-1 text-[8px] uppercase text-slate-300 hover:border-indigo-400 hover:text-white"
                        @click="closeNode"
                    >
                        Close
                    </button>
                </div>

                <div v-if="(selectedNode.resource_meta_list || []).length === 0" class="border border-slate-700 bg-black/30 p-4 text-[9px] uppercase text-slate-500">
                    Belum ada Guide atau Quest di node ini.
                </div>

                <div v-else class="space-y-3">
                    <Link
                        v-for="resource in selectedNode.resource_meta_list"
                        :key="`${resource.type}-${resource.href}`"
                        :href="resource.href"
                        class="flex items-center justify-between gap-3 border border-slate-700 bg-black/30 px-4 py-3 text-[9px] uppercase text-slate-100 hover:border-indigo-400 hover:text-white"
                    >
                        <span class="break-words">{{ resource.label }}</span>
                        <span class="shrink-0 text-[7px]" :class="resource.type === 'guide' ? 'text-cyan-300' : 'text-yellow-300'">
                            {{ resource.type }}
                        </span>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.rpg-panel {
    background-color: #1a1c2c;
    border-width: 4px;
    padding: 1rem;
    box-shadow: 8px 8px 0 0 rgba(0, 0, 0, 0.5);
}

.roadmap-shell {
    height: clamp(420px, 62vh, 620px);
    max-height: 620px;
    overflow: auto;
    border: 2px solid rgba(99, 102, 241, 0.35);
    background:
        linear-gradient(to right, rgba(148, 163, 184, 0.12) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(148, 163, 184, 0.12) 1px, transparent 1px),
        #0b1020;
    background-size: 24px 24px;
    touch-action: pan-x pan-y;
}

.roadmap-toolbar {
    display: flex;
    min-width: 0;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.roadmap-zoom-controls {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.roadmap-zoom-controls button {
    min-height: 34px;
    border: 1px solid #334155;
    background: rgba(15, 23, 42, 0.45);
    padding: 0.45rem 0.6rem;
    color: #cbd5e1;
    font-size: 8px;
    text-transform: uppercase;
    box-shadow: 2px 2px 0 rgba(1, 6, 14, 0.9);
}

.roadmap-zoom-controls button:hover {
    border-color: #22d3ee;
    color: #ffffff;
}

.roadmap-zoom-btn {
    width: 34px;
    min-width: 34px;
}

.roadmap-zoom-value {
    min-width: 62px;
}

.roadmap-zoom-fit {
    min-width: 44px;
}

.roadmap-canvas-stage {
    position: relative;
    min-width: 100%;
    min-height: 100%;
    margin-inline: auto;
    transition: width 0.16s ease, height 0.16s ease;
}

.roadmap-canvas {
    position: relative;
    min-width: 900px;
    transform-origin: top left;
}

.roadmap-connections {
    z-index: 1;
    overflow: visible;
}

.roadmap-edge-halo {
    stroke: rgba(1, 6, 14, 0.82);
}

.roadmap-edge-line {
    stroke: #67e8f9 !important;
    filter: drop-shadow(0 0 2px rgba(34, 211, 238, 0.7));
}

.roadmap-section-box {
    position: absolute;
    z-index: 0;
    display: flex;
    flex-direction: column;
    border: 1px solid rgba(15, 23, 42, 0.12);
    box-shadow: 3px 3px 0 rgba(1, 6, 14, 0.55);
    padding: 12px;
    white-space: pre-wrap;
}

.roadmap-section-title {
    width: 100%;
    margin: 0;
    line-height: 1.2;
    font-family: Inter, sans-serif;
    font-weight: 700;
}

.roadmap-text-block-box {
    position: absolute;
    z-index: 1;
    display: flex;
    flex-direction: column;
    border: 1px solid transparent;
    box-shadow: none;
    padding: 12px;
    white-space: pre-wrap;
}

.roadmap-text-block-content {
    width: 100%;
    margin: 0;
    line-height: 1.45;
    font-family: Inter, sans-serif;
    font-weight: 700;
    text-shadow: 0 1px 0 rgba(2, 8, 23, 0.22);
}

.roadmap-node-box {
    position: absolute;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    border: 0;
    border-radius: 0;
    box-shadow: 3px 3px 0 rgba(1, 6, 14, 0.7);
    padding: 10px;
    cursor: pointer;
    transition: outline 0.15s, transform 0.15s;
}

.roadmap-node-box:hover {
    outline: 2px solid rgba(34, 211, 238, 0.55);
    outline-offset: 2px;
    transform: translate(-1px, -1px);
}

.roadmap-node-box:focus-visible {
    outline: 3px solid #67e8f9;
    outline-offset: 3px;
}

.roadmap-node-title {
    width: 100%;
    line-height: 1.2;
    font-family: Inter, sans-serif;
    font-weight: 700;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.roadmap-theme--light .roadmap-shell {
    border-color: rgba(0, 111, 111, 0.72);
    background:
        linear-gradient(to right, rgba(0, 111, 111, 0.1) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(0, 111, 111, 0.1) 1px, transparent 1px),
        #f7f7f7;
    background-size: 24px 24px;
    box-shadow: 4px 4px 0 rgba(32, 32, 32, 0.16);
}

.roadmap-theme--light .roadmap-edge-halo {
    stroke: rgba(247, 247, 247, 0.96);
}

.roadmap-theme--light .roadmap-edge-line {
    stroke: #006f6f !important;
    filter: drop-shadow(0 1px 0 rgba(32, 32, 32, 0.3));
}

.roadmap-theme--light .roadmap-node-box:hover,
.roadmap-theme--light .roadmap-node-box:focus-visible {
    outline-color: #009999;
}

.roadmap-theme--light .roadmap-zoom-controls button {
    border-color: #6f9292;
    background: #f7f7f7;
    color: #006f6f;
    box-shadow: 2px 2px 0 rgba(0, 111, 111, 0.28);
}

.roadmap-theme--light .roadmap-zoom-controls button:hover {
    border-color: #006f6f;
    background: #009999;
    color: #fff;
}

.classmate-card {
    min-width: 0;
    transition: border-color 0.15s ease, background-color 0.15s ease, transform 0.15s ease;
}

.classmate-card:hover,
.classmate-card:focus-visible {
    border-color: #67e8f9;
    background: rgba(34, 211, 238, 0.1);
    outline: none;
    transform: translate(-1px, -1px);
}

.classmates-page-btn {
    display: inline-flex;
    min-height: 32px;
    align-items: center;
    justify-content: center;
    gap: 5px;
    border: 1px solid #475569;
    background: rgba(15, 23, 42, 0.45);
    padding: 0.45rem 0.65rem;
    color: #cbd5e1;
    font-size: 8px;
    text-transform: uppercase;
    box-shadow: 2px 2px 0 rgba(1, 6, 14, 0.75);
}

.classmates-page-btn:hover:not(:disabled),
.classmates-page-btn:focus-visible:not(:disabled) {
    border-color: #67e8f9;
    color: #fff;
    outline: none;
}

.classmates-page-btn:disabled {
    cursor: not-allowed;
    opacity: 0.35;
}

.classmates-theme--light .classmate-card {
    border-color: #9eb8b8 !important;
    background: #fff !important;
    color: #202020;
}

.classmates-theme--light .classmate-card:hover,
.classmates-theme--light .classmate-card:focus-visible {
    border-color: #009999 !important;
    background: #edf8f8 !important;
}

.classmates-theme--light .classmates-pagination {
    border-top-color: #9eb8b8;
}

.classmates-theme--light .classmates-page-btn {
    border-color: #6f9292;
    background: #f7f7f7;
    color: #006f6f;
    box-shadow: 2px 2px 0 rgba(0, 111, 111, 0.26);
}

.classmates-theme--light .classmates-page-btn:hover:not(:disabled),
.classmates-theme--light .classmates-page-btn:focus-visible:not(:disabled) {
    border-color: #006f6f;
    background: #009999;
    color: #fff;
}
</style>
