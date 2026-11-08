<script setup>
import RedesignTopbar from './RedesignTopbar.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import axios from 'axios';
import AdminSidebar from './AdminSidebar.vue';

const props = defineProps({ selectedStudyGroup: Object, studyGroups: { type: Array, default: () => [] }, jobRoles: { type: Array, default: () => [] }, timezone: { type: String, default: 'UTC' } });
const page = usePage();
const isMentor = computed(() => page.props.auth?.user?.role === 'mentor');
const backUrl = computed(() => props.selectedStudyGroup?.uuid ? route('redesign.study-groups.quests', props.selectedStudyGroup.uuid) : route('redesign.quests'));
const form = reactive({
    job_id: String(props.selectedStudyGroup?.job_id || (isMentor.value ? page.props.auth?.user?.job_id || '' : '')),
    study_group_id: String(props.selectedStudyGroup?.id || ''),
    theme: '', ai_note: '', question_type: 'mixed', question_count: 5, difficulty: 'C-Rank', quest_type: 'main',
    publish_mode: 'draft', available_from: '', available_until: '', deadline: '',
});
const groups = computed(() => props.studyGroups.filter(group => !form.job_id || String(group.job_id) === form.job_id));
const preview = ref(null);
const generating = ref(false);
const committing = ref(false);
const error = ref('');
const success = ref('');
const questionTypes = { multiple_choice: 'Pilihan ganda', essay: 'Esai', mixed: 'Campuran', platforming: 'Platforming', word_match: 'Word match' };
const dateError = computed(() => {
    if (form.publish_mode !== 'schedule') return '';
    if (!form.available_from) return 'Isi waktu publish untuk mode terjadwal.';
    if (form.available_until && form.available_until <= form.available_from) return 'Waktu sembunyikan harus setelah waktu publish.';
    if (form.deadline && form.deadline <= form.available_from) return 'Deadline harus setelah waktu publish.';
    return '';
});
watch(() => form.job_id, () => {
    if (!groups.value.some(group => String(group.id) === form.study_group_id)) form.study_group_id = '';
});
watch(() => form.study_group_id, value => {
    const group = props.studyGroups.find(item => String(item.id) === value);
    if (group) form.job_id = String(group.job_id || '');
});
watch(() => [form.job_id, form.study_group_id, form.theme, form.ai_note, form.question_type, form.question_count, form.difficulty, form.quest_type], () => { preview.value = null; success.value = ''; });
watch(() => form.publish_mode, value => { if (value !== 'schedule') { form.available_from = ''; form.available_until = ''; } });
const firstError = response => {
    const errors = response?.data?.errors;
    return errors && typeof errors === 'object' ? Object.values(errors).flat()[0] : response?.data?.message;
};
const generate = async () => {
    if (generating.value || committing.value) return;
    error.value = '';
    success.value = '';
    preview.value = null;
    generating.value = true;
    try {
        const response = await axios.post(route('admin.quests.optional.theme-preview'), {
            theme: form.theme.trim(), ai_note: form.ai_note.trim() || null, question_type: form.question_type,
            question_count: Number(form.question_count), difficulty: form.difficulty, quest_type: form.quest_type,
            job_id: form.job_id ? Number(form.job_id) : null, study_group_id: form.study_group_id ? Number(form.study_group_id) : null,
        });
        preview.value = response.data;
        success.value = 'Preview siap ditinjau. Periksa judul, deskripsi, dan soal sebelum menyimpan.';
    } catch (failure) {
        error.value = String(firstError(failure.response) || 'Gagal membuat preview. Coba lagi.');
    } finally {
        generating.value = false;
    }
};
const commit = async () => {
    if (!preview.value?.bundle || committing.value || dateError.value) return;
    error.value = '';
    success.value = '';
    committing.value = true;
    try {
        await axios.post(route('admin.quests.optional.commit-theme'), {
            bundle: preview.value.bundle, publish_mode: form.publish_mode, quest_type: form.quest_type,
            available_from: form.publish_mode === 'schedule' ? form.available_from || null : null,
            available_until: form.publish_mode === 'schedule' ? form.available_until || null : null,
            deadline: form.deadline || null,
            job_id: form.job_id ? Number(form.job_id) : null, study_group_id: form.study_group_id ? Number(form.study_group_id) : null,
        });
        router.visit(backUrl.value);
    } catch (failure) {
        error.value = String(firstError(failure.response) || 'Gagal menyimpan quest. Periksa kembali datanya.');
    } finally {
        committing.value = false;
    }
};
</script>

