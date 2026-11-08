<script setup>
import RedesignTopbar from './RedesignTopbar.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminSidebar from './AdminSidebar.vue';

const props = defineProps({ group: { type: Object, default: null }, jobs: { type: Array, default: () => [] }, selectedJobId: Number });
const page = usePage();
const editing = computed(() => Boolean(props.group?.uuid));
const form = useForm({ redesign: '1', name: props.group?.name || '', description: props.group?.description || '', max_members: props.group?.max_members || 30, min_level: props.group?.min_level || 1, job_id: props.group?.job_id || props.selectedJobId || '' });
const save = () => {
    const options = { preserveScroll: true };
    if (editing.value) form.put(route('groups.update', props.group.uuid), options);
    else form.post(route('groups.store'), options);
};
</script>

<template>
    <Head :title="editing ? 'Edit study group' : 'Tambah study group'" />
    <div class="form-app"><AdminSidebar /><main>
        <RedesignTopbar breadcrumb="Program & kelas" />
        <div class="content"><div class="heading"><Link :href="route('redesign.study-groups')" class="back">← Study groups</Link><h1>{{ editing ? 'Edit study group' : 'Tambah study group' }}</h1><p>Kelola aktivitas dan data pembelajaran dalam satu workspace.</p></div>
            <div class="columns"><div><section class="panel"><h2>Informasi {{ editing ? 'edit' : 'tambah' }} study group</h2><p>Lengkapi detail berikut.</p><form id="group-form" class="fields" @submit.prevent="save"><label>Nama kelas<input v-model="form.name" required maxlength="255" placeholder="Contoh: Web Development A" /><small v-if="form.errors.name">{{ form.errors.name }}</small></label><label>Deskripsi / tujuan<textarea v-model="form.description" rows="2" placeholder="Tujuan kelas" /><small v-if="form.errors.description">{{ form.errors.description }}</small></label><label>Kapasitas anggota<input v-model.number="form.max_members" type="number" min="1" required /><small v-if="form.errors.max_members">{{ form.errors.max_members }}</small></label><label>Level minimum<input v-model.number="form.min_level" type="number" min="1" max="100" required /><small v-if="form.errors.min_level">{{ form.errors.min_level }}</small></label><label>Job path<select v-model="form.job_id" required><option value="" disabled>Pilih job / program</option><option v-for="job in jobs" :key="job.id" :value="job.id">{{ job.name }}</option></select><small v-if="form.errors.job_id">{{ form.errors.job_id }}</small></label></form></section><div class="actions"><button form="group-form" type="submit" class="primary" :disabled="form.processing">{{ form.processing ? 'Menyimpan...' : 'Simpan kelas' }}</button><Link :href="route('redesign.study-groups')" class="secondary">Batal</Link></div></div><aside><section class="panel guide"><h2>Panduan pengisian</h2><p>Gunakan nama yang mudah dikenali. Pastikan kapasitas, level minimum, dan job sesuai sebelum menyimpan.</p></section><section class="panel guide"><h2>Konteks akses</h2><p>Hak pengelolaan kelas mengikuti role di aplikasi.</p></section></aside></div>
        </div>
    </main></div>
</template>

<style scoped>
.form-app{display:flex;min-height:100vh;background:#f5f7f8;color:#19333d;font-family:Inter,ui-sans-serif,system-ui,sans-serif}.form-app main{flex:1;min-width:0}.topbar{height:64px;background:#fff;border-bottom:1px solid #dce6e8;padding:0 28px;display:flex;align-items:center;justify-content:space-between;color:#70858c;font-size:11px}.topbar strong,.topbar small{display:block}.topbar strong{color:#19333d;font-size:12px}.topbar small{font-size:10px}.content{max-width:1320px;margin:auto;padding:30px 26px 60px}.heading{margin-bottom:30px}.back{font-size:12px;color:#007f78;font-weight:700}.heading h1{font-size:30px;font-weight:750;letter-spacing:-.025em;margin:8px 0 0}.heading p,.panel>p{color:#70858c;font-size:13px;margin:8px 0 0}.columns{display:grid;grid-template-columns:minmax(0,2.5fr) minmax(230px,1fr);gap:20px}.panel{background:#fff;border:1px solid #dce6e8;border-radius:12px;padding:24px}.panel h2{font-size:18px;font-weight:750;margin:0}.fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px;margin-top:26px}.fields label{display:flex;flex-direction:column;gap:8px;font-size:12px;font-weight:700;min-width:0}.fields input,.fields textarea,.fields select{width:100%;border:1px solid #dce6e8;border-radius:8px;background:#fafbfc;padding:12px 14px;color:#19333d;font:inherit;font-weight:400}.fields small{color:#b74336}.primary{background:#007f78;color:#fff;border:0;border-radius:8px;padding:13px 22px;font-weight:700;cursor:pointer}.primary:disabled{opacity:.55}.secondary{border:1px solid #dce6e8;border-radius:8px;padding:12px 20px;color:#007f78;font-weight:700;background:#fff}.actions{display:flex;align-items:center;gap:12px;margin-top:20px}.actions .primary{min-width:180px}.guide{min-height:205px;margin-bottom:20px}.guide p{color:#70858c;line-height:1.7}.form-app :is(a,button,input,select,textarea):focus-visible{outline:2px solid #008b83;outline-offset:2px}@media(max-width:900px){.columns{grid-template-columns:1fr}.guide{min-height:0}}@media(max-width:800px){.form-app{display:block}}@media(max-width:600px){.content{padding:22px 14px}.fields{grid-template-columns:1fr}.actions{flex-wrap:wrap}.panel{padding:18px}}
</style>
