<script setup>
import RedesignTopbar from './RedesignTopbar.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminSidebar from './AdminSidebar.vue';

const props = defineProps({
    group: { type: Object, required: true },
    attachedRoadmaps: { type: Array, default: () => [] },
    availableRoadmaps: { type: Array, default: () => [] },
});
const page = usePage();
const canManage = computed(() => ['admin', 'super_admin'].includes(page.props.auth?.user?.role));
const tab = ref('attached');
const detaching = ref(null);
const busy = ref(false);
const form = useForm({ roadmap_uuid: '', sort_order: null });
const attachable = computed(() => props.availableRoadmaps.filter(roadmap => !props.attachedRoadmaps.some(attached => attached.uuid === roadmap.uuid)));
const nextSortOrder = computed(() => Math.max(0, ...props.attachedRoadmaps.map(roadmap => Number(roadmap.sort_order) || 0)) + 1);
const attach = () => {
    if (!form.roadmap_uuid || form.processing) return;
    form.sort_order = nextSortOrder.value;
    form.post(route('groups.roadmaps.attach', props.group.uuid), {
        preserveScroll: true,
        onSuccess: () => { form.reset(); tab.value = 'attached'; },
    });
};
const detach = () => {
    if (!detaching.value || busy.value) return;
    busy.value = true;
    router.delete(route('groups.roadmaps.detach', { uuid: props.group.uuid, roadmapUuid: detaching.value.uuid }), {
        preserveScroll: true,
        onSuccess: () => { detaching.value = null; },
        onFinish: () => { busy.value = false; },
    });
};
const nodeKind = type => ({ guide: 'Materi', quest: 'Quest' }[type] || 'Langkah');
</script>

<template>
    <Head :title="`Roadmap kelas · ${group.name}`" />
    <div class="roadmap-app"><AdminSidebar /><main>
        <RedesignTopbar breadcrumb="Program &amp; kelas" />
        <div class="content">
            <div class="heading"><Link :href="route('redesign.study-groups.show', group.uuid)" class="back">← {{ group.name }}</Link><h1>Roadmap kelas</h1><p>Atur alur pembelajaran yang terhubung dengan kelas ini.</p></div>
            <nav class="tabs" aria-label="Daftar roadmap"><button type="button" :class="{ active: tab === 'attached' }" @click="tab = 'attached'">Roadmap terhubung <span>{{ attachedRoadmaps.length }}</span></button><button type="button" :class="{ active: tab === 'available' }" @click="tab = 'available'">Roadmap tersedia <span>{{ attachable.length }}</span></button></nav>
            <div class="columns"><section class="panel roadmap-list">
                <template v-if="tab === 'attached'"><div class="section-head"><div><h2>Roadmap terhubung</h2><p>Langkah pembelajaran dari roadmap yang dipasang pada kelas.</p></div></div>
                    <div v-if="!attachedRoadmaps.length" class="empty">Belum ada roadmap yang terhubung. Pilih roadmap pada panel di samping untuk memulai.</div>
                    <article v-for="roadmap in attachedRoadmaps" :key="roadmap.uuid" class="roadmap-card"><div class="roadmap-head"><div><span class="eyebrow">ROADMAP {{ roadmap.sort_order }}</span><h3>{{ roadmap.title }}</h3><p>{{ roadmap.description || 'Belum ada deskripsi.' }}</p></div><button v-if="canManage" class="detach" type="button" @click="detaching = roadmap">Lepas</button></div>
                        <ol v-if="roadmap.nodes?.length" class="timeline"><li v-for="(node, index) in roadmap.nodes" :key="index"><span class="number">{{ String(index + 1).padStart(2, '0') }}</span><div><strong>{{ node.title }}</strong><small>{{ nodeKind(node.resource_type) }}</small></div></li></ol><p v-else class="no-steps">Roadmap ini belum memiliki langkah.</p>
                    </article>
                </template>
                <template v-else><div class="section-head"><div><h2>Roadmap tersedia</h2><p>Roadmap yang telah dipublikasikan dan belum terhubung ke kelas.</p></div></div><div v-if="!attachable.length" class="empty">Tidak ada roadmap lain yang tersedia.</div><div v-else class="available-list"><article v-for="roadmap in attachable" :key="roadmap.uuid"><h3>{{ roadmap.title }}</h3><p>{{ roadmap.description || 'Belum ada deskripsi.' }}</p><button v-if="canManage" type="button" @click="form.roadmap_uuid = roadmap.uuid">{{ form.roadmap_uuid === roadmap.uuid ? 'Dipilih' : 'Pilih roadmap' }}</button></article></div></template>
            </section><aside v-if="canManage" class="panel attach-panel"><h2>Hubungkan roadmap</h2><p>Pilih roadmap yang tersedia untuk ditampilkan di kelas {{ group.name }}.</p><form @submit.prevent="attach"><label for="roadmap-select">Roadmap tersedia</label><select id="roadmap-select" v-model="form.roadmap_uuid" :disabled="!attachable.length || form.processing"><option value="">Pilih roadmap</option><option v-for="roadmap in attachable" :key="roadmap.uuid" :value="roadmap.uuid">{{ roadmap.title }}</option></select><small v-if="form.errors.roadmap_uuid" class="error">{{ form.errors.roadmap_uuid }}</small><button type="submit" :disabled="!form.roadmap_uuid || form.processing">{{ form.processing ? 'Menghubungkan...' : 'Hubungkan roadmap' }}</button></form><p class="hint">Roadmap yang sudah terhubung dapat dilepas dari daftar di sebelah kiri.</p></aside></div>
        </div>
        <div v-if="detaching" class="dialog-backdrop" @click.self="detaching = null"><section class="dialog" role="dialog" aria-modal="true" aria-labelledby="detach-title"><h2 id="detach-title">Lepas roadmap?</h2><p>{{ detaching.title }} akan dilepas dari kelas ini.</p><div><button type="button" class="secondary" @click="detaching = null">Batal</button><button type="button" class="danger" :disabled="busy" @click="detach">{{ busy ? 'Memproses...' : 'Lepas roadmap' }}</button></div></section></div>
    </main></div>
