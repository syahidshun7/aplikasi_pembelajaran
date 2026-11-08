<script setup>
import RedesignTopbar from './RedesignTopbar.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminSidebar from './AdminSidebar.vue';

const props = defineProps({ group: Object, requests: { type: Array, default: () => [] }, members: { type: Array, default: () => [] } });
const page = usePage();
const canManageMembers = computed(() => ['admin', 'super_admin'].includes(page.props.auth?.user?.role));
const tab = ref('requests');
const search = ref('');
const appliedSearch = ref('');
const pageNumber = ref(1);
const pageSize = 10;
const selected = ref(null);
const reason = ref('');
const busy = ref(false);
const rows = computed(() => (tab.value === 'requests' ? props.requests : props.members).filter(item => `${item.user?.name || item.name || ''} ${item.user?.username || item.username || ''} ${item.user?.email || item.email || ''}`.toLocaleLowerCase().includes(appliedSearch.value.toLocaleLowerCase())));
const pageCount = computed(() => Math.max(1, Math.ceil(rows.value.length / pageSize)));
const visibleRows = computed(() => rows.value.slice((pageNumber.value - 1) * pageSize, pageNumber.value * pageSize));
const applySearch = () => { appliedSearch.value = search.value.trim(); pageNumber.value = 1; };
const resetSearch = () => { search.value = ''; applySearch(); };
const switchTab = value => { tab.value = value; pageNumber.value = 1; selected.value = null; };
watch(() => [props.requests.length, props.members.length], () => { pageNumber.value = Math.min(pageNumber.value, pageCount.value); });
const act = () => {
    if (!selected.value || busy.value) return;
    busy.value = true;
    const { type, item } = selected.value;
    const done = { preserveScroll: true, onFinish: () => { busy.value = false; }, onSuccess: () => { selected.value = null; reason.value = ''; } };
    if (type === 'approve') router.post(route('groups.requests.approve', { uuid: props.group.uuid, requestId: item.id }), {}, done);
    if (type === 'reject') router.post(route('groups.requests.reject', { uuid: props.group.uuid, requestId: item.id }), { reason: reason.value.trim() || null }, done);
    if (type === 'remove') router.delete(route('groups.members.remove', { uuid: props.group.uuid, userId: item.id }), done);
};
const formatDate = value => value ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value)) : '—';
</script>

<template>
    <Head :title="`Keanggotaan · ${group.name}`" />
    <div class="membership-app"><AdminSidebar /><main>
        <RedesignTopbar breadcrumb="Program & kelas" />
        <div class="content"><div class="heading"><Link :href="route('redesign.study-groups.show', group.uuid)" class="back">← {{ group.name }}</Link><h1>Keanggotaan kelas</h1><p>{{ group.name }} / Tinjau permintaan dan kelola anggota.</p></div>
            <div class="tabs" role="group" aria-label="Jenis keanggotaan"><button type="button" :class="{active:tab==='requests'}" @click="switchTab('requests')">Permintaan masuk ({{ requests.length }})</button><button type="button" :class="{active:tab==='members'}" @click="switchTab('members')">Anggota aktif ({{ members.length }})</button></div>
            <form class="filters" @submit.prevent="applySearch"><input v-model="search" aria-label="Cari pengguna" placeholder="Cari nama, username, atau email..." /><button type="submit">Filter</button><button type="button" @click="resetSearch">Reset</button></form>
            <section class="panel"><h2>{{ tab === 'requests' ? 'Permintaan masuk' : 'Anggota aktif' }}</h2><div class="table-wrap"><table><thead><tr><th>Nama / username</th><th>Email</th><th>Level</th><th>{{ tab === 'requests' ? 'Tanggal' : 'Status' }}</th><th>Aksi</th></tr></thead><tbody><tr v-for="item in visibleRows" :key="item.id"><td><strong>{{ item.user?.name || item.name }}</strong><small>@{{ item.user?.username || item.username }}</small></td><td>{{ item.user?.email || item.email }}</td><td>Lv. {{ item.user_level || 1 }}</td><td>{{ tab === 'requests' ? formatDate(item.created_at) : 'Aktif' }}</td><td><div class="row-actions" v-if="tab === 'requests' && canManageMembers"><button type="button" @click="selected = { type:'approve', item }">Terima</button><button type="button" @click="selected = { type:'reject', item }">Tolak</button></div><button v-else-if="tab !== 'requests' && canManageMembers" type="button" class="danger-link" @click="selected = { type:'remove', item }">Keluarkan</button><span v-else>Hanya admin dapat memproses</span></td></tr><tr v-if="!rows.length"><td colspan="5" class="empty">{{ appliedSearch ? 'Tidak ada pengguna yang cocok.' : tab === 'requests' ? 'Belum ada permintaan masuk.' : 'Belum ada anggota aktif.' }}</td></tr></tbody></table></div><div class="table-footer"><span>Menampilkan {{ visibleRows.length }} dari {{ rows.length }} data</span><div v-if="pageCount > 1" class="pager"><button :disabled="pageNumber===1" @click="pageNumber--">‹</button><span>{{ pageNumber }} / {{ pageCount }}</span><button :disabled="pageNumber===pageCount" @click="pageNumber++">›</button></div></div></section>
            <div v-if="selected" class="dialog-backdrop" @click.self="selected = null"><section class="dialog" role="dialog" aria-modal="true" aria-labelledby="dialog-title"><h2 id="dialog-title">{{ selected.type === 'approve' ? 'Terima permintaan?' : selected.type === 'reject' ? 'Tolak permintaan?' : 'Keluarkan anggota?' }}</h2><p>{{ selected.item.user?.name || selected.item.name }}</p><label v-if="selected.type === 'reject'">Alasan penolakan (opsional)<textarea v-model="reason" maxlength="500" rows="3" /></label><div><button type="button" class="secondary" @click="selected = null">Batal</button><button type="button" class="primary" :disabled="busy" @click="act">{{ busy ? 'Memproses...' : 'Konfirmasi' }}</button></div></section></div>
        </div>
    </main></div>
