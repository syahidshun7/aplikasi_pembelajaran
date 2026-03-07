<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onBeforeUpdate, onMounted, ref, watch } from 'vue';

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    availableJobs: {
        type: Array,
        default: () => [],
    },
});

const fallbackJobs = Object.freeze([
    {
        id: 'fallback-job',
        name: 'COMING SOON',
        slug: 'fallback',
        emblem_path: null,
        description: 'Jalur pembelajaran baru sedang dipersiapkan. Cek kembali beberapa saat lagi.',
    },
]);

const features = [
    {
        title: 'Quest System',
        description: 'Kerjakan quest personal atau party, submit hasil, dan pantau progres.',
        icon: 'fi fi-rr-target',
        color: 'text-amber-600',
    },
    {
        title: 'Guide Library',
        description: 'Akses materi umum dan materi khusus group dalam satu tempat.',
        icon: 'fi fi-rr-book-alt',
        color: 'text-indigo-600',
    },
    {
        title: 'Study Party',
        description: 'Belajar bareng dalam party dengan akses terkurasi dan kolaborasi terarah.',
        icon: 'fi fi-rr-users',
        color: 'text-emerald-600',
    },
    {
        title: 'Pro Mentoring Path',
        description: 'User terverifikasi Pro dapat mentoring, membimbing, dan membuka jalur belajar lanjutan.',
        icon: 'fi fi-rr-chart-histogram',
        color: 'text-cyan-600',
    },
];

const normalizedPropJobs = computed(() => {
    if (!Array.isArray(props?.availableJobs)) return [];

    return props.availableJobs
        .filter((job) => job && typeof job === 'object')
        .map((job, index) => ({
            id: job.id ?? `job-${index}`,
            name: String(job.name ?? 'Unknown Job'),
            slug: String(job.slug ?? ''),
            emblem_path: job.emblem_path ?? null,
            description: job.description ?? null,
        }));
});
const hasPropJobsSource = computed(() => Array.isArray(props?.availableJobs));

const loadedJobs = ref([]);
const displayJobs = computed(() => (loadedJobs.value.length > 0 ? loadedJobs.value : fallbackJobs));
const totalAvailableJobs = computed(() => loadedJobs.value.length);

const jobsCarousel = ref(null);
const jobCardRefs = ref([]);
const sidePadding = ref(0);
const scrollRaf = ref(null);
const isMouseDragging = ref(false);
const dragStartX = ref(0);
const dragStartScrollLeft = ref(0);
const activeJobIndex = ref(0);
let domReadyHandler = null;

const getInitialCenterIndex = (length = displayJobs.value.length) => Math.floor(length / 2);

const getJobDescription = (job) => {
    const slug = String(job?.slug || '').toLowerCase();
    const name = String(job?.name || '').toLowerCase();

    if (slug.includes('frontend') || name.includes('frontend')) {
        return 'Membangun tampilan website menggunakan HTML, CSS, dan JavaScript.';
    }
    if (slug.includes('backend') || name.includes('backend')) {
        return 'Mengembangkan server, API, dan logika aplikasi.';
    }
    if ((slug.includes('data') && slug.includes('analyst')) || (name.includes('data') && name.includes('analyst'))) {
        return 'Menganalisis data untuk menemukan insight dan pola.';
    }
    if (slug.includes('devops') || name.includes('devops')) {
        return 'Mengelola deployment, server, dan infrastruktur aplikasi.';
    }
    if ((slug.includes('data') && slug.includes('engineer')) || (name.includes('data') && name.includes('engineer')) || slug.includes('da-engineer') || name.includes('da engineer')) {
        return 'Membangun pipeline data dan sistem pengolahan data.';
    }

    return 'Jalur pembelajaran terstruktur untuk membangun skill profesional secara bertahap.';
};

const extractJobsFromResponse = (response) => {
    const jobsCandidate = response?.payload?.jobs
        || response?.payload
        || response?.data?.payload?.jobs
        || response?.data?.payload
        || response?.jobs
        || [];

    return Array.isArray(jobsCandidate) ? jobsCandidate : [];
};

