<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    hasAccess: { type: Boolean, default: false },
    telemetryStats: { type: Object, default: () => ({}) },
});

const page = usePage();
const userName = computed(() => page.props?.auth?.user?.name || 'Unknown');

const doorOpening = ref(false);

const enterLab = () => {
    router.visit(route('dooplab.dashboard'));
};

const labModules = [
    { title: 'WORKSPACE', icon: 'fi-rr-flask', status: 'ONLINE' },
    { title: 'MENTOR', icon: 'fi-rr-microscope', status: 'ONLINE' },
    { title: 'COLLAB', icon: 'fi-rr-link-alt', status: 'ONLINE' },
    { title: 'DOCS', icon: 'fi-rr-document', status: 'STANDBY' },
];
</script>

<template>
    <AuthenticatedLayout>
        <Head title="DOOPTECH | DoopLab" />

        <div class="lab-root">
            <!-- Background: Teleport ke body agar fixed tidak terjebak overflow:hidden -->
            <Teleport to="body">
                <div class="bg-layer">
                    <img src="/images/Gerbang_lab_pixel_art_website (3).jpeg" alt="" />
                </div>
                <div class="overlay"></div>
            </Teleport>

            <!-- HUD corners -->
            <div class="hud">
                <div class="hud-tl">
                    <span class="hud-text">DOOPLAB v2.1</span>
                </div>
                <div class="hud-br">
                    <span class="hud-text dim">{{ userName.substring(0, 12).toUpperCase() }}</span>
                </div>
            </div>

            <!-- Main content card -->
            <section class="gate-content">
                <div class="content-card">
                    <!-- Header -->
                    <div class="card-header">
                        <div class="blinker"></div>
                        <span>LABORATORY ENTRANCE</span>
                    </div>

                    <!-- Researcher ID -->
                    <div class="id-card" :class="{ granted: hasAccess }">
                        <div class="id-top">
                            <span class="id-label">RESEARCHER_ID</span>
                            <span :class="hasAccess ? 'text-emerald-400' : 'text-amber-400'">
                                {{ hasAccess ? '■ VERIFIED' : '□ PENDING' }}
                            </span>
                        </div>
                        <div class="id-body">
                            <div class="id-avatar">{{ userName.charAt(0).toUpperCase() }}</div>
                            <div>
                                <p class="id-name">{{ userName }}</p>
                                <p class="id-role">{{ hasAccess ? 'RESEARCHER' : 'VISITOR' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Action -->
                    <button v-if="hasAccess" @click="enterLab" class="btn-enter">
                        [ ENTER LABORATORY ]
                    </button>
                    <Link v-else :href="route('shop.index')" class="btn-unlock">
                        [ ACQUIRE ACCESS CARD ]
                    </Link>
                    <p v-if="!hasAccess" class="hint">Beli "Access Card" di Shop.</p>

                    <!-- Module status row -->
                    <div class="module-row">
                        <div v-for="m in labModules" :key="m.title" class="module-chip">
                            <i :class="['fi', m.icon]"></i>
                            <span>{{ m.title }}</span>
                            <span class="dot" :class="m.status === 'ONLINE' ? 'on' : 'standby'"></span>
                        </div>
                    </div>
                </div>

                <!-- Telemetry -->
                <div class="tele-row">
                    <div class="tele-chip">
                        <span class="tele-val">{{ telemetryStats.total_member || 0 }}</span>
                        <span class="tele-key">RESEARCHERS</span>
                    </div>
                    <div class="tele-chip">
                        <span class="tele-val">{{ telemetryStats.total_mentor || 0 }}</span>
                        <span class="tele-key">MENTORS</span>
                    </div>
                </div>
            </section>

            <!-- Back button -->
            <Link :href="route('profile.dashboard')" class="back-btn">← EXIT</Link>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.lab-root {
    --accent: #58f5e5;
    --accent-dim: rgba(88, 245, 229, 0.12);
    position: relative;
    min-height: 100vh;
    font-family: 'Press Start 2P', monospace;
    font-size: 8px;
    color: #d0e8e8;
    /* Hapus overflow:hidden — tidak diperlukan di sini dan menyebabkan fixed terjebak di mobile */
}

/* ===== BACKGROUND ===== */
.bg-layer {
    position: fixed; inset: 0; z-index: -2;
    height: 100dvh;
}
.bg-layer img {
    width: 100%; height: 100%;
    object-fit: cover; object-position: center;
    image-rendering: pixelated;
    transform: translateZ(0);
    backface-visibility: hidden;
    will-change: auto;
}
.overlay {
    position: fixed; inset: 0; z-index: -1;
    height: 100dvh;
    background: radial-gradient(ellipse at center, rgba(0,0,0,0.15) 0%, rgba(0,0,0,0.65) 100%);
}

/* ===== HUD ===== */
.hud { position: fixed; inset: 0; z-index: 5; pointer-events: none; padding: 1.2rem; }
.hud-tl { position: absolute; top: 1.2rem; left: 1.2rem; }
.hud-br { position: absolute; bottom: 1.2rem; right: 1.2rem; text-align: right; }
.hud-text { display: block; font-size: 7px; color: var(--accent); margin-bottom: 4px; }
.hud-text.dim { color: rgba(88, 245, 229, 0.4); }

/* ===== CONTENT ===== */
.gate-content {
    position: relative; z-index: 10;
    min-height: 100vh;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 2rem;
}

.content-card {
    background: rgba(2, 10, 20, 0.88);
    border: 2px solid var(--accent-dim);
    padding: 1.8rem;
    max-width: 360px; width: 100%;
    box-shadow: 0 0 30px rgba(88, 245, 229, 0.08), inset 0 0 30px rgba(0,0,0,0.5);
}

.card-header {
    display: flex; align-items: center; gap: 8px;
    font-size: 7px; color: var(--accent); margin-bottom: 1.5rem;
    border-bottom: 1px solid var(--accent-dim); padding-bottom: 10px;
}
.blinker {
    width: 6px; height: 6px; background: var(--accent);
    box-shadow: 0 0 6px var(--accent);
    animation: blink 1.5s infinite;
}
@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.2; } }

