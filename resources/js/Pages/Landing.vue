<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onBeforeUpdate, onMounted, ref, watch } from 'vue';
import AppBackgroundLayer from '@/Components/AppBackgroundLayer.vue';

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    availableJobs: {
        type: Array,
        default: () => [],
    },
    mentors: {
        type: Array,
        default: () => [],
    },
    featuredCreations: {
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
        status: 'coming_soon',
        mentors_count: 0,
    },
]);

const fallbackMentors = Object.freeze([
    {
        id: 'mentor-fallback-1',
        name: 'Mentor Pixel',
        username: 'pixelmentor',
        job_name: 'Fullstack',
        profile_photo: null,
        bio: 'Mentor fullstack dengan fokus UI modern dan arsitektur sistem.',
        experience: '5+ tahun',
        location: 'Remote',
        skills: ['Vue.js', 'Laravel', 'System Design'],
    },
    {
        id: 'mentor-fallback-2',
        name: 'Mentor Sprite',
        username: 'spritesage',
        job_name: 'Data Analyst',
        profile_photo: null,
        bio: 'Spesialis analisis data dan storytelling insight untuk bisnis.',
        experience: '4+ tahun',
        location: 'Jakarta',
        skills: ['SQL', 'Python', 'Data Viz'],
    },
    {
        id: 'mentor-fallback-3',
        name: 'Mentor Neon',
        username: 'neoncoach',
        job_name: 'DevOps',
        profile_photo: null,
        bio: 'DevOps dengan fokus CI/CD, observability, dan reliability.',
        experience: '6+ tahun',
        location: 'Bandung',
        skills: ['Docker', 'Kubernetes', 'AWS'],
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
            description: job.description ? String(job.description) : null,
            status: String(job.status ?? 'active'),
            mentors_count: Number(job.mentors_count ?? 0),
        }));
});

const normalizedPropMentors = computed(() => {
    if (!Array.isArray(props?.mentors)) return [];
    return props.mentors
        .filter((mentor) => mentor && typeof mentor === 'object')
        .map((mentor, index) => ({
            id: mentor.id ?? `mentor-${index}`,
            name: String(mentor.name ?? 'Mentor'),
            username: mentor.username ? String(mentor.username) : null,
            job_name: mentor.job_name ? String(mentor.job_name) : 'Generalist',
            profile_photo: mentor.profile_photo ?? null,
            bio: mentor.bio ? String(mentor.bio) : null,
            experience: mentor.experience ? String(mentor.experience) : null,
            location: mentor.location ? String(mentor.location) : null,
            skills: Array.isArray(mentor.skills)
                ? mentor.skills.map((skill) => String(skill))
                : (mentor.skills ? [String(mentor.skills)] : []),
        }));
});

const displayJobs = computed(() => (normalizedPropJobs.value.length > 0 ? normalizedPropJobs.value : fallbackJobs));
const totalAvailableJobs = computed(() => normalizedPropJobs.value.length);

const loadedMentors = computed(() => (normalizedPropMentors.value.length > 0 ? normalizedPropMentors.value : fallbackMentors));
const featuredCreations = computed(() => (
    Array.isArray(props?.featuredCreations)
        ? props.featuredCreations.filter((creation) => creation && typeof creation === 'object').slice(0, 5)
        : []
));
const expandedMentorId = ref(null);
const isPageLoaded = ref(false);
let pageLoadHandler = null;

const hallEntryHref = computed(() => route('hall.creations.index'));

const hallEntryLabel = computed(() => (
    'Open Public Hall'
));

const hallSecondaryHref = computed(() => route('register'));

const hallSecondaryLabel = computed(() => (
    'Create Account'
));

const showHallSecondaryCta = computed(() => Boolean(props.canRegister));

const hallStats = computed(() => {
    return featuredCreations.value.reduce((accumulator, creation) => {
        accumulator.appreciations += Number(creation?.appreciations_count || 0);
        accumulator.insights += Number(creation?.insights_count || 0);

        if (String(creation?.status || '') !== 'finished') {
            accumulator.activeProjects += 1;
        }

        return accumulator;
    }, {
        appreciations: 0,
        insights: 0,
        activeProjects: 0,
    });
});

const getHallRankTitle = (index) => {
    if (index === 0) return 'Legendary Pick';
    if (index === 1) return 'Hot Pick';
    if (index === 2) return 'Rising Pick';
    if (index === 3) return 'Community Pick';
    return 'Fresh Pick';
};

const seoTitle = 'DOOPTECH | Platform Pembelajaran Quest-Based';
const seoDescription = 'DOOPTECH adalah aplikasi pembelajaran berbasis game yang menghubungkan pemula dan profesional dalam satu ekosistem belajar.';
const seoCanonicalUrl = computed(() => route('lobby'));
const seoImageUrl = computed(() => new URL('/images/bg-loby2.webp', seoCanonicalUrl.value).toString());
const seoSchemaJson = computed(() => JSON.stringify({
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: 'DOOPTECH',
    url: seoCanonicalUrl.value,
    description: seoDescription,
}, null, 2));

const getCreationStatusClass = (status) => {
    if (status === 'finished') return 'border-emerald-400/70 bg-emerald-500/10 text-emerald-100';
    if (status === 'refining') return 'border-amber-300/70 bg-amber-500/10 text-amber-100';
    return 'border-cyan-300/70 bg-cyan-500/10 text-cyan-100';
};