const normalizeJobs = (jobs) => {
    if (!Array.isArray(jobs)) return [];

    return jobs
        .filter((job) => job && typeof job === 'object')
        .map((job, index) => ({
            id: job.id ?? `job-${index}`,
            name: String(job.name ?? 'Unknown Job'),
            slug: String(job.slug ?? ''),
            emblem_path: job.emblem_path ?? null,
            description: job.description ?? null,
        }));
};

const loadJobs = async () => {
    try {
        if (hasPropJobsSource.value) {
            const mockApiResponse = { payload: { jobs: normalizedPropJobs.value } };
            console.log('API response:', mockApiResponse);
            loadedJobs.value = normalizeJobs(mockApiResponse?.payload?.jobs || []);
            return;
        }

        const response = await fetch('/api/jobs', {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`Jobs API failed with status ${response.status}`);
        }

        const data = await response.json();
        console.log('API response:', data);
        loadedJobs.value = normalizeJobs(extractJobsFromResponse(data));
    } catch (error) {
        console.error('Jobs load error:', error);
        loadedJobs.value = [];
    }
};

const setJobCardRef = (el, index) => {
    if (!el) return;
    jobCardRefs.value[index] = el;
};

const getNearestCardIndex = () => {
    const carousel = jobsCarousel.value;
    if (!carousel || !jobCardRefs.value.length) return 0;

    const centerX = carousel.scrollLeft + (carousel.clientWidth / 2);
    let nearestIndex = 0;
    let minDistance = Number.POSITIVE_INFINITY;

    jobCardRefs.value.forEach((card, index) => {
        if (!card) return;
        const cardCenter = card.offsetLeft + (card.offsetWidth / 2);
        const distance = Math.abs(cardCenter - centerX);
        if (distance < minDistance) {
            minDistance = distance;
            nearestIndex = index;
        }
    });

    return nearestIndex;
};

const updateActiveCardByScroll = () => {
    activeJobIndex.value = getNearestCardIndex();
};

const handleCarouselScroll = () => {
    if (scrollRaf.value) return;
    scrollRaf.value = window.requestAnimationFrame(() => {
        updateActiveCardByScroll();
        scrollRaf.value = null;
    });
};

const scrollToCard = (index, behavior = 'smooth') => {
    const carousel = jobsCarousel.value;
    const card = jobCardRefs.value[index];
    if (!carousel || !card) return;

    const targetLeft = card.offsetLeft - ((carousel.clientWidth - card.offsetWidth) / 2);
    carousel.scrollTo({
        left: targetLeft,
        behavior,
    });
};

const syncCarouselMetrics = () => {
    const carousel = jobsCarousel.value;
    const firstCard = jobCardRefs.value[0];
    if (!carousel || !firstCard) return;

    sidePadding.value = Math.max((carousel.clientWidth - firstCard.offsetWidth) / 2, 0);
    updateActiveCardByScroll();
};

const handleResize = () => {
    syncCarouselMetrics();
    scrollToCard(activeJobIndex.value, 'auto');
};

const onCarouselPointerDown = (event) => {
    if (event.pointerType !== 'mouse' || !jobsCarousel.value) return;

    isMouseDragging.value = true;
    dragStartX.value = event.clientX;
    dragStartScrollLeft.value = jobsCarousel.value.scrollLeft;
    jobsCarousel.value.classList.add('is-dragging');
    jobsCarousel.value.setPointerCapture(event.pointerId);
};

const onCarouselPointerMove = (event) => {
    if (!isMouseDragging.value || !jobsCarousel.value) return;

    const deltaX = event.clientX - dragStartX.value;
    jobsCarousel.value.scrollLeft = dragStartScrollLeft.value - deltaX;
};

const onCarouselPointerUp = (event) => {
    if (!jobsCarousel.value || !isMouseDragging.value) return;

    isMouseDragging.value = false;
    jobsCarousel.value.classList.remove('is-dragging');
    jobsCarousel.value.releasePointerCapture(event.pointerId);

    const targetIndex = getNearestCardIndex();
    activeJobIndex.value = targetIndex;
    scrollToCard(targetIndex, 'smooth');
};

