<script setup>
import RedesignTopbar from './RedesignTopbar.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminSidebar from './AdminSidebar.vue';

const props = defineProps({ guide: Object, studyGroups: { type: Array, default: () => [] }, selectedStudyGroup: Object });
const page = usePage();
const isEdit = computed(() => Boolean(props.guide?.uuid));
const backUrl = computed(() => props.selectedStudyGroup?.uuid
    ? route('redesign.study-groups.guides', props.selectedStudyGroup.uuid)
    : props.guide?.study_group?.uuid
        ? route('redesign.study-groups.guides', props.guide.study_group.uuid)
        : route('redesign.materi'));
const form = useForm({
    title: props.guide?.title || '', description: props.guide?.description || '',
    study_group_id: props.selectedStudyGroup?.id ?? props.guide?.study_group_id ?? null,
    content_source: props.guide?.google_docs_embed_url ? 'google_docs' : props.guide?.video_embed_url ? 'video' : 'file',
    google_docs_url: props.guide?.google_docs_embed_url || '', video_url: props.guide?.video_embed_url || '',
    file: null, redesign: 1,
});
const dragging = ref(false);
const fileInput = ref(null);
const selectFile = event => { form.file = event.target.files?.[0] || null; };
const dropFile = event => { dragging.value = false; form.file = event.dataTransfer.files?.[0] || null; };
const save = () => form.post(isEdit.value ? route('materi.update', props.guide.uuid) : route('materi.store'), { forceFormData: true });
</script>

<template>
    <Head :title="isEdit ? 'Edit materi' : 'Tambah materi'" />
    <div class="guide-form-app"><AdminSidebar /><main>
        <RedesignTopbar breadcrumb="Materi &amp; event" />
        <div class="content"><div class="heading"><Link :href="backUrl" class="back">← Perpustakaan materi</Link><h1>{{ isEdit ? 'Edit materi' : 'Tambah materi' }}</h1><p>Lengkapi informasi dan pilih sumber materi untuk kelas.</p></div>
            <div class="layout"><form id="guide-form" class="panel form-panel" @submit.prevent="save"><h2>Informasi materi</h2><p class="intro">Lengkapi detail berikut.</p><div class="fields">
                <label>Judul materi <input v-model="form.title" required maxlength="255" placeholder="Contoh: Laravel Routing" /><small v-if="form.errors.title" class="error">{{ form.errors.title }}</small></label>
                <label>Ringkasan <textarea v-model="form.description" rows="2" placeholder="Ringkasan materi" /><small v-if="form.errors.description" class="error">{{ form.errors.description }}</small></label>
                <label>Study group <select v-model="form.study_group_id" :disabled="!!selectedStudyGroup"><option :value="null">Materi umum</option><option v-for="group in studyGroups" :key="group.id" :value="group.id">{{ group.name }}</option></select><small v-if="form.errors.study_group_id" class="error">{{ form.errors.study_group_id }}</small></label>
                <label>Sumber konten <select v-model="form.content_source"><option value="file">File</option><option value="google_docs">Google Docs</option><option value="video">Video</option></select><small>Pilih satu sumber konten utama.</small></label>
                <label v-if="form.content_source === 'google_docs'" class="wide">URL Google Docs <input v-model="form.google_docs_url" type="url" placeholder="https://docs.google.com/document/..." required /><small v-if="form.errors.google_docs_url" class="error">{{ form.errors.google_docs_url }}</small></label>
                <label v-if="form.content_source === 'video'" class="wide">URL video <input v-model="form.video_url" type="url" placeholder="https://youtube.com/watch?..." required /><small v-if="form.errors.video_url" class="error">{{ form.errors.video_url }}</small></label>
                <div v-if="form.content_source === 'file'" class="wide"><label for="guide-file">Lampiran</label><div class="drop-zone" :class="{ dragging }" @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="dropFile"><input id="guide-file" ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png,.zip" @change="selectFile" /><strong>{{ form.file?.name || (isEdit && guide.file_path ? 'File tersimpan: ' + guide.file_path.split('/').pop() : 'Tarik file ke sini atau pilih file') }}</strong><span>PDF, JPG, PNG, ZIP · Maksimal 10 MB</span></div><small v-if="form.errors.file" class="error">{{ form.errors.file }}</small></div>
            </div><div class="form-actions"><button type="submit" class="primary" :disabled="form.processing">{{ form.processing ? 'Menyimpan...' : 'Simpan materi' }}</button><Link :href="backUrl" class="secondary">Batal</Link></div></form>
            <aside><section class="panel helper"><h2>Panduan pengisian</h2><p>Gunakan judul yang mudah dikenali. Pastikan kelas dan sumber konten sudah sesuai sebelum menyimpan.</p><p>Jika mengedit materi file tanpa memilih file baru, lampiran yang tersimpan tetap digunakan.</p></section><section class="panel helper"><h2>Konteks akses</h2><p>Materi akan tersedia mengikuti kelas dan hak akses yang berlaku di aplikasi.</p></section></aside></div>
        </div>
    </main></div>