const getCreationTheme = (index) => {
    const mod = Number(index || 0) % 5;

    if (mod === 0) {
        return {
            card: 'from-sky-800 to-cyan-700 border-cyan-300/70 shadow-[0_8px_18px_rgba(14,116,144,0.28)]',
            glow: 'bg-sky-300',
            line: 'from-cyan-500 to-sky-400',
            meta: 'text-cyan-50/90',
            stat: 'border-cyan-200/40 bg-black/20 text-cyan-100',
        };
    }

    if (mod === 1) {
        return {
            card: 'from-indigo-800 to-violet-700 border-indigo-300/70 shadow-[0_8px_18px_rgba(67,56,202,0.28)]',
            glow: 'bg-indigo-300',
            line: 'from-indigo-500 to-violet-400',
            meta: 'text-indigo-50/90',
            stat: 'border-indigo-200/40 bg-black/20 text-indigo-100',
        };
    }

    if (mod === 2) {
        return {
            card: 'from-emerald-800 to-teal-700 border-emerald-300/70 shadow-[0_8px_18px_rgba(6,95,70,0.28)]',
            glow: 'bg-emerald-300',
            line: 'from-emerald-500 to-teal-400',
            meta: 'text-emerald-50/90',
            stat: 'border-emerald-200/40 bg-black/20 text-emerald-100',
        };
    }

    if (mod === 3) {
        return {
            card: 'from-cyan-800 to-blue-700 border-sky-300/70 shadow-[0_8px_18px_rgba(30,64,175,0.28)]',
            glow: 'bg-cyan-300',
            line: 'from-cyan-400 to-blue-400',
            meta: 'text-sky-50/90',
            stat: 'border-sky-200/40 bg-black/20 text-sky-100',
        };
    }

    return {
        card: 'from-amber-700 to-orange-700 border-amber-200/70 shadow-[0_8px_18px_rgba(180,83,9,0.28)]',
        glow: 'bg-amber-200',
        line: 'from-amber-400 to-orange-400',
        meta: 'text-amber-50/90',
        stat: 'border-amber-200/40 bg-black/20 text-amber-100',
    };
};

const toggleMentorCard = (id) => {
    expandedMentorId.value = expandedMentorId.value === id ? null : id;
};

const getMentorThemeClass = (index) => {
    const mod = Number(index || 0) % 4;
    if (mod === 0) return 'mentor-card--sky';
    if (mod === 1) return 'mentor-card--indigo';
    if (mod === 2) return 'mentor-card--emerald';
    return 'mentor-card--cyan';
};

const jobsCarousel = ref(null);
const jobCardRefs = ref([]);
const sidePadding = ref(0);
const scrollRaf = ref(null);
const isMouseDragging = ref(false);
const dragStartX = ref(0);
const dragStartScrollLeft = ref(0);
const activeJobIndex = ref(0);
const resizeRaf = ref(null);

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
    if (!carousel || jobCardRefs.value.length === 0) return;

    const firstCard = jobCardRefs.value[0];
    if (!firstCard) return;

    sidePadding.value = Math.max((carousel.clientWidth - firstCard.offsetWidth) / 2, 0);
};

const handleResize = () => {
    if (resizeRaf.value) return;
    resizeRaf.value = window.requestAnimationFrame(() => {
        syncCarouselMetrics();
        scrollToCard(activeJobIndex.value, 'auto');
        window.requestAnimationFrame(() => updateActiveCardByScroll());
        resizeRaf.value = null;
    });
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

const isComingSoonJob = (job) => String(job?.status || 'active') === 'coming_soon';

const initCarousel = async () => {
    await nextTick();
    await new Promise((resolve) => window.requestAnimationFrame(resolve));

    if (!jobCardRefs.value.length || !jobsCarousel.value) return;

    syncCarouselMetrics();
    await nextTick();
    const centerIndex = Math.floor(jobCardRefs.value.length / 2);
    scrollToCard(centerIndex, 'auto');
    await new Promise((resolve) => window.requestAnimationFrame(resolve));
    activeJobIndex.value = getNearestCardIndex();
};

onMounted(async () => {
    await initCarousel();
    window.addEventListener('resize', handleResize);
    if (document.readyState === 'complete') {
        isPageLoaded.value = true;
        return;
    }
    pageLoadHandler = () => {
        isPageLoaded.value = true;
        pageLoadHandler = null;
    };
    window.addEventListener('load', pageLoadHandler, { once: true });
});

onBeforeUpdate(() => {
    jobCardRefs.value = [];
});

watch(
    () => normalizedPropJobs.value,
    async (jobs) => {
        if (jobs.length > 0) {
            await initCarousel();
        }
    },
    { deep: false },
);

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize);
    if (pageLoadHandler) {
        window.removeEventListener('load', pageLoadHandler);
        pageLoadHandler = null;
    }
    if (scrollRaf.value) {
        window.cancelAnimationFrame(scrollRaf.value);
        scrollRaf.value = null;
    }
    if (resizeRaf.value) {
        window.cancelAnimationFrame(resizeRaf.value);
        resizeRaf.value = null;
    }
});
</script>

