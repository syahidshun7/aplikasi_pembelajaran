<script setup>
import { Link, usePage } from '@inertiajs/vue3';
const page = usePage();
const user = page.props?.auth?.user || {};
const groups = [
    { title: 'Overview', items: [['Dashboard', 'dashboard']] },
    { title: 'Program belajar', items: [['Job / program', 'admin.jobs.index'], ['Study groups', 'groups.manage'], ['Materi', 'admin.materi'], ['Event', 'admin.events.index'], ['Quest', 'quests.index'], ['Task banks', 'admin.task-banks.index'], ['Daily quests', 'admin.daily-quest-definitions.index']] },
    { title: 'Penilaian', items: [['Submissions', 'admin.submissions.manage.index'], ['Rubrics', 'admin.rubrics.index'], ['Creation review', 'admin.creations.queue']] },
    { title: 'Komunitas', items: [['Users', 'admin.users.index'], ['DoopNews', 'admin.doopnews.index'], ['Shop items', 'admin.shop-items.index'], ['Profile skins', 'admin.profile-skins.index']] },
    { title: 'Operasional', items: [['Error logs', 'admin.error-logs.index'], ['Notifikasi', 'notifications.index'], ['Profil', 'admin.profile.edit']] },
];
</script>
<template>
    <aside class="redesign-sidebar">
        <div class="brand"><div class="brand-mark">P</div><div><strong>DOOPTECH</strong><small>ADMIN CONSOLE</small></div></div>
        <nav>
            <div v-for="group in groups" :key="group.title" class="nav-group">
                <p class="nav-group-title">{{ group.title }}</p>
                <Link v-for="item in group.items" :key="item[0]" :href="item[1] === 'dashboard' ? route('redesign.admin-dashboard') : (item[1] === 'admin.materi' ? '/admin/materi' : route(item[1]))" class="nav-link" :class="{ active: item[1] === 'dashboard' }"><span class="nav-dot"></span>{{ item[0] }}</Link>
            </div>
        </nav>
        <div class="profile-chip"><div class="avatar">{{ (user.name || 'AD').slice(0, 2).toUpperCase() }}</div><div><strong>{{ user.name || 'Admin Demo' }}</strong><small>{{ user.role || 'Super admin' }}</small></div></div>
    </aside>
</template>
<style scoped>
.redesign-sidebar{width:248px;min-height:100vh;background:#12383d;color:#a8c1c3;border-right:3px solid #20b7e8;padding:22px 14px 16px;display:flex;flex-direction:column}.brand{display:flex;align-items:center;gap:10px;color:#fff;margin:0 8px 34px}.brand-mark{font-size:30px;line-height:1;font-weight:900;color:#fff}.brand strong{display:block;font-size:15px;letter-spacing:.03em}.brand small,.profile-chip small{display:block;font-size:8px;color:#86a9ac;text-transform:uppercase;margin-top:3px}.nav-group{margin-bottom:18px}.nav-group-title{margin:0 7px 7px;font-size:7px;text-transform:uppercase;color:#7da0a3;letter-spacing:.08em}.nav-link{display:flex;align-items:center;gap:10px;border-radius:6px;padding:8px 8px;font-size:11px;color:#9bb8ba;text-decoration:none}.nav-link:hover,.nav-link.active{background:#235e62;color:#fff}.nav-link.active{font-weight:700}.nav-dot{width:4px;height:4px;border-radius:50%;background:#72a7a9}.profile-chip{display:flex;align-items:center;gap:10px;background:#204f54;border-radius:7px;padding:10px 8px;margin-top:auto;color:#fff}.avatar{width:24px;height:24px;border-radius:50%;display:grid;place-items:center;background:#8ac5c3;color:#12383d;font-size:9px;font-weight:700}.profile-chip strong{font-size:10px;display:block}.profile-chip small{text-transform:none;font-size:8px}@media(max-width:800px){.redesign-sidebar{width:100%;min-height:auto;border-right:0;border-bottom:3px solid #20b7e8}.redesign-sidebar nav{display:none}.profile-chip{margin-top:16px}}
</style>
