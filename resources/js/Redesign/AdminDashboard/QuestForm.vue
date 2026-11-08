<script setup>
import RedesignTopbar from './RedesignTopbar.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminSidebar from './AdminSidebar.vue';
import QuestDescriptionEditor from '@/Components/Quests/QuestDescriptionEditor.vue';
import { toAppDateTimeLocal } from './dateTime';

const props = defineProps({ quest: Object, selectedStudyGroup: Object, studyGroups: Array, taskBanks: Array, jobRoles: Array, rubrics: Array, timezone: { type: String, default: 'UTC' } });
const page = usePage();
const isEdit = computed(() => Boolean(props.quest?.uuid));
const isMentor = computed(() => page.props.auth?.user?.role === 'mentor');
const backUrl = computed(() => props.selectedStudyGroup?.uuid ? route('redesign.study-groups.quests', props.selectedStudyGroup.uuid) : route('redesign.quests'));
const aiUrl = computed(() => props.selectedStudyGroup?.uuid ? route('redesign.study-groups.quests.generate-ai', props.selectedStudyGroup.uuid) : route('redesign.quests.generate-ai'));
const rankRewards = { 'C-Rank': 500, 'B-Rank': 1000, 'A-Rank': 2500, 'S-Rank': 5000 };
const localDate = value => toAppDateTimeLocal(value, props.timezone);
const selectedJob = ref(String(props.quest?.study_group?.job_id || props.quest?.task_bank?.job_role_id || props.selectedStudyGroup?.job_id || (isMentor.value ? page.props.auth?.user?.job_id || '' : '')));
const form = useForm({
    title: props.quest?.title || '', description: props.quest?.description || '', difficulty: props.quest?.difficulty || 'C-Rank', quest_type: props.quest?.quest_type || 'main',
    reward_gold: rankRewards[props.quest?.difficulty || 'C-Rank'], reward_exp: rankRewards[props.quest?.difficulty || 'C-Rank'],
    is_active: props.quest ? (props.quest.schedule_type === 'once' || props.quest.status !== 'In-Progress') : true,
    deadline: localDate(props.quest?.deadline), attempt_mode: props.quest?.attempt_mode || 'single', max_attempts: props.quest?.max_attempts ?? null,
    grading_attempt: props.quest?.grading_attempt || 'highest', schedule_type: props.quest?.schedule_type || 'manual',
    available_from: localDate(props.quest?.available_from), available_until: localDate(props.quest?.available_until),
    study_group_id: props.selectedStudyGroup?.id ?? props.quest?.study_group_id ?? (isMentor.value ? props.studyGroups?.[0]?.id ?? null : null),
    task_bank_id: props.quest?.task_bank_id ?? null, rubric_id: props.quest?.rubric_id ?? null,
    redesign_form: 1, redesign_scope: props.selectedStudyGroup?.uuid || null,
});
const groups = computed(() => (props.studyGroups || []).filter(group => !selectedJob.value || String(group.job_id) === selectedJob.value));
const banks = computed(() => (props.taskBanks || []).filter(bank => !selectedJob.value || String(bank.job_role_id) === selectedJob.value));
const dateError = computed(() => form.schedule_type === 'once' && form.available_from && form.available_until && form.available_until <= form.available_from
    ? 'Waktu sembunyikan harus setelah waktu tampil mulai.' : '');
watch(() => form.difficulty, value => { form.reward_gold = rankRewards[value] || 0; form.reward_exp = rankRewards[value] || 0; });
watch(() => form.task_bank_id, () => { form.rubric_id = null; });
watch(() => form.schedule_type, value => { if (value === 'once') form.is_active = true; else { form.available_from = ''; form.available_until = ''; } });
watch(selectedJob, () => {
    if (!groups.value.some(group => Number(group.id) === Number(form.study_group_id))) form.study_group_id = null;
    if (!banks.value.some(bank => Number(bank.id) === Number(form.task_bank_id))) form.task_bank_id = null;
});
const save = () => {
    if (form.processing || dateError.value) return;
    form.transform(data => ({ ...data, study_group_id: data.study_group_id || null, task_bank_id: data.task_bank_id || null, rubric_id: data.task_bank_id ? null : data.rubric_id || null,
        max_attempts: data.attempt_mode === 'limited' ? Number(data.max_attempts) || null : null, deadline: data.deadline || null,
        available_from: data.schedule_type === 'once' ? data.available_from || null : null, available_until: data.schedule_type === 'once' ? data.available_until || null : null,
        is_active: data.is_active ? 1 : 0 }));
    if (isEdit.value) form.patch(route('quests.update', props.quest.uuid));
    else form.post(route('quests.store'));
};
</script>

