<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        required: true,
    },
    items: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue']);

const containerRef = ref(null);
const cardRefs = new Map();
let scrollFrame = null;
let scrollEndTimer = null;
let initialSyncTimer = null;
let syncTimer = null;
let isReadyForScrollSync = false;
let isSyncing = false;
let isUpdatingFromScroll = false;

const setCardRef = (key) => (element) => {
    if (element) {
        cardRefs.set(key, element);
        return;
    }

    cardRefs.delete(key);
};

const syncFlag = (duration = 360) => {
    isSyncing = true;
    window.clearTimeout(syncTimer);
    syncTimer = window.setTimeout(() => {
        isSyncing = false;
    }, duration);
};

const centerCard = (key, behavior = 'smooth') => {
    const card = cardRefs.get(key);

    if (!card) {
        return;
    }

    syncFlag(behavior === 'smooth' ? 360 : 120);
    card.scrollIntoView({
        behavior,
        block: 'nearest',
        inline: 'center',
    });
};

const getNearestKeyFromScroll = () => {
    const container = containerRef.value;

    if (!container) {
        return props.modelValue;
    }

    const containerRect = container.getBoundingClientRect();
    const containerCenter = containerRect.left + (containerRect.width / 2);
    let nearestKey = props.modelValue;
    let nearestDistance = Number.POSITIVE_INFINITY;

    props.items.forEach((item) => {
        const card = cardRefs.get(item.key);

        if (!card) {
            return;
        }

        const cardRect = card.getBoundingClientRect();
        const cardCenter = cardRect.left + (cardRect.width / 2);
        const distance = Math.abs(containerCenter - cardCenter);

        if (distance < nearestDistance) {
            nearestDistance = distance;
            nearestKey = item.key;
        }
    });

    return nearestKey;
};

const updateActiveFromScroll = () => {
    if (!containerRef.value || !isReadyForScrollSync || isSyncing) {
        return;
    }

    const nearestKey = getNearestKeyFromScroll();

    if (nearestKey && nearestKey !== props.modelValue) {
        isUpdatingFromScroll = true;
        emit('update:modelValue', nearestKey);
    }
};

const handleScroll = () => {
    if (scrollFrame) {
        window.cancelAnimationFrame(scrollFrame);
    }

    scrollFrame = window.requestAnimationFrame(updateActiveFromScroll);

    if (!isReadyForScrollSync) {
        return;
    }

    window.clearTimeout(scrollEndTimer);
    scrollEndTimer = window.setTimeout(() => {
        centerCard(props.modelValue, 'smooth');
    }, 120);
};

const selectItem = (key) => {
    emit('update:modelValue', key);
    nextTick(() => centerCard(key));
};

const getNodeClass = (key) => {
    const classes = {
        quest: 'mission',
        library: 'archive',
        townhall: 'event',
        doopnews: 'broadcast',
        party: 'guild',
        leaderboard: 'rank',
    };

    return classes[key] ?? 'core';
};

const getNodeType = (key) => {
    const labels = {
        quest: 'Mission Board',
        library: 'Archive Node',
        townhall: 'Live Events',
        doopnews: 'Broadcast Board',
        party: 'Guild Party',
        leaderboard: 'Rank Board',
    };

    return labels[key] ?? 'System Node';
};

const badgeCountLabel = (value) => {
    const count = Number(value || 0);

    if (count <= 0) {
        return '';
    }

    return count > 99 ? '99+' : String(count);
};

const handleResize = () => {
    if (typeof window !== 'undefined' && window.innerWidth < 768) {
        return;
    }

    centerCard(props.modelValue, 'auto');
};

watch(
    () => props.modelValue,
    async (nextValue, previousValue) => {
        await nextTick();

        if (isUpdatingFromScroll) {
            isUpdatingFromScroll = false;
            return;
        }

        centerCard(nextValue, previousValue ? 'smooth' : 'auto');
    },
);

