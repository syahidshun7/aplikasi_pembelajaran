<script setup>
import './redesignTable.css';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminSidebar from './AdminSidebar.vue';
import RedesignTopbar from './RedesignTopbar.vue';
import './workspace.css';
defineProps({ title: String, subtitle: { type: String, default: 'Kelola penilaian dan hasil pembelajaran dalam satu workspace.' }, breadcrumb: { type: String, default: 'Penilaian' } });
const page = usePage();
const notice = computed(() => page.props.flash?.message || page.props.flash?.success || null);
</script>
<template>
  <Head :title="title" /><div class="workspace"><AdminSidebar /><main><RedesignTopbar :breadcrumb="breadcrumb" /><div class="content"><div class="heading"><div><h1>{{ title }}</h1><p class="muted">{{ subtitle }}</p></div><slot name="action" /></div><div v-if="notice" class="workspace-notice" role="status">{{ String(notice).replaceAll('_',' ') }}</div><slot /></div></main></div>
</template>
