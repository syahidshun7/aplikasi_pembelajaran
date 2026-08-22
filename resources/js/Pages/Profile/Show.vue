<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import PublicProfileLayout from '@/Layouts/PublicProfileLayout.vue';
import CreationCard from '@/Components/Creations/CreationCard.vue';
import ConfigSkinRenderer from '@/Components/ProfileSkins/ConfigSkinRenderer.vue';
import { toast } from '@/Utils/Alert';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    averageGrade: {
        type: Number,
        default: 0,
    },
    totalCompleted: {
        type: Number,
        default: 0,
    },
    classAverages: {
        type: Array,
        default: () => [],
    },
    creations: {
        type: Array,
        default: () => [],
    },
    creationStats: {
        type: Object,
        default: () => ({
            total_public: 0,
            total_appreciations_received: 0,
        }),
    },
    activeSkin: {
        type: Object,
        default: null,
    },
});

const skinVars = computed(() => {
    const s = props.activeSkin;
    if (!s) return {};
    return {
        '--skin-gradient': s.hero_gradient,
        '--skin-accent': s.accent_color,
        '--skin-border': s.border_color,
        '--skin-glow': s.glow_color,
        '--skin-stat-bg': s.stat_panel_bg,
        '--skin-text': s.text_primary,
    };
});
const skinTemplate = computed(() => String(props.activeSkin?.template_key || 'default'));
const skinRendererType = computed(() => {
    if (props.activeSkin?.renderer_type) {
        return String(props.activeSkin.renderer_type);
    }

    return props.activeSkin?.project_entry_path ? 'project_static' : 'vue_template';
});
const usesConfigSkinLayout = computed(() => skinRendererType.value === 'config');
const usesCustomSkinLayout = computed(() => skinRendererType.value === 'vue_template' && ['asset_showcase', 'arcade_cabinet', 'void_phantom'].includes(skinTemplate.value));
const assetUrl = (path) => path ? `/storage/${path}` : '';
const withCacheVersion = (url, version) => {
    if (!url || !version) return url;
    return `${url}${url.includes('?') ? '&' : '?'}v=${encodeURIComponent(version)}`;
};
const projectFrame = ref(null);
const projectPostTimers = ref([]);
const usesProjectSkinLayout = computed(() => skinRendererType.value === 'project_static' && Boolean(props.activeSkin?.project_entry_path));
const projectSkinUrl = computed(() => withCacheVersion(assetUrl(props.activeSkin?.project_entry_path), props.activeSkin?.updated_at || props.activeSkin?.id));
const profilePhotoUrl = computed(() => {
    if (props.user?.profile_photo) {
        return `/storage/${props.user.profile_photo}`;
    }

    return `https://api.dicebear.com/7.x/pixel-art/svg?seed=${props.user?.username || props.user?.name || 'guild-member'}`;
});
const activeSkinName = computed(() => props.activeSkin?.name || 'Default Profile');
const assetSkinStyle = computed(() => {
    const skin = props.activeSkin || {};
    const backgroundImage = assetUrl(skin.background_image_path);

    return {
        '--skin-gradient': skin.hero_gradient || 'linear-gradient(135deg, #070b11 0%, #131827 100%)',
        '--skin-accent': skin.accent_color || '#4ed4d4',
        '--skin-border': skin.border_color || '#3d415f',
        '--skin-glow': skin.glow_color || 'rgba(78,212,212,0.28)',
        '--skin-stat-bg': skin.stat_panel_bg || '#141b29',
        '--skin-text': skin.text_primary || '#4ed4d4',
        '--skin-bg-image': backgroundImage ? `url('${backgroundImage}')` : 'none',
        '--skin-panel-image': skin.panel_image_path ? `url('${assetUrl(skin.panel_image_path)}')` : 'none',
        '--skin-decoration-image': skin.decoration_image_path ? `url('${assetUrl(skin.decoration_image_path)}')` : 'none',
    };
});
const statCards = computed(() => [
    { label: 'Grade AVG', value: `${props.averageGrade || 0}%`, hint: 'Quest Score' },
    { label: 'Quest Clear', value: props.totalCompleted || 0, hint: 'Finished Logs' },
    { label: 'Creation', value: creationCount.value, hint: 'Public Works' },
    { label: 'Respect', value: appreciationCount.value, hint: 'Appreciations' },
]);
const profileStats = computed(() => ({
    averageGrade: props.averageGrade || 0,
    totalCompleted: props.totalCompleted || 0,
    creationCount: creationCount.value,
    appreciationCount: appreciationCount.value,
}));
const projectProfilePayload = computed(() => ({
    type: 'dooptech:profile-skin-data',
    user: props.user,
    activeSkin: props.activeSkin,
    stats: profileStats.value,
    classAverages: props.classAverages,
    creations: creationItems.value,
    urls: {
        profilePhoto: profilePhotoUrl.value,
        hallOfCreations: route('hall.creations.index'),
        lobby: route('lobby'),
    },
}));

