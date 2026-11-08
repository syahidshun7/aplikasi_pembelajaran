<script setup>
import RedesignTopbar from './RedesignTopbar.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import QRCode from 'qrcode';
import AdminSidebar from './AdminSidebar.vue';

const props = defineProps({ event: { type: Object, required: true }, attendanceUsers: { type: Array, default: () => [] }, activeCheckInCode: Object });
const page = usePage();
const codeForm = useForm({ duration_minutes: 10 });
const attendanceForm = useForm({ attendance: props.attendanceUsers.map(user => ({ user_id: user.id, status: user.attendance_status || 'pending' })) });
const currentCode = computed(() => page.props.flash?.checkInCode || props.activeCheckInCode);
const now = ref(Date.now());
let ticker;
onMounted(() => { ticker = window.setInterval(() => { now.value = Date.now(); }, 1000); });
onBeforeUnmount(() => window.clearInterval(ticker));
const activeCode = computed(() => currentCode.value && new Date(currentCode.value.expires_at).getTime() > now.value ? currentCode.value : null);
const secondsLeft = computed(() => activeCode.value ? Math.max(0, Math.ceil((new Date(activeCode.value.expires_at).getTime() - now.value) / 1000)) : 0);
const qrDataUrl = ref('');
watch(() => activeCode.value?.qr_url, async url => {
    qrDataUrl.value = '';
    if (!url) return;
    try { qrDataUrl.value = await QRCode.toDataURL(url, { width: 260, margin: 2, errorCorrectionLevel: 'M' }); }
    catch { qrDataUrl.value = ''; }
}, { immediate: true });
const generate = () => codeForm.post(route('admin.events.attendance.check-in-code.generate', props.event.uuid), { preserveScroll: true });
const filter = ref('all');
const pageNumber = ref(1);
const pageSize = 10;
const labels = { present: 'Present', absent: 'Absent', excused: 'Excused', sick: 'Sick', pending: 'Pending' };
const statusOf = userId => attendanceForm.attendance.find(row => Number(row.user_id) === Number(userId))?.status || 'pending';
const filteredUsers = computed(() => filter.value === 'all' ? props.attendanceUsers : props.attendanceUsers.filter(user => statusOf(user.id) === filter.value));
const pageCount = computed(() => Math.max(1, Math.ceil(filteredUsers.value.length / pageSize)));
const visibleUsers = computed(() => filteredUsers.value.slice((pageNumber.value - 1) * pageSize, pageNumber.value * pageSize));
watch(filter, () => { pageNumber.value = 1; });
watch(filteredUsers, () => { pageNumber.value = Math.min(pageNumber.value, pageCount.value); });
const save = () => attendanceForm.patch(route('admin.events.attendance.update', props.event.uuid), { preserveScroll: true, preserveState: false });
const time = value => {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' }).format(date);
};
</script>

<template>
    <Head :title="`Absensi · ${event.title}`" />
    <div class="event-attendance-app"><AdminSidebar /><main>
        <RedesignTopbar breadcrumb="Materi &amp; event" />
        <div class="content"><div class="heading"><Link :href="route('redesign.events.show', event.uuid)" class="back">← {{ event.title }}</Link><h1>Absensi event</h1><p>{{ event.title }} / {{ event.study_group?.name || 'Event umum' }}</p></div>
            <section class="panel checkin"><div class="checkin-head"><div><h2>QR check-in</h2><p>Peserta dapat memindai QR untuk mengisi kehadiran. QR baru menonaktifkan yang lama.</p></div><form @submit.prevent="generate"><label>Durasi (menit)<input v-model.number="codeForm.duration_minutes" type="number" min="1" max="120" required /></label><button type="submit" :disabled="codeForm.processing">{{ codeForm.processing ? 'Membuat...' : 'Generate QR' }}</button></form></div><small v-if="codeForm.errors.duration_minutes" class="error">{{ codeForm.errors.duration_minutes }}</small>
                <div v-if="activeCode" class="qr-result"><div class="qr-frame"><img v-if="qrDataUrl" :src="qrDataUrl" :alt="`QR check-in ${event.title}`" /><span v-else>Menyiapkan QR...</span></div><div class="qr-info"><strong>QR aktif</strong><p>Sisa waktu {{ Math.floor(secondsLeft / 60) }}:{{ String(secondsLeft % 60).padStart(2, '0') }}</p><p>Kode manual: <b>{{ activeCode.code || `••${activeCode.last_four}` }}</b></p><a :href="activeCode.qr_url" target="_blank" rel="noopener noreferrer">Buka tautan check-in</a><small>Perlihatkan QR ini kepada peserta. Tautan memerlukan login peserta.</small></div></div><p v-else class="no-code">Belum ada QR check-in aktif. Pilih durasi lalu tekan Generate QR.</p>
            </section>
            <div class="tabs" role="group" aria-label="Filter kehadiran"><button v-for="item in [['all',`Semua (${attendanceUsers.length})`],['present','Present'],['absent','Absent'],['excused','Excused'],['sick','Sick'],['pending','Pending']]" :key="item[0]" type="button" :class="{ active: filter === item[0] }" @click="filter = item[0]">{{ item[1] }}</button></div>
            <section class="panel list-panel"><div class="list-head"><div><h2>Attendance list</h2><p>Ubah status peserta lalu simpan absensi.</p></div><button type="button" class="save" :disabled="attendanceForm.processing || !attendanceForm.attendance.length" @click="save">{{ attendanceForm.processing ? 'Menyimpan...' : 'Simpan absensi' }}</button></div><div v-if="!filteredUsers.length" class="empty">Tidak ada peserta pada status ini.</div><div v-else class="table-wrap"><table><thead><tr><th>Peserta</th><th>Username</th><th>Status</th><th>Check-in</th><th>Aksi</th></tr></thead><tbody><tr v-for="user in visibleUsers" :key="user.id"><td class="person">{{ user.name }}</td><td>{{ user.username || '—' }}</td><td><span class="status" :class="statusOf(user.id)">{{ labels[statusOf(user.id)] }}</span></td><td>{{ time(user.checked_at) }}</td><td><select :aria-label="`Ubah status ${user.name}`" :value="statusOf(user.id)" @change="attendanceForm.attendance.find(row => row.user_id === user.id).status = $event.target.value"><option v-for="(label,key) in labels" :key="key" :value="key">{{ label }}</option></select></td></tr></tbody></table></div><div class="footer"><span>Menampilkan {{ visibleUsers.length }} dari {{ filteredUsers.length }} peserta</span><div v-if="pageCount > 1" class="pager"><button type="button" :disabled="pageNumber === 1" @click="pageNumber--">‹</button><span>{{ pageNumber }} / {{ pageCount }}</span><button type="button" :disabled="pageNumber === pageCount" @click="pageNumber++">›</button></div></div></section>
        </div>
    </main></div>