<template>
    <Head :title="seoTitle">
        <meta head-key="description" name="description" :content="seoDescription" />
        <meta head-key="robots" name="robots" content="index,follow" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:site_name" property="og:site_name" content="DOOPTECH" />
        <meta head-key="og:title" property="og:title" :content="seoTitle" />
        <meta head-key="og:description" property="og:description" :content="seoDescription" />
        <meta head-key="og:url" property="og:url" :content="seoCanonicalUrl" />
        <meta head-key="og:image" property="og:image" :content="seoImageUrl" />
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="seoTitle" />
        <meta head-key="twitter:description" name="twitter:description" :content="seoDescription" />
        <meta head-key="twitter:image" name="twitter:image" :content="seoImageUrl" />
        <link head-key="canonical" rel="canonical" :href="seoCanonicalUrl" />
        <link rel="preload" as="image" href="/images/bg-loby2.webp" />
        <component :is="'script'" head-key="ld-json-website" type="application/ld+json" v-html="seoSchemaJson" />
    </Head>

    <div
        class="relative isolate min-h-screen overflow-x-hidden font-['Press_Start_2P'] text-slate-900"
    >
        <AppBackgroundLayer
            image="/images/bg-loby2.webp"
            overlay-class="bg-[linear-gradient(rgba(248,250,252,0.58),rgba(238,246,255,0.62))]"
            glow-class="bg-[radial-gradient(circle_at_18%_18%,rgba(255,255,255,0.28),transparent_32%),radial-gradient(circle_at_82%_12%,rgba(103,232,249,0.18),transparent_30%),linear-gradient(180deg,rgba(255,255,255,0.08),rgba(255,255,255,0.12))]"
        />
        <div v-if="!isPageLoaded" class="page-loader" role="status" aria-live="polite">
            <div class="page-loader__card">
                <div class="page-loader__grid">
                    <span class="page-loader__pixel"></span>
                    <span class="page-loader__pixel"></span>
                    <span class="page-loader__pixel"></span>
                    <span class="page-loader__pixel"></span>
                    <span class="page-loader__pixel"></span>
                    <span class="page-loader__pixel"></span>
                    <span class="page-loader__pixel"></span>
                    <span class="page-loader__pixel"></span>
                    <span class="page-loader__pixel"></span>
                </div>
                <p class="page-loader__label">Loading World...</p>
                <div class="page-loader__bar">
                    <span class="page-loader__bar-fill"></span>
                </div>
            </div>
        </div>
        <div class="relative z-10">
            <nav class="landing-nav border-b-2 border-white/10 p-4 md:px-8 flex justify-between items-center shadow-2xl sticky top-0 z-50">
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
                    <h1 class="mx-auto mb-5 flex max-w-6xl flex-col items-center gap-2 text-center text-lg uppercase leading-none tracking-[0.08em] text-slate-900 md:text-4xl md:tracking-[0.12em]">
                        <span class="flex flex-wrap items-center justify-center gap-x-3 gap-y-2">
                            <span>Like Playing</span>
                            <span class="text-indigo-600">A Game,</span>
                            <span>Learning</span>
                        </span>
                        <span class="flex flex-wrap items-center justify-center gap-x-3 gap-y-2">
                            <span>Should Be</span>
                            <span class="text-emerald-600">Enjoyable.</span>
                        </span>
                        <span class="flex flex-wrap items-center justify-center gap-x-3 gap-y-2 text-[0.9em] md:text-[0.95em]">
                            <span class="text-cyan-700">No Matter</span>
                            <span>If We</span>
                            <span class="text-indigo-600">Lose Or Win,</span>
                        </span>
                        <span class="flex flex-wrap items-center justify-center gap-x-3 gap-y-2 text-[0.9em] md:text-[0.95em]">
                            <span>We'll Always</span>
                            <span class="text-cyan-700">Return</span>
                        </span>
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
                            @scroll.passive="handleCarouselScroll"
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
                                ref="jobCardRefs"
                                class="job-card relative overflow-hidden snap-center shrink-0 w-[220px] md:w-[260px] h-[340px] md:h-[360px] border p-4"
                                :class="[
                                    getCardStateClass(index),
                                    {
                                        'bg-gradient-to-br from-sky-800 to-cyan-700 border-cyan-300/70': index % 4 === 0,
                                        'bg-gradient-to-br from-indigo-800 to-violet-700 border-indigo-300/70': index % 4 === 1,
                                        'bg-gradient-to-br from-emerald-800 to-teal-700 border-emerald-300/70': index % 4 === 2,
                                        'bg-gradient-to-br from-cyan-800 to-blue-700 border-sky-300/70': index % 4 === 3,
                                        'job-card--coming-soon': isComingSoonJob(job),
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

                                <div v-if="isComingSoonJob(job)" class="pointer-events-none absolute inset-0 z-30">
                                    <div class="coming-soon-chain coming-soon-chain--left"></div>
                                    <div class="coming-soon-chain coming-soon-chain--right"></div>
                                    <div class="absolute left-1/2 top-1/2 flex h-20 w-20 -translate-x-1/2 -translate-y-1/2 items-center justify-center border-4 border-slate-300 bg-black/70 text-slate-200 shadow-[0_0_22px_rgba(148,163,184,0.65)]">
                                        <i class="fi fi-rr-lock text-3xl leading-none"></i>
                                    </div>
                                    <div class="absolute left-1/2 top-5 -translate-x-1/2 border-2 border-slate-300 bg-black/80 px-3 py-2 text-[8px] uppercase tracking-[0.22em] text-slate-200 shadow-[4px_4px_0_rgba(0,0,0,0.45)]">
                                        Coming Soon
                                    </div>
                                </div>

                                <div class="h-full flex flex-col relative z-10">
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-[8px] uppercase text-white/85">Class Card</p>
                                        <span
                                            class="rounded-full border px-2 py-1 text-[6px] uppercase tracking-[0.16em]"
                                            :class="isComingSoonJob(job) ? 'border-slate-200 bg-slate-300/15 text-slate-100' : 'border-white/40 bg-white/10 text-white/85'"
                                        >
                                            {{ isComingSoonJob(job) ? 'Coming Soon' : 'Active' }}
                                        </span>
                                    </div>

                                    <div class="h-[170px] border border-white/60 bg-black/15 overflow-hidden flex items-center justify-center">
                                        <img
                                            v-if="job.emblem_path"
                                            :src="`/storage/${job.emblem_path}`"
                                            :alt="`${job.name || 'Job'} emblem`"
                                            loading="lazy"
                                            decoding="async"
                                            class="w-full h-full object-cover"
                                        />
                                        <img
                                            v-else
                                            src="/images/logo.png"
                                            :alt="`${job.name || 'Job'} default`"
                                            loading="lazy"
                                            decoding="async"
                                            class="w-16 h-16 object-contain opacity-90"
                                        />
                                    </div>

                                    <div class="mt-3 flex h-[104px] flex-col border border-white/60 bg-black/20 px-2 py-2">
                                        <p class="text-[10px] uppercase text-white leading-snug">
                                            {{ job.name || 'UNKNOWN JOB' }}
                                        </p>
                                        <p class="line-clamp-3 text-[10px] font-sans text-white/85 mt-1 leading-[1.35] normal-case">
                                            {{ job.description || getJobDescription(job) }}
                                        </p>
                                        <div class="mt-auto pt-2">
                                            <div class="border-t border-white/20 pt-2 text-[8px] font-sans uppercase tracking-[0.18em] text-white/65">
                                                Active Mentor: {{ job.mentors_count || 0 }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </article>

                            <div class="jobs-carousel-spacer" :style="{ width: `${sidePadding}px` }" aria-hidden="true"></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="px-4 md:px-10 pb-14">
                <div class="max-w-6xl mx-auto">
                    <div class="flex items-center justify-between gap-3 mb-5">
                        <h2 class="text-[10px] md:text-xs uppercase text-slate-900">Featured Mentors</h2>
                        <span class="text-[8px] md:text-[9px] font-sans text-slate-700">{{ loadedMentors.length }} mentor siap bantu</span>
                    </div>

                  <div class="flex flex-wrap justify-center gap-6">
    <article
        v-for="(mentor, idx) in loadedMentors"
        :key="mentor.id"
        class="mentor-card group relative overflow-hidden cursor-pointer"
        :class="[getMentorThemeClass(idx), { 'is-expanded': expandedMentorId === mentor.id }]"
        @click="toggleMentorCard(mentor.id)"
    >
        <div class="mentor-card__inner">
            <div class="mentor-card__front">
                <header class="mentor-card__header">
                    <span class="mentor-card__icon">
                        <i class="fi fi-sr-flame"></i>
                    </span>
                    <span class="mentor-card__title">{{ mentor.job_name || 'Generalist' }}</span>
                </header>

                <div class="mentor-card__body">
                    <img
                        v-if="mentor.profile_photo"
                        :src="`/storage/${mentor.profile_photo}`"
                        :alt="mentor.name"
                        class="mentor-card__photo"
                        loading="lazy"
                        decoding="async"
                    />
                    <div v-else class="mentor-card__photo mentor-card__photo--empty"></div>
                </div>

                <footer class="mentor-card__footer">
                    <span class="mentor-card__role">@{{ mentor.username || 'mentor' }}</span>
                    <div class="mentor-card__stars">
                        <span class="mentor-card__badge">Mentor</span>
                    </div>
                </footer>
            </div>
            <div class="mentor-card__overlay">
                <div class="mentor-card__overlay-content">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-white text-[12px] font-bold uppercase tracking-widest">{{ mentor.name }}</h3>
                        <button class="text-white/50 hover:text-white">
                            <i class="fi fi-rr-cross-small"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-3">
                        <div class="border-l-2 border-cyan-400 pl-3">
                            <p class="text-[8px] text-cyan-400 uppercase mb-1 font-bold">Bio</p>
                            <p class="mentor-bio text-[12px] text-white/90 leading-relaxed font-sans">
                                {{ mentor.bio || 'Mentor berpengalaman yang siap membimbing sesuai jalur belajar.' }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-3 text-[11px] text-white/85 font-sans">
                            <div>
                                <p class="text-[9px] uppercase text-white/50">Username</p>
                                <p>@{{ mentor.username || 'mentor' }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50">Spesialisasi</p>
                                <p>{{ mentor.job_name || 'Generalist' }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50">Pengalaman</p>
                                <p>{{ mentor.experience || '3+ tahun' }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50">Lokasi</p>
                                <p>{{ mentor.location || 'Remote' }}</p>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-white/10">
                            <p class="text-[9px] uppercase text-white/50 mb-2">Skills</p>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="(skill, sIndex) in (mentor.skills && mentor.skills.length ? mentor.skills : ['Mentoring', 'Code Review', 'Roadmap'])"
                                    :key="`${mentor.id}-skill-${sIndex}`"
                                    class="px-1 py-0.5 text-[7px] uppercase tracking-[0.08em] border border-white/20 text-white/80 bg-white/10"
                                >
                                    {{ skill }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </article>
</div>
                </div>
            </section>

            <section class="px-4 md:px-10 pb-12">
                <div class="mx-auto max-w-6xl hall-preview-shell">
                    <div class="hall-preview-panel">
                        <div class="hall-preview-panel__glow hall-preview-panel__glow--one"></div>
                        <div class="hall-preview-panel__glow hall-preview-panel__glow--two"></div>

                        <div class="relative z-10">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="max-w-2xl">
                                    <p class="text-[8px] md:text-[9px] uppercase tracking-[0.26em] text-cyan-200/90">Hall of Creations</p>
                                    <h2 class="mt-2 text-[11px] md:text-[13px] uppercase text-white">Showcase karya terbaik komunitas</h2>
                                    <p class="mt-3 text-[11px] md:text-[13px] font-sans leading-relaxed text-cyan-50/90">
                                        Lihat project yang lagi naik, pantau progres real, dan temukan inspirasi dari learner serta mentor aktif di DOOPTECH.
                                    </p>

                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <span class="hall-stat-chip">
                                            <i class="fi fi-rr-heart text-[10px]"></i>
                                            {{ hallStats.appreciations }} Appreciation
                                        </span>
                                        <span class="hall-stat-chip">
                                            <i class="fi fi-rr-comment-alt text-[10px]"></i>
                                            {{ hallStats.insights }} Insight
                                        </span>
                                        <span class="hall-stat-chip">
                                            <i class="fi fi-rr-target text-[10px]"></i>
                                            {{ hallStats.activeProjects }} Active Project
                                        </span>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <Link
                                        :href="hallEntryHref"
                                        class="hall-cta hall-cta--primary"
                                    >
                                        {{ hallEntryLabel }}
                                    </Link>
                                    <Link
                                        v-if="showHallSecondaryCta"
                                        :href="hallSecondaryHref"
                                        class="hall-cta hall-cta--secondary"
                                    >
                                        {{ hallSecondaryLabel }}
                                    </Link>
                                </div>
                            </div>

                            <div v-if="featuredCreations.length > 0" class="mt-6 hall-preview-grid">
                                <article
                                    v-for="(creation, index) in featuredCreations"
                                    :key="creation.id"
                                    class="group hall-showcase-card relative overflow-hidden border border-white/70 border-r-4 border-b-4 border-r-slate-900 border-b-slate-900 bg-gradient-to-br text-left"
                                    :class="getCreationTheme(index).card"
                                    :style="{ animationDelay: `${index * 90}ms` }"
                                >
                                    <div class="hall-showcase-card__shine"></div>
                                    <div
                                        class="absolute -right-10 -top-10 h-24 w-24 rounded-full blur-2xl opacity-35"
                                        :class="getCreationTheme(index).glow"
                                    ></div>
                                    <div
                                        class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r"
                                        :class="getCreationTheme(index).line"
                                    ></div>

                                    <div class="relative z-10 px-3 pt-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="border border-white/40 bg-black/15 px-2 py-1 text-[6px] uppercase text-white/90">
                                                {{ getHallRankTitle(index) }}
                                            </span>
                                            <span class="border border-white/40 bg-black/15 px-2 py-1 text-[6px] uppercase text-white/90">
                                                Rank 0{{ index + 1 }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="relative mt-3 aspect-[4/3] overflow-hidden border-y border-white/25 bg-black/15">
                                        <img
                                            v-if="creation.thumbnail_url"
                                            :src="creation.thumbnail_url"
                                            :alt="creation.title"
                                            class="h-full w-full object-cover object-center transition-transform duration-300 group-hover:scale-[1.05]"
                                            loading="lazy"
                                            decoding="async"
                                        />
                                        <div v-else class="flex h-full items-center justify-center">
                                            <i class="fi fi-rr-lightbulb-on text-[28px] text-cyan-200/80"></i>
                                        </div>

                                        <span
                                            class="absolute left-2 top-2 rounded border px-2 py-1 text-[6px] uppercase backdrop-blur-[1px]"
                                            :class="getCreationStatusClass(creation.status)"
                                        >
                                            {{ creation.status }}
                                        </span>

                                        <div class="absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-black/55 to-transparent"></div>
                                    </div>

                                    <div class="relative z-10 space-y-2 p-3 text-white">
                                        <div class="min-w-0">
                                            <h3 class="line-clamp-1 text-[9px] uppercase text-white [text-shadow:1px_1px_0_rgba(2,6,23,0.45)]">
                                                {{ creation.title }}
                                            </h3>
                                            <p class="mt-1 line-clamp-1 text-[7px] uppercase" :class="getCreationTheme(index).meta">
                                                {{ creation.creator?.username || creation.creator?.name || 'Adventurer' }}
                                            </p>
                                        </div>

                                        <div v-if="creation.status !== 'finished'" class="space-y-1">
                                            <div class="h-1.5 overflow-hidden border border-white/25 bg-slate-950/80">
                                                <div
                                                    class="h-full bg-gradient-to-r"
                                                    :class="getCreationTheme(index).line"
                                                    :style="{ width: `${creation.progress || 0}%` }"
                                                ></div>
                                            </div>
                                            <p class="text-[6px] uppercase tracking-[0.18em] text-white/75">{{ creation.progress || 0 }}%</p>
                                        </div>

                                        <div class="flex items-center justify-between border-t border-white/15 pt-2 text-[7px] uppercase">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center gap-1 border px-2 py-1" :class="getCreationTheme(index).stat">
                                                    <i class="fi fi-rr-heart text-[9px]"></i>
                                                    {{ creation.appreciations_count || 0 }}
                                                </span>
                                                <span class="inline-flex items-center gap-1 border px-2 py-1" :class="getCreationTheme(index).stat">
                                                    <i class="fi fi-rr-comment-alt text-[9px]"></i>
                                                    {{ creation.insights_count || 0 }}
                                                </span>
                                            </div>

                                            <Link
                                                :href="route('hall.creations.show', { creation: creation.slug || creation.id })"
                                                class="border border-white/45 bg-black/20 px-2 py-1 text-[6px] uppercase text-white/80 transition-colors hover:bg-white/15 hover:text-white"
                                            >
                                                View
                                            </Link>
                                        </div>
                                    </div>
                                </article>
                            </div>

                            <div v-else class="mt-6 hall-empty-state">
                                <div class="hall-empty-state__icon-wrap">
                                    <i class="fi fi-rr-lightbulb-on text-[24px] text-cyan-200"></i>
                                </div>
                                <div class="space-y-2">
                                    <p class="text-[10px] uppercase text-white">Belum ada creation publik di Hall</p>
                                    <p class="text-[11px] md:text-[13px] font-sans text-cyan-100/85">
                                        Jadi yang pertama publish karya dan buka diskusi dari komunitas.
                                    </p>
                                </div>
                            </div>
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
                <p class="text-[8px] text-white/50 uppercase tracking-[0.3em]">Build_Ver_1.2.0 // P-Quest Engine</p>
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
    -webkit-overflow-scrolling: touch;
    cursor: grab;
    padding-block: 1.25rem;
    overscroll-behavior-x: contain;
    overscroll-behavior-y: auto;
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
    scroll-snap-align: center;
    scroll-snap-stop: always;
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

.job-card--coming-soon {
    filter: saturate(0.55) brightness(0.82);
}

.job-card--coming-soon.job-card--focus {
    filter: saturate(0.65) brightness(0.9);
}

.coming-soon-chain {
    position: absolute;
    left: -18%;
    right: -18%;
    top: 50%;
    height: 18px;
    transform: translateY(-50%) rotate(var(--chain-rotation));
    background-image:
        radial-gradient(ellipse at center, transparent 0 38%, rgba(226, 232, 240, 0.98) 39% 54%, transparent 55%),
        radial-gradient(ellipse at center, transparent 0 38%, rgba(100, 116, 139, 0.95) 39% 54%, transparent 55%);
    background-position: 0 0, 18px 0;
    background-size: 36px 18px;
    filter: drop-shadow(0 0 7px rgba(148, 163, 184, 0.85));
    opacity: 0.98;
}

.coming-soon-chain--left {
    --chain-rotation: -18deg;
}

.coming-soon-chain--right {
    --chain-rotation: 18deg;
}

@media (max-width: 640px) {
    .jobs-stage::before,
    .jobs-stage::after {
        width: 28px;
    }

    .jobs-carousel {
        scroll-snap-type: x proximity;
    }

    .job-card--focus {
        transform: scale(1.06);
    }

    .job-card--side {
        transform: scale(0.92);
        opacity: 0.74;
    }

    .job-card {
        scroll-snap-stop: normal;
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
/* Mentor Card - Match Reference */
.mentor-card {
    width: 220px;
    height: 340px;
    max-width: none;
    flex: 0 0 auto;
    position: relative;
    perspective: 900px;
    --mentor-outer-from: #5a3a8b;
    --mentor-outer-to: #311e4f;
    --mentor-header-from: #2a184f;
    --mentor-header-mid: #4b2a76;
    --mentor-header-to: #6b3b9c;
    --mentor-panel-bg: #231538;
    --mentor-body-bg: #1c1230;
    --mentor-border: rgba(196, 181, 253, 0.5);
    --mentor-badge-from: rgba(99, 102, 241, 0.35);
    --mentor-badge-to: rgba(217, 70, 239, 0.35);
    --mentor-accent: #c4b5fd;
    --mentor-accent-glow: rgba(196, 181, 253, 0.8);
    --mentor-text-glow: rgba(167, 139, 250, 0.6);
}

@media (min-width: 768px) {
    .mentor-card {
        width: 260px;
        height: 360px;
    }

    .mentor-card__header {
        min-height: 44px;
        font-size: 9px;
        letter-spacing: 0.85px;
    }

    .mentor-card__footer {
        font-size: 9px;
        letter-spacing: 0.65px;
    }
}

.mentor-card__inner {
    height: 100%;
    border-radius: 0;
    background: linear-gradient(180deg, var(--mentor-outer-from) 0%, var(--mentor-outer-to) 100%);
    box-shadow: 0 12px 24px rgba(7, 6, 18, 0.6);
    padding: 6px;
    transform-style: preserve-3d;
    position: relative;
    transition: transform 600ms ease;
}

.mentor-card__header {
    min-height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 6px 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.55);
    background: rgba(0, 0, 0, 0.18);
    color: #ffffff;
    text-transform: uppercase;
    font-weight: 700;
    font-size: 8px;
    letter-spacing: 0.7px;
    line-height: 1.25;
}

.mentor-card__icon {
    position: absolute;
    left: 12px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.72);
    display: grid;
    place-items: center;
    font-size: 10px;
}

.mentor-card__title {
    padding: 0 8px 0 34px;
    text-align: center;
    display: -webkit-box;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: normal;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.mentor-card__body {
    flex: 1;
    margin: 8px;
    border: 1px solid rgba(255, 255, 255, 0.58);
    border-radius: 0;
    background: rgba(0, 0, 0, 0.16);
    overflow: hidden;
}

.mentor-card__photo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.mentor-card__photo--empty {
    background: rgba(0, 0, 0, 0.16);
}

.mentor-card__footer {
    height: 44px;
    margin: 0 8px 8px;
    border: 1px solid rgba(255, 255, 255, 0.58);
    border-radius: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 76px 0 10px;
    background: rgba(0, 0, 0, 0.2);
    color: #ffffff;
    text-transform: uppercase;
    font-weight: 700;
    font-size: 8px;
    letter-spacing: 0.5px;
    position: relative;
}

.mentor-card__role {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mentor-card__stars {
    position: absolute;
    right: 10px;
    top: -10px;
    background: rgba(0, 0, 0, 0.38);
    padding: 4px 8px;
    border-radius: 0;
    border: 1px solid rgba(255, 255, 255, 0.4);
    display: flex;
    gap: 2px;
    font-size: 8px;
    color: #ffffff;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.mentor-card__badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 1px 6px;
    border-radius: 999px;
    color: #efe7ff;
    background: linear-gradient(90deg, var(--mentor-badge-from), var(--mentor-badge-to));
    border: 1px solid var(--mentor-border);
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.35);
    text-shadow: 0 0 6px var(--mentor-text-glow);
}

.mentor-card__badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--mentor-accent);
    box-shadow: 0 0 6px var(--mentor-accent-glow);
}

.mentor-card__front {
    position: absolute;
    inset: 6px;
    border-radius: 0;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.04), rgba(0, 0, 0, 0.12));
    display: flex;
    flex-direction: column;
    backface-visibility: hidden;
    transform: rotateY(0deg);
}

/* Bagian Detail yang akan slide ke atas */
.mentor-card__overlay {
    position: absolute;
    inset: 6px;
    border-radius: 0;
    background:
        linear-gradient(180deg, rgba(2, 6, 23, 0.92), rgba(15, 23, 42, 0.88)),
        linear-gradient(180deg, var(--mentor-outer-from), var(--mentor-outer-to));
    transform: rotateY(180deg);
    z-index: 30;
    display: flex;
    flex-direction: column;
    padding: 1.25rem;
    backface-visibility: hidden;
}

/* Animasi halus untuk konten di dalam overlay */
.mentor-card__overlay-content {
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.4s ease 0.2s; /* delay sedikit agar slide selesai dulu */
}

.mentor-card.is-expanded .mentor-card__overlay-content {
    opacity: 1;
    transform: translateY(0);
}

.mentor-card.is-expanded .mentor-card__inner {
    transform: rotateY(180deg) scale(1.02);
}

.mentor-card.is-expanded {
    z-index: 20;
}

.mentor-bio {
    white-space: pre-line;
}

.mentor-card--sky {
    --mentor-outer-from: #075985;
    --mentor-outer-to: #0e7490;
    --mentor-header-from: #0b3f5c;
    --mentor-header-mid: #0e7490;
    --mentor-header-to: #22d3ee;
    --mentor-panel-bg: #0b2533;
    --mentor-body-bg: #0c2e3f;
    --mentor-border: rgba(125, 211, 252, 0.55);
    --mentor-badge-from: rgba(14, 165, 233, 0.4);
    --mentor-badge-to: rgba(6, 182, 212, 0.4);
    --mentor-accent: #7dd3fc;
    --mentor-accent-glow: rgba(125, 211, 252, 0.75);
    --mentor-text-glow: rgba(125, 211, 252, 0.6);
}

.mentor-card--indigo {
    --mentor-outer-from: #3730a3;
    --mentor-outer-to: #6d28d9;
    --mentor-header-from: #312e81;
    --mentor-header-mid: #5b21b6;
    --mentor-header-to: #a855f7;
    --mentor-panel-bg: #24154a;
    --mentor-body-bg: #2d1b5a;
    --mentor-border: rgba(196, 181, 253, 0.6);
    --mentor-badge-from: rgba(99, 102, 241, 0.4);
    --mentor-badge-to: rgba(168, 85, 247, 0.4);
    --mentor-accent: #c4b5fd;
    --mentor-accent-glow: rgba(196, 181, 253, 0.8);
    --mentor-text-glow: rgba(196, 181, 253, 0.6);
}

.mentor-card--emerald {
    --mentor-outer-from: #065f46;
    --mentor-outer-to: #0f766e;
    --mentor-header-from: #064e3b;
    --mentor-header-mid: #0f766e;
    --mentor-header-to: #34d399;
    --mentor-panel-bg: #0b2f28;
    --mentor-body-bg: #0d3a32;
    --mentor-border: rgba(110, 231, 183, 0.6);
    --mentor-badge-from: rgba(16, 185, 129, 0.4);
    --mentor-badge-to: rgba(45, 212, 191, 0.4);
    --mentor-accent: #6ee7b7;
    --mentor-accent-glow: rgba(110, 231, 183, 0.8);
    --mentor-text-glow: rgba(110, 231, 183, 0.6);
}

.mentor-card--cyan {
    --mentor-outer-from: #155e75;
    --mentor-outer-to: #1d4ed8;
    --mentor-header-from: #0f4c5f;
    --mentor-header-mid: #1d4ed8;
    --mentor-header-to: #38bdf8;
    --mentor-panel-bg: #0b2a3a;
    --mentor-body-bg: #0c3246;
    --mentor-border: rgba(125, 211, 252, 0.55);
    --mentor-badge-from: rgba(14, 165, 233, 0.4);
    --mentor-badge-to: rgba(59, 130, 246, 0.4);
    --mentor-accent: #7dd3fc;
    --mentor-accent-glow: rgba(125, 211, 252, 0.75);
    --mentor-text-glow: rgba(125, 211, 252, 0.6);
}

.hall-preview-shell {
    position: relative;
}

.hall-preview-panel {
    position: relative;
    overflow: hidden;
    border: 2px solid rgba(103, 232, 249, 0.32);
    background:
        linear-gradient(145deg, rgba(2, 6, 23, 0.92), rgba(15, 23, 42, 0.88)),
        linear-gradient(125deg, rgba(6, 182, 212, 0.16), rgba(59, 130, 246, 0.12));
    padding: 1.15rem;
    box-shadow: 0 14px 30px rgba(2, 6, 23, 0.32);
}

.hall-preview-panel__glow {
    position: absolute;
    width: 260px;
    height: 260px;
    border-radius: 999px;
    filter: blur(62px);
    opacity: 0.12;
    pointer-events: none;
    animation: hall-aurora 7.5s ease-in-out infinite;
}

.hall-preview-panel__glow--one {
    top: -150px;
    right: -60px;
    background: #22d3ee;
}

.hall-preview-panel__glow--two {
    bottom: -170px;
    left: -80px;
    background: #38bdf8;
    animation-delay: 2.4s;
}

.hall-stat-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border: 1px solid rgba(125, 211, 252, 0.46);
    background: rgba(15, 23, 42, 0.74);
    padding: 0.38rem 0.6rem;
    font-size: 8px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgba(207, 250, 254, 0.92);
}

.hall-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid;
    padding: 0.58rem 0.9rem;
    font-size: 8px;
    letter-spacing: 0.11em;
    text-transform: uppercase;
    transition: transform 180ms ease, background-color 180ms ease, color 180ms ease, border-color 180ms ease;
}

.hall-cta:hover {
    transform: translateY(-1px);
}

.hall-cta--primary {
    border-color: rgba(103, 232, 249, 0.72);
    background: rgba(14, 116, 144, 0.3);
    color: #ecfeff;
}

.hall-cta--primary:hover {
    border-color: rgba(165, 243, 252, 0.92);
    background: rgba(8, 145, 178, 0.5);
}

.hall-cta--secondary {
    border-color: rgba(191, 219, 254, 0.6);
    background: rgba(15, 23, 42, 0.76);
    color: rgba(224, 242, 254, 0.94);
}

.hall-cta--secondary:hover {
    border-color: rgba(148, 163, 184, 0.86);
    background: rgba(15, 23, 42, 0.9);
}

.hall-preview-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 1rem;
}

.hall-showcase-card {
    min-height: 295px;
    transform: translateY(14px);
    opacity: 0;
    animation: hall-card-reveal 520ms ease forwards;
}

.hall-showcase-card:hover {
    transform: translateY(-4px);
}

.hall-showcase-card__shine {
    position: absolute;
    top: -50%;
    left: -120%;
    width: 45%;
    height: 200%;
    background: linear-gradient(120deg, transparent, rgba(255, 255, 255, 0.25), transparent);
    transform: rotate(16deg);
    transition: left 420ms ease;
    pointer-events: none;
}

.hall-showcase-card:hover .hall-showcase-card__shine {
    left: 140%;
}

.hall-empty-state {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    border: 1px dashed rgba(125, 211, 252, 0.44);
    background: rgba(2, 6, 23, 0.35);
    padding: 1rem;
}

.hall-empty-state__icon-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    border: 1px solid rgba(125, 211, 252, 0.45);
    background: rgba(8, 47, 73, 0.45);
}

@media (min-width: 768px) {
    .hall-preview-panel {
        padding: 1.35rem;
    }

    .hall-preview-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (min-width: 1280px) {
    .hall-preview-grid {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }
}

@keyframes hall-aurora {
    0%, 100% {
        transform: translate3d(0, 0, 0) scale(1);
    }
    50% {
        transform: translate3d(10px, -8px, 0) scale(1.08);
    }
}

@keyframes hall-card-reveal {
    from {
        transform: translateY(14px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .hall-preview-panel__glow,
    .hall-showcase-card {
        animation: none;
    }

    .hall-showcase-card,
    .hall-showcase-card:hover,
    .hall-cta:hover,
    .hall-showcase-card__shine {
        transform: none;
        transition: none;
    }

    .hall-showcase-card__shine,
    .hall-showcase-card:hover .hall-showcase-card__shine {
        left: -120%;
    }
}

.landing-nav {
    position: sticky;
    top: 0;
    isolation: isolate;
    background: rgba(26, 28, 44, 0.8);
    transform: translateZ(0);
    will-change: transform;
    backface-visibility: hidden;
}

.landing-nav::before {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    background: rgba(26, 28, 44, 0.8);
}

@supports ((-webkit-backdrop-filter: blur(10px)) or (backdrop-filter: blur(10px))) {
    .landing-nav::before {
        background: rgba(26, 28, 44, 0.8);
        -webkit-backdrop-filter: blur(10px);
        backdrop-filter: blur(10px);
    }
}

.page-loader {
    position: fixed;
    inset: 0;
    background: linear-gradient(135deg, rgba(20, 24, 38, 0.96), rgba(44, 16, 64, 0.96));
    display: grid;
    place-items: center;
    z-index: 999;
}

.page-loader__card {
    width: min(320px, 80vw);
    padding: 20px;
    border: 2px solid rgba(255, 255, 255, 0.35);
    background: rgba(8, 10, 22, 0.9);
    box-shadow: 0 16px 32px rgba(0, 0, 0, 0.4);
    text-align: center;
}

.page-loader__grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 16px;
}

.page-loader__pixel {
    width: 100%;
    aspect-ratio: 1;
    background: #5eead4;
    box-shadow: inset 0 0 0 2px rgba(8, 10, 22, 0.7);
    animation: pixel-pulse 1.2s ease-in-out infinite;
}

.page-loader__pixel:nth-child(2),
.page-loader__pixel:nth-child(6) {
    background: #818cf8;
    animation-delay: 0.2s;
}

.page-loader__pixel:nth-child(3),
.page-loader__pixel:nth-child(9) {
    background: #f472b6;
    animation-delay: 0.4s;
}

.page-loader__pixel:nth-child(4),
.page-loader__pixel:nth-child(8) {
    background: #34d399;
    animation-delay: 0.6s;
}

.page-loader__label {
    font-size: 10px;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: #f8fafc;
    margin-bottom: 12px;
}

.page-loader__bar {
    width: 100%;
    height: 8px;
    border: 1px solid rgba(255, 255, 255, 0.35);
    background: rgba(255, 255, 255, 0.1);
    overflow: hidden;
}

.page-loader__bar-fill {
    display: block;
    height: 100%;
    width: 40%;
    background: linear-gradient(90deg, #5eead4, #818cf8, #f472b6);
    animation: load-bar 1.4s ease-in-out infinite;
}

@keyframes pixel-pulse {
    0%, 100% {
        transform: scale(0.9);
        opacity: 0.6;
    }
    50% {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes load-bar {
    0% {
        transform: translateX(-120%);
    }
    100% {
        transform: translateX(260%);
    }
}
</style>
