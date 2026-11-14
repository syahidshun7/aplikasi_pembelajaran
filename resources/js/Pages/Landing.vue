<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onBeforeUpdate, onMounted, ref, watch } from 'vue';
import AppBackgroundLayer from '@/Components/AppBackgroundLayer.vue';
import LandingAcademyScene from '@/Components/LandingAcademyScene.vue';

const academyBackground = '/images/landing/academy-hero.webp';

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
            job_name: mentor.job_name ? String(mentor.job_name) : null,
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

const loadedMentors = computed(() => normalizedPropMentors.value);
const featuredCreations = computed(() => (
    Array.isArray(props?.featuredCreations)
        ? props.featuredCreations.filter((creation) => creation && typeof creation === 'object').slice(0, 3)
        : []
));
const expandedMentorId = ref(null);
const isPageLoaded = ref(false);
let pageLoadHandler = null;

const hallEntryHref = computed(() => route('hall.creations.index'));

const hallEntryLabel = 'Jelajahi Semua Karya';

const seoTitle = 'DOOPTECH | Platform Pembelajaran Quest-Based';
const seoDescription = 'DOOPTECH adalah aplikasi pembelajaran berbasis game yang menghubungkan pemula dan profesional dalam satu ekosistem belajar.';
const seoCanonicalUrl = computed(() => route('lobby'));
const seoImageUrl = computed(() => new URL(academyBackground, seoCanonicalUrl.value).toString());
const seoSchemaJson = computed(() => JSON.stringify({
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: 'DOOPTECH',
    url: seoCanonicalUrl.value,
    description: seoDescription,
}, null, 2));