<template>
    <Head :title="isEdit ? 'Edit quest' : 'Buat quest'" />
    <div class="quest-form-app"><AdminSidebar /><main>
        <RedesignTopbar breadcrumb="Quest &amp; task bank" />
        <div class="content"><div class="heading"><div><Link :href="backUrl" class="back">← Quest management</Link><h1>{{ isEdit ? 'Edit quest' : 'Buat quest' }}</h1><p>Kelola aktivitas dan data pembelajaran dalam satu workspace.</p></div><Link v-if="!isEdit" :href="aiUrl" class="ai-link">✦ Generate quest with AI</Link></div>
            <div class="layout"><form id="quest-form" class="panel form-panel" @submit.prevent="save"><h2>Informasi {{ isEdit ? 'edit' : 'buat' }} quest</h2><p class="intro">Lengkapi detail berikut. Waktu menggunakan zona {{ timezone }}.</p><div class="fields">
                <label class="full-width">Judul quest<input v-model="form.title" required maxlength="255" placeholder="Contoh: Routing Challenge" /><small v-if="form.errors.title" class="error">{{ form.errors.title }}</small></label>
                <div class="field full-width"><label for="quest-description">Deskripsi</label><QuestDescriptionEditor id="quest-description" v-model="form.description" /><small v-if="form.errors.description" class="error">{{ form.errors.description }}</small></div>
                <label>Difficulty<select v-model="form.difficulty"><option value="C-Rank">C-Rank</option><option value="B-Rank">B-Rank</option><option value="A-Rank">A-Rank</option><option value="S-Rank">S-Rank</option></select><small v-if="form.errors.difficulty" class="error">{{ form.errors.difficulty }}</small></label>
                <label>Quest type<select v-model="form.quest_type"><option value="main">Main</option><option value="optional">Optional</option></select><small class="hint">Optional memberi reward, tetapi tidak masuk rata-rata akademik.</small></label>
                <label>Reward gold<input v-model="form.reward_gold" type="number" readonly /><small class="hint">Dihitung otomatis dari difficulty.</small></label>
                <label>Reward EXP<input v-model="form.reward_exp" type="number" readonly /><small class="hint">Dihitung otomatis dari difficulty.</small></label>
                <label>Status aktif<select v-model="form.is_active" :disabled="form.schedule_type === 'once'"><option :value="true">Aktif</option><option :value="false">Nonaktif</option></select><small class="hint">Pada jadwal otomatis, status mengikuti waktu tampil.</small></label>
                <label>Deadline<input v-model="form.deadline" type="datetime-local" /><small v-if="form.errors.deadline" class="error">{{ form.errors.deadline }}</small></label>
                <label>Attempt mode<select v-model="form.attempt_mode"><option value="single">Single attempt</option><option value="limited">Multiple attempts</option><option value="unlimited">Unlimited attempts</option></select></label>
                <label>Max attempts<input v-model.number="form.max_attempts" type="number" min="2" max="100" :disabled="form.attempt_mode !== 'limited'" :required="form.attempt_mode === 'limited'" /><small v-if="form.errors.max_attempts" class="error">{{ form.errors.max_attempts }}</small></label>
                <label>Grade source<select v-model="form.grading_attempt"><option value="highest">Best attempt</option><option value="latest">Latest attempt</option><option value="first">First attempt</option></select></label>
                <label>Schedule mode<select v-model="form.schedule_type"><option value="manual">Manual</option><option value="once">Scheduled</option></select></label>
                <label>Tampil mulai<input v-model="form.available_from" type="datetime-local" :disabled="form.schedule_type !== 'once'" :required="form.schedule_type === 'once'" /><small v-if="form.errors.available_from" class="error">{{ form.errors.available_from }}</small></label>
                <label>Sembunyikan pada<input v-model="form.available_until" type="datetime-local" :disabled="form.schedule_type !== 'once'" :min="form.available_from || undefined" /><small v-if="form.errors.available_until || dateError" class="error">{{ form.errors.available_until || dateError }}</small></label>
                <label>Job scope<select v-model="selectedJob" :disabled="!!selectedStudyGroup || isMentor"><option value="">Semua job</option><option v-for="job in jobRoles" :key="job.id" :value="String(job.id)">{{ job.name }}</option></select><small class="hint">Membantu menyaring pilihan study group dan task bank.</small></label>
                <label>Study group<select v-model="form.study_group_id" :disabled="!!selectedStudyGroup"><option :value="null">Quest umum</option><option v-for="group in groups" :key="group.id" :value="group.id">{{ group.name }}</option></select><small v-if="form.errors.study_group_id" class="error">{{ form.errors.study_group_id }}</small></label>
                <label>Task bank<select v-model="form.task_bank_id"><option :value="null">Tanpa task bank</option><option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option></select><small v-if="form.errors.task_bank_id" class="error">{{ form.errors.task_bank_id }}</small></label>
                <label>Rubric override<select v-model="form.rubric_id" :disabled="!!form.task_bank_id"><option :value="null">Tanpa rubrik (skor manual)</option><option v-for="rubric in rubrics" :key="rubric.id" :value="rubric.id">{{ rubric.title }}</option></select><small v-if="form.errors.rubric_id" class="error">{{ form.errors.rubric_id }}</small></label>
            </div><div class="form-actions"><button type="submit" class="primary" :disabled="form.processing">{{ form.processing ? 'Menyimpan...' : 'Simpan quest' }}</button><Link :href="backUrl" class="secondary">Batal</Link></div></form>
            <aside><section class="panel helper"><h2>Panduan pengisian</h2><p>Pastikan informasi sudah sesuai sebelum menyimpan. Gunakan nama yang mudah dikenali dan periksa kembali cakupan kelas atau program.</p><p>Reward gold dan EXP mengikuti difficulty quest.</p></section><section class="panel helper"><h2>Konteks akses</h2><p>Hak akses mengikuti peran dan study group di aplikasi. Mentor perlu mengaitkan quest dengan study group atau task bank yang dapat diakses.</p></section></aside></div>
        </div>
    </main></div>