const getCardStateClass = (index) => {
    return index === activeJobIndex.value ? 'job-card--focus' : 'job-card--side';
};

const initCarousel = async () => {
    await nextTick();
    syncCarouselMetrics();
    const middleIndex = getInitialCenterIndex(displayJobs.value.length);
    activeJobIndex.value = middleIndex;
    scrollToCard(middleIndex, 'auto');
};

onMounted(() => {
    const boot = async () => {
        await loadJobs();
        await initCarousel();
        window.addEventListener('resize', handleResize);
    };

    if (document.readyState === 'loading') {
        domReadyHandler = () => {
            boot();
            domReadyHandler = null;
        };
        document.addEventListener('DOMContentLoaded', domReadyHandler, { once: true });
        return;
    }

    boot();
});

onBeforeUpdate(() => {
    jobCardRefs.value = [];
});

watch(
    () => normalizedPropJobs.value,
    async (jobs) => {
        if (jobs.length > 0) {
            loadedJobs.value = normalizeJobs(jobs);
            await initCarousel();
        }
    },
    { deep: true },
);

watch(
    () => displayJobs.value.length,
    async () => {
        await initCarousel();
    },
);

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize);
    if (domReadyHandler) {
        document.removeEventListener('DOMContentLoaded', domReadyHandler);
        domReadyHandler = null;
    }
    if (scrollRaf.value) {
        window.cancelAnimationFrame(scrollRaf.value);
        scrollRaf.value = null;
    }
});
</script>

