<script setup>
import { Head, usePage, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';

const props = defineProps({
    user: Object,
    userQuests: [Array, Object],
    averageGrade: Number,
    totalCompleted: Number,
    classAverages: {
        type: Array,
        default: () => [],
    },
    profileView: {
        type: String,
        default: 'settings',
    },
    jobs: Array,
    mustVerifyEmail: Boolean,
    status: String,
});

const page = usePage();
const USER_THEME_STORAGE_KEY = 'dooptech-user-theme';
const USER_THEME_EVENT = 'dooptech:user-theme-change';
const userData = computed(() => props.user || page.props.auth.user);
const isDashboardView = computed(() => props.profileView === 'dashboard');
const userExp = computed(() => Number(userData.value?.exp ?? 0));
const userGold = computed(() => Number(userData.value?.gold ?? 0));
const userLvl = computed(() => Number(userData.value?.lvl ?? 1));
const userExpProgress = computed(() => (userExp.value % 1000) / 10);
const userSkills = computed(() => {
    if (Array.isArray(userData.value?.skills)) {
        return userData.value.skills.filter(Boolean);
    }

    return String(userData.value?.skills || '')
        .split(',')
        .map((skill) => skill.trim())
        .filter(Boolean);
});
const questItems = computed(() => Array.isArray(props.userQuests) ? props.userQuests : (props.userQuests?.data || []));
const questPaginationLinks = computed(() => Array.isArray(props.userQuests) ? [] : (props.userQuests?.links || []));
const classAverageItems = computed(() => Array.isArray(props.classAverages) ? props.classAverages : []);
const isAverageGradeOpen = ref(false);
const averageGradePercent = computed(() => {
    const rawValue = Number(props.averageGrade ?? 0);
    if (!Number.isFinite(rawValue)) {
        return 0;
    }

    return Math.max(0, Math.min(100, rawValue));
});
const classAverageRows = computed(() => {
    return classAverageItems.value.map((classItem, index) => {
        const className = String(classItem?.class_name || `CLASS_${index + 1}`);
        const average = Number(classItem?.average_grade ?? 0);
        const totalQuests = Number(classItem?.total_quests ?? 0);
        const completedQuests = Number(classItem?.completed_quests ?? 0);

        return {
            key: `${classItem?.study_group_id ?? 'general'}-${className}-${index}`,
            class_name: className,
            average_grade: Number.isFinite(average) ? average : 0,
            total_quests: Number.isFinite(totalQuests) ? totalQuests : 0,
            completed_quests: Number.isFinite(completedQuests) ? completedQuests : 0,
        };
    });
});

const allowedTabs = ['profile', 'password', 'danger'];

const resolveActiveTabFromLocation = () => {
    const hash = typeof window !== 'undefined' ? window.location.hash : '';
    if (hash === '#email-verification') {
        return 'profile';
    }

    let queryTab = null;

    if (typeof window !== 'undefined') {
        queryTab = new URLSearchParams(window.location.search).get('tab');
    }

    if (!queryTab) {
        const rawQuery = (page.url.split('?')[1] ?? '').split('#')[0];
        queryTab = new URLSearchParams(rawQuery).get('tab');
    }

    return allowedTabs.includes(queryTab) ? queryTab : 'profile';
};

const activeTab = ref(resolveActiveTabFromLocation());
const themeMode = ref('dark');

const getGradeColor = (grade) => {
    if (grade >= 90) return 'text-yellow-400';
    if (grade >= 75) return 'text-green-400';
    if (grade >= 60) return 'text-blue-400';
    return 'text-red-500';
};

onMounted(() => {
    activeTab.value = resolveActiveTabFromLocation();
});

watch(
    () => page.url,
    () => {
        activeTab.value = resolveActiveTabFromLocation();
    },
);

watch(classAverageRows, (rows) => {
    if (rows.length === 0) {
        isAverageGradeOpen.value = false;
    }
}, { immediate: true });

const toggleAverageGradeDropdown = () => {
    isAverageGradeOpen.value = !isAverageGradeOpen.value;
};

const normalizeTheme = (value) => (String(value || '').toLowerCase() === 'light' ? 'light' : 'dark');

const setThemeMode = (nextTheme, options = {}) => {
    const { persist = true, broadcast = true } = options;
    const normalizedTheme = normalizeTheme(nextTheme);
    themeMode.value = normalizedTheme;

    if (typeof window === 'undefined') {
        return;
    }

    if (persist) {
        window.localStorage.setItem(USER_THEME_STORAGE_KEY, normalizedTheme);
    }

    if (broadcast) {
        window.dispatchEvent(new CustomEvent(USER_THEME_EVENT, { detail: { theme: normalizedTheme } }));
    }
};

const applyDarkTheme = () => {
    setThemeMode('dark');
};

const applyLightTheme = () => {
    setThemeMode('light');
};

const syncThemeFromStorage = (event) => {
    if (event.key !== USER_THEME_STORAGE_KEY) {
        return;
    }

    setThemeMode(event.newValue, { persist: false, broadcast: false });
};

const syncThemeFromBroadcast = (event) => {
    setThemeMode(event?.detail?.theme, { persist: false, broadcast: false });
};

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    setThemeMode(window.localStorage.getItem(USER_THEME_STORAGE_KEY), { persist: false, broadcast: false });
    window.addEventListener('storage', syncThemeFromStorage);
    window.addEventListener(USER_THEME_EVENT, syncThemeFromBroadcast);
});