onMounted(async () => {
    await nextTick();
    containerRef.value?.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('resize', handleResize);

    window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => {
            centerCard(props.modelValue, 'auto');
            window.clearTimeout(initialSyncTimer);
            initialSyncTimer = window.setTimeout(() => {
                centerCard(props.modelValue, 'auto');
                isReadyForScrollSync = true;
            }, 260);
        });
    });
});

onBeforeUnmount(() => {
    containerRef.value?.removeEventListener('scroll', handleScroll);
    window.removeEventListener('resize', handleResize);
    window.clearTimeout(scrollEndTimer);
    window.clearTimeout(initialSyncTimer);
    window.clearTimeout(syncTimer);

    if (scrollFrame) {
        window.cancelAnimationFrame(scrollFrame);
    }
});
</script>

<template>
    <div class="carousel-shell">
        <div
            ref="containerRef"
            class="carousel-track"
            aria-label="Dashboard navigation carousel"
            role="tablist"
        >
            <div class="carousel-track__spacer" aria-hidden="true"></div>

            <button
                v-for="(item, index) in items"
                :key="item.key"
                :ref="setCardRef(item.key)"
                type="button"
                role="tab"
                :aria-selected="modelValue === item.key ? 'true' : 'false'"
                :class="['carousel-card', modelValue === item.key ? 'carousel-card--active' : 'carousel-card--inactive']"
                @click="selectItem(item.key)"
            >
                <div class="carousel-card__frame" :class="[item.accent, `carousel-card__frame--${getNodeClass(item.key)}`]">
                    <span
                        v-if="Number(item.badge_count || 0) > 0"
                        class="carousel-card__new-dot"
                        :aria-label="`${badgeCountLabel(item.badge_count)} new item`"
                    >
                        {{ badgeCountLabel(item.badge_count) }}
                    </span>
                    <div class="carousel-card__header">
                        <div class="carousel-card__icon">
                            <i :class="item.icon"></i>
                        </div>
                        <div class="carousel-card__badge">
                            {{ getNodeType(item.key) }}
                        </div>
                    </div>

                    <div class="carousel-card__body">
                        <p class="carousel-card__eyebrow">{{ modelValue === item.key ? 'Active Node' : 'Explore Node' }}</p>
                        <h3 class="carousel-card__title">{{ item.title }}</h3>
                    </div>

                    <div class="carousel-card__stats">
                        <span class="carousel-card__chip">Node {{ String(index + 1).padStart(2, '0') }}</span>
                        <span class="carousel-card__chip carousel-card__chip--state">
                            {{ modelValue === item.key ? 'Selected' : 'Ready' }}
                        </span>
                    </div>
                </div>
            </button>

            <div class="carousel-track__spacer" aria-hidden="true"></div>
        </div>
    </div>
</template>

<style scoped>
.carousel-shell {
    @apply relative mx-auto max-w-[80rem] px-1 pb-7 pt-9 sm:px-2 sm:pb-8 sm:pt-10;
}

.carousel-track {
    @apply relative z-10 flex snap-x snap-mandatory items-stretch gap-4 overflow-x-auto pb-3 pt-3;
    scrollbar-width: none;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
    touch-action: pan-x;
}

.carousel-track::-webkit-scrollbar {
    display: none;
}

.carousel-track__spacer {
    flex: 0 0 max(1rem, calc(50% - min(36vw, 146px)));
}

.carousel-card {
    @apply snap-center border-none bg-transparent p-0 text-left outline-none transition-all duration-500 ease-in-out;
    flex: 0 0 min(72vw, 292px);
    scroll-snap-align: center;
    user-select: none;
}

