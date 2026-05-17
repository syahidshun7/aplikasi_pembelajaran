<script setup>
import { computed } from 'vue';

const props = defineProps({
    creation: {
        type: Object,
        required: true,
    },
    busy: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['open', 'appreciate', 'insight']);

const statusClass = computed(() => {
    const status = String(props.creation?.status || '');

    if (status === 'finished') {
        return 'border-emerald-500/70 text-emerald-300';
    }

    if (status === 'refining') {
        return 'border-amber-500/70 text-amber-300';
    }

    return 'border-cyan-500/70 text-cyan-300';
});

const previewClass = computed(() => {
    const status = String(props.creation?.status || '');

    if (status === 'finished') {
        return 'from-emerald-900/70 via-[#142018] to-[#0a0f0c]';
    }

    if (status === 'refining') {
        return 'from-amber-900/70 via-[#201a10] to-[#100f09]';
    }

    return 'from-cyan-900/70 via-[#111c22] to-[#090d10]';
});
</script>

<template>
    <article class="group w-full overflow-hidden border-2 border-slate-700 bg-[#121722]/90 shadow-[6px_6px_0_rgba(0,0,0,0.35)] transition-colors hover:border-cyan-500/60">
        <button
            type="button"
            class="block w-full text-left"
            @click="emit('open', creation)"
        >
            <div class="relative aspect-[4/3] overflow-hidden border-b border-slate-700 bg-gradient-to-br p-3" :class="previewClass">
                <div class="absolute -right-3 -top-3 h-12 w-12 rounded-full border border-white/10 bg-white/5 blur-sm" />
                <div class="absolute -bottom-4 -left-4 h-10 w-10 rounded-full border border-white/10 bg-white/5 blur-sm" />
                <img
                    v-if="creation.thumbnail_url"
                    :src="creation.thumbnail_url"
                    alt="Creation thumbnail"
                    loading="lazy"
                    decoding="async"
                    class="absolute inset-0 h-full w-full object-cover object-center"
                >
                <i v-else class="fi fi-rr-lightbulb-on text-[30px] text-cyan-200/80" />

                <span
                    v-if="Number(creation.photos_count || 0) > 1"
                    class="absolute right-2 top-2 rounded border border-black/70 bg-black/60 px-2 py-[2px] text-[6px] uppercase text-cyan-100"
                >
                    +{{ Number(creation.photos_count || 0) - 1 }}
                </span>

                <div class="absolute left-2 top-2 flex flex-wrap gap-1">
                    <span
                        v-if="Number(creation.team_size || 1) > 1"
                        class="rounded border border-black/70 bg-black/60 px-2 py-[2px] text-[6px] uppercase text-emerald-100"
                    >
                        Team {{ creation.team_size }}
                    </span>
                    <span
                        v-if="creation.is_open_for_collaboration"
                        class="rounded border border-black/70 bg-black/60 px-2 py-[2px] text-[6px] uppercase text-amber-100"
                    >
                        Open Collab
                    </span>
                </div>
            </div>

            <div class="space-y-2.5 p-3">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <h3 class="line-clamp-2 text-[8px] uppercase leading-relaxed tracking-wide text-white sm:text-[9px]">
                        {{ creation.title }}
                    </h3>
                    <span class="w-fit rounded border px-2 py-[2px] text-[7px] uppercase" :class="statusClass">
                        {{ creation.status }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-[6px] uppercase sm:text-[7px]">
                    <span class="inline-flex items-center gap-1 text-slate-400">
                        <i class="fi fi-rr-user text-[8px]"></i>
                        <span class="line-clamp-1">{{ creation.creator?.username || creation.creator?.name || 'Adventurer' }}</span>
                    </span>
                    <span v-if="creation.category" class="inline-flex items-center gap-1 border border-cyan-500/20 bg-cyan-500/5 px-2 py-1 text-cyan-200/80">
                        <i class="fi fi-rr-apps text-[8px]"></i>
                        {{ creation.category }}
                    </span>
                    <span v-if="Number(creation.team_size || 1) > 1" class="inline-flex items-center gap-1 border border-emerald-500/20 bg-emerald-500/5 px-2 py-1 text-emerald-200/80">
                        <i class="fi fi-rr-users text-[8px]"></i>
                        Team Project
                    </span>
                </div>

                <div v-if="creation.status !== 'finished'" class="space-y-1">
                    <div class="h-1.5 overflow-hidden border border-slate-700 bg-slate-950">
                        <div class="h-full bg-cyan-500 transition-all" :style="{ width: `${creation.progress || 0}%` }" />
                    </div>
                    <p class="text-[6px] uppercase tracking-wide text-slate-500">{{ creation.progress || 0 }}%</p>
                </div>
            </div>
        </button>

        <div class="flex items-center justify-between border-t border-slate-700 px-3 py-2.5">
            <button
                type="button"
                class="inline-flex items-center gap-1 text-[8px] transition-colors"
                :class="creation.is_appreciated ? 'text-rose-300' : 'text-slate-400 hover:text-rose-300'"
                :disabled="busy"
                @click="emit('appreciate', creation)"
            >
                <i class="fi fi-rr-heart text-[10px]" />
                <span>{{ creation.appreciations_count || 0 }}</span>
            </button>

            <button
                type="button"
                class="inline-flex items-center gap-1 text-[8px] text-slate-400 transition-colors hover:text-cyan-300"
                @click="emit('insight', creation)"
            >
                <i class="fi fi-rr-comment-alt text-[10px]" />
                <span>{{ creation.insights_count || 0 }}</span>
            </button>
        </div>
    </article>
</template>