/* ===== ID CARD ===== */
.id-card {
    background: rgba(0,0,0,0.5); border: 1px solid rgba(88, 245, 229, 0.08);
    padding: 12px; margin-bottom: 1.2rem;
}
.id-card.granted { border-color: rgba(52, 211, 153, 0.3); }
.id-top { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 6px; }
.id-label { color: #5a7a8a; }
.id-body { display: flex; align-items: center; gap: 10px; }
.id-avatar {
    width: 32px; height: 32px;
    background: rgba(88, 245, 229, 0.08); border: 1px solid var(--accent);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; color: var(--accent);
}
.id-name { font-size: 9px; color: white; margin-bottom: 3px; }
.id-role { font-size: 7px; color: var(--accent); }

/* ===== BUTTONS ===== */
.btn-enter, .btn-unlock {
    display: block; width: 100%; text-align: center;
    padding: 14px; font-size: 9px;
    font-family: 'Press Start 2P', monospace;
    border: 2px solid var(--accent); color: var(--accent);
    background: rgba(88, 245, 229, 0.04);
    cursor: pointer; transition: 0.3s; text-decoration: none;
}
.btn-enter:hover {
    background: var(--accent); color: #000;
    box-shadow: 0 0 20px var(--accent), 0 0 40px rgba(88, 245, 229, 0.3);
}
.btn-unlock { border-color: #f59e0b; color: #f59e0b; background: rgba(245, 158, 11, 0.04); }
.btn-unlock:hover { background: #f59e0b; color: #000; box-shadow: 0 0 20px #f59e0b; }
.hint { text-align: center; color: #5a7a8a; margin-top: 8px; font-size: 7px; }

/* ===== MODULE ROW ===== */
.module-row {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px;
    margin-top: 1.2rem; padding-top: 1rem;
    border-top: 1px solid var(--accent-dim);
}
.module-chip {
    display: flex; flex-direction: column; align-items: center; gap: 4px;
    padding: 8px 4px; font-size: 6px; color: #7a9ab0;
}
.module-chip i { font-size: 12px; color: var(--accent); }
.dot { width: 4px; height: 4px; border-radius: 50%; }
.dot.on { background: #34d399; box-shadow: 0 0 4px #34d399; }
.dot.standby { background: #f59e0b; }

/* ===== TELEMETRY ===== */
.tele-row { display: flex; gap: 8px; margin-top: 1rem; }
.tele-chip {
    flex: 1; text-align: center;
    background: rgba(2, 10, 20, 0.8); border: 1px solid var(--accent-dim);
    padding: 10px;
}
.tele-val { display: block; font-size: 14px; color: var(--accent); margin-bottom: 4px; }
.tele-key { font-size: 6px; color: #5a7a8a; }

/* ===== BACK BTN ===== */
.back-btn {
    position: fixed; top: 1rem; left: 1rem; z-index: 20;
    font-size: 7px; color: var(--accent); padding: 6px 10px;
    border: 1px solid var(--accent-dim); background: rgba(0,0,0,0.6);
    text-decoration: none; transition: 0.3s;
}
.back-btn:hover { background: var(--accent); color: #000; }

/* ===== RESPONSIVE ===== */
@media (max-width: 520px) {
    .content-card { padding: 1.2rem; }
    .module-row { grid-template-columns: repeat(2, 1fr); }
    .hud { display: none; }
}

@media (max-width: 380px) {
    .gate-content {
        padding: 1rem 0.75rem;
    }

    .content-card {
        padding: 1rem;
    }

    .btn-enter, .btn-unlock {
        font-size: 7px;
        padding: 12px;
    }

    .id-name {
        font-size: 8px;
    }

    .tele-row {
        flex-direction: column;
    }

    .tele-chip {
        flex: unset;
    }
}
</style>