.carousel-card__frame {
    @apply relative flex min-h-[188px] flex-col justify-between overflow-hidden border-2 border-[#31445f] bg-[#111827]/95 p-4 shadow-[0_18px_34px_rgba(3,8,18,0.48)];
}

.carousel-card__new-dot {
    @apply absolute right-3 top-3 z-20 flex min-h-[18px] min-w-[18px] items-center justify-center rounded-full border border-red-200 bg-red-500 px-1.5 text-[7px] font-bold leading-none text-white shadow-[0_0_14px_rgba(239,68,68,0.78)];
}

.carousel-card__frame::before {
    content: "";
    @apply absolute inset-[10px] border border-white/10;
}

.carousel-card__frame::after {
    content: "";
    @apply absolute inset-x-8 top-0 h-16 bg-cyan-300/10 blur-xl;
}

.carousel-card__header {
    @apply relative z-10 flex items-start justify-between gap-3;
}

.carousel-card__icon {
    @apply relative z-10 flex h-14 w-14 items-center justify-center border border-cyan-300/30 bg-[#0b1220] text-[22px] text-cyan-200 shadow-[0_0_16px_rgba(78,212,212,0.16)];
}

.carousel-card__badge {
    @apply border border-white/10 bg-black/20 px-2.5 py-1 text-[7px] uppercase tracking-[0.18em] text-slate-300;
}

.carousel-card__body {
    @apply relative z-10 mt-5 space-y-3;
}

.carousel-card__eyebrow {
    @apply text-[8px] uppercase tracking-[0.22em] text-cyan-300/70;
}

.carousel-card__title {
    @apply text-[14px] uppercase tracking-[0.12em] text-white;
}

.carousel-card__stats {
    @apply relative z-10 flex items-center justify-between gap-3 border-t border-white/10 pt-4;
}

.carousel-card__chip {
    @apply border border-white/10 bg-black/20 px-2.5 py-1 text-[7px] uppercase tracking-[0.14em] text-slate-300;
}

.carousel-card__chip--state {
    @apply text-cyan-200;
}

.carousel-card--active {
    @apply z-20 scale-100 opacity-100 blur-0;
    transform: translateY(-8px);
}

.carousel-card--active .carousel-card__frame {
    @apply border-cyan-300/70 shadow-[0_24px_42px_rgba(8,24,44,0.6)];
}

.carousel-card--active .carousel-card__badge,
.carousel-card--active .carousel-card__chip {
    @apply border-cyan-300/30 bg-cyan-300/10;
}

.carousel-card--inactive {
    @apply z-10 opacity-55;
    filter: blur(1px);
    transform: translateY(8px) scale(0.82);
}

.from-amber-node {
    background:
        linear-gradient(180deg, rgba(245, 158, 11, 0.14), rgba(255,255,255,0) 22%),
        linear-gradient(160deg, rgba(30, 41, 59, 0.98), rgba(17, 24, 39, 0.98));
}

.from-indigo-node {
    background:
        linear-gradient(180deg, rgba(99, 102, 241, 0.16), rgba(255,255,255,0) 24%),
        linear-gradient(160deg, rgba(20, 25, 48, 0.98), rgba(17, 24, 39, 0.98));
}

.from-blue-node {
    background:
        linear-gradient(180deg, rgba(56, 189, 248, 0.18), rgba(255,255,255,0) 22%),
        linear-gradient(160deg, rgba(15, 29, 46, 0.98), rgba(17, 24, 39, 0.98));
}

.from-rose-node {
    background:
        linear-gradient(180deg, rgba(244, 63, 94, 0.16), rgba(255,255,255,0) 22%),
        linear-gradient(160deg, rgba(48, 18, 32, 0.98), rgba(17, 24, 39, 0.98));
}

.from-emerald-node {
    background:
        linear-gradient(180deg, rgba(16, 185, 129, 0.16), rgba(255,255,255,0) 22%),
        linear-gradient(160deg, rgba(16, 31, 34, 0.98), rgba(17, 24, 39, 0.98));
}

.from-cyan-node {
    background:
        linear-gradient(180deg, rgba(34, 211, 238, 0.16), rgba(255,255,255,0) 22%),
        linear-gradient(160deg, rgba(15, 32, 41, 0.98), rgba(17, 24, 39, 0.98));
}

.carousel-card__frame--mission {
    box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.1);
}

.carousel-card__frame--archive {
    box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.12);
}

