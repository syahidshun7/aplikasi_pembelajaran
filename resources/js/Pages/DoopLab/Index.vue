<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    telemetryStats: {
        type: Object,
        default: () => ({}),
    },
});

const telemetry = computed(() => [
    { label: 'SYSTEM_CORE', value: 'OFFLINE', color: 'text-rose-400' },
    { label: 'TOTAL_MEMBER', value: String(Number(props.telemetryStats?.total_member ?? 0)), color: 'text-cyan-400' },
    { label: 'TOTAL_MENTOR', value: String(Number(props.telemetryStats?.total_mentor ?? 0)), color: 'text-purple-400' },
]);

const modules = [
    { title: 'THE WORKSPACE', desc: 'Workspace untuk eksperimen & project.', icon: 'fi-rr-browser' },
    { title: 'MENTOR HUB', desc: 'Akses ke mentor dan peneliti.', icon: 'fi-rr-user-gear' },
    { title: 'KOLABORASI', desc: 'Kolaborasi dengan member lain.', icon: 'fi-rr-users-alt' },
    { title: 'DOKUMENTASI IDE', desc: 'Dokumentasi & pengembangan ide.', icon: 'fi-rr-folder' },
];

const page = usePage();
const canAccessDoopLab = computed(() => Boolean(page.props?.auth?.user?.can_access_dooplab));
</script>

<template>
    <AuthenticatedLayout>
        <Head title="DOOPTECH | DoopLab">
            <meta head-key="description" name="description" content="DoopLab merupakan area internal eksperimen milik DOOPTECH. Untuk informasi publik, kunjungi DOOPTECH." />
            <meta head-key="robots" name="robots" content="noindex,nofollow,noarchive" />
            <link head-key="canonical" rel="canonical" :href="route('landing')" />
        </Head>

        <div class="dooplab-root">
            
            <div class="main-content">
                <div class="top-nav">
                    <Link :href="route('profile.dashboard')" class="back-btn-premium">
                        <i class="fi fi-rr-arrow-left"></i>
                        BACK_TO_ADMIN
                    </Link>
                </div>

                <section class="showcase-zone">
                    <div class="showcase-inner">
                        
                        <div class="visual-stage">
                            <div class="hud-corner top-left"></div>
                            <div class="hud-corner bottom-right"></div>
                            
                                <div class="hologram-visual-core">
                                    <div class="target-object">
                                        <div class="core-glow"></div>
                                        <div class="lab-logo-shell">
                                            <div class="lab-logo-aura"></div>
                                            <div class="lab-logo-plate"></div>
                                            <img
                                                src="/images/logo%20-dooplab.png"
                                                alt="DoopLab Logo"
                                                class="lab-logo-core"
                                            >
                                        </div>
                                    </div>
                                
                                <div class="hologram-rings">
                                    <div class="ring r1"></div>
                                    <div class="ring r2"></div>
                                    <div class="scan-beam"></div>
                                </div>

                                <div class="data-particles">
                                    <span class="p1">101</span>
                                    <span class="p2">RUN</span>
                                    <span class="p3">ERR_0</span>
                                </div>
                            </div>
                        </div>

                        <div class="text-stage">
                            <div class="text-inner">
                                <p class="eyebrow">{{ canAccessDoopLab ? 'LABORATORY ACCESS GRANTED' : 'LABORATORY ACCESS LIMITED' }}</p>
                                <h1 class="title">DOOPLAB <br><span class="subtitle">(DOOPTECH LABORATORY)</span></h1>
                                <p class="description font-sans">
                                    Ruang kurasi kolaboratif untuk bereksperimen, membangun proyek inovatif, kreatif, dan kolaboratif dari komunitas kami. Pamerkan karya laboratorium Anda di ekosistem digital.
                                </p>
                                
                                <div class="telemetry-bar">
                                    <div v-for="t in telemetry" :key="t.label" class="tele-chip">
                                        <span class="tele-label">{{ t.label }}</span>
                                        <span class="tele-val" :class="t.color">{{ t.value }}</span>
                                    </div>
                                </div>

                                <div class="action-zone">
                                    <Link v-if="canAccessDoopLab" :href="route('dooplab.dashboard')" class="btn-premium-hologram">
                                        MASUK DASHBOARD ->
                                    </Link>
                                    <Link v-else :href="route('shop.index')" class="btn-premium-hologram">
                                        UNLOCK DOOPLAB ->
                                    </Link>
                                    <p v-if="!canAccessDoopLab" class="unlock-note font-sans">
                                        Akses eksperimen penuh tersedia untuk member DoopLab.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="modules-section pb-10">
                    <div class="section-header">
                        <h2 class="section-title">LAB_MODULES</h2>
                    </div>
                    <div class="features-grid">
                        <div v-for="m in modules" :key="m.title" class="module-card">
                            <div class="card-glow"></div>
                            <div class="card-icon-box">
                                <i :class="['fi', m.icon]"></i>
                            </div>
                            <h3 class="card-title">{{ m.title }}</h3>
                            <p class="card-desc font-sans">{{ m.description }}</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Inter:wght@300;400;600&display=swap');