const toProjectSkinMessagePayload = () => JSON.parse(JSON.stringify(projectProfilePayload.value));

const togglingId = ref(0);
const creationItems = ref(Array.isArray(props.creations) ? props.creations : []);

const relativeRoute = (name, params = {}) => route(name, params, false);

const userExp = computed(() => Number(props.user?.exp ?? 0));
const userGold = computed(() => Number(props.user?.gold ?? 0));
const userLvl = computed(() => Number(props.user?.lvl ?? 1));
const levelProgress = computed(() => props.user?.level_progress || {});
const userLevelTitle = computed(() => levelProgress.value?.title || 'Novice');
const userExpProgress = computed(() => Number(levelProgress.value?.progress_percent ?? 0));
const userExpInLevel = computed(() => Number(levelProgress.value?.exp_in_level ?? 0));
const userExpNeeded = computed(() => Number(levelProgress.value?.exp_needed ?? 0));
const userIsMaxLevel = computed(() => Boolean(levelProgress.value?.is_max_level));
const creationCount = computed(() => Number(props.creationStats?.total_public ?? creationItems.value.length ?? 0));
const appreciationCount = computed(() => Number(props.creationStats?.total_appreciations_received ?? 0));
const classAverageItems = computed(() => Array.isArray(props.classAverages) ? props.classAverages : []);
const userSkills = computed(() => {
    if (Array.isArray(props.user?.skills)) {
        return props.user.skills.filter(Boolean);
    }

    const rawSkills = String(props.user?.skills || '')
        .split(',')
        .map((skill) => skill.trim())
        .filter(Boolean);

    return rawSkills;
});

const roleLabel = computed(() => String(props.user?.role || 'Adventurer').replaceAll('_', ' ').toUpperCase());

const replaceCreationCard = (creationId, nextCreation) => {
    const targetId = Number(creationId || 0);

    if (!targetId || !nextCreation) {
        return;
    }

    creationItems.value = creationItems.value.map((item) => {
        if (Number(item?.id || 0) !== targetId) {
            return item;
        }

        return {
            ...item,
            ...nextCreation,
        };
    });
};

const refreshCreationCard = async (creationId) => {
    const response = await window.axios.get(relativeRoute('api.hall.show', { creation: creationId }));
    const nextCreation = response.data?.data || null;

    if (nextCreation) {
        replaceCreationCard(creationId, nextCreation);
    }
};

const getAppreciationErrorMessage = (error) => {
    const status = Number(error?.response?.status || 0);
    const serverMessage = String(error?.response?.data?.message || '').trim();

    if (status === 401) {
        return 'Session expired. Please log in again.';
    }

    if (status === 419) {
        return 'Session mismatch detected. Reload the page and try again.';
    }

    if (serverMessage !== '') {
        return serverMessage;
    }

    return 'Unable to update appreciation.';
};

const openDetail = (creation) => {
    router.visit(relativeRoute('hall.creations.show', { creation: creation.slug || creation.id }));
};

const openInsight = (creation) => {
    openDetail(creation);
};

const toggleAppreciation = async (creation) => {
    if (!creation?.id || togglingId.value === Number(creation.id)) {
        return;
    }

    togglingId.value = Number(creation.id);

    try {
        if (creation.is_appreciated) {
            const response = await window.axios.delete(relativeRoute('api.creations.appreciate.destroy', { creation: creation.id }));
            creation.is_appreciated = false;
            creation.appreciations_count = Number(response.data?.appreciations_count || creation.appreciations_count || 0);
        } else {
            const response = await window.axios.post(relativeRoute('api.creations.appreciate.store', { creation: creation.id }));
            creation.is_appreciated = true;
            creation.appreciations_count = Number(response.data?.appreciations_count || creation.appreciations_count || 0);
        }

        await refreshCreationCard(creation.id);
    } catch (error) {
        console.error('profile creation appreciation failed', {
            status: error?.response?.status,
            message: error?.response?.data?.message,
            url: error?.config?.url,
            method: error?.config?.method,
        });
        toast.error('ACTION_FAILED', getAppreciationErrorMessage(error));
    } finally {
        togglingId.value = 0;
    }
};

