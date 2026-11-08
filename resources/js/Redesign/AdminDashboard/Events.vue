<script setup>
import RedesignTopbar from './RedesignTopbar.vue';
import './redesignTable.css';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminSidebar from './AdminSidebar.vue';
import { confirmAction } from './confirmAction.js';

const props = defineProps({ events: Object, filters: Object, selectedStudyGroup: Object, timezone: { type: String, default: 'UTC' } });
const page = usePage();
const items = computed(() => props.events?.data || []);
const scope = computed(() => props.selectedStudyGroup?.uuid ? route('redesign.study-groups.events', props.selectedStudyGroup.uuid) : route('redesign.events'));
const search = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');
const view = computed(() => props.filters?.view || 'active');
const navigate = (overrides = {}) => router.get(scope.value, { search: search.value.trim() || undefined, view: view.value === 'trash' ? 'trash' : undefined, status: selectedStatus.value || undefined, ...overrides }, { preserveState: true, preserveScroll: true, replace: true });
const reset = () => { search.value = ''; selectedStatus.value = ''; router.get(scope.value, {}, { preserveState: true, replace: true }); };
const formatDate = value => {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('id-ID', { timeZone: props.timezone, day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(date);
};
const archiveEvent = async event => { if (await confirmAction(`Arsipkan event ${event.title}? Event dapat dipulihkan dari tab Arsip.`, 'Arsipkan')) router.delete(route('admin.events.destroy', event.uuid), { preserveScroll: true }); };
const restoreEvent = event => router.patch(route('admin.events.restore', event.uuid), {}, { preserveScroll: true });
const deleteEvent = async event => { if (await confirmAction(`Hapus permanen event ${event.title}? Data ini tidak dapat dipulihkan.`, 'Hapus permanen')) router.delete(route('admin.events.force-destroy', event.uuid), { preserveScroll: true }); };
const status = event => {
    if (view.value === 'trash') return 'Arsip';
    if (!event.starts_at) return 'Belum dijadwalkan';
    const now = Date.now();
    if (new Date(event.starts_at).getTime() > now) return 'Terjadwal';
    if (event.ends_at && new Date(event.ends_at).getTime() < now) return 'Selesai';
    return 'Berlangsung';
};
</script>

<template>
    <Head title="Event kelas" />
    <div class="events-app"><AdminSidebar /><main>
        <RedesignTopbar breadcrumb="Materi &amp; event" />
        <div class="content"><div class="heading"><div><Link v-if="selectedStudyGroup" :href="route('redesign.study-groups.show', selectedStudyGroup.uuid)" class="back">← {{ selectedStudyGroup.name }}</Link><h1>Event kelas</h1><p>Kelola jadwal dan aktivitas event untuk study group.</p></div><Link class="add" :href="selectedStudyGroup ? route('redesign.study-groups.events.create', selectedStudyGroup.uuid) : route('redesign.events.create')">+ Tambah event</Link></div>
            <div class="tabs" role="group" aria-label="Kondisi event"><button type="button" :class="{ active: view !== 'trash' }" @click="navigate({ view: undefined })">Event aktif</button><button type="button" :class="{ active: view === 'trash' }" @click="navigate({ view: 'trash', status: undefined })">Arsip</button></div>
            <form class="filters" @submit.prevent="navigate()"><input v-model="search" aria-label="Cari event" placeholder="Cari nama event atau kelas..." /><select v-model="selectedStatus" aria-label="Filter status" :disabled="view === 'trash'"><option value="">Semua status</option><option value="scheduled">Terjadwal</option><option value="ongoing">Berlangsung</option><option value="finished">Selesai</option><option value="unscheduled">Belum dijadwalkan</option></select><button type="submit">Filter</button><button type="button" @click="reset">Reset</button></form>
            <section class="panel"><h2>Event kelas</h2><div v-if="!items.length" class="empty">{{ search ? 'Tidak ada event yang cocok.' : 'Belum ada event pada tampilan ini.' }}</div><div v-else class="table-wrap"><table><thead><tr><th>Event</th><th>Study group</th><th>Mulai</th><th>Selesai</th><th>Status</th><th>Aksi</th></tr></thead><tbody><tr v-for="event in items" :key="event.uuid"><td><Link v-if="view !== 'trash'" class="event-link" :href="route('redesign.events.show', event.uuid)">{{ event.title }}</Link><strong v-else>{{ event.title }}</strong></td><td>{{ event.study_group?.name || (event.job?.name ? `Umum · ${event.job.name}` : 'Umum') }}</td><td>{{ formatDate(event.starts_at) }}</td><td>{{ formatDate(event.ends_at) }}</td><td><span class="status" :class="{ archived: view === 'trash' }">{{ status(event) }}</span></td><td><div v-if="view !== 'trash'" class="row-actions"><Link class="event-link" :href="route('events.user-preview', { event: event.uuid, from: 'redesign' })">User View</Link><Link class="event-link" :href="route('redesign.events.edit', event.uuid)">Edit</Link><button type="button" class="archive-action" @click="archiveEvent(event)">Arsipkan</button></div><div v-else class="row-actions"><button type="button" @click="restoreEvent(event)">Pulihkan</button><button type="button" class="archive-action" @click="deleteEvent(event)">Hapus permanen</button></div></td></tr></tbody></table></div><div class="footer"><span>Menampilkan {{ items.length }} dari {{ events.total }} event</span><div v-if="events.links?.length > 3" class="pager"><Link v-for="(link,index) in events.links" :key="index" :href="link.url || '#'" :class="{ active: link.active, disabled: !link.url }" :aria-disabled="!link.url" preserve-scroll v-html="link.label" /></div></div></section>
        </div>
    </main></div>
</template>

<style scoped>
.events-app{display:flex;min-height:100vh;background:#f5f7f8;color:#19333d;font-family:Inter,ui-sans-serif,system-ui,sans-serif}.events-app main{flex:1;min-width:0}.topbar{height:64px;background:#fff;border-bottom:1px solid #dce6e8;padding:0 28px;display:flex;align-items:center;justify-content:space-between;color:#70858c;font-size:11px}.topbar strong,.topbar small{display:block}.topbar strong{color:#19333d;font-size:12px}.topbar small{font-size:10px}.content{max-width:1440px;margin:auto;padding:34px 26px 70px}.heading{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:28px}.back{color:#007f78;font-size:12px;font-weight:700}.heading h1{font-size:30px;font-weight:750;letter-spacing:-.025em;margin:8px 0}.heading p{font-size:13px;color:#70858c;margin:0}.add{border-radius:8px;background:#007f78;color:#fff;padding:12px 18px;font-size:13px;font-weight:750;text-decoration:none}.tabs{display:flex;gap:10px;margin-bottom:22px}.tabs button{border:1px solid #dce6e8;border-radius:8px;background:#fff;color:#70858c;padding:10px 16px;font-size:12px;cursor:pointer}.tabs button.active{background:#e5f5f1;color:#007f78;border-color:#e5f5f1;font-weight:750}.filters{display:grid;grid-template-columns:minmax(0,1fr) minmax(160px,220px) auto auto;gap:12px;margin-bottom:28px}.filters :is(input,select,button){min-width:0;border:1px solid #dce6e8;border-radius:8px;background:#fff;padding:12px 15px;font:inherit;font-size:12px}.filters button{color:#007f78;font-weight:700;cursor:pointer}.panel{background:#fff;border:1px solid #dce6e8;border-radius:12px;padding:24px;min-width:0}.panel h2{font-size:18px;font-weight:750;margin:0 0 18px}.table-wrap{overflow-x:auto}table{border-collapse:collapse;width:100%;min-width:750px;font-size:12px}th{text-align:left;background:#f0f5f6;color:#70858c;padding:13px}td{padding:18px 13px;border-bottom:1px solid #e5edef}td:first-child{font-weight:750}.event-link{color:#19333d;text-decoration:underline;text-decoration-color:#9ccbc6;text-underline-offset:3px}.status{color:#007f78;font-weight:700}.status.archived{color:#70858c}.footer{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-top:22px;color:#70858c;font-size:11px}.pager{display:flex;gap:5px;flex-wrap:wrap}.pager a{border:1px solid #dce6e8;border-radius:5px;padding:6px 9px;color:#007f78;text-decoration:none}.pager a.active{background:#e5f5f1;font-weight:700}.pager a.disabled{opacity:.4;pointer-events:none}.empty{color:#70858c;font-size:13px;padding:20px}.events-app :is(a,button,input,select):focus-visible{outline:2px solid #008b83;outline-offset:2px}@media(max-width:800px){.events-app{display:block}}@media(max-width:650px){.content{padding:22px 14px}.heading{align-items:stretch;flex-direction:column}.heading h1{font-size:25px}.panel{padding:16px}.filters{grid-template-columns:1fr 1fr}.filters input,.filters select{grid-column:1/-1}}
</style>
<style scoped>.row-actions{display:flex;gap:12px;white-space:nowrap;align-items:center}.row-actions button{border:0;background:transparent;padding:0;color:#007f78;font:inherit;font-size:12px;font-weight:700;cursor:pointer}.row-actions button.archive-action{color:#b74336}</style>
