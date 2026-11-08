<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AssessmentLayout from './AssessmentLayout.vue';
const props = defineProps({ items: Array });
const search = ref('');
const type = ref('all');
const selected = ref(null);
const visible = computed(() => props.items.filter(item => (type.value === 'all' || item.type === type.value) && item.name.toLowerCase().includes(search.value.toLowerCase())));
const types = ['all', 'quest', 'materi', 'event', 'kelas', 'pengguna', 'submission'];
function restore(item) { router.patch(item.restore_url, {}, { onSuccess: () => router.reload({ only: ['items'] }) }); }
function destroy() { if (!selected.value) return; router.delete(selected.value.destroy_url, { onSuccess: () => { selected.value = null; router.reload({ only: ['items'] }); } }); }
</script>
<template><AssessmentLayout title="Sampah & pemulihan" subtitle="Pulihkan data yang telah diarsipkan dari satu tempat." breadcrumb="Operasional"><section class="panel"><div class="filters"><input v-model="search" placeholder="Cari nama item" aria-label="Cari item yang dihapus" /><select v-model="type" aria-label="Filter jenis"><option v-for="entry in types" :key="entry" :value="entry">{{ entry === 'all' ? 'Semua jenis' : entry }}</option></select></div><div class="table-wrap"><table><thead><tr><th>Item</th><th>Tipe</th><th>Dihapus</th><th>Aksi</th></tr></thead><tbody><tr v-for="item in visible" :key="item.id"><td><strong>{{ item.name }}</strong></td><td>{{ item.type }}</td><td>{{ item.deleted_at?.slice(0,10) || '—' }}</td><td><button type="button" class="secondary" @click="restore(item)">Pulihkan</button> <button type="button" class="danger" @click="selected=item">Hapus permanen</button></td></tr><tr v-if="!visible.length"><td colspan="4">Sampah kosong untuk filter ini.</td></tr></tbody></table></div></section><div v-if="selected" class="modal-backdrop" role="presentation" @click.self="selected=null"><div class="modal" role="alertdialog" aria-modal="true" aria-labelledby="delete-title"><h2 id="delete-title">Hapus permanen?</h2><p>“{{ selected.name }}” akan dihapus dan tidak dapat dipulihkan.</p><div class="actions"><button type="button" class="danger" @click="destroy">Ya, hapus permanen</button><button type="button" class="secondary" @click="selected=null">Batal</button></div></div></div></AssessmentLayout></template>
<style scoped>.danger{border:1px solid #c84c4c;background:#fff;color:#aa3636;border-radius:8px;padding:10px 14px;cursor:pointer}.modal-backdrop{position:fixed;inset:0;background:#10292eaa;display:grid;place-items:center;z-index:100;padding:16px}.modal{background:#fff;border-radius:12px;padding:26px;max-width:440px;width:100%;box-shadow:0 20px 70px #10292e55}</style>
