<script setup>
import RedesignTopbar from './RedesignTopbar.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminSidebar from './AdminSidebar.vue';

const props = defineProps({ group: Object, operations: Object, studentDashboard: { type: Array, default: () => [] }, staffMembers: { type: Array, default: () => [] }, availableStaff: { type: Array, default: () => [] }, canManageStaffAccess: Boolean });
const page = usePage();
const pageNumber = ref(1);
const pageSize = 8;
const students = computed(() => props.studentDashboard || []);
const statusFilter = ref('all');
const sortKey = ref('performance_average');
const sortDirection = ref('desc');
const numericKeys = ['attendance_percentage', 'main_quest_average', 'performance_average'];
const filteredStudents = computed(() => {
    const filtered = statusFilter.value === 'all' ? [...students.value] : students.value.filter(student => student.status === statusFilter.value);
    return filtered.sort((left, right) => {
        const direction = sortDirection.value === 'asc' ? 1 : -1;
        if (numericKeys.includes(sortKey.value)) return (Number(left[sortKey.value] || 0) - Number(right[sortKey.value] || 0)) * direction;
        return String(left[sortKey.value] || '').localeCompare(String(right[sortKey.value] || ''), 'id', { sensitivity: 'base', numeric: true }) * direction;
    });
});
const pageCount = computed(() => Math.max(1, Math.ceil(filteredStudents.value.length / pageSize)));
const visibleStudents = computed(() => filteredStudents.value.slice((pageNumber.value - 1) * pageSize, pageNumber.value * pageSize));
const sortBy = (key) => {
    if (sortKey.value === key) sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    else { sortKey.value = key; sortDirection.value = numericKeys.includes(key) ? 'desc' : 'asc'; }
};
const sortIndicator = (key) => sortKey.value === key ? (sortDirection.value === 'asc' ? '↑' : '↓') : '↕';
watch(statusFilter, () => { pageNumber.value = 1; });
watch([sortKey, sortDirection], () => { pageNumber.value = 1; });
const average = (key) => students.value.length ? Math.round(students.value.reduce((sum, student) => sum + Number(student[key] || 0), 0) / students.value.length * 10) / 10 : 0;
const assignableStaff = computed(() => props.availableStaff.filter(staff => !props.staffMembers.some(member => Number(member.id) === Number(staff.id))));
const staffForm = useForm({ user_id: '', role_in_group: 'mentor' });
const showAssign = ref(false);
const removeCandidate = ref(null);
const assignStaff = () => staffForm.post(route('groups.staff.assign', { uuid: props.group.uuid }), { preserveScroll: true, onSuccess: () => { staffForm.reset(); showAssign.value = false; } });
const removeStaff = (staff) => {
    router.delete(route('groups.staff.remove', { uuid: props.group.uuid, userId: staff.id }), { preserveScroll: true, onSuccess: () => { removeCandidate.value = null; } });
};
const tabs = computed(() => [
    ['Overview', route('redesign.study-groups.show', props.group.uuid), true],
    ['Quest', route('redesign.study-groups.quests', props.group.uuid)],
    ['Materi', route('redesign.study-groups.guides', props.group.uuid)],
    ['Event', route('redesign.study-groups.events', props.group.uuid)],
    ['Roadmap', route('redesign.study-groups.roadmaps', props.group.uuid)],
    ['Kehadiran', route('redesign.study-groups.attendance', props.group.uuid)],
    ['Anggota', route('redesign.study-groups.membership', props.group.uuid)],
]);
</script>

