<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBackgroundLayer from '@/Components/AppBackgroundLayer.vue';
import FloatingChat from '@/Components/FloatingChat.vue';
import UserNavbar from '@/Components/UserNavbar.vue';
import { useUserTheme } from '@/Composables/useUserTheme';

const page = usePage();
const auth = computed(() => page.props.auth);
const showFloatingChat = computed(() => Boolean(auth.value?.user) && !isDoopLabPage.value);
const isEmailUnverified = computed(() => !!(auth.value?.user && !auth.value.user.email_verified_at));
const isEmailVerifiedSuccess = computed(() => page.url.includes('verified=1') && !isEmailUnverified.value);
const profileVerificationHref = computed(() => `${route('profile.edit')}#email-verification`);
const isStaffPlayMode = computed(() => Boolean(auth.value?.user?.staff_play_mode));
const playerModeNotice = computed(() => String(auth.value?.user?.player_mode_notice || '').trim());
const isDoopLabPage = computed(() => String(page.url || '').startsWith('/dooplab'));
const isProfilePage = computed(() => {
    const currentRoute = route().current();
    return currentRoute === 'profile.dashboard'
        || currentRoute === 'profile.edit'
        || String(currentRoute || '').startsWith('profile.creations');
});
const isInventoryPage = computed(() => route().current() === 'inventory.index');
const isShopPage = computed(() => route().current() === 'shop.index');
const isHallPage = computed(() => String(route().current() || '').startsWith('hall.creations.'));
const isSubmissionPage = computed(() => String(page.url || '').split('?')[0].startsWith('/submissions/'));
const isLobbyDetailPage = computed(() => {
    const path = String(page.url || '').split('?')[0];
    return path === '/quests-user'
        || path.startsWith('/quests/')
        || path.startsWith('/submissions/')
        || isProfilePage.value
        || isInventoryPage.value
        || isShopPage.value
        || isHallPage.value
        || path === '/guides'
        || path.startsWith('/guides/')
        || path === '/events'
        || path.startsWith('/events/')
        || path === '/study-groups'
        || path.startsWith('/study-groups/');
});
const showStaffPlayModeNotice = computed(() => isStaffPlayMode.value && !isDoopLabPage.value);
const { themeMode } = useUserTheme();
const userBackgroundImage = computed(() => (
    themeMode.value === 'light' && isLobbyDetailPage.value ? '/images/bg-loby5.png' : '/images/bg-loby.png'
));
const userBackgroundOverlayClass = computed(() => (
    themeMode.value === 'light' && isLobbyDetailPage.value ? 'bg-[#f7f7f7]/92' : 'bg-black/65'
));
const userBackgroundGlowClass = computed(() => (
    themeMode.value === 'light' && isLobbyDetailPage.value
        ? 'bg-[radial-gradient(circle_at_16%_18%,rgba(0,153,153,0.14),transparent_32%),radial-gradient(circle_at_84%_12%,rgba(32,32,32,0.08),transparent_30%)]'
        : 'bg-[radial-gradient(circle_at_18%_20%,rgba(34,211,238,0.18),transparent_34%),radial-gradient(circle_at_82%_14%,rgba(45,212,191,0.14),transparent_30%),linear-gradient(180deg,rgba(2,6,23,0.16),rgba(2,6,23,0.4))]'
));
const usesInlineWorkspaceBackground = computed(() => (
    themeMode.value === 'light' && (
        isProfilePage.value
        || isInventoryPage.value
        || isShopPage.value
        || isHallPage.value
        || isSubmissionPage.value
    )
));
const inlineWorkspaceBackgroundStyle = {
    backgroundImage: "linear-gradient(rgba(247,247,247,0.18), rgba(247,247,247,0.18)), url('/images/bg-loby5.png')",
};
</script>

