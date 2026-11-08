<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AssessmentLayout from './AssessmentLayout.vue';
const props = defineProps({ users: Object, filters: Object, availableRoles: Array });
const search = ref(props.filters?.search || '');
const role = ref(props.filters?.role || 'all');
const view = ref(props.filters?.view || 'active');
const rankBy = ref(props.filters?.rank_by || 'newest');
const gradeOrder = ref(props.filters?.grade_order || 'none');
const query = () => router.get(route('redesign.users'), { search: search.value, role: role.value, view: view.value, rank_by: rankBy.value, grade_order: gradeOrder.value }, { preserveState: true, preserveScroll: true });
const reset = () => { search.value = ''; role.value = 'all'; view.value = 'active'; rankBy.value = 'newest'; gradeOrder.value = 'none'; query(); };
const sortGrade = order => { gradeOrder.value = gradeOrder.value === order ? 'none' : order; query(); };
</script>
<template>
  <AssessmentLayout title="User management" subtitle="Kelola akun, peran, dan progres pengguna." breadcrumb="Komunitas & pengguna">
    <section class="panel">
      <form class="filters" @submit.prevent="query"><input v-model="search" placeholder="Cari nama, username, atau email" aria-label="Cari pengguna" /><select v-model="role" aria-label="Filter peran"><option value="all">Semua peran</option><option v-for="item in availableRoles" :key="item" :value="item">{{ item }}</option></select><select v-model="view" aria-label="Status pengguna"><option value="active">Aktif</option><option value="trash">Trash</option></select><select v-model="rankBy" aria-label="Urutkan pengguna"><option value="newest">Terbaru</option><option value="highest_gold">Gold tertinggi</option><option value="highest_exp">EXP tertinggi</option></select><button class="secondary" type="submit">Terapkan</button><button class="secondary" type="button" @click="reset">Reset</button></form>
      <div class="table-wrap"><table><thead><tr><th>Pengguna</th><th>Email</th><th>Program</th><th>Role</th><th>Progres</th><th>Nilai tertinggi <button type="button" class="sort" :aria-pressed="gradeOrder==='asc'" aria-label="Urutkan nilai dari rendah" @click="sortGrade('asc')">↑</button><button type="button" class="sort" :aria-pressed="gradeOrder==='desc'" aria-label="Urutkan nilai dari tinggi" @click="sortGrade('desc')">↓</button></th><th>{{ view==='trash' ? 'Dihapus' : 'Bergabung' }}</th><th>Aksi</th></tr></thead><tbody><tr v-for="user in users.data" :key="user.id"><td><strong>{{ user.name }}</strong><br><span class="muted">@{{ user.username || '—' }}</span></td><td>{{ user.email }}</td><td>{{ user.job?.name || '—' }}</td><td>{{ user.role }}</td><td>Level {{ user.level_display || 1 }} · {{ user.submissions_count || 0 }} submission</td><td>{{ user.highest_grade ?? 0 }}</td><td>{{ (view==='trash' ? user.deleted_at : user.created_at)?.slice(0,10) || '—' }}</td><td><Link v-if="view==='active'" :href="route('redesign.users.edit',user.id)">Edit</Link> <Link v-if="view==='active'" :href="route('redesign.users.ledger',user.id)">Ledger</Link><button v-if="view==='trash'" type="button" class="secondary" @click="router.patch(route('admin.users.restore',user.id))">Pulihkan</button></td></tr><tr v-if="!users.data?.length"><td colspan="8">Belum ada pengguna.</td></tr></tbody></table></div>
      <div class="pager"><Link v-for="(link,index) in users.links" :key="index" :href="link.url || '#'" :class="{active:link.active,disabled:!link.url}" v-html="link.label" /></div>
    </section>
  </AssessmentLayout>
</template>
<style scoped>.sort{border:1px solid #dce6e8;background:#fff;color:#00857c;border-radius:4px;margin-left:5px;cursor:pointer}.sort[aria-pressed="true"]{background:#e5f5f1;font-weight:800}.filters{grid-template-columns:minmax(200px,1fr) 140px 110px 150px auto auto}@media(max-width:1100px){.filters{grid-template-columns:repeat(3,minmax(0,1fr))}.filters input{grid-column:1/-1}}@media(max-width:650px){.filters{grid-template-columns:1fr}.filters input{grid-column:auto}}</style>
