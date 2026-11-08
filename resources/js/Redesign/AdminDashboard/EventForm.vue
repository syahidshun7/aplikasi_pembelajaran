<script setup>
import RedesignTopbar from './RedesignTopbar.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AdminSidebar from './AdminSidebar.vue';
import { toAppDateTimeLocal } from './dateTime';

const props = defineProps({ event: Object, studyGroups: { type: Array, default: () => [] }, jobRoles: { type: Array, default: () => [] }, selectedStudyGroup: Object, timezone: { type: String, default: 'UTC' } });
const page = usePage();
const isEdit = computed(() => Boolean(props.event?.uuid));
const isMentor = computed(() => page.props.auth?.user?.role === 'mentor');
const backUrl = computed(() => props.selectedStudyGroup?.uuid ? route('redesign.study-groups.events', props.selectedStudyGroup.uuid) : props.event?.study_group?.uuid ? route('redesign.study-groups.events', props.event.study_group.uuid) : route('redesign.events'));
const localDate = value => toAppDateTimeLocal(value, props.timezone);
const form = useForm({
    title: props.event?.title || '', description: props.event?.description || '', sequence_order: props.event?.sequence_order || 1,
    study_group_id: props.selectedStudyGroup?.id ?? props.event?.study_group_id ?? '', job_id: props.event?.job_id ?? '',
    starts_at: localDate(props.event?.starts_at), ends_at: localDate(props.event?.ends_at),
    self_attendance_enabled: Boolean(props.event?.self_attendance_enabled), images: [], remove_image_ids: [], redesign: 1,
});
const existingImages = ref(props.event?.images || []);
const previews = ref([]);
const imageError = ref('');
const dateError = computed(() => {
    if (form.ends_at && !form.starts_at) return 'Isi waktu mulai sebelum waktu selesai.';
    if (form.starts_at && form.ends_at && form.ends_at <= form.starts_at) return 'Waktu selesai harus setelah waktu mulai.';
    return '';
});
const selectedGroup = computed(() => props.studyGroups.find(group => Number(group.id) === Number(form.study_group_id)));
watch(() => form.study_group_id, () => { if (selectedGroup.value) form.job_id = selectedGroup.value.job_id || ''; });
const imageUrl = path => `/storage/${String(path || '').replace(/^\/+/, '')}`;
const clearPreviews = () => { previews.value.forEach(item => URL.revokeObjectURL(item.url)); previews.value = []; };
const selectImages = event => {
    imageError.value = '';
    clearPreviews();
    const files = Array.from(event.target.files || []);
    if (files.length + existingImages.value.length > 8) { imageError.value = 'Maksimal 8 gambar untuk satu event.'; event.target.value = ''; form.images = []; return; }
    form.images = files;
    previews.value = files.map(file => ({ name: file.name, url: URL.createObjectURL(file) }));
};
const removeExisting = image => { form.remove_image_ids.push(image.id); existingImages.value = existingImages.value.filter(item => item.id !== image.id); };
const save = () => {
    if (imageError.value || dateError.value || form.processing) return;
    form.transform(data => ({ ...data, _method: isEdit.value ? 'put' : undefined, sequence_order: Number(data.sequence_order || 1), study_group_id: data.study_group_id || null, job_id: data.job_id || null, starts_at: data.starts_at || null, ends_at: data.ends_at || null, self_attendance_enabled: data.self_attendance_enabled ? 1 : 0 }));
    form.post(isEdit.value ? route('admin.events.update', props.event.uuid) : route('admin.events.store'), { forceFormData: true });
};
onBeforeUnmount(clearPreviews);
</script>