const toggleMentorCard = (id) => {
    expandedMentorId.value = expandedMentorId.value === id ? null : id;
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
        <link rel="preload" as="image" :href="academyBackground" />
        <component :is="'script'" head-key="ld-json-website" type="application/ld+json" v-html="seoSchemaJson" />
    </Head>

    <div
        class="relative isolate min-h-screen overflow-x-hidden font-['Press_Start_2P'] text-slate-900"
    >
        <AppBackgroundLayer
            :image="academyBackground"
            image-position="right center"
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

            <section class="landing-hero" aria-labelledby="landing-hero-title">
                <div class="landing-hero__layout">
                    <div class="landing-hero__intro">
                        <h1 id="landing-hero-title">
                            <span>Like Playing A Game, Learning Should Be Enjoyable.</span>
                            <span>No Matter If We Lose Or Win, We'll Always Return</span>
                        </h1>
                        <div class="landing-hero__actions">
                            <Link v-if="canRegister" :href="route('register')" class="landing-hero__start">Mulai Belajar</Link>
                            <Link v-if="canLogin" :href="route('login')" class="landing-hero__login">Masuk</Link>
                        </div>
                    </div>

                    <LandingAcademyScene class="landing-hero__illustration" />
                </div>
            </section>

            <section class="hall-section px-4 pb-14 pt-8 md:px-10 md:pb-16 md:pt-12" aria-labelledby="hall-heading">
                <div class="mx-auto max-w-6xl hall-preview-shell">
                    <header class="hall-section__header mx-auto text-center">
                        <p class="hall-section__eyebrow">Hall of Creations</p>
                        <h2 id="hall-heading" class="hall-section__title">Showcase Karya Terbaik Komunitas</h2>
                        <div class="hall-stone-divider" aria-hidden="true">
                            <img src="/images/landing/hall-stone-divider.png" alt="" />
                        </div>
                        <p class="hall-section__description font-sans">
                            Jelajahi karya para learner, temukan ide baru, dan dapatkan inspirasi.
                        </p>
                    </header>

                    <div v-if="featuredCreations.length > 0" class="mt-8 hall-preview-grid">
                        <article
                            v-for="(creation, index) in featuredCreations"
                            :key="creation.id"
                            class="hall-showcase-card group"
                        >
                            <span v-if="index === 0" class="hall-showcase-card__featured">
                                <i class="fi fi-rr-crown" aria-hidden="true"></i>
                                Karya Pilihan
                            </span>
                            <div class="hall-showcase-card__thumbnail">
                                <img
                                    v-if="creation.thumbnail_url"
                                    :src="creation.thumbnail_url"
                                    :alt="creation.title"
                                    class="h-full w-full object-cover object-center transition-transform duration-300 group-hover:scale-[1.04]"
                                    loading="lazy"
                                    decoding="async"
                                />
                                <div v-else class="flex h-full items-center justify-center">
                                    <i class="fi fi-rr-lightbulb-on text-[28px] text-[#087f7f]"></i>
                                </div>
                            </div>

                            <div class="hall-showcase-card__body">
                                <div class="min-w-0">
                                    <h3 class="hall-showcase-card__title">{{ creation.title }}</h3>
                                    <p v-if="creation.creator?.username || creation.creator?.name" class="hall-showcase-card__creator font-sans">
                                        {{ creation.creator?.username || creation.creator?.name }}
                                    </p>
                                </div>

                                <div class="hall-showcase-card__footer">
                                    <div class="hall-showcase-card__stats font-sans" aria-label="Apresiasi dan komentar">
                                        <span :aria-label="`${creation.appreciations_count || 0} apresiasi`">
                                            <i class="fi fi-rr-heart" aria-hidden="true"></i>
                                            <span>{{ creation.appreciations_count || 0 }}</span>
                                        </span>
                                        <span :aria-label="`${creation.insights_count || 0} komentar`">
                                            <i class="fi fi-rr-comment-alt" aria-hidden="true"></i>
                                            <span>{{ creation.insights_count || 0 }}</span>
                                        </span>
                                    </div>

                                    <Link
                                        :href="route('hall.creations.show', { creation: creation.slug || creation.id })"
                                        class="hall-showcase-card__link"
                                    >
                                        Lihat Karya
                                        <i class="fi fi-rr-arrow-right" aria-hidden="true"></i>
                                    </Link>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div v-else class="hall-empty-state">
                        <p class="font-sans">Belum ada creation publik di Hall.</p>
                    </div>

                    <div class="mt-8 flex justify-center">
                        <Link :href="hallEntryHref" class="landing-hero__start hall-main-cta">
                            {{ hallEntryLabel }}
                        </Link>
                    </div>
                </div>
            </section>

            <section class="jobs-section px-4 md:px-7 pb-10 md:pb-14" aria-labelledby="jobs-title">
                <div class="jobs-content mx-auto">
                    <header class="jobs-heading">
                        <div class="jobs-heading__copy">
                            <p class="jobs-heading__eyebrow"><span></span>Available Jobs</p>
                            <h2 id="jobs-title">Pilih Kesempatan Belajar</h2>
                            <p class="jobs-heading__description">Temukan kelas yang sesuai dengan minatmu dan kembangkan keterampilan baru.</p>
                        </div>
                        <a class="jobs-heading__link" href="#jobs-carousel">Lihat Semua <span aria-hidden="true">→</span></a>
                    </header>

                    <div class="jobs-stage">
                        <div
                            ref="jobsCarousel"
                            id="jobs-carousel"
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
                                class="job-card relative overflow-hidden snap-center shrink-0"
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
                                <div v-if="isComingSoonJob(job)" class="pointer-events-none absolute inset-0 z-30">
                                    <div class="coming-soon-chain coming-soon-chain--left"></div>
                                    <div class="coming-soon-chain coming-soon-chain--right"></div>
                                    <div class="absolute left-1/2 top-1/2 flex h-16 w-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center border-2 border-slate-300 bg-slate-950/85 text-slate-100 shadow-lg">
                                        <i class="fi fi-rr-lock text-3xl leading-none"></i>
                                    </div>
                                </div>

                                <div class="job-card__content">
                                    <div class="job-card__topline">
                                        <span>Class Card</span>
                                        <span class="job-card__status" :class="{ 'job-card__status--locked': isComingSoonJob(job) }">
                                            {{ isComingSoonJob(job) ? 'Coming Soon' : 'Active' }}
                                        </span>
                                    </div>

                                    <div class="job-card__art">
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

                                    <div class="job-card__details">
                                        <p class="job-card__title">
                                            {{ job.name || 'UNKNOWN JOB' }}
                                        </p>
                                        <p class="job-card__description line-clamp-3">
                                            {{ job.description || getJobDescription(job) }}
                                        </p>
                                        <div class="job-card__count">Mentor tersedia: <strong>{{ job.mentors_count || 0 }}</strong></div>
                                    </div>
                                </div>
                            </article>

                            <div class="jobs-carousel-spacer" :style="{ width: `${sidePadding}px` }" aria-hidden="true"></div>
                        </div>
                    </div>

                    <nav v-if="displayJobs.length > 1" class="jobs-controls" aria-label="Navigasi kesempatan belajar">
                        <button type="button" class="jobs-controls__arrow" aria-label="Kartu sebelumnya" @click="scrollToCard((activeJobIndex - 1 + displayJobs.length) % displayJobs.length)">
                            <i class="fi fi-rr-angle-small-left" aria-hidden="true"></i>
                        </button>
                        <div class="jobs-controls__dots">
                            <button
                                v-for="(job, index) in displayJobs"
                                :key="`job-dot-${job.id || index}`"
                                type="button"
                                class="jobs-controls__dot"
                                :class="{ 'is-active': index === activeJobIndex }"
                                :aria-label="`Tampilkan ${job.name}`"
                                :aria-current="index === activeJobIndex ? 'true' : undefined"
                                @click="scrollToCard(index)"
                            ></button>
                        </div>
                        <button type="button" class="jobs-controls__arrow" aria-label="Kartu berikutnya" @click="scrollToCard((activeJobIndex + 1) % displayJobs.length)">
                            <i class="fi fi-rr-angle-small-right" aria-hidden="true"></i>
                        </button>
                    </nav>
                </div>
            </section>

            <section class="mentor-section px-4 pb-14 md:px-10" aria-labelledby="mentor-heading">
                <div class="mentor-section__inner mx-auto">
                    <header class="mentor-section__header">
                        <h2 id="mentor-heading" class="mentor-section__title">Featured Mentors</h2>
                    </header>

                    <div v-if="loadedMentors.length" class="mentor-grid">
                        <article
                            v-for="(mentor, idx) in loadedMentors"
                            :key="mentor.id"
                            class="mentor-card"
                            :class="{ 'is-expanded': expandedMentorId === mentor.id }"
                        >
                            <div class="mentor-card__inner">
                                <section
                                    class="mentor-card__face mentor-card__front"
                                    :aria-hidden="expandedMentorId === mentor.id"
                                    :inert="expandedMentorId === mentor.id"
                                >
                                    <button
                                        type="button"
                                        class="mentor-card__flip-trigger"
                                        :aria-label="`Lihat detail ${mentor.name}`"
                                        @click="toggleMentorCard(mentor.id)"
                                    ></button>
                                    <div class="mentor-card__photo-frame">
                                        <img
                                            v-if="mentor.profile_photo"
                                            :src="`/storage/${mentor.profile_photo}`"
                                            :alt="`Foto ${mentor.name}`"
                                            class="mentor-card__photo"
                                            loading="lazy"
                                            decoding="async"
                                        />
                                        <div v-else class="mentor-card__photo-fallback" aria-label="Foto mentor tidak tersedia">
                                            <i class="fi fi-rr-user" aria-hidden="true"></i>
                                        </div>
                                    </div>
                                    <footer class="mentor-card__footer">
                                        <h3 class="mentor-card__name">{{ mentor.name }}</h3>
                                        <p v-if="mentor.job_name" class="mentor-card__front-specialty">{{ mentor.job_name }}</p>
                                        <Link
                                            v-if="mentor.username"
                                            :href="route('profiles.show', { user: mentor.username })"
                                            class="mentor-card__profile-link"
                                            @click.stop
                                        >
                                            Lihat Profil
                                            <i class="fi fi-rr-arrow-up-right" aria-hidden="true"></i>
                                        </Link>
                                    </footer>
                                </section>

                                <section
                                    class="mentor-card__face mentor-card__back"
                                    :aria-hidden="expandedMentorId !== mentor.id"
                                    :inert="expandedMentorId !== mentor.id"
                                >
                                    <header class="mentor-card__back-header">
                                        <div class="mentor-card__back-title">
                                            <h3>{{ mentor.name }}</h3>
                                            <span class="mentor-card__badge">Mentor</span>
                                        </div>
                                        <button
                                            type="button"
                                            class="mentor-card__return"
                                            :aria-label="`Kembali ke kartu ${mentor.name}`"
                                            @click="toggleMentorCard(mentor.id)"
                                        >
                                            <i class="fi fi-rr-cross-small" aria-hidden="true"></i>
                                        </button>
                                    </header>

                                    <div class="mentor-card__back-content">
                                        <section v-if="mentor.bio" class="mentor-card__bio-block">
                                            <h4>BIO</h4>
                                            <p class="mentor-bio">{{ mentor.bio }}</p>
                                        </section>

                                        <dl class="mentor-card__facts">
                                            <div v-if="mentor.username">
                                                <dt>Username</dt><dd>@{{ mentor.username }}</dd>
                                            </div>
                                            <div v-if="mentor.job_name">
                                                <dt>Spesialisasi</dt><dd>{{ mentor.job_name }}</dd>
                                            </div>
                                            <div v-if="mentor.experience">
                                                <dt>Pengalaman</dt><dd>{{ mentor.experience }}</dd>
                                            </div>
                                            <div v-if="mentor.location">
                                                <dt>Lokasi</dt><dd>{{ mentor.location }}</dd>
                                            </div>
                                        </dl>

                                        <section v-if="mentor.skills.length" class="mentor-card__skills">
                                            <h4>Skills</h4>
                                            <div class="mentor-card__skill-list">
                                                <span v-for="(skill, sIndex) in mentor.skills" :key="`${mentor.id}-skill-${sIndex}`">{{ skill }}</span>
                                            </div>
                                        </section>

                                        <p v-if="!mentor.bio && !mentor.username && !mentor.job_name && !mentor.experience && !mentor.location && !mentor.skills.length" class="mentor-card__empty-details">
                                            Detail profil belum tersedia.
                                        </p>
                                    </div>
                                </section>
                            </div>
                        </article>
                    </div>

                    <div v-else class="mentor-empty-state font-sans">
                        Mentor belum tersedia.
                    </div>
                </div>
            </section>

            <footer class="p-8 text-center bg-[#1a1c2c]/50 backdrop-blur-md border-t-2 border-white/10 mt-auto">
                <p class="text-[8px] text-white/50 uppercase tracking-[0.3em]">Build_Ver_1.2.1 // P-Quest Engine</p>
            </footer>
        </div>
    </div>
</template>

<style scoped>

.landing-hero {
    --hero-ink: #122335;
    --hero-accent: #087f7f;
    color: var(--hero-ink);
    padding: clamp(2rem, 4vw, 4.5rem) clamp(1rem, 5vw, 5rem);
}

.landing-hero__layout {
    max-width: 76rem;
    margin-inline: auto;
    display: grid;
    grid-template-columns: minmax(0, 0.92fr) minmax(0, 1.08fr);
    align-items: center;
    gap: clamp(0.75rem, 2.25vw, 2rem);
}

.landing-hero h1 {
    font-size: clamp(1.125rem, 2.4vw, 2.2rem);
    line-height: 1.55;
    text-wrap: balance;
}

.landing-hero h1 span { display: block; }
.landing-hero h1 span + span {
    margin-top: 0.6rem;
    color: var(--hero-accent);
    font-size: 0.72em;
}

.landing-hero__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem 1.25rem; margin-top: 1.5rem; }
.landing-hero__actions a { display: inline-flex; min-height: 44px; align-items: center; font-size: clamp(0.62rem, 1.2vw, 0.85rem); }
.landing-hero__start {
    padding: 0.65rem 1rem;
    color: white;
    background: #087f7f;
    border: 2px solid #075e63;
    border-radius: 0;
    box-shadow: inset 0 2px #4ed4d4, 0 4px #06494d;
}
.landing-hero__start:hover { background: #066466; }
.landing-hero__login { color: var(--hero-accent); text-decoration: underline; text-underline-offset: 0.25em; }
.landing-hero__actions a:focus-visible { outline: 2px solid var(--hero-ink); outline-offset: 4px; }

.landing-hero__illustration {
    display: block;
    width: 100%;
    max-width: 43rem;
    height: auto;
    justify-self: center;
    filter: drop-shadow(0 8px 5px rgb(12 52 72 / 30%));
}

@media (max-width: 767px) {
    .landing-hero__layout { grid-template-columns: minmax(0, 1fr); }
}

.jobs-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.5rem;
    margin-bottom: 1.2rem;
}

