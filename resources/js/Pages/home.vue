<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useLobby } from '@/Composables/useLobby';
import FloatingChat from '@/Components/FloatingChat.vue';

const props = defineProps({
    players: Array,
    quests: Array,
    studyGroups: Array,
    materi: Array,
    events: Array,
    auth: Object
});

// Memanggil Logic tanpa mengubah isinya
const {
    joinForm,
    handleLeave,
    handleJoin,
    auth,
    players,
    quests,
    studyGroups,
    guides,
    events,
    handleLogout
} = useLobby(props);

const mobileMenuOpen = ref(false);
const page = usePage();
const isStaff = computed(() => ['admin', 'mentor'].includes(String(auth.value?.user?.role || '').toLowerCase()));
const isEmailUnverified = computed(() => !!(auth.value?.user && !auth.value.user.email_verified_at));
const isEmailVerifiedSuccess = computed(() => page.url.includes('verified=1') && !isEmailUnverified.value);
const profileVerificationHref = computed(() => `${route('profile.edit', { tab: 'profile' })}#email-verification`);

const closeMobileMenu = () => {
    mobileMenuOpen.value = false;
};
</script>

<template>

    <Head title="DOOPTECH" />

    <div class="min-h-screen bg-[#0a0c10] bg-cover bg-center bg-no-repeat bg-fixed relative font-['Press_Start_2P']"
        style="background-image: url('/images/bg-loby.png');">

        <div class="absolute inset-0 bg-black/60 backdrop-blur-[1px]"></div>

        <div class="relative z-10 flex flex-col min-h-screen">

            <nav
                class="bg-[#1a1c2c]/90 backdrop-blur-sm border-b-4 border-[#3d415f] p-4 md:px-8 flex justify-between items-center shadow-2xl sticky top-0 z-50">
                <div class="flex items-center gap-4">
                    <Link :href="route('lobby')" class="flex items-center gap-4 group" @click="closeMobileMenu">
                        <div
                            class="w-10 h-10 bg-[#0a0c10] flex items-center justify-center border-b-4 border-r-4 border-[#4ed4d4] overflow-hidden group-hover:scale-110 transition-transform">
                            <img src="/images/logo.png" alt="Logo" class="w-7 h-7 object-contain pixelated">
                        </div>
                        <h1
                            class="text-[#009999] text-[8px] md:text-sm tracking-tighter uppercase group-hover:text-[#4ed4d4]">
                            DOOPTECH
                        </h1>
                    </Link>
                </div>

                <div class="hidden md:flex items-center">
                    <template v-if="auth.user">
                        <div class="nav-dock">
                            <Link v-if="isStaff" :href="route('admin.dashboard')" class="nav-action nav-action--admin">
                                Admin
                            </Link>

                            <Link :href="route('profile.edit')" class="nav-action nav-action--profile">
                                <i class="fi fi-rr-user text-[10px] leading-none"></i>
                                Profile
                            </Link>

                            <Link :href="route('shop.index')" class="nav-action nav-action--shop">
                                <i class="fi fi-rr-shopping-cart text-[10px] leading-none"></i>
                                Shop
                            </Link>

                            <button @click="handleLogout" class="nav-action nav-action--logout" type="button">
                                <span class="sr-only">Logout</span>
                                [X]
                            </button>
                        </div>
                    </template>

                    <template v-else>
                        <div class="nav-dock">
                            <Link :href="route('login')" class="nav-action nav-action--profile" @click="closeMobileMenu">
                                Login
                            </Link>
                            <Link :href="route('register')" class="nav-action nav-action--shop" @click="closeMobileMenu">
                                Register
                            </Link>
                        </div>
                    </template>
                </div>

                <button
                    type="button"
                    class="md:hidden inline-flex items-center justify-center w-10 h-10 border-2 border-slate-600 bg-slate-900/70 text-cyan-300"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    :aria-expanded="mobileMenuOpen ? 'true' : 'false'"
                    aria-label="Toggle menu"
                >
                    <i :class="mobileMenuOpen ? 'fi fi-rr-cross-small' : 'fi fi-rr-menu-burger'" class="text-[14px]"></i>
                </button>
            </nav>

            <div v-if="isEmailUnverified" class="px-4 md:px-8 pt-4">
                <div class="border-2 border-amber-400/60 bg-amber-500/15 p-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div class="text-[9px] leading-relaxed text-amber-100 uppercase tracking-wide">
                        Email belum terverifikasi. Kamu tetap bisa eksplor Home, tapi beberapa fitur akan dikunci sampai verifikasi selesai.
                    </div>
                    <Link
                        :href="profileVerificationHref"
                        class="text-[8px] bg-amber-300 text-black px-3 py-2 btn-pixel border-amber-700 uppercase font-bold hover:bg-amber-200 transition-colors text-center"
                    >
                        Verifikasi di Profile
                    </Link>
                </div>
            </div>

            <div v-else-if="isEmailVerifiedSuccess" class="px-4 md:px-8 pt-4">
                <div class="border-2 border-emerald-400/60 bg-emerald-500/15 p-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div class="text-[9px] leading-relaxed text-emerald-100 uppercase tracking-wide">
                        Verifikasi email berhasil. Semua fitur akun sekarang sudah terbuka.
                    </div>
                    <Link
                        :href="route('profile.edit', { tab: 'profile' })"
                        class="text-[8px] bg-emerald-300 text-black px-3 py-2 btn-pixel border-emerald-700 uppercase font-bold hover:bg-emerald-200 transition-colors text-center"
                    >
                        Buka Profile
                    </Link>
                </div>
            </div>

            <div v-if="mobileMenuOpen" class="md:hidden relative z-50 px-4 pb-4">
                <div class="bg-[#1a1c2c]/95 backdrop-blur-sm border-2 border-[#3d415f] p-3 space-y-2 shadow-2xl">
                    <template v-if="auth.user">
                        <Link
                            v-if="isStaff"
                            :href="route('admin.dashboard')"
                            class="w-full nav-action nav-action--admin justify-center"
                            @click="closeMobileMenu"
                        >
                            Admin
                        </Link>

                        <Link
                            :href="route('profile.edit')"
                            class="w-full nav-action nav-action--profile justify-center"
                            @click="closeMobileMenu"
                        >
                            <i class="fi fi-rr-user text-[10px] leading-none"></i>
                            Profile
                        </Link>

                        <Link
                            :href="route('shop.index')"
                            class="w-full nav-action nav-action--shop justify-center"
                            @click="closeMobileMenu"
                        >
                            <i class="fi fi-rr-shopping-cart text-[10px] leading-none"></i>
                            Shop
                        </Link>

                        <button
                            @click="handleLogout(); closeMobileMenu()"
                            class="w-full nav-action nav-action--logout justify-center"
                            type="button"
                        >
                            [X]
                        </button>
                    </template>

                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="w-full nav-action nav-action--profile justify-center"
                            @click="closeMobileMenu"
                        >
                            Login
                        </Link>
                        <Link
                            :href="route('register')"
                            class="w-full nav-action nav-action--shop justify-center"
                            @click="closeMobileMenu"
                        >
                            Register
                        </Link>
                    </template>
                </div>
            </div>

            <main class="p-4 md:p-8 grid grid-cols-12 gap-8 flex-1">

                <div class="col-span-12 lg:col-span-4 space-y-6">

                    <div class="rpg-panel border-[#3d415f] h-[350px] flex flex-col bg-[#1a1c2c]/90 backdrop-blur-sm">
                        <h2
                            class="text-[#4ed4d4] text-[10px] mb-4 flex items-center gap-2 border-b border-slate-700 pb-2 flex-shrink-0 uppercase">
                            <i class="fi fi-rr-user text-[11px] text-[#4ed4d4]"></i> Leaderboard - Players[{{ players.length }}]
                        </h2>
                        <div class="space-y-4 overflow-y-auto pr-2 custom-scroll flex-1">
                            <div v-for="(player, index) in players" :key="player.id"
                                class="flex items-center gap-4 p-2 hover:bg-[#009999]/10 border-l-4 border-transparent hover:border-[#009999] transition-all relative">

                                <div class="relative">
                                    <div v-if="index < 3"
                                        class="absolute -top-3 -left-2 z-10 drop-shadow-[0_0_5px_rgba(0,0,0,0.5)]">
                                        <span v-if="index === 0" class="text-xl">👑</span>
                                        <span v-else-if="index === 1" class="text-xl">🥈</span>
                                        <span v-else-if="index === 2" class="text-xl">🥉</span>
                                    </div>

                                    <div class="w-10 h-10 bg-slate-800 border-2 flex-shrink-0 overflow-hidden shadow-lg"
                                        :class="{
                                            'border-yellow-400 shadow-yellow-500/50 animate-pulse': index === 0,
                                            'border-slate-300 shadow-slate-400/50': index === 1,
                                            'border-amber-600 shadow-amber-700/50': index === 2,
                                            'border-slate-600': index > 2
                                        }">
                                        <img v-if="player.profile_photo" :src="'/storage/' + player.profile_photo"
                                            class="w-full h-full object-cover">
                                        <img v-else
                                            :src="`https://api.dicebear.com/7.x/pixel-art/svg?seed=${player.username || player.name}`"
                                            class="w-full h-full">
                                    </div>
                                </div>

                                <div class="flex-1">
                                    <div class="flex justify-between text-[8px] font-sans font-bold items-center">
                                        <span class="text-[14px] text-white uppercase truncate max-w-[120px]"
                                            :class="{ 'text-yellow-400 font-black': index === 0 }">
                                            {{ player.username || player.name }}
                                        </span>
                                        <span class="text-[10px]" :class="index < 3 ? 'text-white' : 'text-[#009999]'">
                                            LVL.{{ player.level || 1 }}
                                        </span>
                                    </div>
                                    <p class="text-[8px] text-slate-400 mt-1 uppercase flex justify-between">
                                        <span>{{ player.role || 'Adventurer' }}</span>
                                        <span v-if="index < 3" class="italic text-slate-500">Rank #{{ index + 1
                                            }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="rpg-panel border-indigo-500/50 h-[380px] flex flex-col bg-[#1a1c2c]/90 backdrop-blur-sm relative">
                        <div
                            class="flex justify-between items-start mb-4 border-b border-indigo-900 pb-2 flex-shrink-0">
                            <h2
                                class="text-indigo-400 text-[10px] uppercase tracking-widest flex items-center gap-2 font-['Press_Start_2P']">
                                Materi Ajar
                            </h2>

                            <Link
                                :href="auth.user ? route('guides.user.index') : route('login')"
                                class="bg-indigo-900/40 p-2 border-b-4 border-r-4 border-indigo-500 shadow-[3px_3px_0px_0px_rgba(0,0,0,0.5)] flex items-center justify-center hover:bg-indigo-500/50 transition-colors"
                                title="Lihat semua materi">
                                <i class="fi fi-rr-book-alt text-xl leading-none text-indigo-200"></i>
                            </Link>
                        </div>

                        <div class="space-y-4 overflow-y-auto pr-2 custom-scroll-indigo flex-1">
                            <div v-for="item in guides" :key="item.uuid"
                                class="p-0 bg-[#0d1117] border-2 border-slate-800 hover:border-indigo-500 transition-all group relative overflow-hidden">

                                <div class="absolute top-0 left-0 w-1 h-full bg-indigo-600"></div>

                                <div class="p-3 pl-5 relative">
                                    <div class="flex justify-between items-center mb-1">
                                        <span
                                            class="text-[7px] text-indigo-400 font-['Press_Start_2P'] uppercase tracking-tighter">[
                                            STUDY_MATERIAL ]</span>
                                        <span class="text-[10px] text-slate-600 font-mono italic uppercase">Ref.{{
                                            item.uuid.substring(0, 5) }}</span>
                                    </div>
                                    <p class="text-[8px] uppercase mb-1"
                                        :class="item.study_group_id ? 'text-emerald-400' : 'text-cyan-400'">
                                        {{ item.study_group_id ? `Party: ${item.study_group?.name || 'Unknown'}` : 'Global' }}
                                    </p>

                                    <h3
                                        class="text-[14px] font-sans font-extrabold text-white uppercase tracking-tight group-hover:text-indigo-300 leading-tight mb-1">
                                        {{ item.title }}
                                    </h3>

                                    <p
                                        class="text-[12px] font-sans text-slate-500 italic mb-4 line-clamp-2 leading-snug">
                                        {{ item.description || 'Accessing knowledge database...' }}
                                    </p>

                                    <div class="flex justify-between items-center border-t border-slate-800/50 pt-2.5">
                                        <div class="flex items-center gap-1">
                                            <span class="w-1 h-1 bg-green-500 rounded-full animate-pulse"></span>
                                            <span
                                                class="text-[8px] text-slate-500 font-['Press_Start_2P'] uppercase">Verified</span>
                                        </div>

                                        <Link :href="route('guides.user.show', item.uuid)"
                                            class="text-[8px] bg-[#1a1c2c] text-indigo-300 px-3 py-1.5 border-b-2 border-r-2 border-indigo-900 hover:bg-indigo-500 hover:text-white transition-all uppercase font-['Press_Start_2P']">
                                            DETAIL
                                        </Link>
                                    </div>
                                </div>
                            </div>

                            <div v-if="guides.length === 0" class="text-center py-8">
                                <p
                                    class="text-slate-700 text-[8px] uppercase font-['Press_Start_2P'] italic tracking-tighter">
                                    Database_Empty</p>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="col-span-12 lg:col-span-8 space-y-6">

                    <div class="rpg-panel flex flex-col bg-[#1a1c2c]/90 backdrop-blur-sm border-emerald-500/50">
                        <div class="flex justify-between items-center mb-4 border-b border-emerald-900 pb-2">
                            <h2 class="text-emerald-400 text-[10px] uppercase tracking-widest flex items-center gap-2">
                                <i class="fi fi-rr-users text-[12px] text-emerald-300 animate-pulse"></i> Active_Parties [{{ studyGroups.length }}]
                            </h2>
                            <span class="text-[10px] text-slate-500 uppercase font-mono">Join_via_Code</span>
                        </div>

                        <div
                            class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[250px] overflow-y-auto pr-2 custom-scroll">
                            <div v-for="group in studyGroups" :key="group.uuid"
                                class="p-3 bg-[#0d1117] border-2 border-slate-800 hover:border-emerald-500 transition-all group relative overflow-hidden">
                                <div class="absolute top-0 left-0 w-1 h-full bg-emerald-600"></div>

                                <div class="flex justify-between items-start mb-1">
                                    <h3
                                        class="text-[14px] text-white uppercase group-hover:text-emerald-400 font-bold tracking-tight">
                                        {{ group.name }}
                                    </h3>
                                    <span class="text-[12px] text-yellow-500 font-mono">{{ group.users_count || 0 }}/{{
                                        group.max_members }}</span>
                                </div>

                                <p class="text-[8px] text-slate-500 italic line-clamp-1 mb-3">
                                    {{ group.description || 'In pursuit of higher knowledge...' }}
                                </p>

                                <div class="flex justify-between items-center">
                                    <span class="text-[10px] text-slate-600 uppercase font-mono tracking-tighter">
                                        Code: {{ group.invite_code }}
                                    </span>

                                    <button v-if="group.is_member" @click="handleLeave(group.uuid)"
                                        class="text-[9px] bg-red-900/50 text-red-400 px-3 py-1 border border-red-700 hover:bg-red-600 hover:text-white transition-all uppercase font-['Press_Start_2P']">
                                        Leave_Party
                                    </button>

                                    <button v-else-if="group.join_request_status === 'pending'" disabled
                                        class="text-[9px] bg-slate-900/60 text-slate-400 px-3 py-1 border border-slate-700 uppercase font-['Press_Start_2P'] cursor-not-allowed">
                                        Request_Pending
                                    </button>

                                    <button v-else @click="handleJoin(group.invite_code)"
                                        :disabled="joinForm.processing"
                                        class="text-[9px] bg-emerald-900/50 text-emerald-400 px-3 py-1 border border-emerald-700 hover:bg-emerald-500 hover:text-black transition-all uppercase font-['Press_Start_2P']">
                                        {{ joinForm.processing ? 'Sending...' : 'Request_Access' }}
                                    </button>
                                </div>
                            </div>

                            <div v-if="studyGroups.length === 0" class="col-span-2 text-center py-4">
                                <p class="text-slate-700 text-[8px] uppercase italic tracking-tighter">
                                    No_Parties_Found_In_This_Realm</p>
                            </div>
                        </div>
                    </div>

                    <div class="rpg-panel flex flex-col bg-[#1a1c2c]/90 backdrop-blur-sm border-blue-500/50">
                        <div class="flex justify-between items-center mb-4 border-b border-blue-900 pb-2">
                            <h2 class="text-blue-300 text-[10px] uppercase tracking-widest flex items-center gap-2">
                                <i class="fi fi-rr-calendar-clock text-[12px]"></i> Event_Timeline [{{ events.length }}]
                            </h2>
                            <Link :href="auth.user ? route('events.user.index') : route('login')"
                                class="bg-blue-900/30 p-2 border-b-4 border-r-4 border-blue-700 shadow-[3px_3px_0px_0px_rgba(0,0,0,0.5)] flex items-center justify-center hover:bg-blue-500/40 transition-colors"
                                title="Lihat semua event">
                                <i class="fi fi-rr-calendar text-xl leading-none text-blue-200"></i>
                            </Link>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[260px] overflow-y-auto pr-2 custom-scroll">
                            <div v-for="event in events" :key="event.uuid"
                                class="p-3 bg-[#0d1117] border-2 border-slate-800 hover:border-blue-500 transition-all group relative overflow-hidden flex flex-col min-h-[180px]">
                                <div class="absolute top-0 left-0 w-1 h-full bg-blue-600"></div>

                                <div class="flex justify-between items-start mb-1">
                                    <div class="text-[7px] text-slate-500 uppercase">
                                        Meeting_{{ event.sequence_order }}
                                    </div>
                                    <div class="text-[7px] uppercase text-blue-300">
                                        #{{ event.uuid.substring(0, 6) }}
                                    </div>
                                </div>
                                <h3 class="text-[12px] text-white uppercase mb-2 group-hover:text-blue-300 leading-snug">
                                    {{ event.title }}
                                </h3>
                                <p class="text-[8px] text-cyan-400 uppercase mb-2">
                                    {{ event.study_group?.name || 'Public' }}
                                </p>
                                <p class="text-[7px] text-slate-400 uppercase mb-2">
                                    {{ event.starts_at ? new Date(event.starts_at).toLocaleString('id-ID') : 'Schedule_Not_Set' }}
                                </p>
                                <div class="flex items-center justify-between text-[7px] uppercase mt-auto pt-2 border-t border-slate-800">
                                    <span class="text-emerald-400">Guide: {{ event.guides_count || 0 }}</span>
                                    <span class="text-yellow-400">Quest: {{ event.quests_count || 0 }}</span>
                                </div>
                                <div class="mt-3 self-end">
                                    <Link :href="route('events.show', event.uuid)"
                                        class="text-[8px] bg-blue-900/50 text-blue-300 px-3 py-1.5 btn-pixel border-blue-800 hover:bg-blue-500 hover:text-black transition-all uppercase">
                                        View_Detail
                                    </Link>
                                </div>
                            </div>

                            <div v-if="events.length === 0" class="col-span-2 text-center py-4">
                                <p class="text-slate-700 text-[8px] uppercase italic tracking-tighter">
                                    No_Events_Available
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="rpg-panel h-[480px] flex flex-col bg-[#1a1c2c]/90 backdrop-blur-sm border-[#3d415f]">
                        <div
                            class="flex justify-between items-center mb-6 border-b border-slate-700 pb-4 flex-shrink-0">
                            <h2 class="text-yellow-400 text-xs uppercase tracking-widest animate-pulse">Available_Quests
                            </h2>
                            <Link :href="auth.user ? route('quests.user.index') : route('login')"
                                class="bg-yellow-900/30 p-2 border-b-4 border-r-4 border-yellow-700 shadow-[3px_3px_0px_0px_rgba(0,0,0,0.5)] flex items-center justify-center hover:bg-yellow-500/40 transition-colors"
                                title="Lihat semua quest">
                                <i class="fi fi-rr-target text-xl leading-none text-yellow-200"></i>
                            </Link>
                        </div>

                        <div class="overflow-y-auto pr-2 custom-scroll flex-1">
                            
                            <div v-if="quests.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-4">
                                <div v-for="quest in quests" :key="quest.uuid"
                                    class="rpg-panel bg-[#161b22] transition-all group cursor-pointer shadow-none flex flex-col h-[200px]"
                                    :class="[
                                        (quest.user_submission_status === 'Approved') ? 'border-emerald-500 shadow-[0_0_12px_rgba(16,185,129,0.35)] bg-emerald-950/20' :
                                        (quest.user_submission_status === 'Pending') ? 'border-yellow-500 shadow-[0_0_12px_rgba(234,179,8,0.35)] bg-yellow-950/20' :
                                        (quest.user_has_unlock && !quest.user_has_submitted) ? 'border-cyan-500 shadow-[0_0_12px_rgba(34,211,238,0.3)] bg-cyan-950/20' :
                                        (quest.status === 'Done' && !quest.user_has_submitted && !quest.user_has_unlock) ? 'border-red-600 shadow-[0_0_10px_rgba(220,38,38,0.2)]' :
                                            (quest.status === 'In-Progress') ? 'border-slate-500 bg-slate-900/50' :
                                                quest.user_has_submitted ? 'border-yellow-600 shadow-[0_0_10px_rgba(202,138,4,0.2)]' : 'border-slate-700 hover:border-[#009999]'
                                    ]">

                                    <div class="flex flex-col h-full">
                                        <div class="flex justify-between items-start mb-3">
                                            <span
                                                class="text-[7px] px-2 py-1 bg-slate-800 text-slate-400 border border-slate-600 uppercase">ID:{{
                                                    quest.id }}</span>
                                            <span :class="{
                                                'text-red-500': quest.difficulty === 'S-Rank',
                                                'text-orange-500': quest.difficulty === 'A-Rank',
                                                'text-cyan-500': quest.difficulty === 'B-Rank',
                                                'text-green-500': quest.difficulty === 'C-Rank'
                                            }" class="text-[8px] font-bold tracking-widest">{{ quest.difficulty
                                            }}</span>
                                        </div>

                                        <h3
                                            class="text-[10px] text-white group-hover:text-[#4ed4d4] leading-relaxed transition-colors uppercase line-clamp-3 mb-2">
                                            {{ quest.title }}
                                        </h3>

                                        <div class="mb-2 flex items-center gap-1">
                                            <span
                                                class="text-orange-500 text-[6px] uppercase tracking-tighter">Deadline:</span>
                                            <span :class="[
                                                'text-[7px] uppercase font-bold tracking-tighter',
                                                (new Date(quest.deadline) < new Date() && !quest.user_has_submitted && !quest.user_has_unlock) ? 'text-red-500 animate-pulse' : 'text-orange-300'
                                            ]">
                                                {{ quest.deadline ? new Date(quest.deadline).toLocaleString('id-ID',
                                                    {
                                                        day: '2-digit',
                                                month:'short', hour:'2-digit', minute:'2-digit'}).toUpperCase() :
                                                'NO_LIMIT' }}
                                            </span>
                                        </div>

                                        <div class="flex-grow">
                                            <p v-if="quest.status === 'Done' && !quest.user_has_submitted && !quest.user_has_unlock"
                                                class="text-[6px] text-red-500 uppercase">Mission_Expired</p>
                                            <p v-if="quest.user_has_unlock && !quest.user_has_submitted"
                                                class="text-[6px] text-cyan-300 uppercase italic tracking-widest">
                                                Quest_Reopened_With_Time_Key
                                            </p>
                                            <p v-if="quest.user_submission_status === 'Pending'"
                                                class="text-[6px] text-yellow-400 uppercase italic tracking-widest">
                                                Waiting_For_Review...
                                            </p>
                                            <p v-if="quest.user_submission_status === 'Approved'"
                                                class="text-[6px] text-emerald-400 uppercase italic tracking-widest">
                                                Approved!
                                            </p>
                                            <p v-if="quest.status === 'In-Progress'"
                                                class="text-[6px] text-slate-500 uppercase italic tracking-widest">
                                                Active_In_Journal...</p>
                                        </div>

                                        <div class="pt-4 flex justify-between items-center border-t border-slate-800">
                                            <div class="flex flex-col">
                                                <span class="text-[6px] text-slate-500 uppercase mb-1">Reward</span>
                                                <span class="text-yellow-500 text-[8px] tracking-tighter font-bold">{{
                                                    quest.reward_gold }}G</span>
                                            </div>

                                            <template v-if="quest.status !== 'In-Progress'">
                                                <Link :href="route('quests.show', quest.uuid)" :class="[
                                                    'text-[8px] px-3 py-2 btn-pixel uppercase font-bold transition-colors whitespace-nowrap',
                                                    (quest.user_submission_status === 'Approved') ? 'bg-emerald-600 text-black border-emerald-800 hover:bg-emerald-400' :
                                                    (quest.user_submission_status === 'Pending') ? 'bg-yellow-600 text-black border-yellow-800 hover:bg-yellow-400' :
                                                    (quest.user_has_unlock && !quest.user_has_submitted) ? 'bg-cyan-600 text-black border-cyan-800 hover:bg-cyan-400' :
                                                    (quest.status === 'Done' && !quest.user_has_submitted && !quest.user_has_unlock) ? 'bg-red-700 text-white border-red-950 hover:bg-red-600' :
                                                        quest.user_has_submitted ? 'bg-slate-700 text-white border-slate-900 hover:bg-slate-600' :
                                                            'bg-[#009999] text-black border-[#006666] hover:bg-[#4ed4d4]'
                                                ]">
                                                    <template
                                                        v-if="quest.user_submission_status === 'Approved'">View</template>
                                                    <template
                                                        v-else-if="quest.user_submission_status === 'Pending'">Preview</template>
                                                    <template
                                                        v-else-if="quest.user_has_unlock && !quest.user_has_submitted">Continue</template>
                                                    <template
                                                        v-else-if="quest.status === 'Done' && !quest.user_has_submitted && !quest.user_has_unlock">Late</template>
                                                    <template v-else>{{ quest.user_has_submitted ? 'View' :
                                                        'Take_Quest' }}</template>
                                                </Link>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else
                                class="flex flex-col items-center justify-center h-[300px] border-2 border-dashed border-slate-800 rounded-lg p-6 text-center">
                                <div class="text-slate-600 text-4xl mb-4 italic">!</div>
                                <h3 class="text-[#4ed4d4] text-[12px] uppercase tracking-[0.2em] mb-2 font-bold">No
                                    Quests Available
                                </h3>
                                <p class="text-slate-500 text-[9px] uppercase leading-relaxed max-w-[250px]">
                                    Your quest journal is empty. Please join a <span class="text-white underline">Party
                                        / Study
                                        Group</span> to unlock exclusive missions and challenges.
                                </p>
                                <div class="mt-6 h-[1px] w-20 bg-slate-800"></div>
                            </div>
                        </div>
                    </div>
                </div>

            </main>

            <FloatingChat v-if="auth.user" />

            <footer class="p-8 text-center bg-[#1a1c2c]/50 backdrop-blur-md border-t-2 border-white/10 mt-auto">
                <p class="text-[8px] text-white/50 uppercase tracking-[0.3em]">Build_Ver_1.1.0 // P-Quest Engine</p>
            </footer>

        </div>
    </div>
</template>

<style scoped>
/* Vite akan otomatis memproses file ini dan meng-enkapsulasinya ke komponen ini saja */
@import "../../css/lobby-style.css";
</style>