</template>

<style scoped>
.event-attendance-app{display:flex;min-height:100vh;background:#f5f7f8;color:#19333d;font-family:Inter,ui-sans-serif,system-ui,sans-serif}.event-attendance-app main{flex:1;min-width:0}.topbar{height:64px;background:#fff;border-bottom:1px solid #dce6e8;padding:0 28px;display:flex;align-items:center;justify-content:space-between;color:#70858c;font-size:11px}.topbar strong,.topbar small{display:block}.topbar strong{color:#19333d;font-size:12px}.topbar small{font-size:10px}.content{max-width:1440px;margin:auto;padding:34px 26px 70px}.heading{margin-bottom:28px}.back{color:#007f78;font-size:12px;font-weight:700}.heading h1{font-size:30px;font-weight:750;letter-spacing:-.025em;margin:8px 0}.heading p,.checkin p,.list-head p{font-size:13px;color:#70858c;margin:0}.panel{background:#fff;border:1px solid #dce6e8;border-radius:12px;padding:24px;min-width:0}.panel h2{font-size:18px;font-weight:750;margin:0 0 8px}.checkin{margin-bottom:24px}.checkin-head{display:flex;justify-content:space-between;align-items:end;gap:24px}.checkin-head form{display:flex;align-items:end;gap:12px}.checkin-head label{display:grid;gap:7px;font-size:12px;font-weight:700}.checkin-head input{border:1px solid #dce6e8;border-radius:7px;padding:10px;width:110px}.checkin-head button,.save{border:0;border-radius:7px;background:#007f78;color:#fff;padding:11px 17px;font-size:12px;font-weight:750;cursor:pointer;white-space:nowrap}.checkin-head button:disabled,.save:disabled{opacity:.5}.qr-result{display:flex;align-items:center;gap:24px;flex-wrap:wrap;margin-top:25px;padding:20px;background:#f4faf8;border:1px solid #dce6e8;border-radius:9px}.qr-frame{width:260px;height:260px;background:#fff;display:grid;place-items:center;border:1px solid #dce6e8;border-radius:8px}.qr-frame img{width:100%;height:100%;object-fit:contain}.qr-info{min-width:220px;flex:1}.qr-info strong{font-size:18px}.qr-info p{margin:10px 0}.qr-info b{font-size:18px;letter-spacing:.12em;color:#007f78}.qr-info a{display:block;color:#007f78;font-size:12px;font-weight:700;overflow-wrap:anywhere;margin:12px 0}.qr-info small{color:#70858c;font-size:11px}.no-code{padding:20px;background:#f5f8f8;border-radius:7px;margin-top:20px!important}.error{color:#b74336}.tabs{display:flex;flex-wrap:wrap;gap:9px;margin-bottom:24px}.tabs button{border:1px solid #dce6e8;border-radius:8px;background:#fff;color:#70858c;padding:10px 14px;font-size:12px;cursor:pointer}.tabs button.active{background:#e5f5f1;border-color:#e5f5f1;color:#007f78;font-weight:750}.list-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px}.table-wrap{overflow-x:auto}table{width:100%;min-width:680px;border-collapse:collapse;font-size:12px}th{text-align:left;padding:13px;background:#f0f5f6;color:#70858c}td{padding:17px 13px;border-bottom:1px solid #e5edef}.person{font-weight:750}.status{font-weight:700}.status.present{color:#007f78}.status.absent{color:#c9464e}.status.excused{color:#2976a1}.status.sick{color:#966514}.status.pending{color:#70858c}td select{border:1px solid #dce6e8;border-radius:6px;background:#fff;padding:8px;color:#19333d}.footer{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-top:20px;color:#70858c;font-size:11px}.pager{display:flex;align-items:center;gap:9px}.pager button{border:1px solid #dce6e8;border-radius:5px;background:#fff;padding:5px 10px;color:#007f78;cursor:pointer}.pager button:disabled{opacity:.4}.empty{padding:22px;color:#70858c;font-size:13px}.event-attendance-app :is(a,button,input,select):focus-visible{outline:2px solid #008b83;outline-offset:2px}@media(max-width:800px){.event-attendance-app{display:block}.checkin-head{align-items:start;flex-direction:column}}@media(max-width:600px){.content{padding:22px 14px}.heading h1{font-size:25px}.panel{padding:18px}.list-head{align-items:start;flex-direction:column}.checkin-head form{width:100%;flex-wrap:wrap}.qr-frame{width:min(100%,260px)}.qr-result{padding:12px}}
</style>
