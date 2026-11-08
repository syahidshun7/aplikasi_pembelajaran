<script setup>
import RedesignTopbar from './RedesignTopbar.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminSidebar from './AdminSidebar.vue';

const props = defineProps({ group: Object, student: Object, summary: Object, questHistory: { type: Array, default: () => [] }, attendanceHistory: { type: Array, default: () => [] } });
const page = usePage();
const pageNumber = ref(1);
const pageSize = 8;
const pageCount = computed(() => Math.max(1, Math.ceil(props.questHistory.length / pageSize)));
const visibleQuests = computed(() => props.questHistory.slice((pageNumber.value - 1) * pageSize, pageNumber.value * pageSize));
watch(() => props.questHistory.length, () => { pageNumber.value = Math.min(pageNumber.value, pageCount.value); });
const questUrl = quest => quest.submission_uuid ? route('redesign.submissions.inspect', quest.submission_uuid) : route('redesign.quests.submissions', quest.uuid);
const openQuest = quest => router.visit(questUrl(quest));
const questStatus = quest => {
    if (!quest.attempts) return 'Belum mulai';
    const status = String(quest.status || '').toLowerCase();
    if (status.includes('approved')) return 'Selesai';
    if (status.includes('rejected')) return 'Ditolak';
    return 'Menunggu review';
};
const date = value => {
    if (!value) return 'Tanggal belum ditentukan';
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? 'Tanggal tidak valid' : new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(parsed);
};
const attendanceLabel = { present: 'Present', absent: 'Absent', excused: 'Excused', sick: 'Sick', pending: 'Pending' };
</script>

<template>
    <Head :title="`Performa ${student.name}`" />
    <div class="performance-app"><AdminSidebar /><main>
        <RedesignTopbar breadcrumb="Program &amp; kelas" />
        <div class="content"><div class="heading"><Link :href="route('redesign.study-groups.show', group.uuid)" class="back">← {{ group.name }}</Link><h1>Performa peserta</h1><p>{{ student.name }} · @{{ student.username || 'user' }} · {{ group.name }}</p></div>
            <section class="metrics" aria-label="Ringkasan performa"><div class="metric"><span>Peserta</span><strong class="student-name">{{ student.name }}</strong><i aria-hidden="true"></i></div><div class="metric"><span>Main avg</span><strong>{{ summary.main_quest_average ?? 0 }}</strong><i aria-hidden="true"></i></div><div class="metric"><span>Combined avg</span><strong>{{ summary.performance_average ?? 0 }}</strong><i aria-hidden="true"></i></div><div class="metric"><span>Attendance</span><strong>{{ summary.attendance_percentage ?? 0 }}%</strong><i aria-hidden="true"></i></div></section>
            <section class="panel"><h2>Seluruh quest study group</h2><div class="table-wrap"><table><thead><tr><th>Quest</th><th>Tipe</th><th>Attempts</th><th>Nilai</th><th>Status</th></tr></thead><tbody><tr v-for="quest in visibleQuests" :key="quest.uuid" class="clickable-row" tabindex="0" role="link" :aria-label="`Buka ${quest.title}`" @click="openQuest(quest)" @keydown.enter.prevent="openQuest(quest)" @keydown.space.prevent="openQuest(quest)"><td class="quest-title"><span>{{ quest.title }}</span><small>{{ quest.submission_uuid ? 'Lihat jawaban →' : 'Lihat submission →' }}</small></td><td><span class="type-badge" :class="quest.quest_type === 'main' ? 'main' : 'optional'">{{ quest.quest_type === 'main' ? 'Main' : 'Optional' }}</span></td><td>{{ quest.attempts }}</td><td><strong :class="{ muted: quest.grade == null }">{{ quest.grade ?? '—' }}</strong></td><td><span class="status-badge" :class="{ pending: !quest.attempts, rejected: questStatus(quest) === 'Ditolak', done: questStatus(quest) === 'Selesai' }">{{ questStatus(quest) }}</span></td></tr><tr v-if="!questHistory.length"><td colspan="5" class="empty">Belum ada quest di kelas ini.</td></tr></tbody></table></div><div class="table-footer"><span>{{ questHistory.length }} quest ditampilkan</span><div v-if="pageCount > 1" class="pager"><button type="button" :disabled="pageNumber === 1" @click="pageNumber--" aria-label="Halaman sebelumnya">‹</button><span>{{ pageNumber }} / {{ pageCount }}</span><button type="button" :disabled="pageNumber === pageCount" @click="pageNumber++" aria-label="Halaman berikutnya">›</button></div></div></section>
            <section class="history"><h2>Riwayat kehadiran</h2><div v-if="!attendanceHistory.length" class="no-history">Belum ada event di kelas ini.</div><ul v-else><li v-for="event in attendanceHistory" :key="event.uuid"><span>{{ event.title }}</span><time>{{ date(event.starts_at) }}</time><strong :class="event.status">{{ attendanceLabel[event.status] || 'Pending' }}</strong></li></ul><Link :href="route('redesign.study-groups.attendance', group.uuid)">Lihat rekap kelas →</Link></section>
        </div>
    </main></div>