</template>

<style scoped>
.guide-form-app{display:flex;min-height:100vh;background:#f5f7f8;color:#19333d;font-family:Inter,ui-sans-serif,system-ui,sans-serif}.guide-form-app main{flex:1;min-width:0}.topbar{height:64px;background:#fff;border-bottom:1px solid #dce6e8;padding:0 28px;display:flex;align-items:center;justify-content:space-between;color:#70858c;font-size:11px}.topbar strong,.topbar small{display:block}.topbar strong{color:#19333d;font-size:12px}.topbar small{font-size:10px}.content{max-width:1440px;margin:auto;padding:34px 26px 70px}.heading{margin-bottom:28px}.back{color:#007f78;font-weight:700;font-size:12px}.heading h1{font-size:30px;font-weight:750;letter-spacing:-.025em;margin:8px 0}.heading p,.intro{font-size:13px;color:#70858c;margin:0}.layout{display:grid;grid-template-columns:minmax(0,2fr) minmax(260px,.8fr);align-items:start;gap:20px}.panel{background:#fff;border:1px solid #dce6e8;border-radius:12px;padding:24px}.panel h2{font-size:18px;font-weight:750;margin:0 0 8px}.form-panel .intro{margin-bottom:24px}.fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px}.fields label{display:grid;align-content:start;gap:8px;font-size:12px;font-weight:700}.fields input,.fields select,.fields textarea{width:100%;min-width:0;border:1px solid #dce6e8;border-radius:8px;background:#fff;color:#19333d;padding:12px;font:inherit;font-weight:400}.fields textarea{resize:vertical}.fields small{font-size:11px;font-weight:400;color:#70858c}.fields .error{color:#b74336}.wide{grid-column:1/-1}.drop-zone{border:1px dashed #9fcac8;border-radius:8px;padding:22px;background:#f7fbfa;display:grid;gap:7px;color:#007f78;position:relative}.drop-zone.dragging{background:#e5f5f1;border-color:#007f78}.drop-zone input{position:absolute;inset:0;opacity:0;cursor:pointer}.drop-zone strong{font-size:12px;overflow-wrap:anywhere}.drop-zone span{font-size:11px;color:#70858c}.form-actions{display:flex;gap:12px;align-items:center;border-top:1px solid #e5edef;padding-top:22px;margin-top:28px}.primary,.secondary{border-radius:8px;padding:12px 18px;font-weight:750;font-size:12px;cursor:pointer;text-decoration:none}.primary{border:0;background:#007f78;color:#fff}.primary:disabled{opacity:.6}.secondary{border:1px solid #dce6e8;background:#fff;color:#007f78}.helper{margin-bottom:18px}.helper p{color:#70858c;font-size:13px;line-height:1.6}.guide-form-app :is(a,button,input,textarea,select):focus-visible,.drop-zone:focus-within{outline:2px solid #008b83;outline-offset:2px}@media(max-width:950px){.layout{grid-template-columns:1fr}}@media(max-width:800px){.guide-form-app{display:block}}@media(max-width:600px){.content{padding:22px 14px}.heading h1{font-size:25px}.panel{padding:18px}.fields{grid-template-columns:1fr}.wide{grid-column:auto}.form-actions{flex-wrap:wrap}}
</style>
