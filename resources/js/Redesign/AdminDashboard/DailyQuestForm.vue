<script setup>
import RedesignTopbar from './RedesignTopbar.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminSidebar from './AdminSidebar.vue';
import './workspace.css';
const props = defineProps({ definition: Object, activityTypes: Array });
const page = usePage();
const edit = computed(() => Boolean(props.definition?.id));
const meta = props.definition?.meta || {};
const form = useForm({ code: props.definition?.code || '', activity_type: props.definition?.activity_type || 'login', title: props.definition?.title || '', description: props.definition?.description || '', target_value: props.definition?.target_value ?? 1, reward_exp: props.definition?.reward_exp ?? 0, reward_gold: props.definition?.reward_gold ?? 0, sort_order: props.definition?.sort_order ?? 1, meta_category: meta.category || '', meta_icon: meta.icon || '', activity_steps_text: (meta.activity_steps || []).join('\n'), is_active: props.definition?.is_active ?? true, redesign_form: 1 });
const save = () => { if (edit.value) form.put(route('admin.daily-quest-definitions.update', props.definition.id)); else form.post(route('admin.daily-quest-definitions.store')); };
</script>
<template>
  <Head :title="edit ? 'Edit daily quest' : 'Definisi daily quest'" /><div class="workspace"><AdminSidebar /><main><RedesignTopbar breadcrumb="Quest &amp; task bank" />
    <div class="content"><div class="heading"><div><Link :href="route('redesign.daily-quests')" class="back">← Daily quests</Link><h1>Definisi daily quest</h1><p class="muted">{{ edit ? 'Perbarui definisi aktivitas harian.' : 'Lengkapi definisi aktivitas harian baru.' }}</p></div></div>
      <div class="layout"><form class="panel" @submit.prevent="save"><h2>Informasi definisi daily quest</h2><p class="muted intro">Lengkapi detail berikut.</p><div class="fields">
        <label>Code<input v-model="form.code" required maxlength="64" pattern="[a-z0-9_]+" placeholder="daily_login" /><small v-if="form.errors.code" class="error">{{ form.errors.code }}</small></label>
        <label>Activity type<select v-model="form.activity_type"><option v-for="type in activityTypes" :key="type" :value="type">{{ type }}</option></select><small v-if="form.errors.activity_type" class="error">{{ form.errors.activity_type }}</small></label>
        <label class="wide">Title<input v-model="form.title" required maxlength="255" placeholder="Login harian" /><small v-if="form.errors.title" class="error">{{ form.errors.title }}</small></label>
        <label class="wide">Description<textarea v-model="form.description" rows="3" placeholder="Jelaskan aktivitas harian" /><small v-if="form.errors.description" class="error">{{ form.errors.description }}</small></label>
        <label>Target<input v-model.number="form.target_value" type="number" min="1" max="1000" required /><small v-if="form.errors.target_value" class="error">{{ form.errors.target_value }}</small></label>
        <label>Reward EXP<input v-model.number="form.reward_exp" type="number" min="0" required /><small v-if="form.errors.reward_exp" class="error">{{ form.errors.reward_exp }}</small></label>
        <label>Reward gold<input v-model.number="form.reward_gold" type="number" min="0" required /><small v-if="form.errors.reward_gold" class="error">{{ form.errors.reward_gold }}</small></label>
        <label>Sort order<input v-model.number="form.sort_order" type="number" min="1" required /><small v-if="form.errors.sort_order" class="error">{{ form.errors.sort_order }}</small></label>
        <label>Meta category<input v-model="form.meta_category" maxlength="120" placeholder="engagement" /><small v-if="form.errors.meta_category" class="error">{{ form.errors.meta_category }}</small></label>
        <label>Meta icon<input v-model="form.meta_icon" maxlength="120" placeholder="fi fi-rr-enter" /><small v-if="form.errors.meta_icon" class="error">{{ form.errors.meta_icon }}</small></label>
        <label class="wide">Activity steps <small class="muted">Satu langkah per baris</small><textarea v-model="form.activity_steps_text" rows="4" placeholder="Login ke akun lalu buka dashboard." /><small v-if="form.errors.activity_steps_text" class="error">{{ form.errors.activity_steps_text }}</small></label>
        <label>Is active<select v-model="form.is_active"><option :value="true">Aktif</option><option :value="false">Nonaktif</option></select></label>
      </div><div class="actions"><button class="primary" :disabled="form.processing">{{ form.processing ? 'Menyimpan...' : 'Simpan definisi' }}</button><Link class="secondary" :href="route('redesign.daily-quests')">Batal</Link></div></form>
      <aside><section class="panel"><h2>Panduan pengisian</h2><p class="muted">Gunakan kode unik yang mudah dikenali. Target menentukan jumlah aktivitas sebelum reward dapat diklaim.</p></section><section class="panel"><h2>Konteks akses</h2><p class="muted">Definisi aktif dipakai untuk membuat tantangan harian pengguna.</p></section></aside></div>
    </div></main></div>
</template>
<style scoped>.back{color:#007f78;font-size:12px;font-weight:700}.intro{margin-bottom:22px}.fields label small.muted{font-weight:400}</style>
