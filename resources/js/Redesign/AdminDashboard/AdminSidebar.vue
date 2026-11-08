<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const menuOpen = ref(false);
const user = computed(() => page.props?.auth?.user || {});
const currentName = computed(() => route().current() || '');
const currentParams = computed(() => route().params || {});
const scopedGroupUuid = computed(() => currentName.value.startsWith('redesign.study-groups.')
    ? currentParams.value.groupUuid || currentParams.value.uuid || null : null);
const isMentor = computed(() => user.value.role === 'mentor');

const adminGroups = [
    { title: 'Ringkasan', items: [
        { label: 'Dashboard', key: 'dashboard', name: 'redesign.admin-dashboard' },
        { label: 'Dashboard kelas saya', key: 'class-dashboard', name: 'redesign.class-dashboard' },
    ] },
    { title: 'Program & kelas', items: [
        { label: 'Job / program', key: 'jobs', name: 'redesign.jobs' },
        { label: 'Study groups', key: 'study-groups', name: 'redesign.study-groups' },
    ] },
    { title: 'Konten pembelajaran', items: [
        { label: 'Materi', key: 'materi', name: 'redesign.materi' },
        { label: 'Event', key: 'events', name: 'redesign.events' },
        { label: 'Quest', key: 'quests', name: 'redesign.quests' },
    ] },
    { title: 'Bank & latihan', items: [
        { label: 'Task banks', key: 'task-banks', name: 'redesign.task-banks' },
        { label: 'Daily quests', key: 'daily-quests', name: 'redesign.daily-quests' },
    ] },
    { title: 'Penilaian', items: [
        { label: 'Submissions', key: 'submissions', name: 'redesign.submissions' },
        { label: 'Rubrics', key: 'rubrics', name: 'redesign.rubrics' },
        { label: 'Creation review', key: 'creation-review', name: 'redesign.creations.queue' },
    ] },
    { title: 'Komunitas', items: [
        { label: 'Users', key: 'users', name: 'redesign.users' },
        { label: 'DoopNews', key: 'doopnews', name: 'redesign.doopnews' },
        { label: 'Shop items', key: 'shop-items', name: 'redesign.shop-items' },
        { label: 'Profile skins', key: 'profile-skins', name: 'redesign.profile-skins' },
    ] },
    { title: 'Operasional', items: [
        { label: 'Error logs', key: 'error-logs', name: 'redesign.error-logs' },
        { label: 'Notifikasi', key: 'notifications', name: 'redesign.notifications' },
        { label: 'Sampah & pemulihan', key: 'trash', name: 'redesign.trash' },
        { label: 'Profil', key: 'profile', name: 'redesign.profile' },
    ] },
];
const mentorKeys = new Set(['class-dashboard', 'task-banks', 'rubrics', 'creation-review', 'notifications', 'profile']);
const groups = computed(() => isMentor.value
    ? adminGroups.map(group => ({ ...group, items: group.items.filter(item => mentorKeys.has(item.key)) })).filter(group => group.items.length)
    : adminGroups);

const isActive = key => {
    const name = currentName.value;
    if (key === 'dashboard') return name === 'redesign.admin-dashboard';
    if (key === 'class-dashboard') return name === 'redesign.class-dashboard';
    if (key === 'jobs') return name.startsWith('redesign.jobs');
    if (key === 'study-groups') return name.startsWith('redesign.study-groups')
        && !/^redesign\.study-groups\.(guides|events|quests)(\.|$)/.test(name);
    if (key === 'materi') return name.startsWith('redesign.materi') || name.startsWith('redesign.study-groups.guides');
    if (key === 'events') return name.startsWith('redesign.events') || name.startsWith('redesign.study-groups.events');
    if (key === 'quests') return name.startsWith('redesign.quests') || name.startsWith('redesign.study-groups.quests');
    if (key === 'task-banks') return name.startsWith('redesign.task-banks') || name.startsWith('admin.task-banks.');
    if (key === 'daily-quests') return name.startsWith('redesign.daily-quests') || name.startsWith('admin.daily-quest-definitions.');
    if (key === 'submissions') return name.startsWith('redesign.submissions') || name.startsWith('redesign.quests.submissions') || name.startsWith('admin.submissions.');
    if (key === 'rubrics') return name.startsWith('redesign.rubrics') || name.startsWith('admin.rubrics.');
    if (key === 'creation-review') return name.startsWith('redesign.creations.') || name.startsWith('admin.creations.');
    if (key === 'profile') return name === 'redesign.profile';
    if (key === 'error-logs') return name === 'redesign.error-logs' || name === 'admin.error-logs.index';
    if (key === 'notifications') return name === 'redesign.notifications' || name === 'notifications.index';
    if (key === 'trash') return name === 'redesign.trash';
    if (['users', 'doopnews', 'shop-items', 'profile-skins'].includes(key) && name.startsWith(`redesign.${key}`)) return true;
    const prefixes = { 'task-banks': 'admin.task-banks.', 'daily-quests': 'admin.daily-quest-definitions.', submissions: 'admin.submissions.', rubrics: 'admin.rubrics.', 'creation-review': 'admin.creations.', users: 'admin.users.', doopnews: 'admin.doopnews.', 'shop-items': 'admin.shop-items.', 'profile-skins': 'admin.profile-skins.', 'error-logs': 'admin.error-logs.', notifications: 'notifications.' };
    return name.startsWith(prefixes[key] || '__none__');
};

