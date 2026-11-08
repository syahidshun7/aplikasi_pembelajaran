<script setup>
import RedesignTopbar from './RedesignTopbar.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import AdminSidebar from './AdminSidebar.vue';

const props = defineProps({ job: { type: Object, default: null } });
const page = usePage();
const isEditing = computed(() => Boolean(props.job?.id));
const fileInput = ref(null);
const objectUrl = ref('');
const emblemUrl = computed(() => objectUrl.value || (props.job?.emblem_path ? `/storage/${props.job.emblem_path}` : ''));
const form = useForm({ _method: isEditing.value ? 'PUT' : 'POST', redesign: '1', name: props.job?.name || '', description: props.job?.description || '', status: props.job?.status || 'active', emblem: null });

const selectEmblem = () => fileInput.value?.click();
const onEmblemChange = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    if (objectUrl.value) URL.revokeObjectURL(objectUrl.value);
    objectUrl.value = URL.createObjectURL(file);
    form.emblem = file;
};
const submit = () => form.post(isEditing.value ? route('admin.jobs.update', props.job.id) : route('admin.jobs.store'), { preserveScroll: true });
onBeforeUnmount(() => { if (objectUrl.value) URL.revokeObjectURL(objectUrl.value); });
</script>

<template>
    <Head :title="isEditing ? 'Edit job' : 'Tambah job'" />
    <div class="job-form-app">
        <AdminSidebar />
        <main>
            <RedesignTopbar breadcrumb="Program & kelas" />
            <div class="content">
                <div class="heading"><h1>{{ isEditing ? 'Edit job' : 'Tambah job' }}</h1><p>Kelola aktivitas dan data pembelajaran dalam satu workspace.</p></div>
                <div class="columns">
                    <div>
                        <section class="panel"><h2>Informasi {{ isEditing ? 'edit' : 'tambah' }} job</h2><p>Lengkapi detail berikut.</p>
                            <form id="job-form" class="fields" @submit.prevent="submit">
                                <label>Nama job<input v-model="form.name" required maxlength="255" placeholder="Contoh: Web Developer" /><small v-if="form.errors.name">{{ form.errors.name }}</small></label>
                                <label>Deskripsi<textarea v-model="form.description" rows="2" placeholder="Jelaskan tujuan program" /><small v-if="form.errors.description">{{ form.errors.description }}</small></label>
                                <label>Status<select v-model="form.status"><option value="active">Aktif</option><option value="coming_soon">Segera hadir</option><option value="draft">Draft</option></select><small v-if="form.errors.status">{{ form.errors.status }}</small></label>
                                <div class="emblem-field"><strong>Emblem job</strong><div class="emblem-picker"><div class="preview"><img v-if="emblemUrl" :src="emblemUrl" alt="Preview emblem job" /><span v-else>Gambar</span></div><div><p>JPG, PNG, atau WebP · maksimal 2 MB</p><input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="visually-hidden" @change="onEmblemChange" /><button type="button" class="secondary" @click="selectEmblem">{{ emblemUrl ? 'Ganti gambar' : 'Pilih gambar' }}</button><small v-if="form.errors.emblem">{{ form.errors.emblem }}</small></div></div></div>
                            </form>
                        </section>
                        <div class="actions"><button type="submit" form="job-form" class="primary" :disabled="form.processing">{{ form.processing ? 'Menyimpan...' : 'Simpan job' }}</button><Link :href="route('redesign.jobs')" class="secondary">Batal</Link></div>
                    </div>
                    <aside><section class="panel guide"><h2>Panduan pengisian</h2><p>Pastikan informasi sudah sesuai sebelum menyimpan. Gunakan nama yang mudah dikenali dan periksa kembali lingkup program.</p></section><section class="panel guide"><h2>Konteks akses</h2><p>Super admin / Admin<br />Hak akses mengikuti role di aplikasi.</p></section></aside>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
.job-form-app{display:flex;min-height:100vh;background:#f5f7f8;color:#19333d;font-family:Inter,ui-sans-serif,system-ui,sans-serif}.job-form-app main{flex:1;min-width:0}.topbar{height:64px;background:#fff;border-bottom:1px solid #dce6e8;padding:0 28px;display:flex;align-items:center;justify-content:space-between;color:#70858c;font-size:11px}.topbar strong,.topbar small{display:block}.topbar strong{color:#19333d;font-size:12px}.topbar small{font-size:10px}.content{max-width:1320px;margin:auto;padding:30px 26px 60px}.heading{margin-bottom:30px}.heading h1{font-size:30px;font-weight:750;letter-spacing:-.025em;margin:0}.heading p,.panel>p{color:#70858c;font-size:13px;margin:8px 0 0}.columns{display:grid;grid-template-columns:minmax(0,2.5fr) minmax(230px,1fr);gap:20px}.panel{background:#fff;border:1px solid #dce6e8;border-radius:12px;padding:24px}.panel h2{font-size:18px;font-weight:750;margin:0}.fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px;margin-top:26px}.fields label{display:flex;flex-direction:column;gap:8px;font-size:12px;font-weight:700;min-width:0}.fields input,.fields textarea,.fields select{width:100%;border:1px solid #dce6e8;border-radius:8px;background:#fafbfc;padding:12px 14px;color:#19333d;font:inherit;font-weight:400}.fields small{color:#b74336}.emblem-field{min-width:0;font-size:12px}.emblem-picker{display:flex;align-items:center;gap:14px;margin-top:8px}.emblem-picker p{color:#70858c;font-weight:400;margin:0 0 10px}.emblem-picker small{display:block;margin-top:8px}.preview{width:70px;height:70px;flex:none;border:1px solid #dce6e8;border-radius:10px;background:#e5f5f1;color:#007f78;display:grid;place-items:center;overflow:hidden}.preview img{width:100%;height:100%;object-fit:contain}.primary{background:#007f78;color:#fff;border:0;border-radius:8px;padding:13px 22px;font-weight:700;cursor:pointer}.primary:disabled{opacity:.55}.secondary{border:1px solid #dce6e8;border-radius:8px;padding:12px 20px;color:#007f78;font-weight:700;background:#fff;cursor:pointer}.emblem-picker .secondary{padding:9px 13px}.actions{display:flex;align-items:center;gap:12px;margin-top:20px}.actions .primary{min-width:180px}.guide{min-height:205px;margin-bottom:20px}.guide p{color:#70858c;line-height:1.7}.visually-hidden{position:absolute;width:1px;height:1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}.job-form-app :is(a,button,input,select,textarea):focus-visible{outline:2px solid #008b83;outline-offset:2px}@media(max-width:900px){.columns{grid-template-columns:1fr}.guide{min-height:0}}@media(max-width:800px){.job-form-app{display:block}}@media(max-width:600px){.content{padding:22px 14px}.fields{grid-template-columns:1fr}.actions{flex-wrap:wrap}.panel{padding:18px}}
</style>