.jobs-heading__copy { min-width: 0; }
.jobs-heading__eyebrow {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    margin: 0 0 0.7rem;
    color: #087f7f;
    font-size: 0.52rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.jobs-heading__eyebrow::before,
.jobs-heading__eyebrow::after {
    content: '';
    width: 2.5rem;
    height: 2px;
    background: #087f7f;
}
.jobs-heading__eyebrow::after { display: block; width: 4rem; }
.jobs-heading__copy h2 {
    margin: 0;
    color: #122335 !important;
    font-size: clamp(1.05rem, 2.2vw, 1.7rem);
    line-height: 1.45;
}
.jobs-heading__description {
    margin: 0.5rem 0 0;
    color: #24445a;
    font-family: sans-serif;
    font-size: 0.88rem;
    line-height: 1.55;
}
.jobs-heading__link {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1.8rem;
    color: #087f7f;
    font-family: sans-serif;
    font-size: 0.88rem;
    font-weight: 700;
    text-underline-offset: 0.25em;
}
.jobs-heading__link span { font-size: 1.25rem; line-height: 1; }
.jobs-heading__link:focus-visible,
.jobs-controls button:focus-visible { outline: 2px solid #122335; outline-offset: 4px; }

.jobs-stage {
    position: relative;
}

.jobs-content { width: 100%; max-width: 1320px; }

.jobs-stage::before,
.jobs-stage::after {
    display: none;
}

.jobs-carousel {
    display: flex;
    align-items: center;
    gap: clamp(0.65rem, 1.3vw, 1.15rem);
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
    width: clamp(190px, 18vw, 250px);
    height: clamp(290px, 20vw, 310px);
    box-sizing: border-box;
    padding: 0.62rem;
    border-width: 3px;
    border-radius: 0;
    clip-path: polygon(8px 0, calc(100% - 8px) 0, calc(100% - 8px) 3px, calc(100% - 3px) 3px, calc(100% - 3px) 8px, 100% 8px, 100% calc(100% - 8px), calc(100% - 3px) calc(100% - 8px), calc(100% - 3px) calc(100% - 3px), calc(100% - 8px) calc(100% - 3px), calc(100% - 8px) 100%, 8px 100%, 8px calc(100% - 3px), 3px calc(100% - 3px), 3px calc(100% - 8px), 0 calc(100% - 8px), 0 8px, 3px 8px, 3px 3px, 8px 3px);
    box-shadow: inset 0 0 0 3px rgb(255 255 255 / 34%), 0 5px 0 rgb(8 43 59 / 65%);
    transform-origin: center center;
    transition: transform 320ms ease-in-out, opacity 320ms ease-in-out, box-shadow 320ms ease-in-out, filter 320ms ease-in-out;
    will-change: transform, opacity;
    scroll-snap-align: center;
    scroll-snap-stop: always;
}

.job-card--focus {
    transform: scale(1.12) translateY(-3px);
    opacity: 1;
    z-index: 30;
    border-color: #4ed4d4 !important;
    background: linear-gradient(145deg, #0f766e, #064e3b) !important;
    filter: drop-shadow(0 5px 0 #075e63) drop-shadow(0 10px 8px rgb(8 43 59 / 25%));
    box-shadow: inset 0 0 0 3px rgb(255 255 255 / 65%), 0 5px 0 #075e63, 0 12px 20px rgb(8 43 59 / 28%);
}

.job-card--side {
    transform: scale(0.94);
    opacity: 1;
    z-index: 12;
    filter: drop-shadow(0 5px 0 rgb(8 43 59 / 65%)) drop-shadow(0 8px 7px rgb(15 23 42 / 18%));
    box-shadow: inset 0 0 0 2px rgb(255 255 255 / 34%), 0 5px 0 rgb(8 43 59 / 65%), 0 8px 14px rgba(15, 23, 42, 0.2);
}

.job-card--coming-soon {
    filter: saturate(0.78) brightness(0.94) drop-shadow(0 5px 0 rgb(8 43 59 / 65%)) drop-shadow(0 8px 7px rgb(15 23 42 / 18%));
}

.job-card--coming-soon.job-card--focus {
    filter: saturate(0.84) brightness(0.98) drop-shadow(0 5px 0 #075e63) drop-shadow(0 10px 8px rgb(8 43 59 / 25%));
}

.job-card__content { display: flex; position: relative; z-index: 10; height: 100%; flex-direction: column; }
.job-card__topline { display: flex; min-height: 1.45rem; align-items: center; justify-content: space-between; gap: 0.35rem; color: rgb(255 255 255 / 88%); font-size: 0.42rem; text-transform: uppercase; }
.job-card__status { flex: 0 0 auto; border: 1px solid rgb(255 255 255 / 70%); border-radius: 999px; padding: 0.18rem 0.4rem; background: rgb(255 255 255 / 10%); font-size: 0.35rem; line-height: 1.2; }
.job-card__status--locked { background: rgb(15 23 42 / 55%); }
.job-card__art { display: flex; height: 7.8rem; flex: 0 0 auto; align-items: center; justify-content: center; overflow: hidden; border: 2px solid rgb(255 255 255 / 62%); background: rgb(5 21 36 / 44%); }
.job-card__art img { image-rendering: pixelated; }
.job-card__details { display: flex; min-height: 0; flex: 1; flex-direction: column; margin-top: 0.4rem; padding: 0.45rem 0.48rem 0.35rem; color: var(--hero-ink); background: #fff9ed; border: 1px solid rgb(255 255 255 / 65%); }
.job-card__title { margin: 0; color: #122335; font-size: 0.52rem; line-height: 1.55; text-transform: uppercase; }
.job-card__description { margin: 0.32rem 0 0; color: #30485c; font-family: sans-serif; font-size: 0.72rem; line-height: 1.34; }
.job-card__count { margin-top: auto; padding-top: 0.35rem; border-top: 1px solid rgb(18 35 53 / 18%); color: #28485b; font-family: sans-serif; font-size: 0.57rem; letter-spacing: 0.06em; text-transform: uppercase; }
.job-card__count strong { color: #122335; }

.jobs-controls { display: flex; align-items: center; justify-content: center; gap: 1.25rem; padding-top: 0.25rem; }
.jobs-controls__arrow { display: grid; width: 2rem; height: 2rem; place-items: center; border: 2px solid #087f7f; color: #087f7f; background: rgb(255 255 255 / 80%); box-shadow: 0 3px 0 rgb(8 43 59 / 25%); font-size: 1.25rem; }
.jobs-controls__arrow:hover { color: white; background: #087f7f; }
.jobs-controls__dots { display: flex; align-items: center; gap: 0.55rem; }
.jobs-controls__dot { width: 0.58rem; height: 0.58rem; padding: 0; border: 2px solid #087f7f; border-radius: 50%; background: transparent; transition: transform 180ms ease, background-color 180ms ease; }
.jobs-controls__dot.is-active { transform: scale(1.18); background: #087f7f; }

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
    .jobs-heading { align-items: start; gap: 0.5rem; margin-bottom: 0.65rem; }
    .jobs-heading__eyebrow { margin-bottom: 0.45rem; font-size: 0.4rem; }
    .jobs-heading__eyebrow::before { width: 1.25rem; }
    .jobs-heading__copy h2 { max-width: 19rem; font-size: clamp(0.82rem, 4vw, 1.1rem); line-height: 1.55; }
    .jobs-heading__description { max-width: 24rem; margin-top: 0.35rem; font-size: 0.76rem; }
    .jobs-heading__link { gap: 0.25rem; margin-top: 1.35rem; font-size: 0.72rem; }
    .jobs-heading__link span { font-size: 1rem; }

    .jobs-carousel {
        scroll-snap-type: x proximity;
    }

    .job-card {
        width: clamp(190px, 62vw, 230px);
        height: 290px;
    }

    .job-card--focus {
        transform: scale(1.06) translateY(-2px);
    }

    .job-card--side {
        transform: scale(0.92);
        opacity: 1;
    }

    .job-card {
        scroll-snap-stop: normal;
    }
    .job-card__art { height: 7rem; }
    .job-card__title { font-size: 0.48rem; }
    .job-card__description { font-size: 0.68rem; }
    .jobs-controls { padding-top: 0; }
}

/* Featured mentor cards */
.mentor-section__inner { width: 100%; max-width: 72rem; }
.mentor-section__header { margin-bottom: 1.5rem; text-align: center; }
.mentor-section__title { display: inline-flex; align-items: center; justify-content: center; gap: clamp(0.75rem, 2vw, 1.5rem); margin: 0; color: #087f7f; font-size: clamp(9px, 1.1vw, 12px); letter-spacing: 0.16em; line-height: 1.8; text-transform: uppercase; }
.mentor-section__title::before,
.mentor-section__title::after { width: clamp(2.5rem, 8vw, 6rem); height: 2px; background: #087f7f; content: ''; }
.mentor-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 360px)); justify-content: center; gap: 1.5rem; }
.mentor-card { position: relative; width: 100%; max-width: 360px; height: 520px; margin-inline: auto; perspective: 1200px; }
.mentor-card__inner { position: relative; width: 100%; height: 100%; transform-style: preserve-3d; transition: transform 580ms cubic-bezier(0.2, 0.72, 0.25, 1); }
.mentor-card.is-expanded { z-index: 3; }
.mentor-card.is-expanded .mentor-card__inner { transform: rotateY(180deg); }
.mentor-card__face { position: absolute; inset: 0; overflow: hidden; border: 3px solid #071e31; background: #083047; box-shadow: inset 0 0 0 3px #0d8290, inset 0 0 0 6px #061b2c; backface-visibility: hidden; clip-path: polygon(12px 0, calc(100% - 12px) 0, calc(100% - 12px) 4px, calc(100% - 4px) 4px, calc(100% - 4px) 12px, 100% 12px, 100% calc(100% - 12px), calc(100% - 4px) calc(100% - 12px), calc(100% - 4px) calc(100% - 4px), calc(100% - 12px) calc(100% - 4px), calc(100% - 12px) 100%, 12px 100%, 12px calc(100% - 4px), 4px calc(100% - 4px), 4px calc(100% - 12px), 0 calc(100% - 12px), 0 12px, 4px 12px, 4px 4px, 12px 4px); }
.mentor-card__front { display: flex; flex-direction: column; padding: 13px; transform: rotateY(0deg); background: linear-gradient(180deg, #0b3a52, #06263b 70%); }
.mentor-card__back { display: flex; flex-direction: column; padding: 1.25rem 1.35rem; transform: rotateY(180deg); background: #061b2e; color: #f2f8fb; }
.mentor-card__flip-trigger { position: absolute; z-index: 1; inset: 0; width: 100%; height: 100%; padding: 0; border: 0; background: transparent; cursor: pointer; }
.mentor-card__flip-trigger:focus-visible { outline: 3px solid #4ed4d4; outline-offset: -8px; }
.mentor-card__photo-frame { position: relative; z-index: 2; display: flex; min-height: 0; flex: 1; align-items: center; justify-content: center; overflow: hidden; margin: 0.6rem 0.25rem 0; border: 2px solid rgb(78 212 212 / 55%); background: #081d2f; pointer-events: none; }
.mentor-card__photo { display: block; width: 100%; height: 100%; object-fit: cover; object-position: center 35%; }
.mentor-card__photo-fallback { display: grid; width: 100%; height: 100%; place-items: center; color: #8eced4; background: #0b2b40; font-size: 4rem; }
.mentor-card__footer { position: relative; z-index: 2; display: flex; flex: 0 0 auto; flex-direction: column; align-items: center; margin-top: -1.15rem; pointer-events: none; }
.mentor-card__name { max-width: calc(100% - 1rem); margin: 0; padding: 0.55rem 1.2rem; overflow-wrap: anywhere; border: 3px solid #896b3d; color: #122335; background: #fff0c9; clip-path: polygon(10px 0, calc(100% - 10px) 0, 100% 10px, 100% calc(100% - 10px), calc(100% - 10px) 100%, 10px 100%, 0 calc(100% - 10px), 0 10px); font-size: clamp(10px, 1vw, 13px); line-height: 1.6; text-align: center; text-transform: uppercase; }
.mentor-card__front-specialty { margin: 0.55rem 0 0.6rem; color: #e3f2f5; font-family: sans-serif; font-size: 0.95rem; line-height: 1.4; text-align: center; }
.mentor-card__profile-link { position: relative; z-index: 3; display: inline-flex; min-height: 44px; align-items: center; justify-content: center; gap: 0.55rem; padding: 0.55rem 1.15rem; border: 2px solid #4ed4d4; color: #fff; background: #087f7f; clip-path: polygon(4px 0, calc(100% - 4px) 0, 100% 4px, 100% calc(100% - 4px), calc(100% - 4px) 100%, 4px 100%, 0 calc(100% - 4px), 0 4px); font-family: sans-serif; font-size: 0.95rem; font-weight: 700; pointer-events: auto; text-decoration: none; }
.mentor-card__profile-link:hover { background: #087f7f; }
.mentor-card__profile-link:focus-visible,
.mentor-card__return:focus-visible { outline: 3px solid #4ed4d4; outline-offset: 3px; }
.mentor-card__back-header { display: flex; flex: 0 0 auto; align-items: flex-start; justify-content: space-between; gap: 1rem; padding-bottom: 0.85rem; border-bottom: 1px solid rgb(78 212 212 / 38%); }
.mentor-card__back-title { display: grid; gap: 0.55rem; }
.mentor-card__back-title h3 { margin: 0; color: #f2f8fb; font-size: 0.78rem; line-height: 1.6; text-transform: uppercase; overflow-wrap: anywhere; }
.mentor-card__badge { width: fit-content; padding: 0.25rem 0.65rem; border: 1px solid #a9dce1; color: #f2f8fb; background: #0d5267; clip-path: polygon(6px 0, calc(100% - 6px) 0, 100% 6px, 100% calc(100% - 6px), calc(100% - 6px) 100%, 6px 100%, 0 calc(100% - 6px), 0 6px); font-family: sans-serif; font-size: 0.78rem; font-weight: 700; }
.mentor-card__return { display: grid; width: 2.5rem; height: 2.5rem; flex: 0 0 auto; place-items: center; border: 2px solid #4ed4d4; color: #f2f8fb; background: #087f7f; cursor: pointer; font-size: 1.4rem; }
.mentor-card__back-content { display: flex; min-height: 0; flex: 1; flex-direction: column; gap: 1rem; overflow-y: auto; padding: 0.9rem 0.1rem 0.25rem; font-family: sans-serif; }
.mentor-card__bio-block { padding: 0.1rem 0 0.75rem 0.85rem; border-left: 2px solid #21b9c0; border-bottom: 1px solid rgb(78 212 212 / 28%); }
.mentor-card__bio-block h4,
.mentor-card__skills h4 { margin: 0 0 0.4rem; color: #4ed4d4; font-family: 'Press Start 2P', cursive; font-size: 0.5rem; line-height: 1.7; text-transform: uppercase; }
.mentor-card__bio-block p { margin: 0; color: #e4edf3; font-size: 0.9rem; line-height: 1.45; white-space: pre-line; }
.mentor-card__facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 0; }
.mentor-card__facts > div { min-width: 0; padding: 0.5rem 0.65rem 0.55rem 0; }
.mentor-card__facts > div:nth-child(2n) { padding-left: 0.7rem; border-left: 1px solid rgb(78 212 212 / 38%); }
.mentor-card__facts > div:nth-child(n + 3) { border-top: 1px solid rgb(78 212 212 / 28%); }
.mentor-card__facts dt { margin-bottom: 0.25rem; color: #65d7dc; font-size: 0.68rem; line-height: 1.4; text-transform: uppercase; }
.mentor-card__facts dd { margin: 0; color: #f1f5f9; font-size: 0.84rem; line-height: 1.4; overflow-wrap: anywhere; }
.mentor-card__skills { padding-top: 0.8rem; border-top: 1px solid rgb(78 212 212 / 28%); }
.mentor-card__skill-list { display: flex; flex-wrap: wrap; gap: 0.45rem; }
.mentor-card__skill-list span { padding: 0.3rem 0.6rem; border: 1px solid #99cbd4; color: #edf7fa; background: #12364a; clip-path: polygon(5px 0, calc(100% - 5px) 0, 100% 5px, 100% calc(100% - 5px), calc(100% - 5px) 100%, 5px 100%, 0 calc(100% - 5px), 0 5px); font-size: 0.72rem; line-height: 1.4; }
.mentor-card__empty-details { margin: auto 0; color: #cfdee8; font-size: 0.9rem; line-height: 1.5; text-align: center; }
.mentor-empty-state { padding: 2rem 1rem; border: 1px solid rgb(8 127 127 / 35%); color: #17364a; background: rgb(255 255 255 / 45%); text-align: center; }
@media (max-width: 767px) {
    .mentor-grid { grid-template-columns: minmax(0, 1fr); gap: 1.25rem; }
    .mentor-card { width: min(100%, 360px); height: 500px; }
    .mentor-card__front { padding: 10px; }
    .mentor-card__back { padding: 1rem; }
    .mentor-card__front-specialty { font-size: 0.88rem; }
    .mentor-card__facts dd { font-size: 0.8rem; }
}
@media (prefers-reduced-motion: reduce) {
    .mentor-card__inner { transition-duration: 1ms; }
}
.hall-preview-shell {
    position: relative;
}

.hall-section__header {
    max-width: 58rem;
}

.hall-section__eyebrow {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: clamp(0.75rem, 2vw, 1.5rem);
    color: #087f7f;
    font-size: clamp(8px, 1vw, 11px);
    letter-spacing: 0.16em;
    text-transform: uppercase;
}

.hall-section__eyebrow::before,
.hall-section__eyebrow::after {
    width: clamp(2.5rem, 11vw, 8rem);
    height: 2px;
    background: #087f7f;
    content: '';
}

.hall-section__title {
    margin-top: 0.5rem;
    color: #122335;
    font-size: clamp(0.95rem, 2.25vw, 1.65rem);
    line-height: 1.65;
    text-wrap: balance;
}

.hall-stone-divider {
    height: clamp(4rem, 16vw, 6rem);
    margin: -0.75rem auto -1rem;
    overflow: hidden;
    pointer-events: none;
}

.hall-stone-divider img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 36%;
    image-rendering: pixelated;
}

.hall-section__description {
    max-width: 54rem;
    margin: 0.1rem auto 0;
    color: #17364a;
    font-size: clamp(0.9rem, 1.25vw, 1.15rem);
    line-height: 1.7;
}

.hall-preview-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 1.25rem;
}

.hall-showcase-card {
    display: flex;
    min-width: 0;
    flex-direction: column;
    overflow: hidden;
    position: relative;
    isolation: isolate;
    padding: 4px;
    background: transparent;
    filter: drop-shadow(0 4px 5px rgba(18, 51, 69, 0.18));
    transition: transform 180ms ease, filter 180ms ease;
}

.hall-showcase-card::before,
.hall-showcase-card::after {
    position: absolute;
    z-index: -1;
    pointer-events: none;
    content: '';
    clip-path: polygon(
        8px 0, calc(100% - 8px) 0,
        calc(100% - 8px) 4px, calc(100% - 4px) 4px,
        calc(100% - 4px) 8px, 100% 8px,
        100% calc(100% - 8px), calc(100% - 4px) calc(100% - 8px),
        calc(100% - 4px) calc(100% - 4px), calc(100% - 8px) calc(100% - 4px),
        calc(100% - 8px) 100%, 8px 100%,
        8px calc(100% - 4px), 4px calc(100% - 4px),
        4px calc(100% - 8px), 0 calc(100% - 8px),
        0 8px, 4px 8px, 4px 4px, 8px 4px
    );
}

.hall-showcase-card::before {
    inset: 0;
    background: #087f7f;
}

.hall-showcase-card::after {
    inset: 3px;
    background: #fffaf0;
}

.hall-showcase-card:hover {
    transform: translateY(-3px);
    filter: drop-shadow(0 8px 9px rgba(18, 51, 69, 0.22));
}

.hall-showcase-card__thumbnail {
    aspect-ratio: 2 / 1;
    overflow: hidden;
    border-bottom: 1px solid rgba(8, 127, 127, 0.25);
    background: #dceff4;
}

.hall-showcase-card__featured {
    position: absolute;
    z-index: 2;
    top: 0.65rem;
    left: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border: 1px solid #9a6c00;
    background: #ffcf3f;
    padding: 0.4rem 0.65rem;
    color: #122335;
    font-size: 8px;
    text-transform: uppercase;
}

.hall-showcase-card__featured i {
    font-size: 0.8rem;
}

.hall-showcase-card__body {
    display: flex;
    min-height: 122px;
    flex: 1;
    flex-direction: column;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.8rem 0.85rem 0.7rem;
    color: #122335;
}

.hall-showcase-card__title {
    overflow: hidden;
    color: #122335;
    font-size: clamp(10px, 1.2vw, 15px);
    line-height: 1.7;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hall-showcase-card__creator {
    overflow: hidden;
    margin-top: 0.35rem;
    color: #315b66;
    font-size: 0.8rem;
    line-height: 1.4;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hall-showcase-card__footer,
.hall-showcase-card__stats,
.hall-showcase-card__stats > span,
.hall-showcase-card__link {
    display: inline-flex;
    align-items: center;
}

.hall-showcase-card__footer {
    justify-content: space-between;
    gap: 0.75rem;
    border-top: 1px solid rgba(8, 127, 127, 0.2);
    padding-top: 0.75rem;
}

.hall-showcase-card__stats {
    gap: 0.85rem;
    color: #17364a;
    font-size: 0.9rem;
}

.hall-showcase-card__stats > span {
    gap: 0.35rem;
}

.hall-showcase-card__stats i {
    color: #cf4b63;
    font-size: 1rem;
}

.hall-showcase-card__stats > span:nth-child(2) i {
    color: #287da0;
}

.hall-showcase-card__link {
    min-height: 40px;
    justify-content: center;
    gap: 0.45rem;
    border: 2px solid #087f7f;
    border-radius: 0;
    padding: 0.5rem 0.65rem;
    color: #087f7f;
    background: #f4ffff;
    clip-path: polygon(4px 0, calc(100% - 4px) 0, 100% 4px, 100% calc(100% - 4px), calc(100% - 4px) 100%, 4px 100%, 0 calc(100% - 4px), 0 4px);
    box-shadow: 0 3px 0 #075e63;
    font-size: 9px;
    text-transform: uppercase;
    transition: color 160ms ease, background-color 160ms ease, transform 160ms ease;
}

.hall-showcase-card__link:hover {
    transform: translateY(-1px);
    background: #087f7f;
    color: white;
}

.hall-showcase-card__link:focus-visible,
.hall-main-cta:focus-visible {
    outline: 3px solid #122335;
    outline-offset: 3px;
}

.hall-main-cta {
    min-height: 48px;
    font-size: clamp(0.68rem, 1.25vw, 0.9rem);
}

.hall-empty-state {
    padding: 2rem 1rem;
    color: #17364a;
    font-size: 0.95rem;
    text-align: center;
}

@media (min-width: 768px) {
    .hall-preview-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .hall-section__title {
        white-space: nowrap;
    }

    .hall-stone-divider {
        height: clamp(6rem, 11vw, 9rem);
        margin: -2.5rem auto -2rem;
    }
}

@media (min-width: 1024px) {
    .hall-preview-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (prefers-reduced-motion: reduce) {
    .hall-showcase-card {
        transform: none;
        transition: none;
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
