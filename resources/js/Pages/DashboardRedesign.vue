<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    stats: Object,
    students: Object,
    helpUsers: { type: Array, default: () => [] },
    jobCommandItems: { type: Array, default: () => [] },
});

const page = usePage();
const currentRole = computed(() => String(page.props?.auth?.user?.role || '').toLowerCase());
const isSuperAdmin = computed(() => currentRole.value === 'super_admin');
const tab = ref('all');
const students = computed(() => props.students?.data || []);
const links = computed(() => props.students?.links || []);

const metrics = computed(() => isSuperAdmin.value
    ? [
        ['Jobs', props.stats?.total_jobs || 0, 'teal'],
        ['Study groups', props.stats?.total_study_groups || 0, 'teal'],
        ['Students', props.stats?.total_students || 0, 'teal'],
        ['Pending reviews', props.stats?.pending_verdicts || 0, 'danger'],
        ['System health', props.stats?.system_health === 'healthy' ? 'Healthy' : 'Attention', 'health'],
    ]
    : [
        ['Materi', props.stats?.total_materi || 0, 'teal'],
        ['Students', props.stats?.total_students || 0, 'teal'],
        ['Pending reviews', props.stats?.pending_verdicts || 0, 'danger'],
        ['Avg grade 30d', `${props.stats?.avg_grade_30d ?? 0}%`, 'teal'],
        ['Graded 7d', props.stats?.graded_7d || 0, 'teal'],
    ]);
</script>

<template>
    <Head title="Admin dashboard" />
    <main class="dashboard-shell">
        <AdminNavbar />

        <section class="metric-grid" aria-label="Dashboard summary">
            <article v-for="metric in metrics" :key="metric[0]" class="metric-card">
                <p class="metric-label">{{ metric[0] }}</p>
                <p class="metric-number" :class="`metric-${metric[2]}`">{{ metric[1] }}</p>
                <p v-if="metric[0] === 'System health'" class="metric-caption">{{ props.stats?.system_errors_24h || 0 }} errors / 24h</p>
            </article>
        </section>

        <section v-if="isSuperAdmin" class="surface-panel">
            <header class="panel-header">
                <div>
                    <h2>Job command</h2>
                    <p>Super admin mulai dari job/program, lalu masuk ke study group dan operasional kelas.</p>
                </div>
                <Link :href="route('admin.jobs.index')" class="outline-button">Manage jobs</Link>
            </header>
            <div v-if="props.jobCommandItems.length" class="job-grid">
                <Link v-for="job in props.jobCommandItems" :key="job.id" :href="job.detail_url" class="job-card">
                    <div class="job-card-head"><strong>{{ job.name }}</strong><span>Open</span></div>
                    <p class="job-slug">{{ job.slug }}</p>
                    <div class="job-meta"><span>Groups {{ job.study_groups_count }}</span><span>Users {{ job.users_count }}</span><span>Banks {{ job.task_banks_count }}</span></div>
                </Link>
            </div>
            <p v-else class="empty-state">Belum ada job/program.</p>
        </section>

        <section class="surface-panel performance-panel">
            <header class="panel-header">
                <div><h2>Student performance</h2><p>Monitor progress siswa berdasarkan nilai dan aktivitas quest.</p></div>
                <div class="tabs"><button :class="{ active: tab === 'all' }" @click="tab = 'all'">All students</button><button :class="{ active: tab === 'help' }" @click="tab = 'help'">Need help</button></div>
            </header>
            <div class="table-wrap">
                <table v-if="tab === 'all'"><thead><tr><th>Student</th><th>Lvl</th><th>Quests done</th><th class="align-right">Avg grade</th></tr></thead><tbody><tr v-for="student in students" :key="student.id"><td>{{ student.name }}</td><td>{{ student.lvl }}</td><td>{{ student.total_completed }}</td><td class="align-right grade">{{ student.avg_grade }}%</td></tr><tr v-if="!students.length"><td colspan="4" class="empty-state">No students found.</td></tr></tbody></table>
                <table v-else><thead><tr><th>Student</th><th>Lvl</th><th>Graded 30d</th><th class="align-right">Avg 30d</th></tr></thead><tbody><tr v-for="student in props.helpUsers" :key="student.id"><td>{{ student.name }}</td><td>{{ student.lvl }}</td><td>{{ student.graded_count_30d }}</td><td class="align-right grade danger-text">{{ student.avg_grade_30d }}%</td></tr><tr v-if="!props.helpUsers.length"><td colspan="4" class="empty-state">No students flagged.</td></tr></tbody></table>
            </div>
            <div v-if="links.length" class="pagination"><button v-for="(link, index) in links" :key="index" :disabled="!link.url" :class="{ active: link.active }" @click="link.url && router.get(link.url, {}, { preserveScroll: true, preserveState: true })" v-html="link.label" /></div>
        </section>
    </main>