</template>

<style scoped>
.roadmap-app{display:flex;min-height:100vh;background:#f5f7f8;color:#19333d;font-family:Inter,ui-sans-serif,system-ui,sans-serif}.roadmap-app main{flex:1;min-width:0}.topbar{height:64px;background:#fff;border-bottom:1px solid #dce6e8;padding:0 28px;display:flex;align-items:center;justify-content:space-between;color:#70858c;font-size:11px}.topbar strong,.topbar small{display:block}.topbar strong{color:#19333d;font-size:12px}.topbar small{font-size:10px}.content{max-width:1320px;margin:auto;padding:30px 26px 60px}.heading{margin-bottom:26px}.back{font-size:12px;color:#007f78;font-weight:700}.heading h1{font-size:30px;font-weight:750;letter-spacing:-.025em;margin:8px 0 0}.heading p,.section-head p,.attach-panel>p,.roadmap-head p,.available-list p{font-size:13px;color:#70858c;line-height:1.5}.heading p{margin:8px 0 0}.tabs{display:flex;gap:8px;border-bottom:1px solid #dce6e8;margin-bottom:24px;overflow-x:auto}.tabs button{border:0;border-bottom:2px solid transparent;background:none;padding:13px 18px;white-space:nowrap;color:#70858c;font-weight:650;cursor:pointer}.tabs button.active{color:#007f78;border-color:#007f78}.tabs span{font-size:11px;margin-left:6px}.columns{display:grid;grid-template-columns:minmax(0,1.8fr) minmax(260px,.75fr);align-items:start;gap:20px}.panel{background:#fff;border:1px solid #dce6e8;border-radius:12px;padding:24px;min-width:0}.section-head h2,.attach-panel h2{font-size:17px;font-weight:750;margin:0}.section-head p{margin:6px 0 0}.roadmap-card{border:1px solid #dce6e8;border-radius:10px;padding:20px;margin-top:20px}.roadmap-head{display:flex;justify-content:space-between;align-items:start;gap:12px}.eyebrow{font-size:10px;letter-spacing:.08em;font-weight:750;color:#008e85}.roadmap-head h3,.available-list h3{font-size:18px;margin:6px 0 0;font-weight:750}.roadmap-head p{margin:7px 0 0}.detach{border:1px solid #e8c7c3;background:#fff;color:#b74336;border-radius:7px;padding:8px 12px;font-weight:700;cursor:pointer}.timeline{list-style:none;padding:0;margin:25px 0 0}.timeline li{display:flex;gap:14px;position:relative;min-height:64px}.timeline li:not(:last-child):before{content:'';position:absolute;left:17px;top:36px;bottom:0;width:1px;background:#bfdcda}.number{position:relative;z-index:1;display:grid;place-items:center;width:36px;height:36px;flex:none;border-radius:50%;background:#e5f5f1;color:#007f78;font-size:11px;font-weight:750}.timeline strong,.timeline small{display:block}.timeline strong{font-size:13px;margin-top:2px}.timeline small{font-size:11px;color:#70858c;margin-top:5px}.no-steps,.empty{color:#70858c;font-size:13px;padding:22px;background:#f5f8f8;border-radius:8px;margin:20px 0 0}.attach-panel>p{margin:8px 0 22px}.attach-panel form{display:grid;gap:10px}.attach-panel label{font-size:12px;font-weight:700}.attach-panel select{width:100%;min-width:0;border:1px solid #dce6e8;border-radius:7px;padding:11px;background:#fff;color:#19333d}.attach-panel form button{border:0;border-radius:7px;background:#007f78;color:#fff;padding:12px;font-weight:700;cursor:pointer}.attach-panel form button:disabled{opacity:.5;cursor:not-allowed}.attach-panel .hint{border-top:1px solid #e5edef;padding-top:18px;margin-top:26px;font-size:11px}.error{color:#b74336}.available-list{display:grid;gap:12px;margin-top:20px}.available-list article{border:1px solid #dce6e8;border-radius:9px;padding:18px}.available-list p{margin:6px 0 12px}.available-list button{border:1px solid #c9dfdc;background:#fff;border-radius:6px;padding:8px 11px;color:#007f78;font-weight:700;cursor:pointer}.dialog-backdrop{position:fixed;inset:0;background:#122b32aa;display:grid;place-items:center;padding:16px;z-index:50}.dialog{background:#fff;border-radius:12px;padding:24px;width:min(100%,430px);box-shadow:0 20px 60px #102b3340}.dialog h2{margin:0;font-size:20px}.dialog p{color:#70858c}.dialog>div{display:flex;justify-content:flex-end;gap:10px;margin-top:20px}.danger,.secondary{border-radius:8px;padding:10px 16px;font-weight:700;cursor:pointer}.danger{border:0;background:#b74336;color:#fff}.secondary{border:1px solid #dce6e8;background:#fff;color:#007f78}.roadmap-app :is(a,button,select):focus-visible{outline:2px solid #008b83;outline-offset:2px}@media(max-width:950px){.columns{grid-template-columns:1fr}}@media(max-width:800px){.roadmap-app{display:block}}@media(max-width:600px){.content{padding:22px 14px}.panel{padding:18px}.topbar{padding:0 16px}.roadmap-card{padding:16px}}
</style>
