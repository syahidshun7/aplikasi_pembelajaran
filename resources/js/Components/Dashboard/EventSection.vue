<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    authUser: {
        type: Boolean,
        default: false,
    },
    newCount: {
        type: Number,
        default: 0,
    },
});
const emit = defineEmits(['item-open']);

const hashGroupKey = (value) => {
    const normalized = String(value || 'public');
    let hash = 0;

    for (let index = 0; index < normalized.length; index += 1) {
        hash = ((hash << 5) - hash) + normalized.charCodeAt(index);
        hash |= 0;
    }

    return Math.abs(hash);
};

const toneForEvent = (event) => {
    const groupId = event?.study_group_id ?? event?.study_group?.id ?? null;

    if (!groupId) {
        return {
            border: '#2d65cf',
            bg: 'rgba(22, 47, 93, 0.24)',
            accent: '#8cc4ff',
        };
    }

    const hash = hashGroupKey(String(groupId));
    let hue = Math.floor((hash * 137.508) % 360);

    // Hindari hue public (biru-cyan) agar public selalu terlihat beda.
    if (hue >= 185 && hue <= 225) {
        hue = (hue + 92) % 360;
    }

    const saturation = 66 + (hash % 8);
    const borderLightness = 56 + ((hash >> 3) % 7);
    const accentLightness = 74 + ((hash >> 5) % 8);

    return {
        border: `hsl(${hue} ${saturation}% ${borderLightness}%)`,
        bg: `hsl(${hue} ${Math.max(60, saturation - 6)}% 20% / 0.24)`,
        accent: `hsl(${hue} ${Math.min(90, saturation + 10)}% ${accentLightness}%)`,
    };
};

const eventItemsWithTone = computed(() => {
    return (props.items || []).map((event) => {
        const tone = toneForEvent(event);

        return {
            ...event,
            __tone_style: {
                '--event-tone-border': tone.border,
                '--event-tone-bg': tone.bg,
                '--event-tone-accent': tone.accent,
            },
        };
    });
});
const visibleNewItemCount = computed(() => {
    return (props.items || []).filter((event) => Boolean(event?.is_new_for_user)).length;
});
const hiddenNewItemCount = computed(() => {
    if (visibleNewItemCount.value > 0) {
        return 0;
    }

    return Math.max(0, Number(props.newCount || 0));
});
</script>

<template>
    <section class="dashboard-section-shell">
        <div class="dashboard-section-header">
            <div>
                <p class="dashboard-section-header__eyebrow text-blue-300/80">Upcoming</p>
                <h2 class="dashboard-section-header__title text-blue-300">Town Hall Timeline</h2>
            </div>
            <Link
                :href="authUser ? route('events.user.index') : route('login')"
                class="dashboard-section-header__action border-blue-700 bg-blue-900/30 text-blue-200 hover:bg-blue-500/40"
            >
                <i class="fi fi-rr-calendar text-[18px] leading-none"></i>
                <span>View All Events</span>
                <span v-if="hiddenNewItemCount > 0" class="view-all-new-badge">{{ hiddenNewItemCount }} NEW</span>
            </Link>
        </div>

        <div v-if="items.length > 0" class="space-y-4">
            <article
                v-for="event in eventItemsWithTone"
                :key="event.uuid"
                class="event-card flex h-full flex-col border p-4 shadow-[0_12px_22px_rgba(3,8,16,0.34)]"
                :style="event.__tone_style"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="event-card__meeting text-[7px] uppercase">Meeting_{{ event.sequence_order }}</span>
                        <span v-if="event.is_new_for_user" class="event-new-badge">NEW</span>
                    </div>
                    <span class="event-card__group rounded-full border px-2 py-1 text-[7px] uppercase">
                        {{ event.study_group?.name || 'Public' }}
                    </span>
                </div>
                <h3 class="mt-3 break-words text-[10px] uppercase text-white">{{ event.title }}</h3>
                <p class="mt-2 text-[8px] uppercase leading-relaxed text-slate-400">{{ event.__starts_at_label }}</p>
                <div class="event-card__footer mt-4 flex min-h-[38px] items-center justify-between gap-2 border-t border-slate-800 pt-3">
                    <span class="text-[7px] uppercase text-cyan-300">Event Node</span>
                    <Link
                        :href="route('events.show', event.uuid)"
                        class="event-card__action text-[7px] uppercase text-blue-300"
                        @click="emit('item-open', { type: 'event', item: event })"
                    >
                        View >
                    </Link>
                </div>
            </article>
        </div>

        <div v-else class="dashboard-empty-state">
            <div class="dashboard-empty-state__icon">+</div>
            <h3 class="dashboard-empty-state__title text-blue-300">No Events Available</h3>
            <p class="dashboard-empty-state__copy">No schedules are queued in the town hall right now.</p>
        </div>
    </section>
</template>

<style scoped>
.dashboard-section-shell {
    @apply border-2 border-[#3d415f] bg-[#1a1c2c] p-5 shadow-[0_18px_50px_rgba(2,8,16,0.46)];
}

.dashboard-section-header {
    @apply mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-slate-700 pb-4;
}

.dashboard-section-header__eyebrow {
    @apply text-[7px] uppercase tracking-[0.24em];
}

.dashboard-section-header__title {
    @apply text-[10px] uppercase tracking-widest sm:text-xs;
}

.dashboard-section-header__action {
    @apply inline-flex items-center gap-2 border-b-4 border-r-4 p-2 text-[8px] uppercase shadow-[3px_3px_0px_0px_rgba(0,0,0,0.5)] transition-colors;
}

.view-all-new-badge {
    @apply inline-flex min-w-[30px] items-center justify-center rounded-full border border-red-200 bg-red-500 px-1.5 py-0.5 text-[7px] font-bold leading-none text-white shadow-[0_0_10px_rgba(239,68,68,0.45)];
}

.dashboard-empty-state {
    @apply flex min-h-[260px] flex-col items-center justify-center border-2 border-dashed border-slate-800 p-6 text-center;
}

.dashboard-empty-state__icon {
    @apply mb-4 text-4xl italic text-slate-600;
}

.dashboard-empty-state__title {
    @apply mb-2 text-[12px] font-bold uppercase tracking-[0.2em];
}

.dashboard-empty-state__copy {
    @apply max-w-[280px] text-[9px] uppercase leading-relaxed text-slate-500;
}

.event-card {
    border-color: color-mix(in srgb, var(--event-tone-border) 52%, #1f2937 48%);
    background:
        linear-gradient(
            180deg,
            var(--event-tone-bg) 0%,
            rgba(13, 17, 23, 0.92) 100%
        );
}

.event-card__meeting {
    color: color-mix(in srgb, var(--event-tone-accent) 86%, #cbd5e1 14%);
}

.event-new-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #fecaca;
    background: #ef4444;
    color: #fff;
    box-shadow: 0 0 12px rgba(239, 68, 68, 0.62);
    padding: 0.22rem 0.4rem;
    font-size: 7px;
    font-weight: 700;
    line-height: 1;
    letter-spacing: 0.14em;
}

.event-card__group {
    border-color: color-mix(in srgb, var(--event-tone-border) 56%, transparent 44%);
    background: color-mix(in srgb, var(--event-tone-bg) 72%, transparent 28%);
    color: color-mix(in srgb, var(--event-tone-accent) 88%, #f8fafc 12%);
}
</style>