</template>

<style scoped>
.performance-app{display:flex;min-height:100vh;background:#f5f7f8;color:#19333d;font-family:Inter,ui-sans-serif,system-ui,sans-serif}.performance-app main{flex:1;min-width:0}.topbar{height:64px;background:#fff;border-bottom:1px solid #dce6e8;padding:0 28px;display:flex;align-items:center;justify-content:space-between;color:#70858c;font-size:11px}.topbar strong,.topbar small{display:block}.topbar strong{color:#19333d;font-size:12px}.topbar small{font-size:10px}.content{max-width:1440px;margin:auto;padding:30px 26px 70px}.heading{margin-bottom:30px}.back{font-size:12px;font-weight:700;color:#007f78}.heading h1{font-size:30px;font-weight:750;letter-spacing:-.025em;margin:8px 0 0}.heading p{color:#70858c;font-size:13px;margin:8px 0 0}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:28px}.metric{min-height:130px;background:#fff;border:1px solid #dce6e8;border-radius:12px;padding:20px;display:flex;flex-direction:column;align-items:start}.metric span{font-size:13px;color:#70858c}.metric strong{font-size:28px;font-weight:750;margin-top:18px;line-height:1.1}.metric .student-name{font-size:21px;overflow-wrap:anywhere}.metric i{width:32px;height:3px;border-radius:2px;background:#008b83;margin-top:auto}.panel{background:#fff;border:1px solid #dce6e8;border-radius:12px;padding:24px;min-width:0}.panel h2,.history h2{font-size:18px;font-weight:750;margin:0 0 20px}.table-wrap{overflow-x:auto}table{width:100%;min-width:650px;border-collapse:collapse;font-size:12px}th{text-align:left;background:#f0f5f6;color:#70858c;padding:13px}td{padding:17px 13px;border-bottom:1px solid #e5edef}.quest-title{font-weight:750}.rejected{color:#b74336}.muted{color:#70858c}.empty{text-align:center;color:#70858c}.table-footer{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:22px;color:#70858c;font-size:11px}.pager{display:flex;align-items:center;gap:12px}.pager button{border:1px solid #dce6e8;border-radius:6px;background:#fff;color:#007f78;padding:5px 10px;cursor:pointer}.pager button:disabled{opacity:.4}.history{padding:32px 24px}.history ul{padding:0;list-style:none;margin:0 0 16px;max-width:780px}.history li{display:grid;grid-template-columns:minmax(0,1fr) 130px 100px;gap:12px;padding:11px 0;border-bottom:1px solid #dce6e8;font-size:13px}.history time{color:#70858c}.history strong{font-weight:700}.history strong.present{color:#008078}.history strong.absent{color:#cc4a4c}.history strong.excused{color:#2976a1}.history strong.sick{color:#966514}.history strong.pending{color:#70858c}.history>a{font-size:12px;color:#007f78;font-weight:700}.no-history{color:#70858c;font-size:13px;margin-bottom:18px}.performance-app :is(a,button):focus-visible{outline:2px solid #008b83;outline-offset:2px}@media(max-width:1100px){.metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:800px){.performance-app{display:block}}@media(max-width:600px){.content{padding:22px 14px}.topbar{padding:0 16px}.panel{padding:18px}.metrics{gap:10px}.metric{padding:16px;min-height:110px}.metric strong{font-size:24px}.metric .student-name{font-size:18px}.heading h1{font-size:25px}.history{padding:28px 4px}.history li{grid-template-columns:1fr auto}.history time{grid-column:1}.history strong{grid-column:2;grid-row:1/3}}
</style>

<style scoped>.clickable-row{cursor:pointer}.clickable-row:nth-child(even) td{background:#f8fbfb}.clickable-row:hover td,.clickable-row:focus-visible td{background:#f0f8f6}.clickable-row:focus-visible{outline:2px solid #00857c;outline-offset:-2px}.quest-title span{display:block;color:#19333d;text-decoration:underline;text-decoration-color:#9ccbc6;text-underline-offset:3px;overflow-wrap:anywhere}.quest-title small{display:block;margin-top:5px;font-size:10px;color:#00857c;font-weight:600}.type-badge,.status-badge{display:inline-block;border-radius:6px;padding:6px 9px;font-size:11px;font-weight:700}.type-badge.main,.status-badge.done{background:#e5f5f1;color:#007f78}.type-badge.optional{background:#eef3fb;color:#41689a}.status-badge.pending{background:#f0f3f4;color:#70858c}.status-badge.rejected{background:#fcecea;color:#b74336}</style>
