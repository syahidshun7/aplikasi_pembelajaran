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
    <article class="group mx-auto w-full max-w-[320px] overflow-hidden border-2 border-slate-700 bg-[#121722]/90 shadow-[6px_6px_0_rgba(0,0,0,0.35)] transition-colors hover:border-cyan-500/60">
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
                    class="absolute inset-0 h-full w-full object-cover object-center"
                >
                <i v-else class="fi fi-rr-lightbulb-on text-[30px] text-cyan-200/80" />

                <span
                    v-if="Number(creation.photos_count || 0) > 1"
                    class="absolute right-2 top-2 rounded border border-black/70 bg-black/60 px-2 py-[2px] text-[6px] uppercase text-cyan-100"
                >
                    +{{ Number(creation.photos_count || 0) - 1 }}
                </span>
            </div>

            <div class="space-y-2 p-2.5">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="line-clamp-1 text-[9px] uppercase tracking-wide text-white">
                        {{ creation.title }}
                    </h3>
                    <span class="rounded border px-2 py-[2px] text-[7px] uppercase" :class="statusClass">
                        {{ creation.status }}
                    </span>
                </div>

                <p class="line-clamp-1 text-[6px] uppercase text-slate-400">
                    {{ creation.creator?.username || creation.creator?.name || 'Adventurer' }}
                </p>

                <div v-if="creation.status !== 'finished'" class="space-y-1">
                    <div class="h-1.5 overflow-hidden border border-slate-700 bg-slate-950">
                        <div class="h-full bg-cyan-500 transition-all" :style="{ width: `${creation.progress || 0}%` }" />
                    </div>
                    <p class="text-[6px] uppercase tracking-wide text-slate-500">{{ creation.progress || 0 }}%</p>
                </div>
            </div>
        </button>

        <div class="flex items-center justify-between border-t border-slate-700 px-2.5 py-2">
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
