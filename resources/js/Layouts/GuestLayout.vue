<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import AppBackgroundLayer from '@/Components/AppBackgroundLayer.vue';
import { useUserTheme } from '@/Composables/useUserTheme';
import { toast } from '@/Utils/Alert';

const page = usePage();

const auth = computed(() => page.props.auth || {});
const isStaff = computed(() => ['super_admin', 'admin', 'mentor'].includes(String(auth.value?.user?.role || '').toLowerCase()));
const { themeMode, setUserTheme } = useUserTheme();
const isThemeApplying = ref(false);
const pendingTheme = ref(null);
const themeActionLabel = computed(() => themeMode.value === 'light' ? 'Dark' : 'Light');
const themeActionIcon = computed(() => themeMode.value === 'light' ? 'fi-rr-moon' : 'fi-rr-sun');
const authBackgroundImage = computed(() => themeMode.value === 'light' ? '/images/bg-loby5.png' : '/images/bg-loby.png');
const authBackgroundOverlay = computed(() => themeMode.value === 'light' ? 'bg-white/28' : 'bg-black/60');

const waitForThemePaint = () => new Promise((resolve) => {
    requestAnimationFrame(() => requestAnimationFrame(resolve));
});

const applyNavbarTheme = async () => {
    if (isThemeApplying.value) return;

    const nextTheme = themeMode.value === 'light' ? 'dark' : 'light';
    pendingTheme.value = nextTheme;
    isThemeApplying.value = true;
    setUserTheme(nextTheme);

    await nextTick();
    await waitForThemePaint();
    await new Promise((resolve) => setTimeout(resolve, 300));

    pendingTheme.value = null;
    isThemeApplying.value = false;
};

const handleLogout = () => {
    toast.confirm('QUIT GAME?', 'Are you sure you want to exit?')
        .then((result) => {
            if (result.isConfirmed) {
                router.post(route('logout'), {}, {
                    preserveScroll: false,
                    preserveState: false,
                    replace: true,
                });
            }
        });
};
</script>