.carousel-card__frame--event {
    box-shadow: inset 0 0 0 1px rgba(56, 189, 248, 0.14);
}

.carousel-card__frame--broadcast {
    box-shadow: inset 0 0 0 1px rgba(244, 63, 94, 0.12);
}

.carousel-card__frame--guild {
    box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.12);
}

.carousel-card__frame--rank {
    box-shadow: inset 0 0 0 1px rgba(34, 211, 238, 0.12);
}

[data-theme='light'] .carousel-card__frame {
    border-color: #087f7f;
    background:
        linear-gradient(180deg, rgba(247, 247, 247, 0.28), rgba(247, 247, 247, 0.10) 42%, rgba(0, 153, 153, 0.12)),
        #009999;
    box-shadow:
        0 18px 34px rgba(0, 153, 153, 0.20),
        0 0 0 1px rgba(32, 32, 32, 0.10);
}

[data-theme='light'] .carousel-card__frame::before {
    border-color: rgba(247, 247, 247, 0.28);
}

[data-theme='light'] .carousel-card__icon {
    background: #202020;
    color: #f7f7f7;
    border-color: rgba(247, 247, 247, 0.58);
    box-shadow: 0 8px 18px rgba(32, 32, 32, 0.18);
}

[data-theme='light'] .carousel-card__badge,
[data-theme='light'] .carousel-card__chip {
    border-color: rgba(247, 247, 247, 0.38);
    background: rgba(247, 247, 247, 0.18);
    color: #f7f7f7;
}

[data-theme='light'] .carousel-card__title {
    color: #ffffff;
    text-shadow: 0 1px 0 rgba(32, 32, 32, 0.18);
}

[data-theme='light'] .carousel-card__eyebrow,
[data-theme='light'] .carousel-card__chip--state {
    color: #ffffff;
}

[data-theme='light'] .carousel-card__stats {
    border-top-color: rgba(247, 247, 247, 0.26);
}

[data-theme='light'] .from-amber-node,
[data-theme='light'] .from-indigo-node,
[data-theme='light'] .from-blue-node,
[data-theme='light'] .from-rose-node,
[data-theme='light'] .from-emerald-node,
[data-theme='light'] .from-cyan-node {
    background:
        linear-gradient(180deg, rgba(247, 247, 247, 0.30), rgba(247,247,247,0.08) 32%),
        linear-gradient(160deg, #38b2b2, #009999 54%, #087f7f);
}

[data-theme='light'] .carousel-card--inactive .carousel-card__frame {
    background:
        linear-gradient(180deg, rgba(247, 247, 247, 0.22), rgba(247,247,247,0.06) 34%),
        linear-gradient(160deg, #38b2b2, #009999 58%, #087f7f);
}

[data-theme='light'] .carousel-card--active .carousel-card__frame {
    border-color: #005f5f;
    box-shadow:
        0 24px 42px rgba(0, 153, 153, 0.28),
        0 0 0 2px rgba(0, 95, 95, 0.28);
}

@media (max-width: 767px) {
    .carousel-shell {
        @apply px-0 pb-5 pt-6;
    }

    .carousel-track {
        @apply gap-3;
        scroll-snap-type: x proximity;
    }

    .carousel-track__spacer {
        flex-basis: max(0.75rem, calc(50% - min(40vw, 126px)));
    }

    .carousel-card {
        flex-basis: min(82vw, 252px);
    }

    .carousel-card__frame {
        @apply min-h-[170px] p-4;
    }

    .carousel-card__badge {
        @apply hidden;
    }

    .carousel-card__body {
        @apply mt-4;
    }

    .carousel-card__stats {
        @apply pt-3;
    }
}
</style>