.dooplab-root {
    --neon-teal: #58f5e5;
    --neon-purple: #9d50bb;
    /* Menghapus warna background gelap agar transparan */
    
    --pixel-font: 'Press Start 2P', cursive;
    
    position: relative;
    color: #ffffff;
    font-family: var(--pixel-font);
    padding: 1rem;
    min-height: 100%;
    z-index: 1; /* Memastikan overlay berada di atas BG asli */
}

.main-content { position: relative; z-index: 10; max-width: 1200px; margin: 0 auto; }

/* Premium Back Button */
.top-nav { display: flex; justify-content: flex-start; margin-bottom: 2rem; }
.back-btn-premium {
    display: flex; align-items: center; gap: 8px;
    color: var(--neon-teal); font-size: 8px;
    padding: 10px 15px; border: 1px solid var(--neon-teal);
    background: rgba(88, 245, 229, 0.05);
    backdrop-filter: blur(4px); /* Sedikit blur agar kontras di atas BG asli */
    transition: 0.3s;
    text-decoration: none;
}
.back-btn-premium:hover {
    background: var(--neon-teal); color: #000;
    box-shadow: 0 0 15px var(--neon-teal);
}

/* Showcase Zone (Non-Linear Layout) */
.showcase-zone { margin-bottom: 3rem; }
.showcase-inner { display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; }

/* Fluid Holographic Visual */
.visual-stage {
    position: relative; height: 480px;
    background: rgba(0,0,0,0.3); /* Panel semi-transparan */
    border: 1px solid rgba(88, 245, 229, 0.1);
    backdrop-filter: blur(8px); /* Menjaga keterbacaan HUD di atas BG asli */
    display: flex; justify-content: center; align-items: center;
}
.hud-corner { position: absolute; width: 15px; height: 15px; border-color: var(--neon-teal); border-style: solid; opacity: 0.6; }
.top-left { top: 20px; left: 20px; border-width: 2px 0 0 2px; }
.bottom-right { bottom: 20px; right: 20px; border-width: 0 2px 2px 0; }

.hologram-visual-core { position: relative; width: 350px; height: 350px; }
.target-object { position: absolute; inset: 0; display: flex; justify-content: center; align-items: center; z-index: 10; }
.core-glow {
    position: absolute;
    inset: -34px;
    background: radial-gradient(circle, rgba(88, 245, 229, 0.6) 0%, rgba(88, 245, 229, 0.12) 42%, transparent 72%);
    opacity: 0.95;
    filter: blur(34px);
}
.lab-logo-shell {
    position: relative;
    width: 252px;
    height: 252px;
    border: 1px solid rgba(88, 245, 229, 0.24);
    border-radius: 48px;
    display: flex;
    justify-content: center;
    align-items: center;
    background:
        radial-gradient(circle at 50% 46%, rgba(88, 245, 229, 0.28), rgba(5, 26, 46, 0.36) 58%, rgba(3, 13, 28, 0.42) 100%),
        linear-gradient(180deg, rgba(88, 245, 229, 0.08), rgba(88, 245, 229, 0.02));
    box-shadow:
        inset 0 0 28px rgba(88, 245, 229, 0.15),
        0 0 24px rgba(88, 245, 229, 0.2);
    animation: floating 5s ease-in-out infinite;
    overflow: hidden;
}

.lab-logo-shell::before {
    content: '';
    position: absolute;
    inset: 10px;
    border: 1px solid rgba(88, 245, 229, 0.2);
    border-radius: 38px;
    pointer-events: none;
}