</template>

<style scoped>
.quest-form-app{display:flex;min-height:100vh;background:#f5f7f8;color:#19333d;font-family:Inter,ui-sans-serif,system-ui,sans-serif}.quest-form-app main{flex:1;min-width:0}.topbar{height:64px;background:#fff;border-bottom:1px solid #dce6e8;padding:0 28px;display:flex;align-items:center;justify-content:space-between;color:#70858c;font-size:11px}.topbar strong,.topbar small{display:block}.topbar strong{color:#19333d;font-size:12px}.topbar small{font-size:10px}.content{max-width:1440px;margin:auto;padding:34px 26px 70px}.heading{margin-bottom:28px}.back{color:#007f78;font-size:12px;font-weight:700}.heading h1{font-size:30px;font-weight:750;letter-spacing:-.025em;margin:8px 0}.heading p,.intro{color:#70858c;font-size:13px;margin:0}.layout{display:grid;grid-template-columns:minmax(0,2fr) minmax(260px,.8fr);align-items:start;gap:20px}.panel{background:#fff;border:1px solid #dce6e8;border-radius:12px;padding:24px}.panel h2{font-size:18px;font-weight:750;margin:0 0 8px}.intro{margin-bottom:24px}.fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px}.fields>label,.field{display:grid;align-content:start;gap:8px;font-size:12px;font-weight:700}.fields :is(input,select){width:100%;min-width:0;border:1px solid #dce6e8;border-radius:8px;background:#fff;color:#19333d;padding:12px;font:inherit;font-weight:400}.fields input:read-only,.fields :disabled{background:#f6f8f9;color:#71858b}.fields .error{color:#b74336;font-size:11px;font-weight:400}.hint{font-size:11px;color:#70858c;font-weight:400}.form-actions{display:flex;gap:12px;border-top:1px solid #e5edef;padding-top:22px;margin-top:28px}.primary,.secondary{border-radius:8px;padding:12px 18px;font-size:12px;font-weight:750;text-decoration:none;cursor:pointer}.primary{border:0;background:#007f78;color:#fff;min-width:180px}.primary:disabled{opacity:.6}.secondary{border:1px solid #dce6e8;background:#fff;color:#007f78}.helper{margin-bottom:18px}.helper p{color:#70858c;font-size:13px;line-height:1.6}.quest-form-app :is(a,button,input,select):focus-visible{outline:2px solid #008b83;outline-offset:2px}@media(max-width:950px){.layout{grid-template-columns:1fr}}@media(max-width:800px){.quest-form-app{display:block}}@media(max-width:600px){.content{padding:22px 14px}.heading h1{font-size:25px}.panel{padding:18px}.fields{grid-template-columns:1fr}.form-actions{flex-wrap:wrap}.primary{min-width:0}}
</style>
<style scoped>.heading{display:flex;align-items:center;justify-content:space-between;gap:16px}.ai-link{border:1px solid #badbd7;border-radius:8px;background:#fff;color:#007f78;padding:12px 18px;font-size:13px;font-weight:750;text-decoration:none;white-space:nowrap}@media(max-width:600px){.heading{align-items:stretch;flex-direction:column}}</style>
<style scoped>.fields .full-width{grid-column:1/-1}</style>
<style scoped>
.quest-form-app :deep(.quest-description-editor){border:1px solid #dce6e8;border-radius:8px;background:#fff;overflow:hidden}
.quest-form-app :deep(.quest-description-editor:focus-within){border-color:#008b83}
.quest-form-app :deep(.quest-description-editor__toolbar){background:#f6f8f9;border-bottom-color:#dce6e8}
.quest-form-app :deep(.quest-description-editor__toolbar button){background:#fff;border-color:#dce6e8;color:#19333d;font-family:inherit}
.quest-form-app :deep(.quest-description-editor__toolbar button:hover),.quest-form-app :deep(.quest-description-editor__toolbar button.is-active){background:#e5f5f1;border-color:#9ccbc6;color:#007f78}
.quest-form-app :deep(.quest-description-editor__divider){background:#dce6e8}
.quest-form-app :deep(.quest-description-editor__content){min-height:92px;color:#19333d;font-family:inherit;font-weight:400}
.quest-form-app :deep(.quest-description-editor__content h2),.quest-form-app :deep(.quest-description-editor__content h3),.quest-form-app :deep(.quest-description-editor__content a){color:#007f78}
</style>