<template>
    <Head title="Generate quest with AI" />
    <div class="ai-app"><AdminSidebar /><main>
        <RedesignTopbar breadcrumb="Quest &amp; task bank" />
        <div class="content"><div class="heading"><div><Link :href="backUrl" class="back">← Quest management</Link><h1>Generate quest with AI</h1><p>Buat quest dan task bank dari topik pembelajaran, lalu tinjau hasilnya sebelum menyimpan.</p></div></div>
            <div class="layout"><div><form id="ai-quest-form" class="panel" @submit.prevent="generate"><h2>Informasi generate quest</h2><p class="intro">Tentukan cakupan, jenis soal, dan tingkat kesulitan.</p><div class="fields">
                <label>Job scope<select v-model="form.job_id" :disabled="!!selectedStudyGroup || isMentor"><option value="">Semua job</option><option v-for="job in jobRoles" :key="job.id" :value="String(job.id)">{{ job.name }}</option></select></label>
                <label>Study group<select v-model="form.study_group_id" :disabled="!!selectedStudyGroup"><option value="">Quest umum</option><option v-for="group in groups" :key="group.id" :value="String(group.id)">{{ group.name }}</option></select></label>
                <label class="wide">Tema / topik<input v-model="form.theme" required maxlength="500" placeholder="Contoh: HTTP Protocol & Laravel Routing" /></label>
                <label class="wide">Catatan untuk AI<textarea v-model="form.ai_note" rows="3" maxlength="1000" placeholder="Contoh: Fokus analisis dan praktik implementasi." /><small class="hint">Arahan tambahan untuk isi soal · {{ form.ai_note.length }}/1000</small></label>
                <label>Tipe soal<select v-model="form.question_type"><option v-for="(label, value) in questionTypes" :key="value" :value="value">{{ label }}</option></select></label>
                <label>Jumlah soal<input v-model.number="form.question_count" type="number" min="3" max="30" required /></label>
                <label>Difficulty<select v-model="form.difficulty"><option value="C-Rank">C-Rank</option><option value="B-Rank">B-Rank</option><option value="A-Rank">A-Rank</option><option value="S-Rank">S-Rank</option></select></label>
                <label>Quest type<select v-model="form.quest_type"><option value="main">Main</option><option value="optional">Optional</option></select><small class="hint">Main masuk penilaian akademik; Optional untuk latihan dan reward.</small></label>
                <label>Publish mode<select v-model="form.publish_mode"><option value="draft">Draft</option><option value="publish_now">Publish sekarang</option><option value="schedule">Terjadwal</option></select></label>
                <label>Publish at<input v-model="form.available_from" type="datetime-local" :disabled="form.publish_mode !== 'schedule'" :required="form.publish_mode === 'schedule'" /></label>
                <label>Hide at<input v-model="form.available_until" type="datetime-local" :disabled="form.publish_mode !== 'schedule'" :min="form.available_from || undefined" /></label>
                <label>Deadline<input v-model="form.deadline" type="datetime-local" /><small class="hint">Waktu menggunakan zona {{ timezone }}.</small></label>
            </div><p v-if="dateError" class="error" role="alert">{{ dateError }}</p><p v-if="error" class="error" role="alert">{{ error }}</p><p v-if="success" class="success" role="status">{{ success }}</p><div class="form-actions"><button class="primary" type="submit" :disabled="generating || committing">{{ generating ? 'Membuat preview...' : 'Generate preview' }}</button><Link :href="backUrl" class="secondary">Batal</Link></div></form>
                <section v-if="preview?.bundle" class="panel preview"><div class="preview-heading"><div><h2>Preview hasil AI</h2><p>Tinjau konten sebelum membuat quest dan task bank.</p></div><span>{{ preview.bundle.questions?.length || 0 }} soal</span></div><h3>{{ preview.bundle.quest?.title }}</h3><p>{{ preview.bundle.quest?.description }}</p><div class="meta"><span>{{ preview.bundle.quest?.difficulty }}</span><span>{{ form.quest_type === 'main' ? 'Main' : 'Optional' }}</span><span>{{ preview.bundle.task_bank?.name }}</span></div><ol><li v-for="(question, index) in preview.bundle.questions" :key="index"><strong>{{ question.question_text }}</strong><small>{{ questionTypes[question.question_type] || question.question_type }} · Bobot {{ question.weight || 1 }}</small><ul v-if="question.options?.length"><li v-for="(option, optionIndex) in question.options" :key="optionIndex">{{ option }}</li></ul><small v-if="question.answer_key">Kunci jawaban: {{ question.answer_key }}</small><small v-if="question.word_match_config?.blanks?.length">Jawaban: {{ question.word_match_config.blanks.join(', ') }}</small><small v-if="question.platforming_config?.stages?.length">{{ question.platforming_config.stages.length }} tahap platforming</small></li></ol><button type="button" class="primary" :disabled="committing || !!dateError" @click="commit">{{ committing ? 'Menyimpan...' : form.publish_mode === 'draft' ? 'Simpan sebagai draft' : 'Simpan dan terbitkan' }}</button></section>
            </div><aside><section class="panel helper"><h2>Panduan pengisian</h2><p>AI menghasilkan quest sekaligus task bank. Periksa preview soal, jawaban, dan cakupan kelas sebelum menyimpan.</p><p>Untuk menyunting quest setelah disimpan, buka aksi Edit dari daftar Quest management.</p></section><section class="panel helper"><h2>Konteks akses</h2><p>Hak akses mengikuti peran dan cakupan kelas di aplikasi. Pilih Main atau Optional sesuai tujuan pembelajaran.</p></section></aside></div>
        </div>
    </main></div>