<template>
    <Head :title="group.name" />
    <div class="detail-app"><AdminSidebar /><main>
        <RedesignTopbar breadcrumb="Program & kelas" />
        <div class="content"><div class="heading"><Link :href="route('redesign.study-groups')" class="back">← Study groups</Link><h1>{{ group.name }}</h1><p>{{ group.description || 'Kelola aktivitas dan data pembelajaran dalam satu workspace.' }}</p></div>
            <section class="metrics" aria-label="Statistik kelas"><div class="metric"><span>Anggota</span><strong>{{ students.length }} / {{ group.max_members || 0 }}</strong></div><div class="metric"><span>Kehadiran</span><strong>{{ average('attendance_percentage') }}%</strong></div><div class="metric"><span>Main quest avg</span><strong>{{ average('main_quest_average') }}</strong></div><div class="metric"><span>Pending review</span><strong>{{ operations?.pending_reviews ?? 0 }}</strong></div></section>
            <nav class="tabs" aria-label="Operasional kelas"><Link v-for="tab in tabs" :key="tab[0]" :href="tab[1]" :class="{ active: tab[2] }">{{ tab[0] }}</Link></nav>
            <div class="columns"><section class="panel performance">
                    <div class="performance-head"><div><h2>Performa kelas</h2><p>{{ filteredStudents.length }} / {{ students.length }} peserta</p></div><label>Filter status <select v-model="statusFilter" aria-label="Filter status peserta"><option value="all">Semua status</option><option value="safe">On track</option><option value="needs_attention">Perlu bantuan</option></select></label></div>
                    <div class="table-wrap"><table><thead><tr>
                        <th><button type="button" class="sort-header" @click="sortBy('username')">Username <span>{{ sortIndicator('username') }}</span></button></th>
                        <th><button type="button" class="sort-header" @click="sortBy('name')">Peserta <span>{{ sortIndicator('name') }}</span></button></th>
                        <th><button type="button" class="sort-header" @click="sortBy('attendance_percentage')">Kehadiran <span>{{ sortIndicator('attendance_percentage') }}</span></button></th>
                        <th><button type="button" class="sort-header" @click="sortBy('main_quest_average')">Main quest <span>{{ sortIndicator('main_quest_average') }}</span></button></th>
                        <th><button type="button" class="sort-header" @click="sortBy('performance_average')">Combined avg <span>{{ sortIndicator('performance_average') }}</span></button></th>
                        <th><button type="button" class="sort-header" @click="sortBy('status')">Status <span>{{ sortIndicator('status') }}</span></button></th>
                    </tr></thead><tbody>
                        <tr v-for="student in visibleStudents" :key="student.id"><td>{{ student.username || '—' }}</td><td><Link class="student-link" :href="route('redesign.study-groups.students.detail', { uuid:group.uuid, userId:student.id })">{{ student.name }}</Link></td><td>{{ student.attendance_percentage }}%</td><td>{{ student.main_quest_average }}</td><td>{{ student.performance_average }}</td><td :class="student.status === 'safe' ? 'good' : 'attention'">{{ student.status === 'safe' ? 'On track' : 'Perlu bantuan' }}</td></tr>
                        <tr v-if="!filteredStudents.length"><td colspan="6" class="empty">{{ students.length ? 'Tidak ada peserta untuk status ini.' : 'Belum ada peserta dalam kelas ini.' }}</td></tr>
                    </tbody></table></div>
                    <div class="table-footer"><span>Menampilkan {{ visibleStudents.length }} dari {{ filteredStudents.length }} peserta</span><div v-if="pageCount > 1" class="pager"><button :disabled="pageNumber === 1" @click="pageNumber--">‹</button><span>{{ pageNumber }} / {{ pageCount }}</span><button :disabled="pageNumber === pageCount" @click="pageNumber++">›</button></div></div>
                </section>
                <aside><section class="panel side"><h2>Staff access</h2><p v-if="page.props.flash?.message === 'STUDY_GROUP_STAFF_ACCESS_GRANTED'" class="feedback" role="status">Akses staf berhasil ditambahkan.</p><p v-if="page.props.flash?.message === 'STUDY_GROUP_STAFF_ACCESS_REVOKED'" class="feedback" role="status">Akses staf berhasil dicabut.</p><ul v-if="staffMembers.length"><li v-for="staff in staffMembers" :key="staff.id"><span><strong>{{ staff.name }}</strong><small>{{ staff.role_in_group }}</small></span><button v-if="canManageStaffAccess" type="button" :aria-label="`Cabut akses ${staff.name}`" @click="removeCandidate = staff">×</button></li></ul><p v-else>Belum ada staf yang ditugaskan.</p><div v-if="removeCandidate" class="confirm-remove" role="group" :aria-label="`Konfirmasi cabut akses ${removeCandidate.name}`"><p>Cabut akses {{ removeCandidate.name }}?</p><button type="button" @click="removeStaff(removeCandidate)">Ya, cabut</button><button type="button" @click="removeCandidate = null">Batal</button></div><button v-if="canManageStaffAccess" type="button" class="secondary" @click="showAssign = !showAssign">+ Assign staff</button><form v-if="showAssign" class="assign-form" @submit.prevent="assignStaff"><label>Staf<select v-model="staffForm.user_id" required><option value="" disabled>Pilih staf</option><option v-for="staff in assignableStaff" :key="staff.id" :value="staff.id">{{ staff.name }}</option></select><small v-if="staffForm.errors.user_id">{{ staffForm.errors.user_id }}</small></label><label>Peran<select v-model="staffForm.role_in_group"><option value="mentor">Mentor</option><option value="admin">Admin</option><option value="viewer">Viewer</option></select><small v-if="staffForm.errors.role_in_group">{{ staffForm.errors.role_in_group }}</small></label><button class="primary" :disabled="staffForm.processing">Simpan akses</button></form></section>
                    <section class="panel side"><h2>Operasional kelas</h2><p>{{ operations?.quests ?? 0 }} quest · {{ operations?.guides ?? 0 }} materi<br />{{ operations?.events ?? 0 }} event · {{ operations?.roadmaps ?? 0 }} roadmap</p><a class="secondary" :href="route('groups.export-recap', { uuid:group.uuid })">Export recap</a></section></aside>
            </div>
        </div>
    </main></div>
</template>

