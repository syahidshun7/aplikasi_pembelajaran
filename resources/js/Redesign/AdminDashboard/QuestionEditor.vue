<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AdminSidebar from './AdminSidebar.vue';
import RedesignTopbar from './RedesignTopbar.vue';
import './workspace.css';
const props = defineProps({ taskBank: Object, questions: Object, filters: Object });
const page = usePage();
const selected = ref(null);
const adding = ref(false);
const optionIds = ref([]);
const optionKeys = ref([]);
const optionImages = ref([]);
const optionImagePaths = ref([]);
const optionImagePreviews = ref([]);
const removeOptionImages = ref([]);
const correctOptionId = ref(null);
const draggingOptionId = ref(null);
const dropTargetId = ref(null);
const newOptionId = () => crypto.randomUUID();
const clearOptionPreviews = () => { optionImagePreviews.value.forEach(url => { if (url) URL.revokeObjectURL(url); }); };
const resetOptionIds = (storedOptions = []) => {
  clearOptionPreviews();
  optionIds.value = form.options.map(() => newOptionId());
  optionKeys.value = form.options.map((_, i) => typeof storedOptions[i] === 'object' && storedOptions[i] ? storedOptions[i].key || `opt_${optionIds.value[i]}` : `opt_${optionIds.value[i]}`);
  optionImages.value = form.options.map(() => null);
  optionImagePaths.value = form.options.map((_, i) => typeof storedOptions[i] === 'object' && storedOptions[i] ? storedOptions[i].image_path || '' : '');
  optionImagePreviews.value = form.options.map(() => '');
  removeOptionImages.value = form.options.map(() => false);
};
const addOption = () => {
  const id = newOptionId();
  form.options.push(''); optionIds.value.push(id); optionKeys.value.push(`opt_${id}`);
  optionImages.value.push(null); optionImagePaths.value.push(''); optionImagePreviews.value.push(''); removeOptionImages.value.push(false);
};
const setOptionImage = (event, index) => {
  if (optionImagePreviews.value[index]) URL.revokeObjectURL(optionImagePreviews.value[index]);
  const file = event.target.files?.[0] || null;
  optionImages.value[index] = file;
  optionImagePreviews.value[index] = file ? URL.createObjectURL(file) : '';
  if (file) removeOptionImages.value[index] = false;
};
const clearOptionImage = index => {
  if (optionImagePreviews.value[index]) URL.revokeObjectURL(optionImagePreviews.value[index]);
  optionImagePreviews.value[index] = '';
  optionImages.value[index] = null;
  removeOptionImages.value[index] = true;
};
const removeOption = index => {
  if (form.options.length <= 2) return;
  if (correctOptionId.value === optionIds.value[index]) correctOptionId.value = null;
  if (optionImagePreviews.value[index]) URL.revokeObjectURL(optionImagePreviews.value[index]);
  form.options.splice(index, 1);
  optionIds.value.splice(index, 1);
  optionKeys.value.splice(index, 1);
  optionImages.value.splice(index, 1);
  optionImagePaths.value.splice(index, 1);
  optionImagePreviews.value.splice(index, 1);
  removeOptionImages.value.splice(index, 1);
};
const moveOption = (from, to) => {
  if (from < 0 || to < 0 || from >= form.options.length || to >= form.options.length || from === to) return;
  const [option] = form.options.splice(from, 1);
  const [id] = optionIds.value.splice(from, 1);
  const [key] = optionKeys.value.splice(from, 1);
  const [image] = optionImages.value.splice(from, 1);
  const [path] = optionImagePaths.value.splice(from, 1);
  const [preview] = optionImagePreviews.value.splice(from, 1);
  const [removeImage] = removeOptionImages.value.splice(from, 1);
  form.options.splice(to, 0, option);
  optionIds.value.splice(to, 0, id);
  optionKeys.value.splice(to, 0, key);
  optionImages.value.splice(to, 0, image);
  optionImagePaths.value.splice(to, 0, path);
  optionImagePreviews.value.splice(to, 0, preview);
  removeOptionImages.value.splice(to, 0, removeImage);
};
const keyboardMoveOption = (event, id) => {
  if (!event.altKey || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
  event.preventDefault();
  const from = optionIds.value.indexOf(id);
  moveOption(from, from + (event.key === 'ArrowUp' ? -1 : 1));
};
const startOptionDrag = (event, id) => {
  draggingOptionId.value = id;
  event.dataTransfer.effectAllowed = 'move';
  event.dataTransfer.setData('text/plain', id);
};
const dropOption = (event, targetId) => {
  event.preventDefault();
  const sourceId = draggingOptionId.value || event.dataTransfer.getData('text/plain');
  const from = optionIds.value.indexOf(sourceId);
  const to = optionIds.value.indexOf(targetId);
  moveOption(from, to);
  draggingOptionId.value = null;
  dropTargetId.value = null;
};
const endOptionDrag = () => { draggingOptionId.value = null; dropTargetId.value = null; };
const imagePreview = ref('');
const search = ref(props.filters?.search || '');
const types = computed(() => { const type = props.taskBank.assessment_type; return type === 'mixed' ? ['essay','multiple_choice','game_stage','platforming','word_match'] : [type]; });
const blank = () => ({ question_text:'', question_type: types.value[0], question_image:null, remove_question_image:false, options:['',''], answer_key:'', weight:1, sort_order:(props.questions?.total || 0)+1, is_active:true, word_blanks:'', word_distractors:'', stage_prompt:'', stage_correct:'', stage_wrong:'', game_prompt:'', game_answers:'', game_hint:'', game_attempts:3 });
const form = useForm(blank());
watch(() => form.question_image, file => {
  if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
  imagePreview.value = file ? URL.createObjectURL(file) : '';
});
onBeforeUnmount(() => { if (imagePreview.value) URL.revokeObjectURL(imagePreview.value); });
onBeforeUnmount(clearOptionPreviews);
const isMcq = computed(() => form.question_type === 'multiple_choice');
const isWord = computed(() => form.question_type === 'word_match');
const isPlatform = computed(() => form.question_type === 'platforming');
const isGame = computed(() => form.question_type === 'game_stage');
const select = row => { selected.value = row; adding.value = false; form.reset(); form.question_text = row.question_text || ''; form.question_type = row.question_type; form.question_image = null; form.remove_question_image = false; form.options = row.question_type === 'multiple_choice' ? (Array.isArray(row.options_json) ? row.options_json.map(o => typeof o === 'string' ? o : o.text || '') : ['','']) : ['','']; form.answer_key = row.answer_key || ''; resetOptionIds(Array.isArray(row.options_json) ? row.options_json : []); const correctIndex = form.options.findIndex((option, index) => (option || optionKeys.value[index]) === form.answer_key); correctOptionId.value = correctIndex >= 0 ? optionIds.value[correctIndex] : null; form.weight = row.weight || 1; form.sort_order = row.sort_order || 1; form.is_active = !!row.is_active; const config = row.options_json || {}; if (row.question_type === 'word_match') { form.word_blanks = (config.blanks || []).join('\n'); form.word_distractors = (config.distractors || []).join('\n'); } if (row.question_type === 'platforming') { const stage = config.stages?.[0] || {}; form.stage_prompt = stage.prompt || ''; form.stage_correct = stage.correct_answer || ''; form.stage_wrong = (stage.wrong_answers || []).join('\n'); } if (row.question_type === 'game_stage') { form.game_prompt = config.prompt || ''; form.game_answers = (config.accepted_answers || []).join('\n'); form.game_hint = config.hint || ''; form.game_attempts = config.max_attempts || 3; } };
const create = () => { selected.value = null; adding.value = true; form.reset(); Object.assign(form, blank()); resetOptionIds(); correctOptionId.value = null; };
const lines = value => String(value || '').split(/\r?\n/).map(v => v.trim()).filter(Boolean);
const save = () => {
  form.clearErrors();
  let options = [], answer = null, optionUploads = [];
  if (isMcq.value) {
    const entries = form.options.map((text, index) => ({
      index, text: text.trim(), key: optionKeys.value[index],
      image: optionImages.value[index], path: optionImagePaths.value[index], remove: removeOptionImages.value[index],
    })).filter(entry => entry.text || entry.image || (entry.path && !entry.remove));
    options = entries.map(entry => ({ key: entry.key, text: entry.text, existing_image_path: entry.path || '', remove_image: Boolean(entry.remove) }));
    optionUploads = entries.map(entry => entry.image || null);
    const correct = entries.find(entry => optionIds.value[entry.index] === correctOptionId.value);
    answer = correct ? correct.text || correct.key : '';
    if (options.length < 2 || !answer) { form.setError('options', 'Isi minimal dua opsi dan pilih jawaban benar.'); return; }
    const values = entries.map(entry => entry.text || entry.key);
    if (new Set(values).size !== values.length) { form.setError('options', 'Teks setiap opsi harus berbeda agar jawaban benar tidak ambigu.'); return; }
  }
  if (isWord.value) { const blanks = lines(form.word_blanks); if (!blanks.length || (form.question_text.match(/___/g) || []).length !== blanks.length) { form.setError('options', 'Jumlah ___ harus sama dengan jumlah jawaban kosong.'); return; } options = [{ sentence:form.question_text, blanks, distractors:lines(form.word_distractors) }]; }
  if (isPlatform.value) { if (!form.stage_prompt.trim() || !form.stage_correct.trim() || !lines(form.stage_wrong).length) { form.setError('options', 'Isi prompt, jawaban benar, dan minimal satu pengecoh.'); return; } options = [{ stages:[{prompt:form.stage_prompt, correct_answer:form.stage_correct, wrong_answers:lines(form.stage_wrong)}] }]; }
  if (isGame.value) { if (!form.game_prompt.trim() || !lines(form.game_answers).length) { form.setError('options', 'Isi prompt dan minimal satu jawaban yang diterima.'); return; } options = [JSON.stringify({prompt:form.game_prompt, accepted_answers:lines(form.game_answers), hint:form.game_hint, max_attempts:Number(form.game_attempts)})]; }
  const payload = { question_text:form.question_text, question_type:form.question_type, question_image:form.question_image, remove_question_image:form.remove_question_image, options, option_images:optionUploads, answer_key:answer, weight:Number(form.weight), sort_order:Number(form.sort_order), is_active:form.is_active ? 1 : 0 };
  const url = selected.value ? route('admin.task-banks.tasks.update', {taskBank:props.taskBank.uuid,question:selected.value.uuid}) : route('admin.task-banks.tasks.store',props.taskBank.uuid);
  router.post(url, selected.value ? {...payload,_method:'put'} : payload, { forceFormData:true, preserveScroll:true, onError: errors => form.setError(errors), onSuccess:() => { if (adding.value) { adding.value = false; form.reset(); } } });
};
const remove = async row => { if (await confirmAction(`Hapus soal nomor ${row.sort_order}?`)) router.delete(route('admin.task-banks.tasks.destroy',{taskBank:props.taskBank.uuid,question:row.uuid}),{preserveScroll:true,onSuccess:() => { if (selected.value?.uuid === row.uuid) {selected.value=null;adding.value=false;} }}); };
watch(() => props.questions?.data, rows => { if (selected.value) selected.value = rows?.find(row => row.uuid === selected.value.uuid) || null; });
if (props.questions?.data?.length) select(props.questions.data[0]);
</script>
<template>
  <Head :title="`Editor soal · ${taskBank.name}`" /><div class="workspace"><AdminSidebar /><main><RedesignTopbar breadcrumb="Quest &amp; task bank" />
    <div class="content"><div class="heading"><div><Link class="back" :href="route('redesign.task-banks')">← Task banks</Link><h1>Editor soal</h1><p class="muted">{{ taskBank.name }} · {{ questions?.total || 0 }} soal</p></div><Link class="secondary" :href="route('redesign.task-banks.edit',taskBank.uuid)">Pengaturan bank</Link></div>
      <div class="tabs"><Link class="active" :href="route('redesign.task-banks.questions',taskBank.uuid)">Daftar soal</Link><button class="tab-button" type="button" @click="create">Tambah soal</button><Link :href="route('redesign.task-banks.import',taskBank.uuid)">Import JSON</Link></div>
      <div class="editor-grid"><section class="panel list"><div class="list-head"><h2>Daftar soal</h2><button class="action" type="button" @click="create">+ Tambah soal</button></div><form class="search" @submit.prevent="router.get(route('redesign.task-banks.questions',taskBank.uuid),{search},{preserveState:true})"><input v-model="search" placeholder="Cari pertanyaan..." aria-label="Cari soal" /><button class="action">Cari</button></form><p v-if="page.props.flash?.message" class="success">{{ page.props.flash.message }}</p><div v-for="row in questions?.data || []" :key="row.uuid" class="question-row" :class="{chosen:selected?.uuid===row.uuid}" ><button type="button" class="question-select" @click="select(row)"><strong>Pertanyaan {{ String(row.sort_order).padStart(2,'0') }}</strong><small>{{ row.question_type }} · Bobot {{ row.weight }} · {{ row.is_active ? 'Aktif' : 'Nonaktif' }}</small><span>{{ row.question_text || '(Tanpa teks)' }}</span></button><button type="button" class="delete" @click="remove(row)">Hapus</button></div><p v-if="!questions?.data?.length" class="muted">Belum ada soal.</p><div class="pager"><Link v-for="(link,i) in questions?.links || []" :key="i" :href="link.url || '#'" :class="{active:link.active,disabled:!link.url}" v-html="link.label" /></div></section>
      <form class="panel edit-panel" @submit.prevent="save"><h2>{{ adding ? 'Tambah soal' : selected ? `Pertanyaan ${String(selected.sort_order).padStart(2,'0')}` : 'Pilih soal' }}</h2><p class="muted">{{ adding || selected ? 'Perbarui isi dan pengaturan soal.' : 'Pilih soal di daftar atau tambah soal baru.' }}</p><template v-if="adding || selected"><div class="fields"><label class="wide">Pertanyaan<textarea v-model="form.question_text" rows="4" :required="!isPlatform" /><small v-if="form.errors.question_text" class="error">{{ form.errors.question_text }}</small></label><label>Question type<select v-model="form.question_type"><option v-for="type in types" :key="type" :value="type">{{ type }}</option></select></label><label>Weight<input v-model.number="form.weight" type="number" min="1" max="100" required /></label><label>Sort order<input v-model.number="form.sort_order" type="number" min="1" required /></label><label>Status<select v-model="form.is_active"><option :value="true">Aktif</option><option :value="false">Nonaktif</option></select></label><label class="wide">Gambar soal (opsional)<input type="file" accept="image/jpeg,image/png,image/webp" @change="form.question_image=$event.target.files?.[0] || null" /><img v-if="imagePreview" :src="imagePreview" alt="Pratinjau gambar soal" class="image-preview" /><img v-else-if="selected?.question_image_path && !form.remove_question_image" :src="`/storage/${selected.question_image_path}`" alt="Gambar soal tersimpan" class="image-preview" /><span v-if="selected?.question_image_path" class="muted">Gambar tersimpan: <a :href="`/storage/${selected.question_image_path}`" target="_blank" rel="noopener">Lihat gambar</a> <input v-model="form.remove_question_image" type="checkbox" /> Hapus gambar</span></label>
        <div v-if="isMcq" class="wide options"><h3>Opsi jawaban</h3><p class="muted option-help">Seret pegangan untuk mengubah urutan (atau Alt + panah atas/bawah). Pilih lingkaran untuk menandai jawaban benar.</p><div v-for="(option,i) in form.options" :key="optionIds[i]" class="option" :class="{ correct: correctOptionId === optionIds[i], dragging: draggingOptionId === optionIds[i], 'drop-target': dropTargetId === optionIds[i] }" @dragover.prevent="dropTargetId = optionIds[i]" @drop="dropOption($event, optionIds[i])"><span class="drag-handle" draggable="true" tabindex="0" role="button" :aria-label="`Seret opsi ${String.fromCharCode(65+i)} untuk mengubah urutan`" title="Seret untuk mengubah urutan" @dragstart="startOptionDrag($event, optionIds[i])" @dragend="endOptionDrag" @keydown="keyboardMoveOption($event, optionIds[i])">⠿</span><label class="correct-toggle"><input type="radio" name="correct-option" :value="optionIds[i]" v-model="correctOptionId" :aria-label="`Tandai opsi ${String.fromCharCode(65+i)} sebagai jawaban benar`" /><span class="radio-mark" aria-hidden="true"></span></label><span class="option-letter">{{ String.fromCharCode(65+i) }}</span><div class="option-content"><input v-model="form.options[i]" :aria-label="`Teks opsi ${String.fromCharCode(65+i)}`" :placeholder="`Opsi ${String.fromCharCode(65+i)}`" /><div class="option-media"><label class="image-upload">+ Gambar<input type="file" accept="image/jpeg,image/png,image/webp" :aria-label="`Unggah gambar opsi ${String.fromCharCode(65+i)}`" @change="setOptionImage($event,i)" /></label><img v-if="optionImagePreviews[i]" :src="optionImagePreviews[i]" :alt="`Pratinjau gambar opsi ${String.fromCharCode(65+i)}`" class="option-image" /><img v-else-if="optionImagePaths[i] && !removeOptionImages[i]" :src="`/storage/${optionImagePaths[i]}`" :alt="`Gambar opsi ${String.fromCharCode(65+i)}`" class="option-image" /><button v-if="optionImagePreviews[i] || (optionImagePaths[i] && !removeOptionImages[i])" type="button" class="remove-image" @click="clearOptionImage(i)">Hapus gambar</button><button v-if="optionImagePaths[i] && removeOptionImages[i]" type="button" class="remove-image" @click="removeOptionImages[i]=false">Pulihkan gambar</button></div></div><span v-if="correctOptionId === optionIds[i]" class="correct-badge">Jawaban benar</span><button type="button" class="action remove-option" :aria-label="`Hapus opsi ${String.fromCharCode(65+i)}`" :disabled="form.options.length<=2" @click="removeOption(i)">×</button></div><button type="button" class="action add-option" @click="addOption">+ Tambah opsi</button></div>
        <template v-if="isWord"><label class="wide">Jawaban untuk setiap ___, satu per baris<textarea v-model="form.word_blanks" rows="3" /></label><label class="wide">Pengecoh, satu per baris<textarea v-model="form.word_distractors" rows="3" /></label></template>
        <template v-if="isPlatform"><label class="wide">Prompt stage<input v-model="form.stage_prompt" /></label><label>Jawaban benar<input v-model="form.stage_correct" /></label><label>Pengecoh, satu per baris<textarea v-model="form.stage_wrong" rows="3" /></label></template>
        <template v-if="isGame"><label class="wide">Prompt game<input v-model="form.game_prompt" /></label><label class="wide">Jawaban diterima, satu per baris<textarea v-model="form.game_answers" rows="3" /></label><label>Hint<input v-model="form.game_hint" /></label><label>Maksimal percobaan<input v-model.number="form.game_attempts" type="number" min="1" max="10" /></label></template>
        <small v-if="form.errors.options || form.errors.answer_key" class="error wide">{{ form.errors.options || form.errors.answer_key }}</small></div><div class="actions"><button class="primary" :disabled="form.processing">Simpan soal</button><button type="button" class="secondary" @click="selected=null;adding=false">Batal</button></div></template></form></div>
    </div></main></div>
</template>
<style scoped>.back{color:#007f78;font-size:12px;font-weight:700}.tab-button{border:1px solid #dce6e8;border-radius:8px;background:#fff;color:#70858c;padding:10px 16px;cursor:pointer;font:inherit;font-size:12px}.editor-grid{display:grid;grid-template-columns:minmax(300px,.9fr) minmax(0,1.4fr);gap:20px;align-items:start}.list-head,.search{display:flex;gap:10px;align-items:center;justify-content:space-between}.search{margin:16px 0}.question-row{border:1px solid #dce6e8;border-radius:8px;margin:10px 0;display:flex;align-items:center}.question-row.chosen{border-color:#70c6b5;background:#e9f7f4}.question-select{display:grid;text-align:left;gap:5px;flex:1;border:0;background:none;padding:14px;cursor:pointer;color:#19333d;font:inherit;font-size:12px}.question-select small{color:#70858c}.delete{border:0;background:none;color:#b74336;padding:10px;cursor:pointer}.options{display:grid;gap:10px}.option{display:flex!important;align-items:center;gap:10px}.option input[type=radio],.edit-panel input[type=checkbox]{width:auto}.option input:nth-child(2){flex:1}.edit-panel h3{font-size:14px}.edit-panel .fields{margin-top:20px}.edit-panel a{color:#007f78}.image-preview{max-width:240px;max-height:160px;object-fit:contain;border:1px solid #dce6e8;border-radius:8px}@media(max-width:1050px){.editor-grid{grid-template-columns:1fr}}</style>
<style scoped>
.option-help{margin:-4px 0 4px}.option{display:flex;align-items:center;gap:10px;border:1px solid #dce6e8;border-radius:9px;padding:8px;background:#fff;min-width:0;transition:border-color .15s,background .15s,opacity .15s}.option.correct{border-color:#60b9a9;background:#f0faf7}.option.dragging{opacity:.45}.option.drop-target{border-color:#00857c;border-style:dashed}.drag-handle{flex:0 0 28px;display:grid;place-items:center;align-self:stretch;color:#789099;cursor:grab;font-size:20px;user-select:none}.drag-handle:active{cursor:grabbing}.correct-toggle{position:relative;display:grid!important;place-items:center;flex:0 0 28px;height:28px;cursor:pointer}.correct-toggle input{position:absolute;inset:0;opacity:0;cursor:pointer}.radio-mark{display:block;width:24px;height:24px;border:2px solid #b8cbd0;border-radius:50%;background:#fff}.correct-toggle input:checked + .radio-mark{border:7px solid #00857c}.correct-toggle input:focus-visible + .radio-mark{outline:2px solid #00857c;outline-offset:2px}.option-letter{font-size:12px;font-weight:800;color:#607b83;min-width:15px}.option input[aria-label^="Teks opsi"]{flex:1;min-width:0;border:0;background:transparent;font-size:13px;font-weight:600}.option input[aria-label^="Teks opsi"]:focus{outline:1px solid #00857c}.correct-badge{font-size:10px;font-weight:700;color:#007f78;white-space:nowrap}.remove-option{min-width:32px}.add-option{justify-self:start}@media(max-width:650px){.option{gap:5px}.correct-badge{display:none}.drag-handle{flex-basis:22px}}
</style>
<style scoped>
.option-content{flex:1;min-width:0;display:grid;gap:5px}.option-media{display:flex;align-items:center;gap:9px;flex-wrap:wrap}.image-upload{display:inline-flex!important;align-items:center;position:relative;width:max-content;color:#007f78;font-size:11px!important;font-weight:700;cursor:pointer}.image-upload input{position:absolute;width:1px!important;height:1px;opacity:0;overflow:hidden}.image-upload:focus-within{outline:2px solid #008b83;outline-offset:2px}.option-image{width:72px;height:48px;object-fit:cover;border:1px solid #dce6e8;border-radius:5px}.remove-image{border:0;background:none;padding:0;color:#b74336;font-size:10px;cursor:pointer}.option .option-content input[aria-label^="Teks opsi"]{width:100%;padding:5px;border:0;background:transparent;font-size:13px;font-weight:600}
</style>