<template>
    <Head>
        <meta head-key="robots" name="robots" content="noindex,nofollow" />
    </Head>

    <div
        data-app-surface="user"
        :data-theme="themeMode"
        class="user-theme-root min-h-screen font-['Press_Start_2P'] selection:bg-[var(--accent)] relative isolate overflow-x-hidden flex flex-col"
        :class="{
            'user-theme-root--dooplab': isDoopLabPage,
            'user-theme-root--lobby-detail': isLobbyDetailPage,
        }"
    >
        <div
            v-if="usesInlineWorkspaceBackground"
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 z-0 bg-cover bg-center bg-no-repeat"
            :style="inlineWorkspaceBackgroundStyle"
        />

        <AppBackgroundLayer
            v-if="!isDoopLabPage && !usesInlineWorkspaceBackground"
            :image="userBackgroundImage"
            :overlay-class="userBackgroundOverlayClass"
            :glow-class="userBackgroundGlowClass"
        />

        <UserNavbar class="relative z-20" />

        <div v-if="isEmailUnverified" class="relative z-20 px-4 md:px-8 pt-4">
            <div class="border-2 border-amber-400/60 bg-amber-500/10 p-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="text-[9px] leading-relaxed text-amber-100 uppercase tracking-wide">
                    Email belum terverifikasi. Buka profile untuk kirim ulang verifikasi, lalu cek inbox/spam.
                </div>
                <Link
                    :href="profileVerificationHref"
                    class="text-[8px] bg-amber-300 text-black px-3 py-2 btn-pixel border-amber-700 uppercase font-bold hover:bg-amber-200 transition-colors text-center"
                >
                    Buka Profile
                </Link>
            </div>
        </div>
        <div v-else-if="isEmailVerifiedSuccess" class="relative z-20 px-4 md:px-8 pt-4">
            <div class="border-2 border-emerald-400/60 bg-emerald-500/15 p-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="text-[9px] leading-relaxed text-emerald-100 uppercase tracking-wide">
                    Verifikasi email berhasil. Semua fitur akun sekarang sudah terbuka.
                </div>
                <Link
                    :href="route('lobby')"
                    class="text-[8px] bg-emerald-300 text-black px-3 py-2 btn-pixel border-emerald-700 uppercase font-bold hover:bg-emerald-200 transition-colors text-center"
                >
                    Ke Home User
                </Link>
            </div>
        </div>
        <div v-if="showStaffPlayModeNotice" class="relative z-20 px-4 md:px-8 pt-4">
            <div class="border-2 border-cyan-400/60 bg-cyan-500/10 p-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="text-[9px] leading-relaxed text-cyan-100 uppercase tracking-wide">
                    {{ playerModeNotice || 'Mode preview aktif. Reward, leaderboard, dan akses kelas student tidak dihitung.' }}
                </div>
                <Link
                    :href="route('admin.dashboard')"
                    class="text-[8px] bg-cyan-300 text-black px-3 py-2 btn-pixel border-cyan-700 uppercase font-bold hover:bg-cyan-200 transition-colors text-center"
                >
                    Kembali ke Admin
                </Link>
            </div>
        </div>

        <main
            class="relative z-10 animate-in fade-in zoom-in-95 duration-500 flex-1"
            :class="isDoopLabPage ? 'p-0' : 'p-4 md:p-8'"
        >
            <slot />
        </main>
        <footer v-if="!isDoopLabPage" class="user-theme-footer relative z-10 mt-auto border-t-2 p-6 text-center backdrop-blur-md md:p-8">
            <p class="user-theme-muted break-words text-[7px] uppercase tracking-[0.18em] sm:text-[8px] sm:tracking-[0.3em]">Build_Ver_1.1.0 // P-Quest Engine</p>
        </footer>

        <FloatingChat v-if="showFloatingChat" />
    </div>
</template>

<style scoped>
/* Pastikan font pixelated untuk logo */
.pixelated {
    image-rendering: pixelated;
}

/* Button Style */
.btn-pixel {
    border-bottom-width: 4px;
    border-right-width: 4px;
}

.btn-pixel:active {
    border-bottom-width: 0px;
    border-right-width: 0px;
    transform: translate(2px, 2px);
}

/* Animasi Fade In untuk perpindahan halaman */
.animate-in {
    animation-fill-mode: forwards;
}

@media (max-width: 768px) {
    .user-theme-root--dooplab :deep(.user-navbar-shell) {
        min-height: 52px;
        padding: 8px 12px !important;
        border-bottom-width: 2px !important;
        box-shadow: 0 8px 18px rgba(0, 0, 0, 0.24) !important;
    }

    .user-theme-root--dooplab :deep(.user-navbar-brand-logo) {
        width: 34px !important;
        height: 34px !important;
        border-bottom-width: 2px !important;
        border-right-width: 2px !important;
    }

    .user-theme-root--dooplab :deep(.user-navbar-brand-title) {
        font-size: 7px !important;
        letter-spacing: 0 !important;
    }

    .user-theme-root--dooplab :deep(.user-navbar-mobile-toggle) {
        width: 34px !important;
        height: 34px !important;
    }
}
</style>
