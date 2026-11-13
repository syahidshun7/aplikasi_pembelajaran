<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import doopNewsIcon from './node-icons/doopnews.png';
import leaderboardIcon from './node-icons/leaderboard.png';
import libraryIcon from './node-icons/library.png';
import partyIcon from './node-icons/party.png';
import questIcon from './node-icons/quest.png';
import townhallIcon from './node-icons/townhall.png';

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
let scrollEndTimer = null;
let isUpdatingFromScroll = false;
let trackWidth = 0;

const setCardRef = (key) => (element) => {
    if (element) {
        cardRefs.set(key, element);
        return;
    }

    cardRefs.delete(key);
};

const centerCard = (key, behavior = 'smooth') => {
    const container = containerRef.value;
    const card = cardRefs.get(key);

    if (!container || !card) {
        return;
    }

    const trackRect = container.getBoundingClientRect();
    const cardRect = card.getBoundingClientRect();
    const offset = cardRect.left + cardRect.width / 2 - trackRect.left - trackRect.width / 2;
    container.scrollBy({ left: offset, behavior });
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

const syncCenteredCard = () => {
    window.clearTimeout(scrollEndTimer);
    const nearestKey = getNearestKeyFromScroll();

    if (nearestKey && nearestKey !== props.modelValue) {
        isUpdatingFromScroll = true;
        emit('update:modelValue', nearestKey);
        nextTick(() => {
            isUpdatingFromScroll = false;
        });
    }
};

const handleScroll = () => {
    window.clearTimeout(scrollEndTimer);
    scrollEndTimer = window.setTimeout(syncCenteredCard, 180);
};

const nodeIcons = {
    doopnews: doopNewsIcon,
    quest: questIcon,
    library: libraryIcon,
    townhall: townhallIcon,
    party: partyIcon,
    leaderboard: leaderboardIcon,
};

const badgeCountLabel = (value) => {
    const count = Number(value || 0);

    if (count <= 0) {
        return '';
    }

    return count > 99 ? '99+' : String(count);
};

const handleResize = () => {
    const width = containerRef.value?.clientWidth;

    if (width && width !== trackWidth) {
        trackWidth = width;
        centerCard(props.modelValue, 'instant');
    }
};

watch(
    () => props.modelValue,
    (key) => {
        if (!isUpdatingFromScroll) {
            centerCard(key);
        }
    },
    { flush: 'post' },
);

onMounted(() => {
    handleResize();
    window.addEventListener('resize', handleResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize);
    window.clearTimeout(scrollEndTimer);
});
</script>

<template>
    <div class="carousel-shell">
        <div
            ref="containerRef"
            class="carousel-track"
            aria-label="Dashboard navigation carousel"
            role="tablist"
            @scroll.passive="handleScroll"
            @scrollend="syncCenteredCard"
        >
            <div class="carousel-track__spacer" aria-hidden="true"></div>

            <button
                v-for="item in items"
                :key="item.key"
                :ref="setCardRef(item.key)"
                type="button"
                role="tab"
                :aria-selected="modelValue === item.key ? 'true' : 'false'"
                class="carousel-card"
                @click="centerCard(item.key)"
            >
                <div class="carousel-card__frame">
                    <span
                        v-if="Number(item.badge_count || 0) > 0"
                        class="carousel-card__new-dot"
                        :aria-label="`${badgeCountLabel(item.badge_count)} new item`"
                    >
                        {{ badgeCountLabel(item.badge_count) }}
                    </span>
                    <div class="carousel-card__icon" aria-hidden="true">
                        <img v-if="nodeIcons[item.key]" :src="nodeIcons[item.key]" width="48" height="40" alt="">
                        <i v-else :class="item.icon"></i>
                    </div>
                    <h3 class="carousel-card__title">{{ item.title }}</h3>
                </div>
            </button>

            <div class="carousel-track__spacer" aria-hidden="true"></div>
        </div>
        <p class="carousel-swipe-hint">Swipe left or right</p>
    </div>
</template>

<style scoped>
.carousel-shell {
    @apply relative mx-auto max-w-[76rem] px-1 pb-4 pt-6 sm:px-2 sm:pb-5 sm:pt-7;
    --card-idle-edge: var(--panel-border);
    --card-shadow: color-mix(in srgb, var(--bg) 60%, transparent);
    --card-glow: color-mix(in srgb, var(--accent) 28%, transparent);
}

:global([data-app-surface='user'][data-theme='light']) .carousel-shell {
    --card-idle-edge: color-mix(in srgb, var(--panel-border) 82%, var(--text));
    --card-shadow: color-mix(in srgb, var(--text) 15%, transparent);
    --card-glow: color-mix(in srgb, var(--accent) 12%, transparent);
}

.carousel-track {
    @apply relative z-10 flex snap-x snap-mandatory items-stretch gap-4 overflow-x-auto pb-3 pt-3;
    scrollbar-width: none;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
    touch-action: pan-x pan-y pinch-zoom;
}

.carousel-track::-webkit-scrollbar {
    display: none;
}

.carousel-swipe-hint {
    display: none;
}

.carousel-track__spacer {
    flex: 0 0 max(1rem, calc(50% - min(34vw, 126px)));
}

.carousel-card {
    --card-edge: var(--card-idle-edge);
    --card-surface: var(--panel);
    --pixel-corners: polygon(
        12px 0, calc(100% - 12px) 0, calc(100% - 12px) 4px,
        calc(100% - 8px) 4px, calc(100% - 8px) 8px,
        calc(100% - 4px) 8px, calc(100% - 4px) 12px, 100% 12px,
        100% calc(100% - 12px), calc(100% - 4px) calc(100% - 12px),
        calc(100% - 4px) calc(100% - 8px), calc(100% - 8px) calc(100% - 8px),
        calc(100% - 8px) calc(100% - 4px), calc(100% - 12px) calc(100% - 4px),
        calc(100% - 12px) 100%, 12px 100%, 12px calc(100% - 4px),
        8px calc(100% - 4px), 8px calc(100% - 8px), 4px calc(100% - 8px),
        4px calc(100% - 12px), 0 calc(100% - 12px), 0 12px, 4px 12px,
        4px 8px, 8px 8px, 8px 4px, 12px 4px
    );
    @apply snap-center border-none bg-transparent p-0 text-center duration-500 ease-in-out;
    flex: 0 0 min(68vw, 252px);
    scroll-snap-align: center;
    user-select: none;
    color: var(--text);
    transform: translateY(4px) scale(0.88);
    transition-property: transform, filter;
    filter: drop-shadow(0 4px 4px var(--card-shadow));
}

.carousel-card:hover {
    --card-edge: color-mix(in srgb, var(--panel-border) 45%, var(--accent));
}

.carousel-card[aria-selected='true'] {
    --card-edge: var(--accent);
    --card-surface: color-mix(in srgb, var(--panel) 88%, var(--accent));
    z-index: 20;
    transform: translateY(-4px);
    filter: drop-shadow(0 4px 4px var(--card-shadow)) drop-shadow(0 2px 8px var(--card-glow));
}

.carousel-card:focus-visible {
    outline: none;
}

.carousel-card__frame {
    @apply relative isolate flex min-h-[164px] flex-col items-center justify-center gap-3 p-3;
}

.carousel-card__frame::before,
.carousel-card__frame::after {
    content: '';
    position: absolute;
    clip-path: var(--pixel-corners);
    pointer-events: none;
}

.carousel-card__frame::before {
    z-index: -2;
    inset: 0;
    background: var(--card-edge);
}

.carousel-card__frame::after {
    z-index: -1;
    inset: 2px;
    background:
        linear-gradient(155deg, var(--card-surface), color-mix(in srgb, var(--card-surface) 76%, var(--bg)));
    box-shadow: inset 0 0 0 2px var(--card-surface), inset 0 0 0 3px var(--card-edge);
}

.carousel-card__new-dot {
    @apply absolute right-2 top-2 z-10 flex min-h-[24px] min-w-[24px] items-center justify-center bg-red-700 px-1.5 text-[8px] leading-none text-white;
    clip-path: polygon(4px 0, calc(100% - 4px) 0, 100% 4px, 100% calc(100% - 4px),
        calc(100% - 4px) 100%, 4px 100%, 0 calc(100% - 4px), 0 4px);
}

.carousel-card__icon {
    @apply grid h-24 w-28 place-items-center;
    color: var(--accent);
}

.carousel-card__icon img {
    width: min(100%, 108px);
    height: auto;
    image-rendering: pixelated;
    filter: drop-shadow(0 3px 0 var(--card-shadow));
}

.carousel-card__title {
    @apply text-[11px] uppercase leading-relaxed tracking-[0.04em];
    font-family: "Press Start 2P", monospace;
}

@media (max-width: 767px) {
    .carousel-shell {
        @apply px-0 pb-2 pt-4;
    }

    .carousel-swipe-hint {
        display: block;
        margin: 0.25rem 0 0;
        color: var(--text);
        font-size: 7px;
        line-height: 1.5;
        text-align: center;
    }

    .carousel-track {
        @apply gap-3;
        scroll-snap-type: x proximity;
    }

    .carousel-track__spacer {
        flex-basis: max(0.75rem, calc(50% - min(41vw, 126px)));
    }

    .carousel-card {
        flex-basis: min(82vw, 252px);
    }

    .carousel-card__frame {
        @apply min-h-[170px] p-4;
    }
}
</style>