<style scoped>
.performance-head{display:flex;align-items:end;justify-content:space-between;gap:16px;margin-bottom:18px}.performance-head h2{margin:0}.performance-head p{font-size:12px;color:#70858c;margin:6px 0 0}.performance-head label{display:grid;gap:6px;font-size:11px;font-weight:700;color:#70858c}.performance-head select{min-width:155px;border:1px solid #dce6e8;border-radius:7px;background:#fff;color:#19333d;padding:9px}.sort-header{width:100%;display:flex;align-items:center;justify-content:space-between;gap:8px;border:0;background:none;color:inherit;padding:0;text-align:left;font:inherit;font-weight:700;cursor:pointer}.sort-header span{color:#007f78;font-size:14px}.sort-header:hover{color:#007f78}@media(max-width:600px){.performance-head{align-items:flex-start;flex-direction:column}}
.student-link{color:#007f78;font-weight:750;text-decoration:underline;text-decoration-color:#9ccbc6;text-underline-offset:3px}
.feedback{padding:10px;background:#e5f5f1;border-radius:7px;color:#007f78!important}.confirm-remove{padding:12px;background:#fff5ef;border:1px solid #eac8b9;border-radius:8px;margin-bottom:16px}.confirm-remove p{margin:0 0 10px!important;color:#7c3b29!important}.confirm-remove button{border:1px solid #e4b4a4;border-radius:6px;background:#fff;color:#9a4b35;padding:7px 10px;margin-right:7px;cursor:pointer}
.detail-app{display:flex;min-height:100vh;background:#f5f7f8;color:#19333d;font-family:Inter,ui-sans-serif,system-ui,sans-serif}.detail-app main{flex:1;min-width:0}.topbar{height:64px;background:#fff;border-bottom:1px solid #dce6e8;padding:0 28px;display:flex;align-items:center;justify-content:space-between;color:#70858c;font-size:11px}.topbar strong,.topbar small{display:block}.topbar strong{color:#19333d;font-size:12px}.topbar small{font-size:10px}.content{max-width:1320px;margin:auto;padding:30px 26px 60px}.heading{margin-bottom:30px}.back{font-size:12px;color:#007f78;font-weight:700}.heading h1{font-size:30px;font-weight:750;letter-spacing:-.025em;margin:8px 0 0;overflow-wrap:anywhere}.heading p{font-size:13px;color:#70858c;margin:8px 0 0}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.metric{min-height:120px;background:#fff;border:1px solid #dce6e8;border-radius:12px;padding:20px;display:flex;flex-direction:column;justify-content:space-between}.metric span{color:#70858c;font-size:12px}.metric strong{font-size:28px;line-height:1.1;overflow-wrap:anywhere}.metric:after{content:'';width:32px;height:3px;background:#007f78;border-radius:3px}.tabs{display:flex;gap:10px;flex-wrap:wrap;margin:28px 0}.tabs a{min-width:110px;border:1px solid #dce6e8;border-radius:8px;background:#fff;color:#70858c;padding:11px 16px;font-size:12px}.tabs a.active{background:#e5f5f1;color:#007f78;border-color:#e5f5f1;font-weight:700}.columns{display:grid;grid-template-columns:minmax(0,2.2fr) minmax(250px,1fr);gap:20px}.panel{background:#fff;border:1px solid #dce6e8;border-radius:12px;padding:24px;min-width:0}.panel h2{font-size:18px;font-weight:750;margin:0 0 22px}.table-wrap{overflow-x:auto}table{width:100%;min-width:600px;border-collapse:collapse;font-size:12px}th{text-align:left;background:#f0f5f6;color:#70858c;padding:12px}td{padding:15px 12px;border-bottom:1px solid #e5edef}td:first-child{font-weight:700}td a{color:#19333d}.good{color:#007f78}.attention{color:#b65d25}.empty{color:#70858c;text-align:center}.table-footer{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:22px;color:#70858c;font-size:11px}.pager{display:flex;align-items:center;gap:10px}.pager button{background:#fff;border:1px solid #dce6e8;border-radius:5px;color:#007f78;padding:5px 10px;cursor:pointer}.pager button:disabled{opacity:.4}.side{margin-bottom:20px}.side p{color:#70858c;font-size:13px;line-height:1.7}.side ul{padding:0;list-style:none}.side li{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px}.side li strong,.side li small{display:block;font-size:12px}.side li small{font-size:11px;color:#70858c;margin-top:3px}.side li button{border:0;background:none;color:#b74336;font-size:20px;cursor:pointer}.secondary{display:inline-block;border:1px solid #dce6e8;border-radius:8px;padding:11px 15px;color:#007f78;background:#fff;font-weight:700;font-size:12px;cursor:pointer}.primary{background:#007f78;border:0;border-radius:8px;color:#fff;padding:11px 15px;font-weight:700;cursor:pointer}.assign-form{display:grid;gap:12px;margin-top:16px}.assign-form label{display:grid;gap:6px;font-size:12px;font-weight:700}.assign-form select{border:1px solid #dce6e8;border-radius:8px;padding:10px;min-width:0}.assign-form small{color:#b74336}.detail-app :is(a,button,select):focus-visible{outline:2px solid #008b83;outline-offset:2px}@media(max-width:1000px){.columns{grid-template-columns:1fr}}@media(max-width:800px){.detail-app{display:block}.metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.content{padding:22px 14px}.panel{padding:18px}.tabs a{min-width:0}.metric strong{font-size:24px}}
</style>