const sectionUrl = (key, child = '') => {
    const section = { materi: 'guides', events: 'events', quests: 'quests' }[key];
    if (section && scopedGroupUuid.value) {
        return route(`redesign.study-groups.${section}${child ? `.${child}` : ''}`, scopedGroupUuid.value);
    }
    const base = { jobs: 'redesign.jobs', 'study-groups': 'redesign.study-groups', materi: 'redesign.materi', events: 'redesign.events', quests: 'redesign.quests', 'task-banks': 'redesign.task-banks', 'daily-quests': 'redesign.daily-quests' }[key];
    return route(`${base}${child ? `.${child}` : ''}`);
};
const itemUrl = item => ['materi', 'events', 'quests'].includes(item.key) ? sectionUrl(item.key) : route(item.name);
const subnavItems = key => {
    if (['users', 'doopnews', 'shop-items', 'profile-skins'].includes(key)) {
        const labels = { users: 'pengguna', doopnews: 'berita', 'shop-items': 'item', 'profile-skins': 'skin' };
        const base = `redesign.${key}`;
        const entries = [{ label: `Daftar ${labels[key]}`, href: route(base), selected: currentName.value === base }];
        if (key !== 'users') entries.push({ label: `Tambah ${labels[key]}`, href: route(`${base}.create`), selected: currentName.value === `${base}.create` });
        return entries;
    }
    if (!['jobs', 'study-groups', 'materi', 'events', 'quests', 'task-banks', 'daily-quests'].includes(key)) return [];
    const noun = { jobs: 'program', 'study-groups': 'kelas', materi: 'materi', events: 'event', quests: 'quest', 'task-banks': 'task bank', 'daily-quests': 'definisi daily quest' }[key];
    const entries = [
        { label: `Daftar ${noun}`, href: sectionUrl(key), selected: currentName.value === ({ jobs: 'redesign.jobs', 'study-groups': 'redesign.study-groups', materi: scopedGroupUuid.value ? 'redesign.study-groups.guides' : 'redesign.materi', events: scopedGroupUuid.value ? 'redesign.study-groups.events' : 'redesign.events', quests: scopedGroupUuid.value ? 'redesign.study-groups.quests' : 'redesign.quests', 'task-banks': 'redesign.task-banks', 'daily-quests': 'redesign.daily-quests' })[key] },
        { label: `Tambah ${noun}`, href: sectionUrl(key, 'create'), selected: currentName.value === ({ jobs: 'redesign.jobs.create', 'study-groups': 'redesign.study-groups.create', materi: scopedGroupUuid.value ? 'redesign.study-groups.guides.create' : 'redesign.materi.create', events: scopedGroupUuid.value ? 'redesign.study-groups.events.create' : 'redesign.events.create', quests: scopedGroupUuid.value ? 'redesign.study-groups.quests.create' : 'redesign.quests.create', 'task-banks': 'redesign.task-banks.create', 'daily-quests': 'redesign.daily-quests.create' })[key] },
    ];
    if (key === 'quests') {
        entries[1].label = 'Buat quest';
        entries.push({ label: 'Generate dengan AI', href: sectionUrl('quests', 'generate-ai'), selected: ['redesign.quests.generate-ai', 'redesign.study-groups.quests.generate-ai'].includes(currentName.value) });
    }
    if (key === 'study-groups' && isActive('study-groups') && scopedGroupUuid.value) {
        for (const [name, label] of [['show', 'Detail kelas'], ['membership', 'Keanggotaan'], ['roadmaps', 'Roadmap'], ['attendance', 'Rekap kehadiran']]) {
            entries.push({ label, href: route(`redesign.study-groups.${name}`, scopedGroupUuid.value), selected: currentName.value === `redesign.study-groups.${name}` });
        }
    }
    return entries;
};
</script>

