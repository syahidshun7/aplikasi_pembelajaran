<script setup>
import RedesignTopbar from './RedesignTopbar.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AdminSidebar from './AdminSidebar.vue';

const props = defineProps({ taskBank: Object, jobs: { type: Array, default: () => [] } });
const page = usePage();
const isEdit = computed(() => Boolean(props.taskBank?.uuid));
const isMentor = computed(() => page.props.auth?.user?.role === 'mentor');
const backUrl = route('redesign.task-banks');
const form = useForm({
    name: props.taskBank?.name || '',
    description: props.taskBank?.description || '',
    job_role_id: props.taskBank?.job_role_id ?? (isMentor.value ? props.jobs[0]?.id ?? null : null),
    assessment_type: props.taskBank?.assessment_type || 'essay',
    duration: props.taskBank?.duration ?? 60,
    has_time_limit: props.taskBank?.has_time_limit ?? false,
    question_display_mode: props.taskBank?.question_display_mode || 'all',
    is_active: props.taskBank?.is_active ?? true,
    redesign_form: 1,
});
const gameType = computed(() => ['platforming', 'word_match'].includes(form.assessment_type));
watch(() => form.assessment_type, () => {
    if (gameType.value) { form.has_time_limit = true; form.question_display_mode = 'all'; }
});
const save = () => {
    if (form.processing) return;
    form.transform(data => ({ ...data, job_role_id: data.job_role_id || null, duration: Number(data.duration),
        has_time_limit: gameType.value || data.has_time_limit ? 1 : 0, is_active: data.is_active ? 1 : 0,
        question_display_mode: gameType.value ? 'all' : data.question_display_mode }));
    if (isEdit.value) form.put(route('admin.task-banks.update', props.taskBank.uuid));
    else form.post(route('admin.task-banks.store'));
};
</script>

<template>
    <Head :title="isEdit ? 'Edit task bank' : 'Tambah task bank'" />
    <div class="bank-form-app"><AdminSidebar /><main>
        <RedesignTopbar breadcrumb="Quest &amp; task bank" />
        <div class="content"><div class="heading"><Link :href="backUrl" class="back">← Task banks</Link><h1>Pengaturan task bank</h1><p>{{ isEdit ? 'Perbarui konfigurasi bank soal.' : 'Lengkapi konfigurasi bank soal baru.' }}</p></div>
            <div class="layout"><form class="panel form-panel" @submit.prevent="save"><h2>Informasi pengaturan task bank</h2><p class="intro">Periksa tipe asesmen, timer, dan cakupan program sebelum menyimpan.</p><div class="fields">
                <label class="wide">Nama bank<input v-model="form.name" required maxlength="255" placeholder="Contoh: Laravel Fundamentals" /><small v-if="form.errors.name" class="error">{{ form.errors.name }}</small></label>
                <label class="wide">Deskripsi<textarea v-model="form.description" rows="3" placeholder="Tujuan dan cakupan bank soal" /><small v-if="form.errors.description" class="error">{{ form.errors.description }}</small></label>
                <label>Job scope<select v-model="form.job_role_id" :disabled="isMentor"><option :value="null">Semua program</option><option v-for="job in jobs" :key="job.id" :value="job.id">{{ job.name }}</option></select><small v-if="form.errors.job_role_id" class="error">{{ form.errors.job_role_id }}</small></label>
                <label>Assessment type<select v-model="form.assessment_type"><option value="essay">Esai</option><option value="multiple_choice">Pilihan ganda</option><option value="mixed">Campuran</option><option value="platforming">Platforming</option><option value="word_match">Word match</option></select><small v-if="form.errors.assessment_type" class="error">{{ form.errors.assessment_type }}</small></label>
                <label v-if="gameType">Durasi game (detik)<input v-model.number="form.duration" type="number" min="5" max="3600" required /><small class="hint">Platforming: per soal. Word match: total sesi.</small><small v-if="form.errors.duration" class="error">{{ form.errors.duration }}</small></label>
                <label v-else>Exam timer<select v-model="form.has_time_limit"><option :value="false">Nonaktif</option><option :value="true">Aktif</option></select><small v-if="form.errors.has_time_limit" class="error">{{ form.errors.has_time_limit }}</small></label>
                <label v-if="!gameType && form.has_time_limit">Durasi ujian (menit)<input v-model.number="form.duration" type="number" min="1" max="1440" required /><small v-if="form.errors.duration" class="error">{{ form.errors.duration }}</small></label>
                <label v-if="!gameType">Question display<select v-model="form.question_display_mode"><option value="all">Semua soal</option><option value="single">Satu per halaman</option></select><small v-if="form.errors.question_display_mode" class="error">{{ form.errors.question_display_mode }}</small></label>
                <label>Bank active<select v-model="form.is_active"><option :value="true">Aktif</option><option :value="false">Draft</option></select><small v-if="form.errors.is_active" class="error">{{ form.errors.is_active }}</small></label>
            </div><div class="form-actions"><button type="submit" class="primary" :disabled="form.processing">{{ form.processing ? 'Menyimpan...' : 'Simpan bank' }}</button><Link :href="backUrl" class="secondary">Batal</Link></div></form>
            <aside><section class="panel helper"><h2>Panduan pengisian</h2><p>Gunakan nama yang mudah dikenali. Pilih tipe asesmen sesuai soal yang akan ditambahkan ke bank.</p><p>Durasi game disimpan dalam detik; durasi ujian biasa disimpan dalam menit.</p></section><section class="panel helper"><h2>Konteks akses</h2><p>Bank soal mengikuti program yang dipilih dan hak akses peran di aplikasi.</p></section></aside></div>
        </div>
    </main></div>