<template>
    <Head title="DOOPTECH" />

    <div
        class="min-h-screen bg-[#f8fafc] bg-cover bg-center bg-fixed text-slate-900 relative overflow-x-hidden font-['Press_Start_2P']"
        style="background-image: linear-gradient(rgba(248,250,252,0.58), rgba(238,246,255,0.62)), url('/images/bg-loby2.png');"
    >
        <div class="relative z-10">
            <nav class="bg-[#1a1c2c]/80 backdrop-blur-md border-b-2 border-white/10 p-4 md:px-8 flex justify-between items-center shadow-2xl sticky top-0 z-50">
                <Link :href="route('lobby')" class="flex items-center gap-4 group">
                    <div class="w-10 h-10 bg-[#0a0c10] flex items-center justify-center border-b-4 border-r-4 border-[#4ed4d4] overflow-hidden group-hover:scale-110 transition-transform">
                        <img src="/images/logo.png" alt="DOOPTECH Logo" class="w-7 h-7 object-contain pixelated" />
                    </div>
                    <span class="text-[#009999] text-[8px] md:text-sm tracking-tighter uppercase group-hover:text-[#4ed4d4]">DOOPTECH</span>
                </Link>

                <div class="flex items-center gap-2 md:gap-3">
                    <Link
                        v-if="canLogin"
                        :href="route('login')"
                        class="text-[8px] bg-[#009999] text-black px-4 py-2 btn-pixel border-[#006666] uppercase font-bold hover:bg-[#4ed4d4] transition-all"
                    >
                        Login
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="text-[8px] bg-[#facc15] text-black px-4 py-2 btn-pixel border-[#854d0e] uppercase font-bold hover:bg-yellow-400 transition-all"
                    >
                        Register
                    </Link>
                </div>
            </nav>

            <section class="px-4 md:px-10 pt-12 md:pt-16 pb-10">
                <div class="max-w-5xl mx-auto text-center">
                    <p class="text-[9px] md:text-[10px] uppercase tracking-widest text-cyan-700 mb-5">
                        <span class="text-cyan-700">Learning Hub</span>
                        <span class="text-sky-600"> for</span>
                        <span class="text-indigo-600"> Quest-Based</span>
                        <span class="text-emerald-600"> Study</span>
                    </p>
                    <h1 class="text-xl md:text-4xl leading-tight uppercase text-slate-900 mb-5">
                        From <span class="text-indigo-600">Beginner</span> to <span class="text-cyan-700">Pro Player</span> with <span class="text-emerald-600">Quest-Based</span> Learning
                    </h1>
                    <p class="text-[10px] md:text-xs text-slate-700 leading-relaxed max-w-3xl mx-auto font-sans">
                        Ini adalah aplikasi pembelajaran berbasis game yang menghubungkan pemula dan profesional dalam satu ekosistem belajar.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="text-[9px] md:text-[10px] px-5 py-3 bg-emerald-500 text-black border border-emerald-200 border-b-4 border-r-4 border-r-emerald-800 border-b-emerald-800 uppercase hover:bg-emerald-300 transition-colors"
                        >
                            Mulai Belajar
                        </Link>
                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="text-[9px] md:text-[10px] px-5 py-3 bg-slate-800 text-white border border-slate-300 border-b-4 border-r-4 border-r-slate-900 border-b-slate-900 uppercase hover:bg-slate-700 transition-colors"
                        >
                            Masuk Dashboard
                        </Link>
                    </div>

                    <div class="mt-6 flex items-center justify-center gap-2 md:gap-3">
                        <span class="w-2 h-2 rounded-full bg-cyan-500 animate-pulse"></span>
                        <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse [animation-delay:150ms]"></span>
                        <span class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse [animation-delay:300ms]"></span>
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse [animation-delay:450ms]"></span>
                    </div>
                </div>
            </section>

            <section class="px-4 md:px-10 pb-10 md:pb-14">
                <div class="max-w-6xl mx-auto grid grid-cols-1 sm:grid-cols-2 gap-4 md:gap-6">
                    <article
                        v-for="(feature, index) in features"
                        :key="feature.title"
                        class="group relative overflow-hidden border p-5 md:p-6 transition-all duration-200 cursor-default
                               border-white/70 border-r-4 border-b-4 border-r-slate-900 border-b-slate-900
                               hover:-translate-y-1
                               active:translate-y-0.5 active:border-r-2 active:border-b-2"
                        :class="{
                            'bg-gradient-to-br from-sky-800 to-cyan-700 shadow-[0_8px_18px_rgba(14,116,144,0.35)]': index === 0,
                            'bg-gradient-to-br from-indigo-800 to-violet-700 shadow-[0_8px_18px_rgba(67,56,202,0.35)]': index === 1,
                            'bg-gradient-to-br from-emerald-800 to-teal-700 shadow-[0_8px_18px_rgba(6,95,70,0.35)]': index === 2,
                            'bg-gradient-to-br from-cyan-800 to-blue-700 shadow-[0_8px_18px_rgba(30,64,175,0.35)]': index === 3,
                        }"
                    >
                        <div
                            class="absolute -top-12 -right-12 w-32 h-32 rounded-full blur-2xl opacity-35"
                            :class="{
                                'bg-sky-300': index === 0,
                                'bg-indigo-300': index === 1,
                                'bg-emerald-300': index === 2,
                                'bg-cyan-300': index === 3,
                            }"
                        ></div>
                        <div
                            class="absolute inset-x-0 top-0 h-[2px]"
                            :class="{
                                'bg-sky-400/80': index === 0,
                                'bg-indigo-400/80': index === 1,
                                'bg-emerald-400/80': index === 2,
                                'bg-cyan-400/80': index === 3,
                            }"
                        ></div>
                        <span class="absolute top-2 right-2 text-[8px] px-1.5 py-0.5 border border-white/50 bg-black/20 text-white/90">
                            0{{ index + 1 }}
                        </span>

                        <div class="relative z-10 flex items-center gap-3 mb-4">
                            <div
                                class="w-10 h-10 border border-white/70 flex items-center justify-center bg-black/20
                                       group-hover:scale-110 group-hover:rotate-3 transition-transform"
                            >
                                <i :class="[feature.icon, 'text-lg text-white']"></i>
                            </div>
                            <span
                                class="text-[8px] px-2 py-1 uppercase border border-white/50 bg-black/15 text-white/90"
                                :class="{
                                    'shadow-[0_0_0_1px_rgba(56,189,248,0.45)]': index === 0,
                                    'shadow-[0_0_0_1px_rgba(99,102,241,0.45)]': index === 1,
                                    'shadow-[0_0_0_1px_rgba(16,185,129,0.45)]': index === 2,
                                    'shadow-[0_0_0_1px_rgba(6,182,212,0.45)]': index === 3,
                                }"
                            >
                                FEATURE
                            </span>
                            <h2
                                class="text-[10px] md:text-xs uppercase tracking-wider font-black [text-shadow:1px_1px_0_rgba(2,6,23,0.65)]"
                            >
                                <template v-if="index === 0">
                                    <span class="text-cyan-100">Quest</span>
                                    <span class="text-sky-100"> System</span>
                                </template>
                                <template v-else-if="index === 1">
                                    <span class="text-indigo-100">Guide</span>
                                    <span class="text-violet-100"> Library</span>
                                </template>
                                <template v-else-if="index === 2">
                                    <span class="text-emerald-100">Study</span>
                                    <span class="text-teal-100"> Party</span>
                                </template>
                                <template v-else>
                                    <span class="text-cyan-100">Pro</span>
                                    <span class="text-blue-100"> Mentoring</span>
                                    <span class="text-sky-100"> Path</span>
                                </template>
                            </h2>
                        </div>
                        <p
                            class="relative z-10 text-[8px] md:text-[9px] leading-[1.9] uppercase tracking-wide"
                            :class="{
                                'text-cyan-50/95': index === 0,
                                'text-indigo-50/95': index === 1,
                                'text-emerald-50/95': index === 2,
                                'text-sky-50/95': index === 3,
                            }"
                        >
                            {{ feature.description }}
                        </p>
                        <div class="relative z-10 mt-4 flex items-center justify-between">
                            <span class="text-[8px] uppercase tracking-wider text-white/85">Ready_Path</span>
                            <span class="text-[8px] uppercase tracking-wider px-2 py-1 bg-black/25 border border-white/45 text-white">Explore</span>
                        </div>
                    </article>
                </div>
            </section>

            <section class="px-4 md:px-10 pb-10 md:pb-14">
                <div class="max-w-6xl mx-auto">
                    <div class="flex items-center justify-between gap-3 mb-5">
                        <h2 class="text-[10px] md:text-xs uppercase text-slate-900">Available Jobs</h2>
                        <span class="text-[8px] md:text-[9px] font-sans text-slate-700">{{ totalAvailableJobs }} jalur tersedia</span>
                    </div>

                    <div class="jobs-stage">
                        <div
                            ref="jobsCarousel"
                            class="jobs-carousel"
                            @scroll="handleCarouselScroll"
                            @pointerdown="onCarouselPointerDown"
                            @pointermove="onCarouselPointerMove"
                            @pointerup="onCarouselPointerUp"
                            @pointercancel="onCarouselPointerUp"
                            @pointerleave="onCarouselPointerUp"
                        >
                            <div class="jobs-carousel-spacer" :style="{ width: `${sidePadding}px` }" aria-hidden="true"></div>

                            <article
                                v-for="(job, index) in displayJobs"
                                :key="job.id || `job-card-${index}`"
                                :ref="(el) => setJobCardRef(el, index)"
                                class="job-card relative overflow-hidden snap-center shrink-0 w-[220px] md:w-[260px] h-[340px] md:h-[360px] border p-4"
                                :class="[
                                    getCardStateClass(index),
                                    {
                                        'bg-gradient-to-br from-sky-800 to-cyan-700 border-cyan-300/70': index % 4 === 0,
                                        'bg-gradient-to-br from-indigo-800 to-violet-700 border-indigo-300/70': index % 4 === 1,
                                        'bg-gradient-to-br from-emerald-800 to-teal-700 border-emerald-300/70': index % 4 === 2,
                                        'bg-gradient-to-br from-cyan-800 to-blue-700 border-sky-300/70': index % 4 === 3,
                                    },
                                ]"
                            >
                                <div
                                    class="absolute top-0 left-0 w-full h-[3px]"
                                    :class="{
                                        'bg-gradient-to-r from-cyan-500 to-sky-400': index % 4 === 0,
                                        'bg-gradient-to-r from-indigo-500 to-violet-400': index % 4 === 1,
                                        'bg-gradient-to-r from-emerald-500 to-teal-400': index % 4 === 2,
                                        'bg-gradient-to-r from-cyan-400 to-blue-400': index % 4 === 3,
                                    }"
                                ></div>

                                <div class="h-full flex flex-col relative z-10">
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-[8px] uppercase text-white/85">Class Card</p>
                                        <span class="w-2 h-2 rounded-full bg-white/80 animate-pulse"></span>
                                    </div>

                                    <div class="h-[170px] border border-white/60 bg-black/15 overflow-hidden flex items-center justify-center">
                                        <img
                                            v-if="job.emblem_path"
                                            :src="`/storage/${job.emblem_path}`"
                                            :alt="`${job.name || 'Job'} emblem`"
                                            class="w-full h-full object-cover"
                                        />
                                        <img
                                            v-else
                                            src="/images/logo.png"
                                            :alt="`${job.name || 'Job'} default`"
                                            class="w-16 h-16 object-contain opacity-90"
                                        />
                                    </div>

                                    <div class="mt-3 border border-white/60 bg-black/20 px-2 py-2 h-[92px]">
                                        <p class="text-[10px] uppercase text-white leading-snug">
                                            {{ job.name || 'UNKNOWN JOB' }}
                                        </p>
                                        <p class="text-[10px] font-sans text-white/85 mt-1 leading-[1.35] normal-case">
                                            {{ job.description || getJobDescription(job) }}
                                        </p>
                                    </div>
                                </div>
                            </article>

                            <div class="jobs-carousel-spacer" :style="{ width: `${sidePadding}px` }" aria-hidden="true"></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="px-4 md:px-10 pb-16">
                <div class="max-w-6xl mx-auto bg-slate-900/90 border-2 border-cyan-300/40 shadow-[0_10px_24px_rgba(14,116,144,0.2)] p-5 md:p-8">
                    <div class="relative">
                        <div class="hidden md:block pointer-events-none absolute left-[14%] right-[14%] top-1/2 -translate-y-1/2 z-0">
                            <div class="relative h-6">
                                <div class="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-[2px] bg-gradient-to-r from-sky-400 via-indigo-400 to-emerald-400 opacity-80"></div>
                                <span class="path-star path-star-1 absolute top-1/2 -translate-y-1/2 text-indigo-200 text-sm">*</span>
                                <span class="path-star path-star-2 absolute top-1/2 -translate-y-1/2 text-cyan-200 text-xs">*</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 md:gap-6 relative z-10">
                        <div class="relative overflow-hidden border p-4 bg-gradient-to-br from-sky-800 to-cyan-700 border-cyan-300/70 shadow-[0_6px_14px_rgba(14,165,233,0.28)]">
                            <div class="absolute top-0 left-0 w-full h-[3px] bg-gradient-to-r from-cyan-500 to-sky-400"></div>
                            <p class="text-[9px] uppercase text-cyan-100 mb-2">Step 01</p>
                            <h3 class="text-[10px] md:text-xs text-white uppercase mb-2">Join Platform</h3>
                            <div class="w-2 h-2 rounded-full bg-cyan-200 mb-2 animate-pulse"></div>
                            <p class="text-[11px] md:text-[13px] font-sans text-cyan-50/95">Daftar sebagai learner, masuk dashboard, lalu pilih jalur belajar yang sesuai.</p>
                        </div>
                        <div class="relative overflow-hidden border p-4 bg-gradient-to-br from-indigo-800 to-violet-700 border-indigo-300/70 shadow-[0_6px_14px_rgba(79,70,229,0.28)]">
                            <div class="absolute top-0 left-0 w-full h-[3px] bg-gradient-to-r from-indigo-500 to-violet-400"></div>
                            <p class="text-[9px] uppercase text-indigo-100 mb-2">Step 02</p>
                            <h3 class="text-[10px] md:text-xs text-white uppercase mb-2">Complete Quest</h3>
                            <div class="w-2 h-2 rounded-full bg-indigo-200 mb-2 animate-pulse [animation-delay:200ms]"></div>
                            <p class="text-[11px] md:text-[13px] font-sans text-indigo-50/95">Kerjakan quest publik atau party, kirim submission, lalu naik level dari hasil review.</p>
                        </div>
                        <div class="relative overflow-hidden border p-4 bg-gradient-to-br from-emerald-800 to-teal-700 border-emerald-300/70 shadow-[0_6px_14px_rgba(16,185,129,0.28)]">
                            <div class="absolute top-0 left-0 w-full h-[3px] bg-gradient-to-r from-emerald-500 to-teal-400"></div>
                            <p class="text-[9px] uppercase text-emerald-100 mb-2">Step 03</p>
                            <h3 class="text-[10px] md:text-xs text-white uppercase mb-2">Become Pro & Mentor</h3>
                            <div class="w-2 h-2 rounded-full bg-emerald-200 mb-2 animate-pulse [animation-delay:400ms]"></div>
                            <p class="text-[11px] md:text-[13px] font-sans text-emerald-50/95">Saat sudah Pro terverifikasi, user bisa mentoring dan menginisiasi project kolaboratif.</p>
                        </div>
                        </div>
                    </div>
                </div>
            </section>

            <footer class="p-8 text-center bg-[#1a1c2c]/50 backdrop-blur-md border-t-2 border-white/10 mt-auto">
                <p class="text-[8px] text-white/50 uppercase tracking-[0.3em]">Build_Ver_1.1.0 // P-Quest Engine</p>
            </footer>
        </div>
    </div>
