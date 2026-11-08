<script setup>
import RedesignTopbar from './RedesignTopbar.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminSidebar from './AdminSidebar.vue';

const props = defineProps({
    group: { type: Object, required: true },
    attendanceDashboard: { type: Object, default: () => ({ summary: {}, events: [], students: [] }) },
});
const page = usePage();
const events = computed(() => props.attendanceDashboard?.events || []);
const students = computed(() => props.attendanceDashboard?.students || []);
const counts = computed(() => students.value.reduce((total, student) => {
    for (const key of ['present', 'absent', 'excused', 'sick', 'pending']) total[key] += Number(student.counts?.[key] || 0);
    return total;
}, { present: 0, absent: 0, excused: 0, sick: 0, pending: 0 }));
const total = computed(() => events.value.length * students.value.length);
const rate = count => total.value ? `${Math.round(count / total.value * 100)}%` : '0%';
const metrics = computed(() => [
    { label: 'Event', value: events.value.length },
    { label: 'Present', value: rate(counts.value.present) },
    { label: 'Absent', value: rate(counts.value.absent) },
    { label: 'Excused / Sick', value: rate(counts.value.excused + counts.value.sick) },
]);
const labels = { present: 'Present', absent: 'Absent', excused: 'Excused', sick: 'Sick', pending: 'Pending' };
const shortDate = value => {
    if (!value) return '';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '' : new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
};
</script>

<template>
    <Head :title="`Rekap kehadiran · ${group.name}`" />
    <div class="attendance-app"><AdminSidebar /><main>
        <RedesignTopbar breadcrumb="Program &amp; kelas" />
        <div class="content"><div class="heading"><Link :href="route('redesign.study-groups.show', group.uuid)" class="back">← {{ group.name }}</Link><h1>Rekap kehadiran kelas</h1><p>Status kehadiran peserta pada setiap event di {{ group.name }}.</p></div>
            <section class="metrics" aria-label="Ringkasan kehadiran"><div v-for="metric in metrics" :key="metric.label" class="metric"><span>{{ metric.label }}</span><strong>{{ metric.value }}</strong><i aria-hidden="true"></i></div></section>
            <section class="panel"><div class="panel-heading"><h2>Matriks kehadiran</h2><p>Status per peserta dan per event.</p></div>
                <div v-if="!events.length" class="empty">Belum ada event kelas untuk ditampilkan.</div>
                <div v-else-if="!students.length" class="empty">Belum ada peserta di kelas ini.</div>
                <div v-else class="table-wrap"><table><thead><tr><th scope="col" class="student-col">Peserta</th><th v-for="(event, index) in events" :key="event.uuid" scope="col"><Link :href="event.attendance_url" :title="event.title">{{ event.title || `Event ${index + 1}` }}</Link><small>{{ shortDate(event.starts_at) }}</small></th></tr></thead><tbody><tr v-for="student in students" :key="student.id"><th scope="row" class="student-col"><Link :href="route('redesign.study-groups.students.detail', { uuid: group.uuid, userId: student.id })">{{ student.name }}</Link><small>@{{ student.username || 'user' }} · {{ student.attendance_rate }}% hadir</small></th><td v-for="(entry, index) in student.events" :key="entry.event_uuid || index"><span class="status" :class="entry.status || 'pending'" :title="entry.checked_at ? `Dicatat ${shortDate(entry.checked_at)}` : ''">{{ labels[entry.status] || 'Pending' }}</span></td></tr></tbody></table></div>
                <p class="legend">Present = hadir <span>·</span> Absent = tidak hadir <span>·</span> Excused = izin <span>·</span> Sick = sakit <span>·</span> Pending = belum dicatat</p>
            </section>
        </div>
    </main></div>
</template>

<style scoped>
.attendance-app{display:flex;min-height:100vh;background:#f5f7f8;color:#19333d;font-family:Inter,ui-sans-serif,system-ui,sans-serif}.attendance-app main{flex:1;min-width:0}.topbar{height:64px;background:#fff;border-bottom:1px solid #dce6e8;padding:0 28px;display:flex;align-items:center;justify-content:space-between;color:#70858c;font-size:11px}.topbar strong,.topbar small{display:block}.topbar strong{color:#19333d;font-size:12px}.topbar small{font-size:10px}.content{max-width:1440px;margin:auto;padding:30px 26px 60px}.heading{margin-bottom:30px}.back{color:#007f78;font-size:12px;font-weight:700}.heading h1{font-size:30px;font-weight:750;letter-spacing:-.025em;margin:8px 0 0}.heading p,.panel-heading p{color:#70858c;font-size:13px;margin:8px 0 0}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:28px}.metric{min-height:130px;background:#fff;border:1px solid #dce6e8;border-radius:12px;padding:20px;display:flex;flex-direction:column;align-items:flex-start}.metric span{font-size:13px;color:#70858c}.metric strong{font-size:28px;font-weight:750;margin-top:18px;line-height:1}.metric i{width:32px;height:3px;border-radius:2px;background:#008b83;margin-top:auto}.panel{background:#fff;border:1px solid #dce6e8;border-radius:12px;padding:24px;min-width:0}.panel-heading h2{font-size:18px;font-weight:750;margin:0}.panel-heading p{margin-bottom:20px}.table-wrap{overflow:auto;max-width:100%;border-radius:8px}table{border-collapse:separate;border-spacing:0;width:100%;min-width:max-content;font-size:12px}th,td{padding:13px 12px;border-bottom:1px solid #e6edef;text-align:left;min-width:120px}thead th{font-size:12px;color:#70858c;background:#f1f6f6;vertical-align:top}thead th a{display:block;color:#19333d;font-weight:700;text-decoration:none;max-width:140px;overflow-wrap:anywhere}thead th a:hover,.student-col a:hover{color:#007f78;text-decoration:underline}th small{display:block;margin-top:5px;color:#70858c;font-size:10px;font-weight:400}.student-col{min-width:190px;max-width:240px;position:sticky;left:0;z-index:1;background:#fff;box-shadow:1px 0 #e6edef}thead .student-col{z-index:2;background:#f1f6f6}.student-col a{color:#19333d;text-decoration:none;font-weight:750}.status{display:inline-flex;align-items:center;min-width:90px;min-height:28px;border-radius:6px;padding:5px 10px;font-size:11px;font-weight:700}.status.present{background:#e2f3ed;color:#008078}.status.absent{background:#fff0f0;color:#cc4a4c}.status.excused{background:#e8f3fa;color:#2976a1}.status.sick{background:#fff3dc;color:#966514}.status.pending{background:#eef1f2;color:#70858c}.legend{color:#70858c;font-size:11px;line-height:1.8;margin:20px 0 0}.legend span{padding:0 8px}.empty{padding:30px;background:#f5f8f8;border-radius:8px;color:#70858c;font-size:13px}.attendance-app :is(a,button):focus-visible{outline:2px solid #008b83;outline-offset:2px}@media(max-width:1100px){.metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:800px){.attendance-app{display:block}}@media(max-width:600px){.content{padding:22px 14px}.topbar{padding:0 16px}.panel{padding:18px}.metrics{gap:10px}.metric{min-height:110px;padding:16px}.metric strong{font-size:24px}.heading h1{font-size:25px}}
</style>