<template>
    <div
        data-app-surface="user"
        :data-theme="themeMode"
        class="auth-page-shell min-h-screen font-['Press_Start_2P'] selection:bg-[#009999] relative isolate overflow-x-hidden text-[var(--accent)]"
    >
        <AppBackgroundLayer
            :image="authBackgroundImage"
            :overlay-class="authBackgroundOverlay"
            :show-glow="themeMode !== 'light'"
        />

        <nav class="auth-page-navbar bg-[var(--panel)]/90 backdrop-blur-sm border-b-4 border-[var(--panel-border)] p-4 md:px-8 flex justify-between items-center shadow-2xl sticky top-0 z-50">
            <div class="flex items-center gap-4">
                <Link :href="route('lobby')" class="flex items-center gap-4 group">
                    <div class="w-10 h-10 bg-[var(--bg)] flex items-center justify-center border-b-4 border-r-4 border-[var(--accent)] overflow-hidden group-hover:scale-110 transition-transform">
                        <img src="/images/logo.png" alt="Logo" class="w-7 h-7 object-contain pixelated">
                    </div>
                    <h1 class="text-[var(--accent)] text-[8px] md:text-sm tracking-tighter uppercase group-hover:brightness-125">
                        DOOPTECH
                    </h1>
                </Link>
            </div>

            <div class="flex gap-2 md:gap-4 items-center">
                <button
                    type="button"
                    class="theme-nav-button text-[8px] px-3 py-2 btn-pixel uppercase font-bold transition-all"
                    :class="themeMode === 'dark' ? 'theme-nav-button--light' : 'theme-nav-button--dark'"
                    :aria-label="`Ubah tema ke ${themeActionLabel}`"
                    :title="`Ubah tema ke ${themeActionLabel}`"
                    :disabled="isThemeApplying"
                    @click="applyNavbarTheme"
                >
                    <i :class="['fi', themeActionIcon, 'text-[10px]', 'leading-none']"></i>
                    <span class="hidden sm:inline">{{ themeActionLabel }}</span>
                </button>

                <template v-if="auth.user">
                    <Link v-if="isStaff" :href="route('admin.dashboard')"
                        class="text-[8px] bg-purple-600/80 text-white px-3 py-2 btn-pixel border-purple-900 uppercase font-bold hover:bg-purple-500 transition-colors">
                        Admin
                    </Link>

                    <Link :href="route('profile.dashboard')"
                        class="text-[8px] bg-[var(--panel-border)]/80 text-[var(--text)] px-3 py-2 btn-pixel border-[var(--panel)] uppercase font-bold hover:brightness-125 transition-colors">
                        Profile
                    </Link>

                    <button type="button" @click="handleLogout"
                        class="text-[8px] bg-red-900/80 text-white px-3 py-2 btn-pixel border-red-950 uppercase font-bold hover:bg-red-700 transition-colors">
                        [X]
                    </button>
                </template>

                <template v-else>
                    <Link :href="route('login')"
                        class="text-[8px] bg-[#009999] text-black px-4 py-2 btn-pixel border-[#006666] uppercase font-bold hover:brightness-125 transition-all">
                        Login
                    </Link>
                    <Link :href="route('register')"
                        class="text-[8px] bg-[#facc15] text-black px-4 py-2 btn-pixel border-[#854d0e] uppercase font-bold hover:bg-yellow-400 transition-all">
                        Register
                    </Link>
                </template>
            </div>
        </nav>

        <main class="auth-page-main flex flex-col items-center justify-center min-h-[calc(100vh-80px)] p-6 relative z-10">
            <div class="auth-page-prompt flex flex-col items-center mb-6">
                <h2 class="text-[var(--accent)] text-[10px] tracking-[0.2em] animate-pulse">
                    > AUTHENTICATION_REQUIRED <
                </h2>
            </div>

            <div class="auth-page-card w-full sm:max-w-md p-8 rpg-panel shadow-[0_0_50px_rgba(0,0,0,0.9)] border-4 border-[var(--panel-border)] bg-[var(--panel)]/95 backdrop-blur-md">
                <slot />
            </div>
        </main>

        <footer class="auth-page-footer p-8 text-center bg-[var(--panel)]/50 backdrop-blur-md border-t-2 border-[var(--text)]/10 mt-auto">
            <p class="text-[8px] text-[var(--text)]/50 uppercase tracking-[0.3em]">Build_Ver_1.1.0 // P-Quest Engine</p>
        </footer>

        <Teleport to="body">
            <div
                v-if="isThemeApplying"
                data-app-surface="user"
                :data-theme="themeMode"
                class="fixed inset-0 z-[300] flex items-center justify-center bg-black/55 px-4 font-['Press_Start_2P']"
                role="status"
                aria-live="polite"
                aria-busy="true"
            >
                <div class="w-full max-w-sm border-2 border-[#009999] bg-[#202020] p-5 text-center shadow-[6px_6px_0_rgba(0,0,0,0.35)]">
                    <div class="mx-auto h-8 w-8 animate-spin border-4 border-[#f7f7f7]/25 border-t-[#009999]" />
                    <p class="mt-4 text-[9px] uppercase leading-relaxed text-[#f7f7f7]">
                        Applying_{{ pendingTheme || themeMode }}_Theme
                    </p>
                    <p class="mt-2 text-[7px] uppercase text-[#b9d4d4]">Synchronizing_Display...</p>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.rpg-panel {
    position: relative;
    box-shadow: 12px 12px 0px 0px rgba(0, 0, 0, 0.5);
}

.btn-pixel {
    border-bottom-width: 4px;
    border-right-width: 4px;
    border-style: solid;
}

.btn-pixel:active {
    border-bottom-width: 0px;
    border-right-width: 0px;
    transform: translate(2px, 2px);
}

.theme-nav-button {
    display: inline-flex;
    min-width: 42px;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.theme-nav-button--light {
    border-color: #9ca3af;
    background: #f7f7f7;
    color: #202020;
}

.theme-nav-button--light:hover {
    border-color: #009999;
    background: #ffffff;
    color: #006f6f;
}

.theme-nav-button--dark {
    border-color: #050505;
    background: #202020;
    color: #f7f7f7;
}

.theme-nav-button--dark:hover {
    border-color: #009999;
    background: #303030;
    color: #ffffff;
}

.theme-nav-button:disabled {
    cursor: wait;
    opacity: 0.65;
}

.pixelated {
    image-rendering: pixelated;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

.animate-pulse {
    animation: pulse 2s infinite;
}
</style>