<template>
    <Head :title="isEdit ? 'Edit event' : 'Tambah event'" />
    <div class="event-form-app"><AdminSidebar /><main>
        <RedesignTopbar breadcrumb="Materi &amp; event" />
        <div class="content"><div class="heading"><Link :href="backUrl" class="back">← Event kelas</Link><h1>{{ isEdit ? 'Edit event' : 'Tambah event' }}</h1><p>Lengkapi detail dan jadwal kegiatan kelas.</p></div>
            <div class="layout"><form class="panel form-panel" @submit.prevent="save"><h2>Informasi event</h2><p class="intro">Lengkapi detail berikut. Waktu menggunakan zona {{ timezone }}.</p><div class="fields">
                <label>Judul event<input v-model="form.title" required maxlength="255" placeholder="Contoh: Workshop Laravel" /><small v-if="form.errors.title" class="error">{{ form.errors.title }}</small></label>
                <label>Deskripsi<textarea v-model="form.description" rows="2" placeholder="Deskripsi kegiatan" /><small v-if="form.errors.description" class="error">{{ form.errors.description }}</small></label>
                <label>Urutan<input v-model.number="form.sequence_order" type="number" min="1" required /><small v-if="form.errors.sequence_order" class="error">{{ form.errors.sequence_order }}</small></label>
                <label>Study group<select v-model="form.study_group_id" :disabled="!!selectedStudyGroup || isMentor"><option value="">Event umum</option><option v-for="group in studyGroups" :key="group.id" :value="group.id">{{ group.name }}</option></select><small v-if="form.errors.study_group_id" class="error">{{ form.errors.study_group_id }}</small></label>
                <label>Target job<select v-model="form.job_id" :disabled="!!selectedGroup || isMentor"><option value="">Semua job</option><option v-for="job in jobRoles" :key="job.id" :value="job.id">{{ job.name }}</option></select><small v-if="form.errors.job_id" class="error">{{ form.errors.job_id }}</small></label>
                <label>Mulai<input v-model="form.starts_at" type="datetime-local" :required="!!form.ends_at" /><small v-if="form.errors.starts_at" class="error">{{ form.errors.starts_at }}</small></label>
                <label>Selesai<input v-model="form.ends_at" type="datetime-local" :min="form.starts_at || undefined" /><small v-if="form.errors.ends_at || dateError" class="error">{{ form.errors.ends_at || dateError }}</small></label>
                <label>Self attendance<select v-model="form.self_attendance_enabled"><option :value="false">Dinonaktifkan</option><option :value="true">Diaktifkan</option></select><small v-if="form.errors.self_attendance_enabled" class="error">{{ form.errors.self_attendance_enabled }}</small></label>
                <div class="wide"><label for="event-images">Gambar event</label><input id="event-images" type="file" multiple accept="image/jpeg,image/png,image/webp" @change="selectImages" /><p class="hint">JPG, PNG, WebP · Maksimal 4 MB per gambar · Maksimal 8 gambar</p><small v-if="imageError || form.errors.images" class="error">{{ imageError || form.errors.images }}</small><div v-if="existingImages.length || previews.length" class="image-grid"><div v-for="image in existingImages" :key="image.id" class="image-item"><img :src="imageUrl(image.path)" alt="Gambar event tersimpan" /><button type="button" @click="removeExisting(image)">Hapus</button></div><div v-for="preview in previews" :key="preview.url" class="image-item"><img :src="preview.url" :alt="preview.name" /><span>Baru</span></div></div></div>
            </div><div class="form-actions"><button type="submit" class="primary" :disabled="form.processing">{{ form.processing ? 'Menyimpan...' : 'Simpan event' }}</button><Link :href="backUrl" class="secondary">Batal</Link></div></form>
            <aside><section class="panel helper"><h2>Panduan pengisian</h2><p>Gunakan judul yang mudah dikenali dan periksa jadwal serta kelas tujuan sebelum menyimpan.</p><p>Jika study group dipilih, target job mengikuti job kelas tersebut.</p></section><section class="panel helper"><h2>Konteks akses</h2><p>Event mengikuti hak akses kelas atau job yang dipilih.</p></section></aside></div>
        </div>
    </main></div>
</template>

<style scoped>
.event-form-app{display:flex;min-height:100vh;background:#f5f7f8;color:#19333d;font-family:Inter,ui-sans-serif,system-ui,sans-serif}.event-form-app main{flex:1;min-width:0}.topbar{height:64px;background:#fff;border-bottom:1px solid #dce6e8;padding:0 28px;display:flex;align-items:center;justify-content:space-between;color:#70858c;font-size:11px}.topbar strong,.topbar small{display:block}.topbar strong{color:#19333d;font-size:12px}.topbar small{font-size:10px}.content{max-width:1440px;margin:auto;padding:34px 26px 70px}.heading{margin-bottom:28px}.back{color:#007f78;font-size:12px;font-weight:700}.heading h1{font-size:30px;font-weight:750;letter-spacing:-.025em;margin:8px 0}.heading p,.intro{color:#70858c;font-size:13px;margin:0}.layout{display:grid;grid-template-columns:minmax(0,2fr) minmax(260px,.8fr);align-items:start;gap:20px}.panel{background:#fff;border:1px solid #dce6e8;border-radius:12px;padding:24px}.panel h2{font-size:18px;font-weight:750;margin:0 0 8px}.intro{margin-bottom:24px}.fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px}.fields label{display:grid;align-content:start;gap:8px;font-size:12px;font-weight:700}.fields :is(input,select,textarea){width:100%;min-width:0;border:1px solid #dce6e8;border-radius:8px;background:#fff;color:#19333d;padding:12px;font:inherit;font-weight:400}.fields textarea{resize:vertical}.fields small,.hint{font-size:11px;color:#70858c;font-weight:400}.fields .error{color:#b74336}.wide{grid-column:1/-1}.wide>label{margin-bottom:8px}.hint{margin:7px 0}.image-grid{display:flex;flex-wrap:wrap;gap:12px;margin-top:14px}.image-item{width:110px;position:relative}.image-item img{width:110px;height:86px;object-fit:cover;border-radius:6px;border:1px solid #dce6e8}.image-item button,.image-item span{display:block;margin-top:4px;border:0;background:none;color:#b74336;font-size:11px;font-weight:700;cursor:pointer}.image-item span{color:#007f78}.form-actions{display:flex;gap:12px;border-top:1px solid #e5edef;padding-top:22px;margin-top:28px}.primary,.secondary{border-radius:8px;padding:12px 18px;font-size:12px;font-weight:750;text-decoration:none;cursor:pointer}.primary{border:0;background:#007f78;color:#fff}.primary:disabled{opacity:.6}.secondary{border:1px solid #dce6e8;background:#fff;color:#007f78}.helper{margin-bottom:18px}.helper p{color:#70858c;font-size:13px;line-height:1.6}.event-form-app :is(a,button,input,textarea,select):focus-visible{outline:2px solid #008b83;outline-offset:2px}@media(max-width:950px){.layout{grid-template-columns:1fr}}@media(max-width:800px){.event-form-app{display:block}}@media(max-width:600px){.content{padding:22px 14px}.heading h1{font-size:25px}.panel{padding:18px}.fields{grid-template-columns:1fr}.wide{grid-column:auto}.form-actions{flex-wrap:wrap}}
</style>
