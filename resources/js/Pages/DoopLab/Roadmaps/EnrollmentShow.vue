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
const edges = computed(() => roadmap.value.edges || []);
const isOwner = computed(() => Boolean(props.enrollment?.is_owner));
const isMentor = computed(() => Boolean(props.enrollment?.is_mentor));

const selectedNode = ref(null);

const nodeMap = computed(() => {
    return new Map(nodes.value.map((n) => [String(n.uuid), n]));
});

const boardWidth = computed(() => {
    const maxX = Math.max(1000, ...nodes.value.map((n) => Number(n.x || 0) + Number(n.width || 0)),
        ...sections.value.map((s) => Number(s.x || 0) + Number(s.width || 0)));
    return maxX + 120;
});

const boardHeight = computed(() => {
    const maxY = Math.max(680, ...nodes.value.map((n) => Number(n.y || 0) + Number(n.height || 0)),
        ...sections.value.map((s) => Number(s.y || 0) + Number(s.height || 0)));
    return maxY + 120;
});

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
    }), {}, { preserveScroll: true });
};

const lockNode = (node) => {
    router.post(route('dooplab.roadmaps.enrollments.lock', {
        enrollment: props.enrollment.uuid,
        nodeUuid: node.uuid,
    }), {}, { preserveScroll: true });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Path: ${roadmap.title}`" />
        <div class="p-4 md:p-8 text-[10px] font-['Press_Start_2P'] text-[#4ed4d4] space-y-4">
            <div class="flex items-center justify-between border-b-2 border-cyan-900 pb-3">
                <div>
                    <h1 class="text-sm md:text-lg uppercase tracking-wider">{{ roadmap.title }}</h1>
                    <p class="text-[8px] text-slate-400 uppercase mt-1">
                        {{ isMentor ? `Student: ${enrollment.student_name}` : `Mentor: ${enrollment.mentor_name}` }}
                    </p>
                </div>
                <Link :href="route('dooplab.roadmaps.enrollments.index')" class="px-3 py-2 border border-slate-700 text-slate-300 hover:text-white uppercase text-[8px]">Back</Link>
            </div>

            <div class="panel overflow-auto">
                <div class="roadmap-board" :style="{ width: `${boardWidth}px`, height: `${boardHeight}px` }">
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

            <div v-if="selectedNode" class="panel space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-[10px] text-cyan-300 uppercase">{{ selectedNode.title }}</h3>
                    <button class="text-[8px] text-slate-400 uppercase" @click="selectedNode = null">Close</button>
                </div>
                <p class="text-[9px] text-slate-300">Status: {{ getNodeStatusLabel(selectedNode) }}</p>

                <div v-if="selectedNode.resource_meta" class="text-[9px]">
                    <a :href="selectedNode.resource_meta.href" target="_blank" class="text-cyan-400 underline">
                        {{ selectedNode.resource_meta.type === 'guide' ? '📖' : '⚔️' }} {{ selectedNode.resource_meta.label }}
                    </a>
                </div>

                <div v-if="selectedNode.progress?.mentor_note" class="text-[9px] text-amber-300">
                    Mentor: {{ selectedNode.progress.mentor_note }}
                </div>

                <div v-if="isOwner && (selectedNode.progress?.status === 'unlocked' || selectedNode.progress?.status === 'revision')" class="space-y-2">
                    <textarea v-model="submitForm.student_note" placeholder="Catatan (opsional)" class="field w-full h-16 resize-none" />
                    <button class="btn-primary" :disabled="submitForm.processing" @click="submitNode(selectedNode)">Submit</button>
                </div>

                <div v-if="isMentor && selectedNode.progress?.status === 'submitted'" class="space-y-2">
                    <textarea v-model="reviewForm.mentor_note" placeholder="Feedback (opsional)" class="field w-full h-16 resize-none" />
                    <div class="flex gap-2">
                        <button class="btn-primary" :disabled="reviewForm.processing" @click="reviewForm.decision = 'approved'; reviewNode(selectedNode)">Approve</button>
                        <button class="btn-danger" :disabled="reviewForm.processing" @click="reviewForm.decision = 'revision'; reviewNode(selectedNode)">Revisi</button>
                    </div>
                </div>

                <div v-if="isMentor && selectedNode.progress?.status === 'locked'" class="space-y-2">
                    <button class="btn-primary" @click="unlockNode(selectedNode)">Unlock Node</button>
                </div>

                <div v-if="isMentor && selectedNode.progress?.status === 'unlocked'" class="space-y-2">
                    <button class="btn-danger" @click="lockNode(selectedNode)">Relock Node</button>
                </div>
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
    position: relative;
    background-image:
        linear-gradient(to right, rgba(148, 163, 184, 0.12) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(148, 163, 184, 0.12) 1px, transparent 1px);
    background-size: 24px 24px;
    background-color: #0b1220;
    border-radius: 0;
    border: 2px solid rgba(87, 214, 255, 0.24);
    box-shadow: inset 0 0 0 1px rgba(87, 214, 255, 0.06);
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
</style>
