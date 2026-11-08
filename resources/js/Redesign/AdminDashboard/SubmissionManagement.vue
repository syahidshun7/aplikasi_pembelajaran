<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AssessmentLayout from './AssessmentLayout.vue';
import { confirmAction } from './confirmAction.js';
const props = defineProps({ submissions:Object, filters:Object });
const page = usePage();
const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const view = ref(props.filters?.view || 'active');
const duplicates = ref(props.filters?.duplicates || '0');
const apply = (changes = {}) => router.get(route('redesign.submissions'), { search:search.value || undefined, status:status.value, view:view.value, duplicates:duplicates.value, ...changes }, { preserveState:true, replace:true });
const reset = () => router.get(route('redesign.submissions'));
const remove = async item => { if(await confirmAction(`Arsipkan submission ${item.user?.name || item.uuid}?`)) router.delete(route('admin.submissions.manage.destroy',item.uuid)); };
const restore = item => router.patch(route('admin.submissions.manage.restore',item.uuid));
</script>
<template>
  <AssessmentLayout title="Submission management" subtitle="Kelola kiriman peserta dan status penilaiannya."><div class="tabs"><Link :class="{active:view==='active' && duplicates==='0' && status==='all'}" :href="route('redesign.submissions')">Semua</Link><Link :class="{active:status==='Pending'}" :href="route('redesign.submissions',{status:'Pending'})">Menunggu</Link><Link :class="{active:duplicates==='1'}" :href="route('redesign.submissions',{duplicates:'1'})">Duplikat</Link><Link :class="{active:view==='trash'}" :href="route('redesign.submissions',{view:'trash'})">Sampah</Link></div>
    <form class="filters" @submit.prevent="apply()"><input v-model="search" aria-label="Cari submission" placeholder="Cari peserta, quest, atau konten..." /><select v-model="status" aria-label="Status"><option value="all">Semua status</option><option value="Pending">Menunggu</option><option value="Approved">Disetujui</option><option value="Rejected">Ditolak</option></select><button class="action">Filter</button><button type="button" class="action" @click="reset">Reset</button></form>
    <p v-if="page.props.flash?.message" class="success">{{ page.props.flash.message }}</p><section class="panel"><h2>Submission management</h2><div class="table-wrap"><table><thead><tr><th>Peserta / quest</th><th>Status</th><th>Nilai</th><th>Reward</th><th>Attempts</th><th>Aksi</th></tr></thead><tbody><tr v-for="item in submissions?.data || []" :key="item.uuid"><td><Link v-if="view!=='trash'" class="row-link" :href="route('redesign.submissions.inspect',item.uuid)">{{ item.user?.name || item.user?.username || 'Peserta' }} / {{ item.quest?.title || 'Quest' }}</Link><strong v-else>{{ item.user?.name }} / {{ item.quest?.title }}</strong><small v-if="item.duplicate_count > 1" class="muted">{{ item.duplicate_count }} submission untuk quest ini</small></td><td>{{ item.status }}</td><td>{{ item.grade ?? '—' }}</td><td>{{ item.earned_exp || 0 }} EXP · {{ item.earned_gold || 0 }} G</td><td>{{ item.attempt_number || 1 }}</td><td><div class="row-actions"><button v-if="view==='trash'" type="button" @click="restore(item)">Pulihkan</button><template v-else><Link :href="route('redesign.submissions.edit',item.uuid)">Edit</Link><button type="button" @click="remove(item)">Arsipkan</button></template></div></td></tr><tr v-if="!submissions?.data?.length"><td colspan="6" class="muted">Belum ada submission yang cocok.</td></tr></tbody></table></div><div class="pager"><Link v-for="(link,i) in submissions?.links || []" :key="i" :href="link.url || '#'" :class="{active:link.active,disabled:!link.url}" v-html="link.label" /></div></section>
  </AssessmentLayout>
</template>
<style scoped>.row-link{font-weight:700;color:#19333d;text-decoration:underline;text-decoration-color:#9ccbc6;text-underline-offset:3px}.row-actions{display:flex;gap:10px}.row-actions :is(a,button){border:0;background:none;color:#007f78;font:inherit;cursor:pointer}.row-actions button:last-child{color:#b74336}td small{display:block}</style>