</template>

<style scoped>
.membership-app{display:flex;min-height:100vh;background:#f5f7f8;color:#19333d;font-family:Inter,ui-sans-serif,system-ui,sans-serif}.membership-app main{flex:1;min-width:0}.topbar{height:64px;background:#fff;border-bottom:1px solid #dce6e8;padding:0 28px;display:flex;align-items:center;justify-content:space-between;color:#70858c;font-size:11px}.topbar strong,.topbar small{display:block}.topbar strong{color:#19333d;font-size:12px}.topbar small{font-size:10px}.content{max-width:1320px;margin:auto;padding:30px 26px 60px}.heading{margin-bottom:30px}.back{font-size:12px;color:#007f78;font-weight:700}.heading h1{font-size:30px;font-weight:750;letter-spacing:-.025em;margin:8px 0 0}.heading p{font-size:13px;color:#70858c;margin:8px 0 0}.tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px}.tabs button,.filters button{border:1px solid #dce6e8;border-radius:8px;background:#fff;color:#70858c;padding:11px 16px;cursor:pointer}.tabs button.active{background:#e5f5f1;color:#007f78;border-color:#e5f5f1;font-weight:700}.filters{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:12px;margin-bottom:28px}.filters input{min-width:0;border:1px solid #dce6e8;border-radius:8px;padding:12px 16px}.filters button{color:#007f78;font-weight:700}.panel{background:#fff;border:1px solid #dce6e8;border-radius:12px;padding:24px;min-width:0}.panel h2{font-size:18px;font-weight:750;margin:0 0 20px}.table-wrap{overflow-x:auto}table{width:100%;min-width:680px;border-collapse:collapse;font-size:12px}th{text-align:left;background:#f0f5f6;color:#70858c;padding:12px}td{padding:16px 12px;border-bottom:1px solid #e5edef}td strong,td small{display:block}td small{color:#70858c;margin-top:4px}.row-actions{display:flex;gap:12px}.row-actions button,.danger-link{border:0;background:none;color:#007f78;font-weight:700;cursor:pointer}.row-actions button:last-child,.danger-link{color:#b74336}.empty{text-align:center;color:#70858c}.table-footer{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:20px;color:#70858c;font-size:11px}.pager{display:flex;align-items:center;gap:10px}.pager button{border:1px solid #dce6e8;border-radius:5px;background:#fff;color:#007f78;padding:5px 10px;cursor:pointer}.pager button:disabled{opacity:.4}.dialog-backdrop{position:fixed;inset:0;background:#122b32aa;display:grid;place-items:center;padding:16px;z-index:50}.dialog{background:#fff;border-radius:12px;padding:24px;width:min(100%,430px);box-shadow:0 20px 60px #102b3340}.dialog h2{margin:0;font-size:20px}.dialog p{color:#70858c}.dialog label{display:grid;gap:8px;font-size:12px;font-weight:700}.dialog textarea{border:1px solid #dce6e8;border-radius:8px;padding:10px}.dialog>div{display:flex;justify-content:flex-end;gap:10px;margin-top:20px}.primary,.secondary{border-radius:8px;padding:10px 16px;font-weight:700;cursor:pointer}.primary{border:0;background:#007f78;color:#fff}.secondary{border:1px solid #dce6e8;background:#fff;color:#007f78}.membership-app :is(a,button,input,textarea):focus-visible{outline:2px solid #008b83;outline-offset:2px}@media(max-width:800px){.membership-app{display:block}}@media(max-width:600px){.content{padding:22px 14px}.panel{padding:18px}.filters{grid-template-columns:1fr 1fr}.filters input{grid-column:1/-1}}
</style>