onBeforeUnmount(() => {
    if (typeof window === 'undefined') {
        return;
    }

    window.removeEventListener('storage', syncThemeFromStorage);
    window.removeEventListener(USER_THEME_EVENT, syncThemeFromBroadcast);
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="isDashboardView ? 'HERO_STATUS | P-QUEST' : 'PROFILE_SETTINGS | P-QUEST'" />

        <div class="user-page-shell space-y-6 font-['Press_Start_2P'] text-[10px] leading-relaxed text-[#4ed4d4] md:space-y-8">
            <div class="rpg-panel flex flex-col items-center gap-4 border-cyan-500/50 bg-[#1a1c2c]/80 backdrop-blur-md md:flex-row md:gap-6">
                <div class="w-20 h-20 border-4 border-cyan-400 bg-slate-800 shadow-[0_0_15px_rgba(78,212,212,0.3)] relative overflow-hidden">
                    <img
                        v-if="userData.profile_photo"
                        :src="'/storage/' + userData.profile_photo"
                        loading="lazy"
                        decoding="async"
                        class="w-full h-full object-cover"
                    >
                    <img
                        v-else
                        :src="`https://api.dicebear.com/7.x/pixel-art/svg?seed=${userData.username || 'guild-member'}`"
                        loading="lazy"
                        decoding="async"
                        class="w-full h-full object-cover"
                    >
                </div>

                <div class="flex-1 w-full space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h1 class="break-words text-base uppercase italic tracking-tighter text-white sm:text-lg">
                            {{ userData.username || userData.name }}
                        </h1>
                        <span class="shrink-0 text-sm text-yellow-400">
                            {{ userGold }}
                            <span class="text-[8px]">G</span>
                        </span>
                    </div>

                    <div class="w-full h-4 bg-black border-2 border-slate-700 p-[2px] overflow-hidden relative">
                        <div
                            class="h-full bg-cyan-500 shadow-[0_0_10px_#06b6d4] transition-all duration-1000"
                            :style="{ width: userExpProgress + '%' }"
                        />
                    </div>

                    <div class="flex flex-wrap justify-between gap-2 text-[8px] text-slate-400">
                        <span>LVL. {{ userLvl }}</span>
                        <span>EXP: {{ userExp % 1000 }} / 1000</span>
                    </div>

                    <div class="flex flex-wrap gap-2 text-[7px] uppercase text-slate-400">
                        <span v-if="userData.location">{{ userData.location }}</span>
                        <span v-if="userData.experience">{{ userData.experience }}</span>
                    </div>

                    <p v-if="userData.bio" class="text-[8px] uppercase leading-relaxed text-slate-300">
                        {{ userData.bio }}
                    </p>
                </div>
            </div>

            <template v-if="isDashboardView">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <button
                        type="button"
                        class="rpg-panel py-4 border-slate-700 bg-black/40 shadow-none w-full text-left transition-colors hover:border-cyan-500/50 focus:outline-none focus:ring-2 focus:ring-cyan-500/50"
                        @click="toggleAverageGradeDropdown"
                    >
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <p class="text-[7px] text-slate-500 uppercase italic">Overall_Grade_AVG</p>
                            <svg
                                class="h-4 w-4 text-cyan-300 transition-transform duration-300"
                                :class="isAverageGradeOpen ? 'rotate-180' : 'rotate-0'"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 011.08 1.04l-4.25 4.51a.75.75 0 01-1.08 0l-4.25-4.51a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </div>

                        <div class="flex items-baseline gap-2">
                            <span class="text-xl font-bold font-mono" :class="getGradeColor(averageGradePercent)">
                                {{ averageGrade || 0 }}%
                            </span>
                            <div class="flex-1 h-1 bg-slate-800 ml-2 relative overflow-hidden">
                                <div
                                    class="h-full bg-current transition-all duration-1000"
                                    :class="getGradeColor(averageGradePercent)"
                                    :style="{ width: averageGradePercent + '%' }"
                                />
                            </div>
                        </div>

                        <Transition name="avg-dropdown">
                            <div
                                v-if="isAverageGradeOpen"
                                class="mt-3 border border-slate-700 bg-black/35 max-h-52 overflow-y-auto"
                            >
                                <div v-if="classAverageRows.length > 0">
                                    <div
                                        v-for="classItem in classAverageRows"
                                        :key="classItem.key"
                                        class="flex items-center justify-between gap-3 border-b border-slate-800 px-3 py-2 text-[7px] uppercase last:border-b-0"
                                    >
                                        <span class="truncate text-slate-200">{{ classItem.class_name }}</span>
                                        <span class="shrink-0 text-emerald-300">{{ classItem.average_grade }}%</span>
                                    </div>
                                </div>
                                <p v-else class="px-3 py-2 text-[7px] uppercase text-slate-500">
                                    Belum ada data quest per kelas untuk user ini.
                                </p>
                            </div>
                        </Transition>
                    </button>

                    <div class="rpg-panel py-4 border-slate-700 bg-black/40 shadow-none">
                        <p class="text-[7px] text-slate-500 uppercase italic mb-2">Quests_Completed</p>
                        <div class="flex items-center gap-2 text-white">
                            <span class="text-xl font-bold font-mono">{{ totalCompleted || 0 }}</span>
                            <span class="text-[7px] text-slate-600 tracking-widest">SUCCESSFUL_LOGS</span>
                        </div>
                    </div>

                    <div class="rpg-panel py-4 border-slate-700 bg-black/40 shadow-none">
                        <p class="text-[7px] text-slate-500 uppercase italic mb-2">Profile_Settings</p>
                        <div class="flex flex-col items-start gap-2">
                            <Link :href="route('profile.edit')" class="text-[8px] text-cyan-400 underline hover:text-white">
                                Edit_Profile_Data
                            </Link>
                            <Link :href="route('profile.creations')" class="text-[8px] text-emerald-400 underline hover:text-white">
                                My_Creations
                            </Link>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12 lg:col-span-9 min-h-[400px]">
                        <div class="rpg-panel h-full animate-in fade-in slide-in-from-bottom-4 duration-300">
                            <div class="space-y-6 h-full flex flex-col">
                                <h3 class="text-green-400 mb-6 uppercase tracking-widest border-l-4 border-green-400 pl-3">
                                    Quest_Log
                                </h3>
                                <div class="flex-1 flex flex-col">
                                    <div v-if="questItems.length > 0" class="space-y-4 flex-1">
                                        <div
                                            v-for="q in questItems"
                                            :key="q.uuid"
                                            class="flex flex-col gap-3 border-2 border-slate-700 bg-black/40 p-3 transition-colors hover:border-cyan-500/50 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div>
                                                <p class="break-words text-[8px] text-white">{{ q.title }}</p>
                                                <p class="text-[6px] text-slate-500 mt-1 uppercase">
                                                    Status:
                                                    <span
                                                        :class="{
                                                            'text-yellow-500': q.status.toLowerCase() === 'pending',
                                                            'text-green-500': q.status.toLowerCase() === 'approved',
                                                            'text-red-500': q.status.toLowerCase() === 'rejected',
                                                        }"
                                                        class="font-bold"
                                                    >
                                                        {{ q.status }}
                                                    </span>
                                                    <span class="ml-2 text-slate-600">| {{ q.submitted_at }}</span>
                                                </p>
                                            </div>
                                            <Link
                                                :href="route('submissions.show', { submission: q.uuid })"
                                                class="self-start text-[8px] text-yellow-500 transition-colors hover:text-white hover:underline sm:self-auto"
                                            >
                                                VIEW >
                                            </Link>
                                        </div>
                                    </div>
                                    <div v-else class="text-center flex-1 flex flex-col items-center justify-center py-10">
                                        <p class="text-slate-600 italic">No quests taken yet...</p>
                                        <Link :href="route('lobby')" class="text-cyan-400 underline mt-4 inline-block hover:text-white">
                                            Browse_Quests
                                        </Link>
                                    </div>

                                    <div v-if="questPaginationLinks.length > 0" class="flex flex-wrap gap-2 pt-4 mt-4 border-t border-slate-800">
                                        <Link
                                            v-for="(link, idx) in questPaginationLinks"
                                            :key="`${idx}-${link.label}`"
                                            :href="link.url || '#'"
                                            class="px-3 py-1 border text-[8px] uppercase transition-all"
                                            :class="[
                                                link.active
                                                    ? 'border-cyan-400 text-cyan-400 bg-cyan-900/20'
                                                    : 'border-slate-700 text-slate-300 hover:bg-slate-700 hover:text-white',
                                                !link.url ? 'opacity-40 pointer-events-none' : '',
                                            ]"
                                            v-html="link.label"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-12 lg:col-span-3 space-y-4">
                        <div class="rpg-panel border-indigo-500/50 bg-indigo-900/20">
                            <h2 class="text-indigo-400 mb-6 border-b-2 border-indigo-900 pb-2 uppercase text-center text-[8px]">
                                Jobs_Status
                            </h2>
                            <div class="py-2">
                                <div class="job-card border border-indigo-400/50 bg-[#0d1117] p-2 shadow-[4px_4px_0_rgba(0,0,0,0.45)]">
                                    <div class="text-[6px] uppercase tracking-widest text-indigo-300 mb-2">USER_JOB_CARD</div>
                                    <div class="border border-indigo-500/40 bg-black/40 h-[120px] overflow-hidden flex items-center justify-center">
                                        <img
                                            v-if="userData.job_emblem_path"
                                            :src="`/storage/${userData.job_emblem_path}`"
                                            :alt="`${userData.job_name} emblem`"
                                            class="w-full h-full object-cover"
                                        >
                                        <img
                                            v-else
                                            src="/images/logo.png"
                                            alt="default job"
                                            class="w-12 h-12 object-contain opacity-80"
                                        >
                                    </div>
                                    <div class="mt-2 border border-indigo-500/40 bg-indigo-900/20 px-2 py-2">
                                        <p class="text-[6px] text-slate-400 uppercase mb-1">JOBS_PATH</p>
                                        <p class="text-[8px] text-white uppercase leading-snug">
                                            {{ userData.job_name || 'UNASSIGNED_JOB' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rpg-panel border-emerald-500/40 bg-emerald-950/15">
                            <h2 class="text-emerald-300 mb-4 border-b border-emerald-900/60 pb-2 uppercase text-center text-[8px]">
                                Biodata_User
                            </h2>
                            <div class="space-y-3 text-[7px] uppercase text-slate-400">
                                <div>
                                    <p class="text-slate-500">Display_Name</p>
                                    <p class="mt-1 break-words text-white">{{ userData.name || userData.username }}</p>
                                </div>
                                <div v-if="userData.location">
                                    <p class="text-slate-500">Location</p>
                                    <p class="mt-1 break-words text-white">{{ userData.location }}</p>
                                </div>
                                <div v-if="userData.experience">
                                    <p class="text-slate-500">Experience</p>
                                    <p class="mt-1 break-words text-white">{{ userData.experience }}</p>
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
                                <div v-if="userData.bio">
                                    <p class="text-slate-500">Bio</p>
                                    <p class="mt-1 break-words text-[7px] leading-relaxed text-slate-300">{{ userData.bio }}</p>
                                </div>
                                <p v-if="!userData.bio && !userData.location && !userData.experience && userSkills.length === 0" class="leading-relaxed text-slate-500">
                                    Lengkapi biodata dari menu edit profile agar profilmu terlihat lebih hidup.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template v-else>
                <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12 lg:col-span-3 space-y-4">
                        <div class="rpg-panel bg-slate-900/60">
                            <h2 class="text-white mb-6 border-b-2 border-slate-700 pb-2 uppercase text-center text-[8px]">
                                Menu_Navigation
                            </h2>
                            <nav class="space-y-3">
                                <Link
                                    :href="route('profile.dashboard')"
                                    class="w-full p-3 text-left border-r-4 border-black transition-all uppercase text-[8px] bg-yellow-500 text-black hover:translate-x-1 block"
                                >
                                    [1] Profile_Dashboard
                                </Link>
                                <button
                                    @click="activeTab = 'profile'"
                                    :class="activeTab === 'profile' ? 'bg-cyan-400 text-black' : 'bg-slate-800 text-cyan-400'"
                                    class="w-full p-3 text-left border-r-4 border-black hover:translate-x-1 transition-all uppercase text-[8px]"
                                >
                                    [2] Edit_Identity
                                </button>
                                <button
                                    @click="activeTab = 'password'"
                                    :class="activeTab === 'password' ? 'bg-cyan-400 text-black' : 'bg-slate-800 text-cyan-400'"
                                    class="w-full p-3 text-left border-r-4 border-black hover:translate-x-1 transition-all uppercase text-[8px]"
                                >
                                    [3] Change_Password
                                </button>
                                <button
                                    @click="activeTab = 'danger'"
                                    :class="activeTab === 'danger' ? 'bg-red-600 text-white' : 'bg-slate-800 text-red-500'"
                                    class="w-full p-3 text-left border-r-4 border-black hover:translate-x-1 transition-all uppercase text-[8px]"
                                >
                                    [4] Danger_Zone
                                </button>
                            </nav>
                        </div>
                    </div>

                    <div class="col-span-12 lg:col-span-6 min-h-[400px]">
                        <div class="rpg-panel h-full animate-in fade-in slide-in-from-bottom-4 duration-300">
                            <div v-if="activeTab === 'profile'" class="space-y-6">
                                <h3 class="text-cyan-400 mb-6 uppercase tracking-widest border-l-4 border-cyan-400 pl-3">
                                    Update_Identity
                                </h3>
                                <div class="border-2 border-cyan-500/40 bg-black/35 p-4">
                                    <p class="text-[7px] uppercase text-cyan-300">Theme_Display</p>
                                    <p class="mt-2 text-[7px] leading-relaxed text-slate-400">
                                        Atur mode tampilan aplikasi. Tema akan tersimpan di browser ini.
                                    </p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            class="border px-3 py-2 text-[7px] uppercase transition-colors"
                                            :class="themeMode === 'dark'
                                                ? 'border-cyan-300 bg-cyan-400 text-black'
                                                : 'border-slate-600 bg-slate-900 text-slate-200 hover:border-cyan-500/50'"
                                            @click="applyDarkTheme"
                                        >
                                            Dark
                                        </button>
                                        <button
                                            type="button"
                                            class="border px-3 py-2 text-[7px] uppercase transition-colors"
                                            :class="themeMode === 'light'
                                                ? 'border-cyan-300 bg-cyan-400 text-black'
                                                : 'border-slate-600 bg-slate-900 text-slate-200 hover:border-cyan-500/50'"
                                            @click="applyLightTheme"
                                        >
                                            Light
                                        </button>
                                    </div>
                                </div>
                                <div class="form-container">
                                    <UpdateProfileInformationForm
                                        :must-verify-email="mustVerifyEmail"
                                        :status="status"
                                        :user="props.user"
                                        :jobs="jobs"
                                        class="max-w-xl"
                                    />
                                </div>
                            </div>

                            <div v-if="activeTab === 'password'" class="space-y-6">
                                <h3 class="text-yellow-500 mb-6 uppercase tracking-widest border-l-4 border-yellow-500 pl-3">
                                    Security_Protocol
                                </h3>
                                <div class="form-container">
                                    <UpdatePasswordForm />
                                </div>
                            </div>

                            <div v-if="activeTab === 'danger'" class="space-y-6">
                                <h3 class="text-red-600 mb-6 uppercase tracking-widest border-l-4 border-red-600 pl-3">
                                    Termination_Process
                                </h3>
                                <div class="bg-red-900/10 p-4 border border-red-900/50 mb-6">
                                    <p class="text-red-500 text-[8px] leading-normal">
                                        WARNING: This action is irreversible. All character data, progress, and gold will be purged from the realm.
                                    </p>
                                </div>
                                <DeleteUserForm />
                            </div>
                        </div>
                    </div>

                    <div class="col-span-12 lg:col-span-3 space-y-4">
                        <div class="rpg-panel border-indigo-500/50 bg-indigo-900/20">
                            <h2 class="text-indigo-400 mb-6 border-b-2 border-indigo-900 pb-2 uppercase text-center text-[8px]">
                                Jobs_Status
                            </h2>
                            <div class="py-2">
                                <div class="job-card border border-indigo-400/50 bg-[#0d1117] p-2 shadow-[4px_4px_0_rgba(0,0,0,0.45)]">
                                    <div class="text-[6px] uppercase tracking-widest text-indigo-300 mb-2">USER_JOB_CARD</div>
                                    <div class="border border-indigo-500/40 bg-black/40 h-[120px] overflow-hidden flex items-center justify-center">
                                        <img
                                            v-if="userData.job_emblem_path"
                                            :src="`/storage/${userData.job_emblem_path}`"
                                            :alt="`${userData.job_name} emblem`"
                                            class="w-full h-full object-cover"
                                        >
                                        <img
                                            v-else
                                            src="/images/logo.png"
                                            alt="default job"
                                            class="w-12 h-12 object-contain opacity-80"
                                        >
                                    </div>
                                    <div class="mt-2 border border-indigo-500/40 bg-indigo-900/20 px-2 py-2">
                                        <p class="text-[6px] text-slate-400 uppercase mb-1">JOBS_PATH</p>
                                        <p class="text-[8px] text-white uppercase leading-snug">
                                            {{ userData.job_name || 'UNASSIGNED_JOB' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rpg-panel border-emerald-500/40 bg-emerald-950/15">
                            <h2 class="text-emerald-300 mb-4 border-b border-emerald-900/60 pb-2 uppercase text-center text-[8px]">
                                Biodata_User
                            </h2>
                            <div class="space-y-3 text-[7px] uppercase text-slate-400">
                                <div>
                                    <p class="text-slate-500">Display_Name</p>
                                    <p class="mt-1 break-words text-white">{{ userData.name || userData.username }}</p>
                                </div>
                                <div v-if="userData.location">
                                    <p class="text-slate-500">Location</p>
                                    <p class="mt-1 break-words text-white">{{ userData.location }}</p>
                                </div>
                                <div v-if="userData.experience">
                                    <p class="text-slate-500">Experience</p>
                                    <p class="mt-1 break-words text-white">{{ userData.experience }}</p>
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
                                <div v-if="userData.bio">
                                    <p class="text-slate-500">Bio</p>
                                    <p class="mt-1 break-words text-[7px] leading-relaxed text-slate-300">{{ userData.bio }}</p>
                                </div>
                                <p v-if="!userData.bio && !userData.location && !userData.experience && userSkills.length === 0" class="leading-relaxed text-slate-500">
                                    Biodata publikmu masih kosong. Isi dari tab Edit Identity.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
button {
    cursor: pointer;
    font-family: 'Press Start 2P', cursive;
}

.rpg-panel {
    @apply p-6 relative border-4 border-[#3d415f] bg-[#1a1c2c];
    box-shadow: 8px 8px 0px 0px rgba(0, 0, 0, 0.5);
}

.form-container :deep(button) {
    @apply w-full mt-4 p-3 bg-cyan-900/40 border-2 border-cyan-400 text-cyan-400 text-[8px] hover:bg-cyan-400 hover:text-black transition-all font-['Press_Start_2P'];
}

.form-container :deep(input) {
    @apply bg-[#0d1117] border-2 border-slate-700 text-cyan-400 p-2 text-[10px] w-full mt-1 focus:border-cyan-400 outline-none;
}

.form-container :deep(textarea),
.form-container :deep(select) {
    @apply bg-[#0d1117] border-2 border-slate-700 text-cyan-400 p-2 text-[10px] w-full mt-1 focus:border-cyan-400 outline-none;
}

.form-container :deep(label) {
    @apply text-slate-400 text-[8px] uppercase;
}

.avg-dropdown-enter-active,
.avg-dropdown-leave-active {
    transition: opacity 0.25s ease, max-height 0.25s ease, transform 0.25s ease;
    overflow: hidden;
}

.avg-dropdown-enter-from,
.avg-dropdown-leave-to {
    opacity: 0;
    max-height: 0;
    transform: translateY(-4px);
}

.avg-dropdown-enter-to,
.avg-dropdown-leave-from {
    opacity: 1;
    max-height: 220px;
    transform: translateY(0);
}
</style>