const postProjectSkinData = () => {
    const target = projectFrame.value?.contentWindow;
    if (!target) {
        return;
    }

    target.postMessage(toProjectSkinMessagePayload(), '*');
};

const clearProjectSkinPostTimers = () => {
    projectPostTimers.value.forEach((timer) => window.clearTimeout(timer));
    projectPostTimers.value = [];
};

const queueProjectSkinData = () => {
    clearProjectSkinPostTimers();

    [0, 120, 350, 750, 1500].forEach((delay) => {
        const timer = window.setTimeout(postProjectSkinData, delay);
        projectPostTimers.value.push(timer);
    });
};

const handleProjectSkinMessage = (event) => {
    const payload = event.data || {};
    if (payload.type !== 'dooptech:profile-skin-ready') {
        return;
    }

    postProjectSkinData();
};

watch(projectProfilePayload, () => {
    if (usesProjectSkinLayout.value) {
        queueProjectSkinData();
    }
});

onMounted(() => {
    window.addEventListener('message', handleProjectSkinMessage);
});

onBeforeUnmount(() => {
    clearProjectSkinPostTimers();
    window.removeEventListener('message', handleProjectSkinMessage);
});
</script>

<template>
    <PublicProfileLayout :full-bleed="usesProjectSkinLayout" :hide-footer="usesProjectSkinLayout">
        <Head :title="`${user.username || user.name} | Profile`" />

        <div v-if="usesProjectSkinLayout" class="project-skin-shell">
            <iframe
                ref="projectFrame"
                :src="projectSkinUrl"
                :title="`${activeSkinName} profile skin`"
                class="project-skin-frame"
                sandbox="allow-scripts allow-popups allow-forms allow-top-navigation-by-user-activation"
                @load="queueProjectSkinData"
            />
        </div>

        <ConfigSkinRenderer
            v-else-if="usesConfigSkinLayout"
            :user="user"
            :active-skin="activeSkin"
            :stats="profileStats"
            :class-averages="classAverages"
            :creations="creationItems"
            :profile-photo-url="profilePhotoUrl"
            :hall-of-creations-url="route('hall.creations.index')"
        />

        <div
            v-else-if="usesCustomSkinLayout"
            class="profile-skin-stage font-['Press_Start_2P'] text-[10px]"
            :class="`profile-skin-stage--${skinTemplate}`"
            :style="assetSkinStyle"
        >
            <section class="skin-hero-shell">
                <div class="skin-bg" />
                <div class="skin-decoration" />

                <div class="skin-hero-grid">
                    <div class="skin-avatar-zone">
                        <div class="skin-avatar-frame">
                            <img
                                :src="profilePhotoUrl"
                                :alt="user.username || user.name"
                                class="skin-avatar-image"
                            >
                            <img
                                v-if="activeSkin?.avatar_frame_image_path"
                                :src="assetUrl(activeSkin.avatar_frame_image_path)"
                                :alt="`${activeSkinName} avatar frame`"
                                class="skin-avatar-frame-asset"
                            >
                        </div>
                        <p class="skin-equipped-label">{{ activeSkinName }}</p>
                    </div>

                    <div class="skin-identity-zone">
                        <p class="skin-kicker">{{ skinTemplate === 'arcade_cabinet' ? 'PLAYER SELECT' : 'PUBLIC HERO CARD' }}</p>
                        <h1 class="skin-title">{{ user.username || user.name }}</h1>
                        <div class="skin-tags">
                            <span>{{ roleLabel }}</span>
                            <span v-if="user.location">{{ user.location }}</span>
                            <span v-if="user.experience">{{ user.experience }}</span>
                        </div>
                        <p class="skin-bio">
                            {{ user.bio || 'Profil publik adventurer ini sedang memakai cosmetic skin khusus dari shop.' }}
                        </p>

                        <div class="skin-progress">
                            <div class="skin-progress__bar" :style="{ width: `${userExpProgress}%` }" />
                        </div>
                        <div class="skin-progress-caption">
                            <span>LVL {{ userLvl }} / {{ userLevelTitle }}</span>
                            <span v-if="!userIsMaxLevel">EXP {{ userExpInLevel }} / {{ userExpNeeded }}</span>
                            <span v-else>MAX LEVEL</span>
                            <span>{{ userGold }} G</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="skin-stat-grid">
                <article
                    v-for="stat in statCards"
                    :key="stat.label"
                    class="skin-stat-card"
                >
                    <p>{{ stat.label }}</p>
                    <strong>{{ stat.value }}</strong>
                    <span>{{ stat.hint }}</span>
                </article>
            </section>

            <section class="skin-content-grid">
                <div class="skin-main-panel">
                    <div class="skin-section-heading">
                        <div>
                            <p>{{ skinTemplate === 'arcade_cabinet' ? 'INSERT COIN TO VIEW' : 'SHOWCASE FEED' }}</p>
                            <h2>Creator Artifacts</h2>
                        </div>
                        <Link :href="route('hall.creations.index')" class="skin-action-link">Hall</Link>
                    </div>

                    <div v-if="creationItems.length > 0" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <CreationCard
                            v-for="creation in creationItems"
                            :key="creation.id"
                            :creation="creation"
                            :busy="togglingId === Number(creation.id)"
                            @open="openDetail"
                            @appreciate="toggleAppreciation"
                            @insight="openInsight"
                        />
                    </div>
                    <div v-else class="skin-empty">
                        <p>No public creations yet</p>
                        <span>Profil ini belum menampilkan creation publik di Hall of Creations.</span>
                    </div>
                </div>

                <aside class="skin-side-panel">
                    <div class="skin-section-heading skin-section-heading--compact">
                        <div>
                            <p>JOB MODULE</p>
                            <h2>{{ user.job_name || 'Unassigned Job' }}</h2>
                        </div>
                    </div>
                    <div class="skin-job-asset">
                        <img
                            v-if="user.job_emblem_path"
                            :src="`/storage/${user.job_emblem_path}`"
                            :alt="`${user.job_name} emblem`"
                        >
                        <img v-else src="/images/logo.png" alt="default job">
                    </div>

                    <div class="skin-note-list">
                        <div v-if="user.name">
                            <span>Display</span>
                            <p>{{ user.name }}</p>
                        </div>
                        <div v-if="userSkills.length > 0">
                            <span>Skills</span>
                            <p>{{ userSkills.join(' / ') }}</p>
                        </div>
                        <div v-if="classAverageItems.length > 0">
                            <span>Class Data</span>
                            <p>{{ classAverageItems.length }} grade channel</p>
                        </div>
                        <div>
                            <span>Skin Template</span>
                            <p>{{ skinTemplate }}</p>
                        </div>
                    </div>
                </aside>
            </section>
        </div>

        <div v-else class="user-page-shell space-y-6 font-['Press_Start_2P'] text-[10px] leading-relaxed text-[#4ed4d4] md:space-y-8" :style="skinVars">
            <section class="profile-hero">
                <div class="profile-hero__glow" />

                <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="avatar-shell">
                            <img
                                v-if="user.profile_photo"
                                :src="`/storage/${user.profile_photo}`"
                                :alt="user.username || user.name"
                                class="h-full w-full object-cover"
                            >
                            <img
                                v-else
                                :src="`https://api.dicebear.com/7.x/pixel-art/svg?seed=${user.username || user.name || 'guild-member'}`"
                                :alt="user.username || user.name"
                                class="h-full w-full object-cover"
                            >
                        </div>

                        <div class="space-y-3">
                            <div class="space-y-1">
                                <p class="text-[7px] uppercase tracking-[0.3em] text-cyan-300/80">Visited Adventurer</p>
                                <h1 class="break-words text-base uppercase italic tracking-tight text-white sm:text-xl">
                                    {{ user.username || user.name }}
                                </h1>
                                <div class="flex flex-wrap items-center gap-2 text-[7px] uppercase text-slate-400">
                                    <span class="rounded border border-cyan-500/50 px-2 py-1 text-cyan-200">{{ roleLabel }}</span>
                                    <span v-if="user.location">{{ user.location }}</span>
                                    <span v-if="user.experience">{{ user.experience }}</span>
                                </div>
                            </div>

                            <p class="max-w-3xl text-[8px] uppercase leading-relaxed text-slate-300">
                                {{ user.bio || 'Adventurer profile connected to the Hall of Creations. Explore their best public artifacts and current job path.' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                        <Link :href="route('lobby')" class="pixel-link">
                            Leaderboard
                        </Link>
                        <Link :href="route('hall.creations.index')" class="pixel-link pixel-link--alt">
                            Hall of Creations
                        </Link>
                    </div>
                </div>

                <div class="relative z-10 mt-5 space-y-2">
                    <div class="h-4 overflow-hidden border-2 border-slate-700 bg-black/60 p-[2px]">
                        <div
                            class="h-full bg-cyan-500 shadow-[0_0_12px_rgba(34,211,238,0.7)] transition-all duration-700"
                            :style="{ width: `${userExpProgress}%` }"
                        />
                    </div>

                    <div class="flex flex-wrap justify-between gap-2 text-[7px] uppercase text-slate-400">
                        <span>LVL. {{ userLvl }} — {{ userLevelTitle }}</span>
                        <span v-if="!userIsMaxLevel">EXP {{ userExpInLevel }} / {{ userExpNeeded }}</span>
                        <span v-else>MAX LEVEL</span>
                        <span>{{ userGold }} G</span>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="stat-panel">
                    <p class="stat-label">Overall Grade AVG</p>
                    <div class="flex items-end justify-between gap-3">
                        <span class="stat-value text-emerald-300">{{ averageGrade || 0 }}%</span>
                        <span class="stat-hint">QUEST SCORE</span>
                    </div>
                </article>

                <article class="stat-panel">
                    <p class="stat-label">Quests Completed</p>
                    <div class="flex items-end justify-between gap-3">
                        <span class="stat-value text-cyan-300">{{ totalCompleted || 0 }}</span>
                        <span class="stat-hint">FINISHED LOGS</span>
                    </div>
                </article>

                <article class="stat-panel">
                    <p class="stat-label">Public Creations</p>
                    <div class="flex items-end justify-between gap-3">
                        <span class="stat-value text-amber-300">{{ creationCount }}</span>
                        <span class="stat-hint">VISIBLE WORKS</span>
                    </div>
                </article>

                <article class="stat-panel">
                    <p class="stat-label">Appreciations</p>
                    <div class="flex items-end justify-between gap-3">
                        <span class="stat-value text-rose-300">{{ appreciationCount }}</span>
                        <span class="stat-hint">FROM HALL</span>
                    </div>
                </article>
            </section>

            <section class="rpg-panel border-cyan-500/30 bg-[#0f172a]/70">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-cyan-500/30 pb-3">
                    <h2 class="text-[8px] uppercase tracking-[0.2em] text-cyan-300">Average Grade Per Class</h2>
                    <span class="text-[6px] uppercase text-slate-500">Per-class breakdown</span>
                </div>

                <div v-if="classAverageItems.length > 0" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="classItem in classAverageItems"
                        :key="`${classItem.study_group_id ?? 'general'}-${classItem.class_name}`"
                        class="border border-slate-700 bg-black/30 p-3"
                    >
                        <p class="text-[7px] uppercase text-white">{{ classItem.class_name }}</p>
                        <div class="mt-2 flex items-end justify-between gap-2">
                            <span class="text-[11px] font-bold text-emerald-300">{{ classItem.average_grade ?? 0 }}%</span>
                            <span class="text-[6px] uppercase text-slate-500">{{ classItem.total_quests ?? 0 }} Quest</span>
                        </div>
                        <p class="mt-2 text-[6px] uppercase text-slate-500">
                            Completed {{ classItem.completed_quests ?? 0 }} / {{ classItem.total_quests ?? 0 }}
                        </p>
                    </article>
                </div>

                <p v-else class="text-[7px] uppercase text-slate-500">
                    Belum ada data quest per kelas.
                </p>
            </section>

            <section class="grid grid-cols-12 gap-6">
                <div class="col-span-12 xl:col-span-9">
                    <div class="rpg-panel h-full">
                        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-slate-700 pb-4">
                            <div>
                                <p class="text-[7px] uppercase tracking-[0.28em] text-cyan-300/80">Best Visit Scenario</p>
                                <h2 class="text-[11px] uppercase tracking-[0.18em] text-white">Creator Showcase</h2>
                            </div>
                            <span class="text-[7px] uppercase text-slate-500">Public artifacts from this profile</span>
                        </div>

                        <div v-if="creationItems.length > 0" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <CreationCard
                                v-for="creation in creationItems"
                                :key="creation.id"
                                :creation="creation"
                                :busy="togglingId === Number(creation.id)"
                                @open="openDetail"
                                @appreciate="toggleAppreciation"
                                @insight="openInsight"
                            />
                        </div>

                        <div v-else class="empty-panel">
                            <p class="text-[11px] uppercase text-slate-500">No public creations yet</p>
                            <p class="mt-2 max-w-xl text-[8px] uppercase leading-relaxed text-slate-600">
                                Profil ini belum menampilkan creation publik di Hall of Creations.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 xl:col-span-3 space-y-6">
                    <aside class="rpg-panel border-indigo-500/40 bg-indigo-950/20">
                        <h3 class="mb-4 border-b border-indigo-800 pb-3 text-[8px] uppercase tracking-[0.22em] text-indigo-300">
                            Jobs Status
                        </h3>

                        <div class="overflow-hidden border border-indigo-500/40 bg-black/35">
                            <div class="flex h-[160px] items-center justify-center border-b border-indigo-500/30 bg-[#0d1117]">
                                <img
                                    v-if="user.job_emblem_path"
                                    :src="`/storage/${user.job_emblem_path}`"
                                    :alt="`${user.job_name} emblem`"
                                    class="h-full w-full object-cover"
                                >
                                <img
                                    v-else
                                    src="/images/logo.png"
                                    alt="default job"
                                    class="h-16 w-16 object-contain opacity-80"
                                >
                            </div>

                            <div class="space-y-2 p-3">
                                <p class="text-[7px] uppercase text-slate-500">Current Path</p>
                                <p class="text-[8px] uppercase leading-relaxed text-white">
                                    {{ user.job_name || 'Unassigned Job' }}
                                </p>
                            </div>
                        </div>
                    </aside>

                    <aside class="rpg-panel border-emerald-500/30 bg-emerald-950/10">
                        <h3 class="mb-4 border-b border-emerald-800/70 pb-3 text-[8px] uppercase tracking-[0.22em] text-emerald-300">
                            Profile Notes
                        </h3>

                        <div class="space-y-3 text-[7px] uppercase text-slate-400">
                            <div>
                                <p class="text-slate-500">Display Name</p>
                                <p class="mt-1 break-words text-white">{{ user.name || user.username }}</p>
                            </div>

                            <div v-if="user.location">
                                <p class="text-slate-500">Location</p>
                                <p class="mt-1 break-words text-white">{{ user.location }}</p>
                            </div>

                            <div v-if="user.experience">
                                <p class="text-slate-500">Experience</p>
                                <p class="mt-1 break-words text-white">{{ user.experience }}</p>
                            </div>

                            <div v-if="userSkills.length > 0">
                                <p class="text-slate-500">Skills</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <span
                                        v-for="skill in userSkills"
                                        :key="skill"
                                        class="rounded border border-emerald-500/40 bg-emerald-500/10 px-2 py-1 text-[7px] text-emerald-200"
                                    >
                                        {{ skill }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="user.bio">
                                <p class="text-slate-500">Bio</p>
                                <p class="mt-1 break-words text-[7px] leading-relaxed text-slate-300">{{ user.bio }}</p>
                            </div>

                            <p v-if="!user.bio && !user.location && !user.experience && userSkills.length === 0" class="leading-relaxed text-slate-500">
                                Belum ada skill publik yang dibagikan di profil ini.
                            </p>
                        </div>
                    </aside>
                </div>
            </section>
        </div>
    </PublicProfileLayout>
</template>

<style scoped>
.project-skin-shell {
    flex: 1 1 auto;
    min-height: 0;
    width: 100%;
    overflow: hidden;
    background: #05070b;
}

.project-skin-frame {
    display: block;
    height: 100%;
    min-height: 0;
    width: 100%;
    border: 0;
    background: #05070b;
}

.profile-skin-stage {
    position: relative;
    isolation: isolate;
    min-height: calc(100vh - 180px);
    color: var(--skin-text, #4ed4d4);
}

.skin-hero-shell,
.skin-main-panel,
.skin-side-panel,
.skin-stat-card {
    position: relative;
    overflow: hidden;
    border: 2px solid var(--skin-border, #3d415f);
    background-color: color-mix(in srgb, var(--skin-stat-bg, #141b29) 88%, black);
}

.skin-hero-shell {
    min-height: 430px;
    padding: clamp(1rem, 4vw, 3rem);
}

.skin-bg {
    position: absolute;
    inset: 0;
    background-image: linear-gradient(90deg, rgba(0, 0, 0, 0.74), rgba(0, 0, 0, 0.22)), var(--skin-bg-image), var(--skin-gradient);
    background-position: center;
    background-size: cover;
}

.skin-decoration {
    position: absolute;
    inset: 0;
    background-image: var(--skin-decoration-image);
    background-position: right bottom;
    background-repeat: no-repeat;
    background-size: min(42vw, 420px);
    opacity: 0.9;
    pointer-events: none;
}

.skin-hero-grid {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: minmax(180px, 280px) minmax(0, 1fr);
    gap: clamp(1rem, 4vw, 3rem);
    align-items: end;
    min-height: 350px;
}

.skin-avatar-zone {
    display: grid;
    gap: 0.9rem;
    justify-items: center;
}

.skin-avatar-frame {
    position: relative;
    width: min(56vw, 230px);
    aspect-ratio: 1;
    border: 3px solid var(--skin-accent, #4ed4d4);
    background: #05070b;
    box-shadow: 0 0 28px var(--skin-glow, rgba(78, 212, 212, 0.35));
}

.skin-avatar-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.skin-avatar-frame-asset {
    position: absolute;
    inset: -14%;
    width: 128%;
    height: 128%;
    object-fit: contain;
    pointer-events: none;
}

.skin-equipped-label,
.skin-kicker,
.skin-progress-caption,
.skin-stat-card p,
.skin-stat-card span,
.skin-section-heading p,
.skin-note-list span {
    text-transform: uppercase;
}

.skin-equipped-label {
    max-width: 100%;
    border: 1px solid var(--skin-accent, #4ed4d4);
    background: rgba(0, 0, 0, 0.66);
    padding: 0.6rem 0.8rem;
    text-align: center;
    font-size: 7px;
    overflow-wrap: anywhere;
}

.skin-identity-zone {
    display: grid;
    gap: 1rem;
    max-width: 820px;
}

.skin-kicker {
    color: var(--skin-accent, #4ed4d4);
    font-size: 8px;
    letter-spacing: 0.24em;
}

.skin-title {
    color: #fff;
    font-size: clamp(1.25rem, 5vw, 3.7rem);
    line-height: 1.08;
    overflow-wrap: anywhere;
}

.skin-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    font-size: 7px;
    text-transform: uppercase;
    color: #dbeafe;
}

.skin-tags span {
    border: 1px solid color-mix(in srgb, var(--skin-accent, #4ed4d4) 70%, white);
    background: rgba(0, 0, 0, 0.55);
    padding: 0.45rem 0.65rem;
}

.skin-bio {
    max-width: 72ch;
    color: #d1d5db;
    font-size: 8px;
    line-height: 1.9;
    text-transform: uppercase;
}

.skin-progress {
    height: 18px;
    border: 2px solid var(--skin-border, #3d415f);
    background: rgba(0, 0, 0, 0.7);
    padding: 3px;
}

.skin-progress__bar {
    height: 100%;
    background: var(--skin-accent, #4ed4d4);
    box-shadow: 0 0 18px var(--skin-glow, rgba(78, 212, 212, 0.4));
    transition: width 0.7s ease;
}

.skin-progress-caption {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 0.7rem;
    color: #94a3b8;
    font-size: 7px;
}

.skin-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.9rem;
    margin-top: 1rem;
}

.skin-stat-card {
    min-height: 120px;
    padding: 1rem;
    background-image: linear-gradient(135deg, rgba(255,255,255,0.04), transparent), var(--skin-panel-image);
    background-position: center;
    background-size: cover;
}

.skin-stat-card p {
    color: #94a3b8;
    font-size: 7px;
}

.skin-stat-card strong {
    display: block;
    margin-top: 1rem;
    color: #fff;
    font-size: clamp(1rem, 3vw, 1.8rem);
    line-height: 1;
}

.skin-stat-card span {
    display: block;
    margin-top: 0.8rem;
    color: var(--skin-accent, #4ed4d4);
    font-size: 7px;
}

.skin-content-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(280px, 360px);
    gap: 1rem;
    margin-top: 1rem;
}

.skin-main-panel,
.skin-side-panel {
    padding: 1rem;
    background-image: linear-gradient(180deg, rgba(0,0,0,0.12), rgba(0,0,0,0.42)), var(--skin-panel-image);
    background-position: center;
    background-size: cover;
}

.skin-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid color-mix(in srgb, var(--skin-border, #3d415f) 75%, transparent);
    padding-bottom: 1rem;
}

.skin-section-heading p {
    color: var(--skin-accent, #4ed4d4);
    font-size: 7px;
    letter-spacing: 0.22em;
}

.skin-section-heading h2 {
    margin-top: 0.45rem;
    color: #fff;
    font-size: 11px;
    text-transform: uppercase;
    overflow-wrap: anywhere;
}

.skin-action-link {
    border: 1px solid var(--skin-accent, #4ed4d4);
    padding: 0.6rem 0.8rem;
    color: var(--skin-accent, #4ed4d4);
    text-transform: uppercase;
}

.skin-empty {
    display: grid;
    min-height: 260px;
    place-content: center;
    border: 1px dashed var(--skin-border, #3d415f);
    text-align: center;
    color: #94a3b8;
}

.skin-empty p {
    color: #fff;
    text-transform: uppercase;
}

.skin-empty span {
    margin-top: 0.8rem;
    max-width: 420px;
    font-size: 8px;
    line-height: 1.8;
    text-transform: uppercase;
}

.skin-job-asset {
    display: grid;
    min-height: 190px;
    place-items: center;
    border: 1px solid var(--skin-border, #3d415f);
    background: rgba(0, 0, 0, 0.44);
}

.skin-job-asset img {
    max-height: 180px;
    width: 100%;
    object-fit: contain;
}

.skin-note-list {
    display: grid;
    gap: 0.8rem;
    margin-top: 1rem;
}

.skin-note-list div {
    border: 1px solid color-mix(in srgb, var(--skin-border, #3d415f) 65%, transparent);
    background: rgba(0, 0, 0, 0.35);
    padding: 0.75rem;
}

.skin-note-list span {
    display: block;
    color: #94a3b8;
    font-size: 7px;
}

.skin-note-list p {
    margin-top: 0.5rem;
    color: #fff;
    font-size: 8px;
    line-height: 1.6;
    overflow-wrap: anywhere;
    text-transform: uppercase;
}

.profile-skin-stage--arcade_cabinet {
    background: #050505;
}

.profile-skin-stage--arcade_cabinet .skin-hero-shell {
    border-width: 8px;
    border-color: #111827;
    border-radius: 18px 18px 42px 42px;
    box-shadow: inset 0 0 0 8px #0f172a, 0 18px 0 #020617;
}

.profile-skin-stage--arcade_cabinet .skin-hero-grid {
    align-items: center;
}

.profile-skin-stage--arcade_cabinet .skin-avatar-frame,
.profile-skin-stage--arcade_cabinet .skin-stat-card,
.profile-skin-stage--arcade_cabinet .skin-main-panel,
.profile-skin-stage--arcade_cabinet .skin-side-panel {
    border-radius: 0;
    box-shadow: 6px 6px 0 rgba(0, 0, 0, 0.75);
}

.profile-skin-stage--void_phantom .skin-hero-shell {
    clip-path: polygon(0 0, 100% 0, 100% 88%, 96% 100%, 0 100%);
}

.profile-skin-stage--void_phantom .skin-avatar-frame {
    transform: rotate(-2deg);
}

.profile-skin-stage--asset_showcase .skin-hero-shell {
    min-height: 520px;
    border: 0;
}

.profile-skin-stage--asset_showcase .skin-hero-grid {
    align-items: center;
}

@media (max-width: 900px) {
    .skin-hero-grid,
    .skin-content-grid {
        grid-template-columns: 1fr;
    }

    .skin-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 560px) {
    .skin-stat-grid {
        grid-template-columns: 1fr;
    }
}

.profile-hero {
    @apply relative overflow-hidden border-4 p-6;
    border-color: var(--skin-border, rgba(6,182,212,0.5));
    background: var(--skin-gradient, linear-gradient(135deg, #101726 0%, #0f172a 100%));
    box-shadow: 10px 10px 0 rgba(0, 0, 0, 0.42);
}

.profile-hero__glow {
    @apply absolute inset-0;
    background: radial-gradient(circle at top right, var(--skin-glow, rgba(34,211,238,0.2)), transparent 28%);
}

.avatar-shell {
    @apply h-24 w-24 overflow-hidden border-4 bg-slate-900 sm:h-28 sm:w-28;
    border-color: var(--skin-accent, #4ed4d4);
    box-shadow: 0 0 18px var(--skin-glow, rgba(34,211,238,0.28));
}

.pixel-link {
    @apply inline-flex items-center justify-center border-b-4 border-r-4 border-cyan-950 bg-cyan-400 px-3 py-2 text-[8px] uppercase text-black transition-colors hover:bg-cyan-300;
}

.pixel-link--alt {
    @apply border-amber-950 bg-amber-400 hover:bg-amber-300;
}

.stat-panel {
    @apply border-2 p-4;
    border-color: var(--skin-border, #334155);
    background: var(--skin-stat-bg, rgba(20,27,41,0.9));
    box-shadow: 6px 6px 0 rgba(0, 0, 0, 0.35);
}

.stat-label {
    @apply mb-3 text-[7px] uppercase tracking-[0.22em] text-slate-500;
}

.stat-value {
    @apply text-xl font-bold leading-none;
}

.stat-hint {
    @apply text-[7px] uppercase text-slate-600;
}

.rpg-panel {
    @apply relative border-4 border-slate-700 bg-[#141b29]/90 p-5;
    box-shadow: 8px 8px 0 rgba(0, 0, 0, 0.45);
}

.empty-panel {
    @apply flex min-h-[340px] flex-col items-center justify-center border border-dashed border-slate-700 bg-black/20 px-6 py-10 text-center;
}
</style>
