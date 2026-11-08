<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AssessmentLayout from './AssessmentLayout.vue';
const form = useForm({title:'',description:'',redesign_form:1});
const save = () => form.post(route('admin.rubrics.store'));
</script>
<template><AssessmentLayout title="Buat rubrik" subtitle="Buat rubrik lalu lengkapi kriteria dan level di editor."><div class="layout"><form class="panel" @submit.prevent="save"><h2>Informasi rubrik</h2><p class="muted">Berikan nama dan tujuan yang jelas.</p><div class="fields"><label class="wide">Title<input v-model="form.title" required maxlength="255" placeholder="Contoh: Web Implementation" /><small class="error">{{ form.errors.title }}</small></label><label class="wide">Description<textarea v-model="form.description" rows="4" placeholder="Tujuan penilaian rubrik" /><small class="error">{{ form.errors.description }}</small></label></div><div class="actions"><button class="primary" :disabled="form.processing">Buat &amp; lanjut ke editor</button><Link class="secondary" :href="route('redesign.rubrics')">Batal</Link></div></form><aside><section class="panel"><h2>Panduan pengisian</h2><p class="muted">Setelah rubrik dibuat, tambahkan kriteria, bobot, dan deskripsi untuk setiap level.</p></section></aside></div></AssessmentLayout></template>
