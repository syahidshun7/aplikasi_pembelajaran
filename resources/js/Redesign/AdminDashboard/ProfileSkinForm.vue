<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { onUnmounted, reactive } from 'vue';
import AssessmentLayout from './AssessmentLayout.vue';
const props = defineProps({ skin: Object });
const skin = props.skin || {};
const form = useForm({ name: skin.name || '', description: skin.description || '', price_gold: skin.shop_item?.price_gold || 1, renderer_type: skin.renderer_type || 'vue_template', template_key: skin.template_key || 'default', hero_gradient: skin.hero_gradient || 'linear-gradient(135deg,#12383d,#00857c)', accent_color: skin.accent_color || '#00857c', border_color: skin.border_color || '#dce6e8', glow_color: skin.glow_color || '#20b7e8', stat_panel_bg: skin.stat_panel_bg || '#ffffff', text_primary: skin.text_primary || '#19333d', is_active: skin.is_active ?? true, preview_image: null, background_image: null, avatar_frame_image: null, panel_image: null, decoration_image: null });
const images = [
  { field: 'preview_image', path: 'preview_image_path', label: 'Gambar pratinjau' },
  { field: 'background_image', path: 'background_image_path', label: 'Latar belakang' },
  { field: 'avatar_frame_image', path: 'avatar_frame_image_path', label: 'Bingkai avatar' },
  { field: 'panel_image', path: 'panel_image_path', label: 'Gambar panel' },
  { field: 'decoration_image', path: 'decoration_image_path', label: 'Dekorasi' },
];
const previews = reactive(Object.fromEntries(images.map(image => [image.field, skin[image.path] ? `/storage/${skin[image.path]}` : null])));
const objectUrls = {};
function selectImage(event, image) {
  const file = event.target.files?.[0] || null;
  form[image.field] = file;
  if (objectUrls[image.field]) URL.revokeObjectURL(objectUrls[image.field]);
  objectUrls[image.field] = file ? URL.createObjectURL(file) : null;
  previews[image.field] = objectUrls[image.field] || (skin[image.path] ? `/storage/${skin[image.path]}` : null);
}
onUnmounted(() => Object.values(objectUrls).forEach(url => { if (url) URL.revokeObjectURL(url); }));
const save = () => form.transform(data => skin.id ? { ...data, _method: 'put' } : data).post(skin.id ? route('admin.profile-skins.update', skin.id) : route('admin.profile-skins.store'), { forceFormData: true });
</script>
<template>
  <AssessmentLayout :title="skin.id ? 'Edit profile skin' : 'Tambah profile skin'" subtitle="Atur tampilan, aset, dan harga skin profil." breadcrumb="Toko & personalisasi">
    <div class="layout">
      <form class="panel" @submit.prevent="save">
        <h2>Informasi profile skin</h2>
        <div class="fields">
          <label>Nama skin<input v-model="form.name" required maxlength="100" /><span class="error">{{ form.errors.name }}</span></label>
          <label>Harga gold<input v-model.number="form.price_gold" type="number" min="1" required /><span class="error">{{ form.errors.price_gold }}</span></label>
          <label>Renderer<select v-model="form.renderer_type"><option value="vue_template">Template</option><option value="config">Konfigurasi</option><option value="project_static">Project static</option></select></label>
          <label>Template key<select v-model="form.template_key"><option v-for="key in ['default','void_phantom','arcade_cabinet','asset_showcase','project_static']" :key="key" :value="key">{{ key }}</option></select><span class="error">{{ form.errors.template_key }}</span></label>
          <label class="wide">Deskripsi<textarea v-model="form.description" rows="3" maxlength="500" /></label>
          <label class="wide">Hero gradient<input v-model="form.hero_gradient" required /></label>
          <label v-for="field in [['accent_color','Warna aksen'],['border_color','Warna bingkai'],['glow_color','Warna cahaya'],['stat_panel_bg','Latar panel'],['text_primary','Warna teks']]" :key="field[0]">{{ field[1] }}<input v-model="form[field[0]]" required /></label>
          <label>Status<select v-model="form.is_active"><option :value="true">Aktif</option><option :value="false">Nonaktif</option></select></label>
          <label v-for="image in images" :key="image.field">{{ image.label }}<input type="file" accept="image/png,image/jpeg,image/webp" @change="selectImage($event,image)" /><span class="error">{{ form.errors[image.field] }}</span><img v-if="previews[image.field]" :src="previews[image.field]" :alt="`Pratinjau ${image.label}`" class="asset-preview" /></label>
        </div>
        <div class="actions"><button class="primary" :disabled="form.processing">Simpan skin</button><Link class="secondary" :href="route('redesign.profile-skins')">Batal</Link></div>
      </form>
      <aside><div class="panel"><h2>Pratinjau tema</h2><img v-if="previews.preview_image" :src="previews.preview_image" alt="Pratinjau skin" class="main-preview" /><div class="theme-preview" :style="{ background: form.hero_gradient, color: form.text_primary, borderColor: form.border_color }"><strong>{{ form.name || 'Nama skin' }}</strong><p>Contoh tampilan tema</p></div></div><div class="panel"><h2>Panduan pengisian</h2><p class="muted">Gambar yang dipilih langsung tampil sebagai pratinjau. Simpan untuk menerapkan perubahan.</p></div></aside>
    </div>
  </AssessmentLayout>
</template>
<style scoped>.asset-preview{width:96px;height:72px;object-fit:cover;border-radius:8px;border:1px solid #dce6e8}.main-preview{width:100%;max-height:220px;object-fit:contain;border-radius:8px}.theme-preview{border:2px solid;border-radius:10px;padding:24px;margin-top:12px;min-height:100px}</style>