.lab-logo-shell::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background:
        linear-gradient(135deg, rgba(88, 245, 229, 0.12), transparent 36%),
        linear-gradient(315deg, rgba(88, 245, 229, 0.08), transparent 42%);
    pointer-events: none;
}

.lab-logo-aura {
    position: absolute;
    inset: -24px;
    border-radius: 56px;
    border: 1px dashed rgba(88, 245, 229, 0.3);
    animation: rotate-hologram 18s linear infinite;
    pointer-events: none;
}

.lab-logo-plate {
    position: absolute;
    inset: 26px;
    border-radius: 34px;
    border: none;
    background: radial-gradient(circle at 50% 52%, rgba(88, 245, 229, 0.28), rgba(14, 35, 56, 0.02) 70%);
    box-shadow: inset 0 0 24px rgba(88, 245, 229, 0.16);
    filter: blur(0.4px);
}

.lab-logo-core {
    width: 260px;
    height: 182px;
    object-fit: cover;
    object-position: center;
    transform: translateY(-4px);
    filter:
        drop-shadow(0 0 20px rgba(88, 245, 229, 0.95))
        drop-shadow(0 0 40px rgba(88, 245, 229, 0.55))
        drop-shadow(0 0 8px rgba(255, 255, 255, 0.28));
    image-rendering: -webkit-optimize-contrast;
    image-rendering: crisp-edges;
    z-index: 12;
}

.hologram-rings { position: absolute; inset: -42px; z-index: 5; }
.ring {
    position: absolute; inset: 0; border-radius: 50%;
    border: 1px solid rgba(88, 245, 229, 0.48);
    box-shadow: 0 0 18px rgba(88, 245, 229, 0.28);
    animation: rotate-hologram 12s linear infinite;
}
.r1 { transform: rotateX(65deg) rotateY(10deg); }
.r2 { transform: rotateX(-65deg) rotateY(-10deg); border-style: dashed; }
.scan-beam {
    position: absolute; top: 0; left: -26px; right: -26px; height: 6px;
    background: rgba(88, 245, 229, 0.65);
    filter: blur(4px) drop-shadow(0 0 8px var(--neon-teal));
    animation: scan-up-down 5s ease-in-out infinite;
    z-index: 4;
}

/* Floating Data Particles */
.data-particles { position: absolute; inset: 0; z-index: 20; pointer-events: none; }
.data-particles span { position: absolute; font-size: 6px; color: var(--neon-teal); opacity: 0.42; }
.p1 { top: 9%; left: 8%; animation: float-data 4s infinite; }
.p2 { top: 50%; right: 3%; animation: float-data 5s infinite 1s; }
.p3 { bottom: 14%; left: 13%; animation: float-data 6s infinite 2s; }