</template>

<style scoped>
.ai-app{display:flex;min-height:100vh;background:#f5f7f8;color:#19333d;font-family:Inter,ui-sans-serif,system-ui,sans-serif}.ai-app main{flex:1;min-width:0}.topbar{height:64px;background:#fff;border-bottom:1px solid #dce6e8;padding:0 28px;display:flex;align-items:center;justify-content:space-between;color:#70858c;font-size:11px}.topbar strong,.topbar small{display:block}.topbar strong{color:#19333d;font-size:12px}.topbar small{font-size:10px}.content{max-width:1440px;margin:auto;padding:34px 26px 70px}.heading{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:28px}.heading-action{white-space:nowrap}.back{color:#007f78;font-size:12px;font-weight:700}.heading h1{font-size:30px;font-weight:750;letter-spacing:-.025em;margin:8px 0}.heading p,.intro{color:#70858c;font-size:13px;margin:0}.layout{display:grid;grid-template-columns:minmax(0,2fr) minmax(260px,.8fr);align-items:start;gap:20px}.panel{background:#fff;border:1px solid #dce6e8;border-radius:12px;padding:24px;margin-bottom:18px}.panel h2{font-size:18px;font-weight:750;margin:0 0 8px}.intro{margin-bottom:24px}.fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px}.fields label{display:grid;align-content:start;gap:8px;font-size:12px;font-weight:700}.fields .wide{grid-column:1/-1}.fields :is(input,select,textarea){width:100%;min-width:0;border:1px solid #dce6e8;border-radius:8px;background:#fff;color:#19333d;padding:12px;font:inherit;font-weight:400}.fields textarea{resize:vertical}.fields :disabled{background:#f6f8f9;color:#71858b}.hint{font-size:11px;color:#70858c;font-weight:400}.form-actions{display:flex;gap:12px;border-top:1px solid #e5edef;padding-top:22px;margin-top:28px}.primary,.secondary{border-radius:8px;padding:12px 18px;font-size:12px;font-weight:750;text-decoration:none;cursor:pointer}.primary{border:0;background:#007f78;color:#fff}.primary:disabled{opacity:.6;cursor:wait}.secondary{border:1px solid #dce6e8;background:#fff;color:#007f78}.helper p,.preview p{color:#70858c;font-size:13px;line-height:1.6}.preview{margin-top:18px}.preview-heading{display:flex;justify-content:space-between;gap:16px}.preview-heading>span{color:#007f78;font-size:12px;font-weight:750}.preview h3{font-size:17px;margin:20px 0 8px}.preview .meta{display:flex;gap:8px;flex-wrap:wrap;margin:16px 0}.meta span{background:#e5f5f1;color:#007f78;border-radius:6px;padding:6px 9px;font-size:11px}.preview ol{padding-left:20px;margin:20px 0}.preview ol>li{padding:12px 0;border-top:1px solid #e5edef;font-size:13px}.preview li strong,.preview li small{display:block}.preview li small{color:#70858c;margin-top:5px}.preview ul{padding-left:20px;margin-top:8px;color:#526b73}.error{color:#b74336;font-size:12px}.success{color:#007f78;font-size:12px}.ai-app :is(a,button,input,textarea,select):focus-visible{outline:2px solid #008b83;outline-offset:2px}@media(max-width:950px){.layout{grid-template-columns:1fr}}@media(max-width:800px){.ai-app{display:block}}@media(max-width:600px){.content{padding:22px 14px}.heading{align-items:stretch;flex-direction:column}.heading h1{font-size:25px}.panel{padding:18px}.fields{grid-template-columns:1fr}.fields .wide{grid-column:auto}.form-actions{flex-wrap:wrap}}
</style>