</template>

<style scoped>
.bank-form-app{display:flex;min-height:100vh;background:#f5f7f8;color:#19333d;font-family:Inter,ui-sans-serif,system-ui,sans-serif}.bank-form-app main{flex:1;min-width:0}.topbar{height:64px;background:#fff;border-bottom:1px solid #dce6e8;padding:0 28px;display:flex;align-items:center;justify-content:space-between;color:#70858c;font-size:11px}.topbar strong,.topbar small{display:block}.topbar strong{color:#19333d;font-size:12px}.topbar small{font-size:10px}.content{max-width:1440px;margin:auto;padding:34px 26px 70px}.heading{margin-bottom:28px}.back{color:#007f78;font-size:12px;font-weight:700}.heading h1{font-size:30px;font-weight:750;letter-spacing:-.025em;margin:8px 0}.heading p,.intro{color:#70858c;font-size:13px;margin:0}.layout{display:grid;grid-template-columns:minmax(0,2fr) minmax(260px,.8fr);align-items:start;gap:20px}.panel{background:#fff;border:1px solid #dce6e8;border-radius:12px;padding:24px}.panel h2{font-size:18px;font-weight:750;margin:0 0 8px}.intro{margin-bottom:24px}.fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px}.fields label{display:grid;align-content:start;gap:8px;font-size:12px;font-weight:700}.fields .wide{grid-column:1/-1}.fields :is(input,select,textarea){width:100%;min-width:0;border:1px solid #dce6e8;border-radius:8px;background:#fff;color:#19333d;padding:12px;font:inherit;font-weight:400}.fields textarea{resize:vertical}.fields :disabled{background:#f6f8f9}.hint{font-size:11px;color:#70858c;font-weight:400}.error{font-size:11px;color:#b74336;font-weight:400}.form-actions{display:flex;gap:12px;border-top:1px solid #e5edef;padding-top:22px;margin-top:28px}.primary,.secondary{border-radius:8px;padding:12px 18px;font-size:12px;font-weight:750;text-decoration:none;cursor:pointer}.primary{border:0;background:#007f78;color:#fff;min-width:180px}.primary:disabled{opacity:.6}.secondary{border:1px solid #dce6e8;background:#fff;color:#007f78}.helper{margin-bottom:18px}.helper p{color:#70858c;font-size:13px;line-height:1.6}.bank-form-app :is(a,button,input,textarea,select):focus-visible{outline:2px solid #008b83;outline-offset:2px}@media(max-width:950px){.layout{grid-template-columns:1fr}}@media(max-width:800px){.bank-form-app{display:block}}@media(max-width:600px){.content{padding:22px 14px}.heading h1{font-size:25px}.panel{padding:18px}.fields{grid-template-columns:1fr}.fields .wide{grid-column:auto}.form-actions{flex-wrap:wrap}.primary{min-width:0}}
</style>