<template>
    <aside class="redesign-sidebar">
        <div class="brand"><img class="brand-mark" src="/images/logo.png" alt="Logo Dooptech" /><div><strong>DOOPTECH</strong><small>{{ isMentor ? 'MENTOR CONSOLE' : 'ADMIN CONSOLE' }}</small></div><button class="mobile-toggle" type="button" :aria-expanded="menuOpen" aria-controls="redesign-navigation" @click="menuOpen = !menuOpen">{{ menuOpen ? 'Tutup' : 'Menu' }}</button></div>
        <nav id="redesign-navigation" :aria-label="isMentor ? 'Navigasi mentor' : 'Navigasi admin'" :class="{ open: menuOpen }">
            <section v-for="group in groups" :key="group.title" class="nav-group" :aria-label="group.title">
                <h2 class="nav-group-title">{{ group.title }}</h2>
                <div v-for="item in group.items" :key="item.key">
                    <Link :href="itemUrl(item)" class="nav-link" :class="{ active: isActive(item.key) }" :aria-current="isActive(item.key) && !subnavItems(item.key).some(child => child.selected) ? 'page' : undefined" @click="menuOpen = false"><span class="nav-dot" aria-hidden="true"></span>{{ item.label }}</Link>
                    <div v-if="isActive(item.key) && subnavItems(item.key).length" class="subnav" :aria-label="`Halaman ${item.label}`">
                        <Link v-for="child in subnavItems(item.key)" :key="child.label" :href="child.href" :class="{ selected: child.selected }" :aria-current="child.selected ? 'page' : undefined" @click="menuOpen = false">{{ child.label }}</Link>
                    </div>
                </div>
            </section>
        </nav>
        <div class="profile-chip"><div class="avatar">{{ (user.name || 'AD').slice(0, 2).toUpperCase() }}</div><div><strong>{{ user.name || 'Pengguna' }}</strong><small>{{ user.role?.replaceAll('_', ' ') || 'Staff' }}</small></div></div>
    </aside>
</template>

<style scoped>
.redesign-sidebar{width:248px;min-height:100vh;flex:none;background:#12383d;color:#a8c1c3;border-right:3px solid #20b7e8;padding:22px 14px 16px;display:flex;flex-direction:column}.brand{display:flex;align-items:center;gap:10px;color:#fff;margin:0 8px 26px}.brand-mark{width:30px;height:30px;object-fit:contain;flex:none}.brand strong{display:block;font-size:14px;font-weight:800;letter-spacing:-.02em}.brand small,.profile-chip small{display:block;font-size:9px;color:#86a9ac;margin-top:3px}.brand small{letter-spacing:.04em}.redesign-sidebar nav{flex:1}.nav-group{padding:0 0 14px;margin-bottom:13px;border-bottom:1px solid rgba(173,213,214,.14)}.nav-group:last-child{border-bottom:0}.nav-group-title{margin:0 8px 8px;font-size:10px;font-weight:750;text-transform:uppercase;color:#91b7b8;letter-spacing:.07em}.nav-link{display:flex;align-items:center;gap:12px;border-radius:7px;padding:9px 10px;font-size:12px;color:#b6ced0;text-decoration:none;line-height:1.35}.nav-link:hover{background:#1c4b50;color:#fff}.nav-link.active{background:#235e62;color:#fff;font-weight:750}.nav-dot{width:6px;height:6px;flex:none;border-radius:50%;background:#76a7aa}.nav-link.active .nav-dot{background:#53d4c7}.subnav{display:grid;gap:2px;margin:3px 0 7px 12px;padding-left:16px;border-left:1px solid #4a8586}.subnav a{display:block;border-radius:5px;padding:7px 9px;color:#a8c6c7;font-size:11px;text-decoration:none}.subnav a:hover,.subnav a.selected{color:#fff;background:#1c4b50}.subnav a.selected{font-weight:750}.profile-chip{display:flex;align-items:center;gap:10px;background:#204f54;border-radius:7px;padding:10px 8px;margin-top:12px;color:#fff}.avatar{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;background:#8ac5c3;color:#12383d;font-size:10px;font-weight:700}.profile-chip strong{font-size:10px;display:block}.profile-chip small{text-transform:none;font-size:9px}.mobile-toggle{display:none}.redesign-sidebar :is(a,button):focus-visible{outline:2px solid #8de6df;outline-offset:2px}@media(max-width:800px){.redesign-sidebar{width:100%;min-height:auto;border-right:0;border-bottom:2px solid #20b7e8;padding:14px}.brand{margin:0}.mobile-toggle{display:block;margin-left:auto;border:1px solid #5c888a;border-radius:5px;background:transparent;color:#fff;padding:8px 12px;font-size:12px}.redesign-sidebar nav{display:none}.redesign-sidebar nav.open{display:block;margin-top:18px;max-height:60vh;overflow-y:auto}.profile-chip{display:none}}
</style>