/* Text Stage & Telemetry Bar */
.text-stage { display: flex; align-items: center; }
.eyebrow { font-size: 8px; color: var(--neon-teal); margin-bottom: 12px; letter-spacing: 2px; }
.title { font-size: 1.6rem; line-height: 1.4; color: white; margin-bottom: 1.5rem; }
.subtitle { font-size: 0.9rem; color: #fff; opacity: 0.7; }
.description { font-family: 'Inter', sans-serif; font-size: 15px; line-height: 1.8; color: #d0d9e0; margin-bottom: 2.5rem; }

.telemetry-bar { display: flex; gap: 10px; margin-bottom: 3rem; flex-wrap: wrap; }
.tele-chip {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(88, 245, 229, 0.15);
    padding: 10px 15px; flex: 1; text-align: left;
    backdrop-filter: blur(4px);
}
.tele-label { display: block; font-size: 6px; color: #a0b0c0; margin-bottom: 8px; }
.tele-val { font-size: 9px; }

/* Premium Hologram Button */
.btn-premium-hologram {
    display: inline-block; padding: 1.25rem 2.5rem;
    background: transparent; border: 2px solid var(--neon-teal);
    color: white; font-size: 10px; cursor: pointer; transition: 0.3s;
    box-shadow: 0 0 15px rgba(88, 245, 229, 0.2);
    text-decoration: none;
}
.btn-premium-hologram:hover {
    background: rgba(88, 245, 229, 0.1);
    box-shadow: 0 0 30px var(--neon-teal);
    transform: translateY(-3px);
}

.unlock-note {
    margin-top: 12px;
    color: #c8d6e5;
    font-size: 12px;
}

/* Modules Section */
.modules-section { position: relative; z-index: 10; }
.section-header { display: flex; justify-content: center; margin-bottom: 2rem; }
.section-title { font-size: 12px; color: white; padding-bottom: 10px; border-bottom: 2px solid var(--neon-teal); }

.features-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
.module-card {
    background: rgba(7, 21, 38, 0.8); /* Semi-transparan agar menyatu dengan BG asli */
    border: 1px solid rgba(88, 245, 229, 0.2);
    padding: 2.5rem 1.5rem; text-align: center;
    backdrop-filter: blur(8px); /* Efek kaca di atas BG asli */
    transition: 0.3s;
    position: relative; overflow: hidden;
}
.card-glow { position: absolute; inset: 0; background: radial-gradient(var(--neon-teal), transparent 80%); opacity: 0; transition: 0.3s; }
.module-card:hover {
    border-color: var(--neon-teal);
    transform: translateY(-5px);
    background: rgba(88, 245, 229, 0.05);
}
.module-card:hover .card-glow { opacity: 0.1; }

.card-icon-box {
    width: 50px; height: 50px; margin: 0 auto 1.5rem;
    border: 1px solid var(--neon-teal);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 0 10px rgba(88, 245, 229, 0.2);
}
.card-icon-box i { font-size: 22px; color: var(--neon-teal); filter: drop-shadow(0 0 5px var(--neon-teal)); }
.card-title { font-size: 9px; margin-bottom: 10px; color: white; }
.card-desc { font-family: 'Inter', sans-serif; font-size: 13px; color: #a0b0c0; line-height: 1.5; }

/* Animations Keyframes */
@keyframes floating { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }
@keyframes rotate-hologram { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
@keyframes scan-up-down {
    0%, 100% { transform: translateY(0); opacity: 0.5; }
    50% { transform: translateY(350px); opacity: 1; }
}
@keyframes float-data {
    0% { transform: translateY(0) scale(1); opacity: 0; }
    20% { opacity: 0.6; }
    100% { transform: translateY(-30px) scale(1.1); opacity: 0; }
}

@media (max-width: 968px) {
    .showcase-inner { grid-template-columns: 1fr; gap: 2rem; }
    .features-grid { grid-template-columns: repeat(2, 1fr); }
    .visual-stage { height: 400px; }
    .hologram-visual-core { width: 280px; height: 280px; }
    .lab-logo-shell { width: 206px; height: 206px; border-radius: 36px; }
    .lab-logo-plate { inset: 20px; border-radius: 26px; }
    .lab-logo-core { width: 208px; height: 146px; }
    .hologram-rings { inset: -30px; }
}

@media (max-width: 520px) {
    .visual-stage { height: 350px; }
    .hologram-visual-core { width: 250px; height: 250px; }
    .lab-logo-shell { width: 186px; height: 186px; border-radius: 32px; }
    .lab-logo-plate { inset: 18px; border-radius: 22px; }
    .lab-logo-core { width: 188px; height: 132px; }
    .hologram-rings { inset: -26px; }
}
</style>

<style scoped>
/* DoopLab pixel/square typography override */
.dooplab-root {
    font-family: "Press Start 2P", Inter, sans-serif !important;
    font-size: 10px;
}

.dooplab-root *,
.dooplab-root *::before,
.dooplab-root *::after {
    border-radius: 0 !important;
}

.hero-shell,
.module-card,
.tele-chip,
.btn-premium-hologram,
.lab-logo-shell,
.card-icon-box {
    box-shadow: 4px 4px 0 rgba(1, 6, 14, 0.85) !important;
}

.eyebrow { font-size: 7px !important; }
.title { font-size: clamp(14px, 2.2vw, 18px) !important; line-height: 1.35 !important; }
.subtitle,
.description,
.unlock-note,
.card-desc { font-size: 9px !important; line-height: 1.6 !important; }

.btn-premium-hologram { font-size: 8px !important; padding: 10px 14px !important; }
.section-title { font-size: 10px !important; }
.card-title { font-size: 8px !important; }
.tele-label { font-size: 6px !important; }
.tele-val { font-size: 8px !important; }
</style>
