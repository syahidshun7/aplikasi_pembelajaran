<script setup>
import RedesignTopbar from './RedesignTopbar.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminSidebar from './AdminSidebar.vue';
import './workspace.css';
const props = defineProps({ definitions: Object, filters: Object });
const page = usePage();
const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const apply = () => router.get(route('redesign.daily-quests'), { search: search.value || undefined, status: status.value === 'all' ? undefined : status.value }, { preserveState: true, replace: true });
const reset = () => { search.value = ''; status.value = 'all'; router.get(route('redesign.daily-quests')); };
const remove = async item => { if (await confirmAction(`Hapus definisi ${item.title}?`)) router.delete(route('admin.daily-quest-definitions.destroy', item.id)); };
</script>
<template>
  <Head title="Daily quests" /><div class="workspace"><AdminSidebar /><main><RedesignTopbar breadcrumb="Quest &amp; task bank" />
    <div class="content"><div class="heading"><div><h1>Daily quests</h1><p class="muted">Definisi aktivitas dan reward harian.</p></div><Link class="primary" :href="route('redesign.daily-quests.create')">+ Definisi baru</Link></div>
      <div class="tabs"><Link :class="{ active: filters?.status === 'all' }" :href="route('redesign.daily-quests')">Semua</Link><Link :class="{ active: filters?.status === 'active' }" :href="route('redesign.daily-quests', { status: 'active' })">Aktif</Link></div>
      <form class="filters" @submit.prevent="apply"><input v-model="search" aria-label="Cari daily quest" placeholder="Cari kode, judul, atau kata kunci..." /><select v-model="status" aria-label="Status"><option value="all">Semua status</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select><button class="action">Filter</button><button type="button" class="action" @click="reset">Reset</button></form>
      <p v-if="page.props.flash?.message" class="success" role="status">{{ page.props.flash.message }}</p><p v-if="page.props.errors?.daily_quest_definition" class="error" role="alert">{{ page.props.errors.daily_quest_definition }}</p>
      <section class="panel"><h2>Daily quests</h2><div class="table-wrap"><table><thead><tr><th>Kode / judul</th><th>Activity type</th><th>Target</th><th>Reward</th><th>Status</th><th>Aksi</th></tr></thead><tbody><tr v-for="item in definitions?.data || []" :key="item.id"><td><Link class="row-link" :href="route('redesign.daily-quests.edit', item.id)">{{ item.code }} / {{ item.title }}</Link></td><td>{{ item.activity_type }}</td><td>{{ item.target_value }}</td><td>{{ item.reward_exp }} EXP · {{ item.reward_gold }} G</td><td :class="item.is_active ? 'active-status' : 'muted'">{{ item.is_active ? 'Aktif' : 'Nonaktif' }}</td><td><Link :href="route('redesign.daily-quests.edit', item.id)">Edit</Link> <button type="button" class="delete" @click="remove(item)">Hapus</button></td></tr><tr v-if="!definitions?.data?.length"><td colspan="6" class="muted">Belum ada definisi daily quest yang cocok.</td></tr></tbody></table></div><div class="pager"><Link v-for="(link, i) in definitions?.links || []" :key="i" :href="link.url || '#'" :class="{ active: link.active, disabled: !link.url }" v-html="link.label" /></div></section>
    </div></main></div>
</template>
<style scoped>.row-link{font-weight:700;color:#19333d;text-decoration:underline;text-decoration-color:#9ccbc6;text-underline-offset:3px}.active-status{color:#00857c}.delete{border:0;background:none;color:#b74336;cursor:pointer;margin-left:8px;font:inherit}td a{color:#007f78}</style>
