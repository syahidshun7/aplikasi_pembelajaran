<script setup>
import { computed } from 'vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    activeSkin: {
        type: Object,
        default: null,
    },
    stats: {
        type: Object,
        required: true,
    },
    classAverages: {
        type: Array,
        default: () => [],
    },
    creations: {
        type: Array,
        default: () => [],
    },
    profilePhotoUrl: {
        type: String,
        required: true,
    },
    hallOfCreationsUrl: {
        type: String,
        required: true,
    },
});

const config = computed(() => props.activeSkin?.config_json || {});
const theme = computed(() => config.value.theme || {});
const sections = computed(() => Array.isArray(config.value.sections) ? config.value.sections : []);
const hasSection = (type) => sections.value.length === 0 || sections.value.some((section) => section?.type === type);
const sectionConfig = (type) => sections.value.find((section) => section?.type === type) || {};
const creationLimit = computed(() => Number(sectionConfig('creations')?.limit || 6));
const classLimit = computed(() => Number(sectionConfig('classes')?.limit || 5));
const displayName = computed(() => props.user?.username || props.user?.name || 'Unknown Hero');
const levelProgress = computed(() => props.user?.level_progress || {});
const skills = computed(() => {
    if (Array.isArray(props.user?.skills)) {
        return props.user.skills.filter(Boolean);
    }

    return String(props.user?.skills || '')
        .split(',')
        .map((skill) => skill.trim())
        .filter(Boolean);
});
const roleLabel = computed(() => String(props.user?.role || 'Member').replaceAll('_', ' ').toUpperCase());
const shellStyle = computed(() => ({
    '--cfg-bg': theme.value.background || props.activeSkin?.hero_gradient || 'linear-gradient(135deg, #f8fafc 0%, #e0f2fe 100%)',
    '--cfg-surface': theme.value.surface || props.activeSkin?.stat_panel_bg || '#ffffff',
    '--cfg-accent': theme.value.accent || props.activeSkin?.accent_color || '#2563eb',
    '--cfg-border': theme.value.border || props.activeSkin?.border_color || '#cbd5e1',
    '--cfg-text': theme.value.text || props.activeSkin?.text_primary || '#0f172a',
    '--cfg-muted': theme.value.muted || '#64748b',
    '--cfg-radius': theme.value.radius || '24px',
    '--cfg-font': theme.value.font || 'Inter, Segoe UI, ui-sans-serif, system-ui, sans-serif',
}));
const statItems = computed(() => [
    { label: 'Average Grade', value: `${props.stats.averageGrade || 0}%` },
    { label: 'Quest Clear', value: props.stats.totalCompleted || 0 },
    { label: 'Creations', value: props.stats.creationCount || 0 },
    { label: 'Respect', value: props.stats.appreciationCount || 0 },
]);
</script>

<template>
    <section class="config-skin" :style="shellStyle">
        <div v-if="hasSection('hero')" class="config-hero">
            <div class="config-hero__copy">
                <p class="config-kicker">{{ config.kicker || activeSkin?.name || 'Profile Skin' }}</p>
                <h1>{{ displayName }}</h1>
                <p class="config-bio">
                    {{ user.bio || user.experience || 'Profil publik ini dirender lewat config skin ringan tanpa iframe.' }}
                </p>
                <div class="config-pills">
                    <span>{{ roleLabel }}</span>
                    <span v-if="user.job_name">{{ user.job_name }}</span>
                    <span v-if="user.location">{{ user.location }}</span>
                </div>
            </div>

            <div class="config-avatar-card">
                <img :src="profilePhotoUrl" :alt="displayName" class="config-avatar">
                <div class="config-level">
                    <span>{{ levelProgress.title || 'Level' }}</span>
                    <strong>{{ user.lvl || levelProgress.level || 1 }}</strong>
                </div>
            </div>
        </div>

        <div v-if="hasSection('stats')" class="config-stats">
            <article v-for="stat in statItems" :key="stat.label">
                <span>{{ stat.label }}</span>
                <strong>{{ stat.value }}</strong>
            </article>
        </div>

        <div class="config-grid">
            <article v-if="hasSection('skills')" class="config-panel">
                <div class="config-panel__head">
                    <span>Skills</span>
                </div>
                <div v-if="skills.length > 0" class="config-tags">
                    <span v-for="skill in skills" :key="skill">{{ skill }}</span>
                </div>
                <p v-else class="config-empty">Belum ada skill publik.</p>
            </article>

            <article v-if="hasSection('classes')" class="config-panel">
                <div class="config-panel__head">
                    <span>Class Progress</span>
                </div>
                <div v-if="classAverages.length > 0" class="config-list">
                    <div
                        v-for="item in classAverages.slice(0, classLimit)"
                        :key="`${item.study_group_id ?? 'general'}-${item.class_name}`"
                        class="config-row"
                    >
                        <strong>{{ item.class_name }}</strong>
                        <span>{{ item.average_grade || 0 }}% / {{ item.completed_quests || 0 }} clear</span>
                    </div>
                </div>
                <p v-else class="config-empty">Belum ada data kelas.</p>
            </article>

            <article v-if="hasSection('creations')" class="config-panel config-panel--wide">
                <div class="config-panel__head">
                    <span>Public Creations</span>
                    <a :href="hallOfCreationsUrl">Open Hall</a>
                </div>
                <div v-if="creations.length > 0" class="config-creations">
                    <article
                        v-for="creation in creations.slice(0, creationLimit)"
                        :key="creation.id"
                    >
                        <img v-if="creation.thumbnail_url" :src="creation.thumbnail_url" :alt="creation.title">
                        <div>
                            <strong>{{ creation.title || 'Untitled Creation' }}</strong>
                            <p>{{ creation.description || creation.content || 'No description.' }}</p>
                            <span>{{ creation.appreciations_count || 0 }} respect / {{ creation.insights_count || 0 }} insight</span>
                        </div>
                    </article>
                </div>
                <p v-else class="config-empty">Belum ada creation publik.</p>
            </article>
        </div>
    </section>
