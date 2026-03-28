<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CreationCard from '@/Components/Creations/CreationCard.vue';
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
});

const togglingId = ref(0);
const creationItems = ref(Array.isArray(props.creations) ? props.creations : []);

const relativeRoute = (name, params = {}) => route(name, params, false);

const userExp = computed(() => Number(props.user?.exp ?? 0));
const userGold = computed(() => Number(props.user?.gold ?? 0));
const userLvl = computed(() => Number(props.user?.lvl ?? 1));
const userExpProgress = computed(() => (userExp.value % 1000) / 10);
const creationCount = computed(() => Number(props.creationStats?.total_public ?? creationItems.value.length ?? 0));
const appreciationCount = computed(() => Number(props.creationStats?.total_appreciations_received ?? 0));
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
    router.visit(relativeRoute('hall.creations.show', { creation: creation.id }));
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
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`${user.username || user.name} | Profile`" />

        <div class="user-page-shell space-y-6 font-['Press_Start_2P'] text-[10px] leading-relaxed text-[#4ed4d4] md:space-y-8">
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
                        <span>LVL. {{ userLvl }}</span>
                        <span>EXP {{ userExp % 1000 }} / 1000</span>
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
    </AuthenticatedLayout>
</template>

<style scoped>
.profile-hero {
    @apply relative overflow-hidden border-4 border-cyan-500/50 bg-[#101726]/90 p-6;
    box-shadow: 10px 10px 0 rgba(0, 0, 0, 0.42);
}

.profile-hero__glow {
    @apply absolute inset-0;
    background:
        radial-gradient(circle at top right, rgba(34, 211, 238, 0.2), transparent 28%),
        linear-gradient(135deg, rgba(8, 145, 178, 0.18), rgba(15, 23, 42, 0.15) 42%, rgba(16, 185, 129, 0.08));
}

.avatar-shell {
    @apply h-24 w-24 overflow-hidden border-4 border-cyan-400 bg-slate-900 shadow-[0_0_18px_rgba(34,211,238,0.28)] sm:h-28 sm:w-28;
}

.pixel-link {
    @apply inline-flex items-center justify-center border-b-4 border-r-4 border-cyan-950 bg-cyan-400 px-3 py-2 text-[8px] uppercase text-black transition-colors hover:bg-cyan-300;
}

.pixel-link--alt {
    @apply border-amber-950 bg-amber-400 hover:bg-amber-300;
}

.stat-panel {
    @apply border-2 border-slate-700 bg-[#141b29]/90 p-4;
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
