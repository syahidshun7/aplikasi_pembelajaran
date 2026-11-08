<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import AssessmentLayout from './AssessmentLayout.vue';
import { resolveNotificationActionUrl } from '@/Utils/notificationAction';
const props = defineProps({ notifications: Object, unreadCount: Number, filter: String });
const page = usePage();
const tabs = [['all','Semua'],['unread','Belum dibaca'],['quest','Quest'],['class','Kelas'],['system','Sistem']];
const read = item => router.post(route('notifications.read',item.id),{}, { preserveScroll:true });
const readAll = () => router.post(route('notifications.read-all'),{}, { preserveScroll:true });
const actionUrl = item => resolveNotificationActionUrl(item, {
    isStaff: true,
    isAdmin: ['super_admin', 'admin'].includes(page.props.auth?.user?.role),
    preferRedesign: true,
    routeFn: route,
    currentUrl: page.url,
});
const open = async item => {
    if (!item.read_at) {
        try { await window.axios.post(route('notifications.read', item.id)); } catch (error) { console.warn('Failed to mark notification as read:', error); }
    }
    router.visit(actionUrl(item));
};
</script>
<template><AssessmentLayout title="Riwayat notifikasi" subtitle="Aktivitas terbaru untuk akun operator." breadcrumb="Operasional"><div class="summary"><div class="panel"><span class="muted">Belum dibaca</span><h2>{{ unreadCount }}</h2></div><div class="panel"><span class="muted">Pada filter ini</span><h2>{{ notifications.meta?.total || 0 }}</h2></div></div><div class="tabs"><Link v-for="tab in tabs" :key="tab[0]" :href="route('redesign.notifications',{filter:tab[0]})" :class="{active:filter===tab[0]}">{{ tab[1] }}</Link></div><section class="panel"><div class="title-row"><h2>Operator timeline</h2><button class="secondary" type="button" :disabled="!unreadCount" @click="readAll">Tandai semua dibaca</button></div><div v-if="!notifications.data?.length" class="empty">Belum ada notifikasi pada filter ini.</div><article v-for="item in notifications.data" :key="item.id" class="notice"><span class="dot" :class="{unread:!item.read_at}" aria-hidden="true"></span><div><h3>{{ item.title }}</h3><p class="muted">{{ item.message }}</p><div class="notice-actions"><button type="button" @click="open(item)">{{ item.action_label || 'Buka' }}</button><button v-if="!item.read_at" type="button" @click="read(item)">Tandai dibaca</button></div></div><time :datetime="item.created_at">{{ item.created_at ? new Date(item.created_at).toLocaleString('id-ID') : '—' }}</time></article><div class="pager"><Link v-if="notifications.links?.prev" :href="notifications.links.prev">‹ Sebelumnya</Link><span>Halaman {{ notifications.meta?.current_page || 1 }} / {{ notifications.meta?.last_page || 1 }}</span><Link v-if="notifications.links?.next" :href="notifications.links.next">Berikutnya ›</Link></div></section></AssessmentLayout></template>
<style scoped>.summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-bottom:22px}.summary h2{font-size:28px;margin:10px 0 0}.title-row{display:flex;align-items:center;justify-content:space-between;gap:12px}.notice{display:grid;grid-template-columns:12px minmax(0,1fr) auto;gap:18px;padding:22px 4px;border-bottom:1px solid #e4edef}.notice h3{font-size:15px;margin:0 0 5px}.notice p{margin:0}.notice time{font-size:11px;color:#70858c}.dot{width:9px;height:9px;border-radius:50%;background:#dfe8e8;margin-top:5px}.dot.unread{background:#00857c}.notice-actions{display:flex;gap:14px;margin-top:8px}.notice-actions button{border:0;background:transparent;color:#00857c;cursor:pointer;padding:0}.notice-actions a{color:#00857c}.empty{padding:45px;text-align:center;color:#70858c}@media(max-width:650px){.notice{grid-template-columns:12px 1fr}.notice time{grid-column:2}.summary{grid-template-columns:1fr}.title-row{align-items:flex-start;flex-direction:column}}</style>