</template>

<style scoped>
.config-skin {
    min-height: calc(100vh - 180px);
    padding: clamp(18px, 4vw, 56px);
    background: var(--cfg-bg);
    color: var(--cfg-text);
    font-family: var(--cfg-font);
}

.config-hero,
.config-panel,
.config-stats article {
    border: 1px solid color-mix(in srgb, var(--cfg-border) 82%, transparent);
    border-radius: var(--cfg-radius);
    background: color-mix(in srgb, var(--cfg-surface) 88%, transparent);
    box-shadow: 0 24px 80px rgba(15, 23, 42, 0.12);
    backdrop-filter: blur(18px);
}

.config-hero {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(220px, 340px);
    gap: clamp(24px, 5vw, 64px);
    align-items: center;
    min-height: 420px;
    padding: clamp(24px, 5vw, 72px);
}

.config-kicker,
.config-panel__head,
.config-stats span,
.config-level span {
    color: var(--cfg-accent);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}

.config-hero h1 {
    margin: 0;
    max-width: 900px;
    color: var(--cfg-text);
    font-size: clamp(46px, 9vw, 112px);
    line-height: 0.92;
    letter-spacing: -0.04em;
}

.config-bio {
    max-width: 760px;
    color: var(--cfg-muted);
    font-size: clamp(16px, 2vw, 22px);
    line-height: 1.75;
}

.config-pills,
.config-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.config-pills span,
.config-tags span {
    border: 1px solid color-mix(in srgb, var(--cfg-border) 80%, transparent);
    border-radius: 999px;
    background: color-mix(in srgb, var(--cfg-surface) 92%, transparent);
    padding: 10px 14px;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

.config-avatar-card {
    position: relative;
    display: grid;
    place-items: center;
    min-height: 330px;
}

.config-avatar {
    width: min(68vw, 240px);
    aspect-ratio: 1;
    border: 8px solid color-mix(in srgb, var(--cfg-surface) 96%, white);
    border-radius: 50%;
    object-fit: cover;
    box-shadow: 0 26px 70px color-mix(in srgb, var(--cfg-accent) 26%, transparent);
}

.config-level {
    position: absolute;
    right: 0;
    bottom: 24px;
    min-width: 112px;
    border: 1px solid color-mix(in srgb, var(--cfg-border) 80%, transparent);
    border-radius: 18px;
    background: color-mix(in srgb, var(--cfg-surface) 94%, transparent);
    padding: 14px 16px;
}

.config-level strong {
    display: block;
    color: var(--cfg-accent);
    font-size: 46px;
    line-height: 1;
}

.config-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-top: 16px;
}

.config-stats article {
    min-height: 128px;
    padding: 20px;
}

.config-stats strong {
    display: block;
    margin-top: 16px;
    font-size: clamp(30px, 4vw, 50px);
    line-height: 1;
}

.config-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-top: 16px;
}

.config-panel {
    padding: 22px;
}

.config-panel--wide {
    grid-column: 1 / -1;
}

.config-panel__head {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}

.config-panel__head a {
    color: var(--cfg-accent);
    text-decoration: none;
}

.config-list {
    display: grid;
    gap: 10px;
}

.config-row {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    border: 1px solid color-mix(in srgb, var(--cfg-border) 76%, transparent);
    border-radius: 16px;
    padding: 14px;
}

.config-row span,
.config-empty,
.config-creations p,
.config-creations span {
    color: var(--cfg-muted);
}

.config-creations {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.config-creations article {
    overflow: hidden;
    border: 1px solid color-mix(in srgb, var(--cfg-border) 76%, transparent);
    border-radius: 18px;
    background: color-mix(in srgb, var(--cfg-surface) 96%, white);
}

.config-creations img {
    display: block;
    width: 100%;
    aspect-ratio: 16 / 9;
    object-fit: cover;
}

.config-creations div {
    padding: 14px;
}

.config-creations strong {
    display: block;
}

.config-creations p {
    display: -webkit-box;
    min-height: 42px;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

@media (max-width: 900px) {
    .config-hero,
    .config-grid,
    .config-creations {
        grid-template-columns: 1fr;
    }

    .config-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 560px) {
    .config-skin {
        padding: 14px;
    }

    .config-stats {
        grid-template-columns: 1fr;
    }
}
</style>
