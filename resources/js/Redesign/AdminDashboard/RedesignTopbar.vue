<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount } from 'vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import { confirmAction } from './confirmAction.js';
const props = defineProps({ breadcrumb: { type: String, default: 'Overview' } });
const page = usePage();
const menuOpen = ref(false);
const menuRoot = ref(null);
const closeOutside = event => { if (!menuRoot.value?.contains(event.target)) menuOpen.value = false; };
onMounted(() => document.addEventListener('click', closeOutside));
onBeforeUnmount(() => document.removeEventListener('click', closeOutside));
const logout = async () => { if (await confirmAction('Akhiri sesi admin sekarang?', 'Logout')) router.post(route('logout')); };
</script>
<template><header class="topbar redesign-topbar"><span class="breadcrumb">Workspace / {{ breadcrumb }}</span><div class="topbar-actions"><NotificationBell variant="redesign" /><div ref="menuRoot" class="account-menu"><button type="button" class="account-button" :aria-expanded="menuOpen" @click="menuOpen=!menuOpen"><span class="account-avatar">{{ (page.props.auth?.user?.name || 'A').slice(0,1).toUpperCase() }}</span><span class="account-label"><strong>{{ page.props.auth?.user?.name }}</strong><small>{{ page.props.auth?.user?.role?.replaceAll('_',' ') }}</small></span><span aria-hidden="true">⌄</span></button><div v-if="menuOpen" class="account-dropdown"><Link :href="route('redesign.profile')" @click="menuOpen=false">Profil saya</Link><button type="button" @click="logout">Logout</button></div></div></div></header></template>
<style scoped>.redesign-topbar{height:64px;position:relative;z-index:50;display:flex;align-items:center;justify-content:space-between;gap:16px;background:#fff;border-bottom:1px solid #dce6e8;padding:0 28px;color:#70858c;font-family:Inter,ui-sans-serif,system-ui,sans-serif;font-size:11px}.topbar-actions{display:flex;align-items:center;gap:12px}.account-menu{position:relative}.account-button{display:flex;align-items:center;gap:8px;background:transparent;border:0;color:#19333d;cursor:pointer;padding:5px;font:inherit}.account-avatar{width:29px;height:29px;border-radius:50%;display:grid;place-items:center;background:#e5f5f1;color:#00857c;font-weight:800}.account-label{text-align:left}.account-label strong,.account-label small{display:block}.account-label strong{font-size:11px}.account-label small{font-size:10px;color:#70858c;margin-top:2px}.account-dropdown{position:absolute;right:0;top:calc(100% + 9px);width:170px;background:#fff;border:1px solid #dce6e8;border-radius:9px;box-shadow:0 12px 34px #10292e20;padding:6px;z-index:130}.account-dropdown a,.account-dropdown button{display:block;width:100%;text-align:left;padding:10px;border:0;border-radius:6px;background:#fff;color:#19333d;text-decoration:none;font:inherit;font-size:12px;cursor:pointer}.account-dropdown :is(a,button):hover{background:#e5f5f1;color:#007f78}@media(max-width:650px){.redesign-topbar{padding:0 14px}.breadcrumb{max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.account-label{display:none}.topbar-actions{gap:5px}}</style>
