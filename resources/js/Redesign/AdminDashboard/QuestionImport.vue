<script setup>
import RedesignTopbar from './RedesignTopbar.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminSidebar from './AdminSidebar.vue';
import './workspace.css';
const props = defineProps({ taskBank: Object, importResult: Object, importTemplate: Object });
const page = usePage();
const fileInput = ref(null);
const form = useForm({ import_file: null, import_json_text: '', skip_invalid: true });
const sample = computed(() => JSON.stringify(props.importTemplate?.sample || [], null, 2));
const submit = () => form.post(route('admin.task-banks.tasks.import-json', props.taskBank.uuid), { forceFormData: true, preserveScroll: true, onSuccess: () => { form.reset(); if (fileInput.value) fileInput.value.value = ''; } });
</script>
<template>
  <Head :title="`Import soal · ${taskBank.name}`" /><div class="workspace"><AdminSidebar /><main><RedesignTopbar breadcrumb="Quest &amp; task bank" />
    <div class="content"><div class="heading"><div><Link class="back" :href="route('redesign.task-banks.questions', taskBank.uuid)">← {{ taskBank.name }}</Link><h1>Import soal JSON</h1><p class="muted">Unggah file JSON atau tempel datanya ke bank soal ini.</p></div></div>
      <div class="layout"><div><form class="panel" @submit.prevent="submit"><h2>Informasi import soal JSON</h2><p class="muted">Pilih salah satu sumber. Jika keduanya diisi, teks JSON yang digunakan.</p><div class="fields"><label>JSON file<input ref="fileInput" type="file" accept=".json,.txt,application/json,text/plain" @change="form.import_file = $event.target.files?.[0] || null" /><small v-if="form.errors.import_file" class="error">{{ form.errors.import_file }}</small></label><label>Skip invalid rows<select v-model="form.skip_invalid"><option :value="true">Aktif — lewati baris tidak valid</option><option :value="false">Nonaktif — batalkan semua jika ada kesalahan</option></select></label><label class="wide">JSON input (paste)<textarea v-model="form.import_json_text" rows="12" :placeholder="sample" /><small v-if="form.errors.import_json_text" class="error">{{ form.errors.import_json_text }}</small></label></div><div class="actions"><button class="primary" :disabled="form.processing || (!form.import_file && !form.import_json_text.trim())">{{ form.processing ? 'Mengimpor...' : 'Import JSON' }}</button><Link class="secondary" :href="route('redesign.task-banks.questions', taskBank.uuid)">Batal</Link></div></form>
        <section v-if="importResult" class="panel result" role="status"><h2>Hasil import</h2><p>{{ importResult.success_count }} soal berhasil · {{ importResult.failed_count }} baris gagal</p><ul v-if="importResult.errors?.length"><li v-for="(error, i) in importResult.errors" :key="i">{{ error }}</li></ul></section></div>
      <aside><section class="panel"><h2>Panduan pengisian</h2><p class="muted">Periksa format dan tipe soal sebelum import. Soal duplikat atau tidak valid akan dilaporkan per baris.</p><a :href="importTemplate?.download_url" download>Unduh template JSON</a><details><summary>Lihat contoh JSON</summary><pre>{{ sample }}</pre></details></section><section class="panel"><h2>Konteks akses</h2><p class="muted">Import masuk ke {{ taskBank.name }}. Tipe asesmen: {{ taskBank.assessment_type }}.</p></section></aside></div>
    </div></main></div>
</template>
<style scoped>.back{color:#007f78;font-size:12px;font-weight:700}.result{margin-top:18px}.result li{font-size:12px;margin-bottom:6px;color:#b74336}aside a{color:#007f78;font-size:12px}details{margin-top:18px}summary{cursor:pointer;color:#007f78;font-size:12px}pre{overflow:auto;background:#f4f7f8;padding:12px;border-radius:8px;font-size:10px}</style>