</template>

<style scoped>
.jobs-stage {
    position: relative;
}

.jobs-stage::before,
.jobs-stage::after {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    width: 56px;
    pointer-events: none;
    z-index: 20;
}

.jobs-stage::before {
    left: 0;
    background: linear-gradient(90deg, rgba(248, 250, 252, 0.9), rgba(248, 250, 252, 0));
}

.jobs-stage::after {
    right: 0;
    background: linear-gradient(270deg, rgba(248, 250, 252, 0.9), rgba(248, 250, 252, 0));
}

.jobs-carousel {
    display: flex;
    align-items: center;
    gap: 1rem;
    overflow-x: auto;
    overflow-y: visible;
    scroll-snap-type: x mandatory;
    scroll-behavior: smooth;
    touch-action: pan-x;
    -webkit-overflow-scrolling: touch;
    cursor: grab;
    padding-block: 1.25rem;
    scrollbar-width: none;
}

.jobs-carousel::-webkit-scrollbar {
    display: none;
}

.jobs-carousel.is-dragging {
    cursor: grabbing;
    scroll-snap-type: none;
}

.jobs-carousel-spacer {
    flex: 0 0 auto;
    height: 1px;
}

.job-card {
    transform-origin: center center;
    transition: transform 320ms ease-in-out, opacity 320ms ease-in-out, box-shadow 320ms ease-in-out, filter 320ms ease-in-out;
    will-change: transform, opacity;
}

.job-card--focus {
    transform: scale(1.1);
    opacity: 1;
    z-index: 30;
    box-shadow: 0 18px 34px rgba(15, 23, 42, 0.32);
}

.job-card--side {
    transform: scale(0.9);
    opacity: 0.68;
    z-index: 12;
    filter: saturate(0.92);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.22);
}

@media (max-width: 640px) {
    .jobs-stage::before,
    .jobs-stage::after {
        width: 28px;
    }

    .job-card--focus {
        transform: scale(1.06);
    }

    .job-card--side {
        transform: scale(0.92);
        opacity: 0.74;
    }
}

.path-star {
    left: 0;
    opacity: 0;
    animation: path-star-move 3.6s linear infinite;
}

.path-star-2 {
    animation-delay: 1.8s;
}

@keyframes path-star-move {
    0% {
        left: 0;
        opacity: 0;
    }
    10% {
        opacity: 1;
    }
    90% {
        opacity: 1;
    }
    100% {
        left: calc(100% - 12px);
        opacity: 0;
    }
}
</style>