</template>

<style scoped>
.dashboard-shell { min-height: 100vh; background: #f4f7f7; color: #173b40; padding: 28px clamp(16px, 4vw, 54px) 64px; }
.metric-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 24px; margin: 50px 0; }
.metric-card, .surface-panel { background: #fff; border: 1px solid #d8e4e6; border-radius: 22px; box-shadow: 0 12px 30px rgba(22, 66, 72, .06); }
.metric-card { min-height: 212px; padding: 34px; }
.metric-label { color: #637b8f; font-size: 18px; letter-spacing: .12em; text-transform: uppercase; }
.metric-number { margin-top: 23px; font-size: 52px; line-height: 1; font-weight: 800; }
.metric-teal { color: #008b83; }.metric-danger { color: #db5a50; }.metric-health { color: #2acb91; font-size: 44px; }.metric-caption { margin-top: 20px; color: #637b8f; font-size: 17px; }
.surface-panel { padding: 40px; margin-bottom: 28px; }.panel-header { display:flex; align-items:flex-start; justify-content:space-between; gap:24px; margin-bottom:30px; }.panel-header h2 { color:#44d8ef; font-size:20px; letter-spacing:.12em; text-transform:uppercase; }.panel-header p { color:#637b8f; margin-top:12px; font-size:13px; text-transform:uppercase; }.outline-button { border:1px solid #40d3ec; color:#40d3ec; padding:14px 20px; text-transform:uppercase; font-size:12px; }.job-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; }.job-card { background:#686d79; color:white; border:1px solid #303746; padding:28px; }.job-card-head { display:flex; justify-content:space-between; gap:16px; font-size:16px; }.job-card-head span { border:1px solid #35d0e8; color:#35d0e8; padding:8px 14px; font-size:11px; }.job-slug { color:#45d3eb; margin-top:16px; font-size:12px; }.job-meta { display:flex; justify-content:space-between; margin-top:30px; color:#aab7c9; font-size:12px; }.tabs { display:flex; gap:8px; }.tabs button, .pagination button { border:1px solid #c8d9dc; background:#fff; color:#637b8f; padding:10px 14px; text-transform:uppercase; font-size:11px; }.tabs button.active, .pagination button.active { border-color:#008b83; color:#008b83; background:#edf8f7; }.table-wrap { overflow-x:auto; } table { width:100%; border-collapse:collapse; } th, td { padding:18px 12px; text-align:left; border-bottom:1px solid #e5edef; } th { color:#637b8f; font-size:11px; text-transform:uppercase; letter-spacing:.1em; } td { font-size:14px; } .align-right { text-align:right; }.grade { color:#008b83; font-weight:700; }.danger-text { color:#db5a50; }.empty-state { padding:28px; text-align:center; color:#8b9ca1; }.pagination { display:flex; justify-content:flex-end; gap:8px; margin-top:20px; }
@media (max-width: 900px) { .metric-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .job-grid { grid-template-columns:1fr; } }
@media (max-width: 640px) { .dashboard-shell { padding:16px 12px 40px; }.metric-grid { grid-template-columns:1fr; gap:12px; margin:24px 0; }.metric-card { min-height:0; padding:24px; }.metric-number { font-size:40px; }.surface-panel { padding:20px; }.panel-header { flex-direction:column; }.job-meta { gap:10px; flex-wrap:wrap; }.tabs { width:100%; }.tabs button { flex:1; } }
</style>
