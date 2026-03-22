<script setup>
import AdminNavbar from '@/Components/AdminNavbar.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { resolveNotificationActionUrl } from '@/Utils/notificationAction';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    notifications: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const authUser = computed(() => page.props?.auth?.user || null);
const isStaff = computed(() => ['super_admin', 'admin', 'mentor'].includes(String(authUser.value?.role || '').toLowerCase()));
const notificationItems = computed(() => props.notifications?.data || []);
const notificationMeta = computed(() => props.notifications?.meta || {});
const notificationLinks = computed(() => props.notifications?.links || {});
const unreadCount = computed(() => notificationItems.value.filter((item) => !item.read_at).length);
const readCount = computed(() => notificationItems.value.length - unreadCount.value);
const totalCount = computed(() => Number(notificationMeta.value?.total || notificationItems.value.length || 0));
const canMarkAllRead = computed(() => unreadCount.value > 0);

const categoryLabels = {
    assignment: 'Assignment',
    grade: 'Grade',
    chat: 'Chat',
    event: 'Event',
    announcement: 'Announcement',
    general: 'General',
};

const categorySummary = computed(() => {
    const counts = notificationItems.value.reduce((summary, item) => {
        const category = String(item?.category || 'general');
        summary[category] = (summary[category] || 0) + 1;
        return summary;
    }, {});

    return Object.entries(counts)
        .map(([category, count]) => ({
            category,
            count,
            label: categoryLabels[category] || category,
        }))
        .sort((left, right) => right.count - left.count);
});

const panelAccentClass = (accent) => {
    return {
        amber: 'border-amber-400/60 bg-amber-500/10',
        emerald: 'border-emerald-400/60 bg-emerald-500/10',
        fuchsia: 'border-fuchsia-400/60 bg-fuchsia-500/10',
        cyan: 'border-cyan-400/60 bg-cyan-500/10',
    }[accent] || 'border-cyan-400/60 bg-cyan-500/10';
};

const adminRowAccentClass = (accent) => {
    return {
        amber: 'border-l-amber-400',
        emerald: 'border-l-emerald-400',
        fuchsia: 'border-l-fuchsia-400',
        cyan: 'border-l-cyan-400',
    }[accent] || 'border-l-cyan-400';
};

const actionToneClass = (accent) => {
    return {
        amber: 'border-amber-700 text-amber-300 hover:bg-amber-500 hover:text-black',
        emerald: 'border-emerald-700 text-emerald-300 hover:bg-emerald-500 hover:text-black',
        fuchsia: 'border-fuchsia-700 text-fuchsia-300 hover:bg-fuchsia-500 hover:text-black',
        cyan: 'border-cyan-700 text-cyan-300 hover:bg-cyan-400 hover:text-black',
    }[accent] || 'border-cyan-700 text-cyan-300 hover:bg-cyan-400 hover:text-black';
};

const formatTime = (value) => {
    if (!value) return '';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    return date.toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const categoryText = (notification) => {
    return categoryLabels[String(notification?.category || 'general')] || String(notification?.category || 'General');
};

const resourceReference = (notification) => {
    return notification?.resource?.title
        || notification?.resource?.name
        || notification?.meta?.assignment_title
        || notification?.meta?.quest_title
        || notification?.meta?.chat_title
        || notification?.meta?.announcement_title
        || '';
};

const resolvedActionUrl = (notification) => {
    return resolveNotificationActionUrl(notification, {
        isStaff: isStaff.value,
        routeFn: route,
        currentUrl: page.url,
    });
};

const markAsRead = async (id) => {
    await window.axios.post(route('notifications.read', id));
    router.reload({ only: ['notifications', 'notificationCenter'] });
};

const markAllAsRead = async () => {
    if (!canMarkAllRead.value) return;

    await window.axios.post(route('notifications.read-all'));
    router.reload({ only: ['notifications', 'notificationCenter'] });
};

const goToPage = (url) => {
    if (!url) return;
    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Head :title="isStaff ? 'ADMIN_NOTIFICATIONS' : 'Notifications'" />

    <AuthenticatedLayout v-if="!isStaff">
        <section class="space-y-6">
            <div class="flex flex-col gap-3 border-b border-slate-800 pb-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-[8px] uppercase tracking-[0.28em] text-cyan-400/80">Notification Center</p>
                    <h1 class="mt-2 text-[12px] uppercase text-cyan-100">Riwayat Notifikasi</h1>
                    <p class="mt-2 text-[8px] uppercase tracking-wide text-slate-500">
                        {{ unreadCount }} unread | {{ totalCount }} total
                    </p>
                </div>

                <button
                    type="button"
                    class="nav-action nav-action--profile"
                    :disabled="!canMarkAllRead"
                    :class="{ 'opacity-50 cursor-not-allowed': !canMarkAllRead }"
                    @click="markAllAsRead"
                >
                    Mark All Read
                </button>
            </div>

            <div class="space-y-2">
                <article
                    v-for="notification in notificationItems"
                    :key="notification.id"
                    class="border p-3 transition-colors hover:bg-white/[0.03]"
                    :class="panelAccentClass(notification.accent)"
                >
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <i :class="['fi', notification.icon || 'fi-rr-bell']" class="text-[11px] text-cyan-300"></i>
                                <span class="border border-white/10 bg-black/20 px-2 py-[3px] text-[7px] uppercase text-slate-300">
                                    {{ categoryText(notification) }}
                                </span>
                                <span v-if="!notification.read_at" class="text-[7px] uppercase text-amber-300">Unread</span>
                                <span class="text-[7px] uppercase text-slate-500">{{ formatTime(notification.created_at) }}</span>
                            </div>

                            <h2 class="mt-2 text-[9px] uppercase text-slate-100">{{ notification.title }}</h2>
                            <p class="mt-2 text-[9px] leading-relaxed text-slate-300">{{ notification.message }}</p>
                            <p v-if="resourceReference(notification)" class="mt-2 text-[7px] uppercase text-slate-500">
                                Ref: {{ resourceReference(notification) }}
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-2 md:justify-end">
                            <button
                                v-if="!notification.read_at"
                                type="button"
                                class="border border-amber-700 px-2.5 py-1.5 text-[7px] uppercase text-amber-300 transition-colors hover:bg-amber-500 hover:text-black"
                                @click="markAsRead(notification.id)"
                            >
                                Mark Read
                            </button>
                            <Link
                                :href="resolvedActionUrl(notification)"
                                class="border border-cyan-700 px-2.5 py-1.5 text-[7px] uppercase text-cyan-300 transition-colors hover:bg-cyan-400 hover:text-black"
                            >
                                {{ notification.action_label || 'Buka' }}
                            </Link>
                        </div>
                    </div>
                </article>

                <div
                    v-if="notificationItems.length === 0"
                    class="border-2 border-dashed border-slate-700 bg-slate-900/40 p-10 text-center text-[10px] uppercase text-slate-500"
                >
                    Belum ada notifikasi untuk akun ini.
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-800 pt-4 md:flex-row md:items-center md:justify-between">
                <p class="text-[8px] uppercase text-slate-500">
                    Page {{ notificationMeta.current_page || 1 }} / {{ notificationMeta.last_page || 1 }}
                    | Total {{ totalCount }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="border border-slate-700 px-3 py-2 text-[8px] uppercase text-slate-300 transition-colors hover:bg-slate-700 hover:text-white"
                        :disabled="!notificationLinks.prev"
                        :class="{ 'cursor-not-allowed opacity-40': !notificationLinks.prev }"
                        @click="goToPage(notificationLinks.prev)"
                    >
                        Prev
                    </button>
                    <button
                        type="button"
                        class="border border-slate-700 px-3 py-2 text-[8px] uppercase text-slate-300 transition-colors hover:bg-slate-700 hover:text-white"
                        :disabled="!notificationLinks.next"
                        :class="{ 'cursor-not-allowed opacity-40': !notificationLinks.next }"
                        @click="goToPage(notificationLinks.next)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>

    <div v-else class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px] relative">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="flex flex-col gap-3 border-b-4 border-cyan-900 pb-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[8px] uppercase tracking-[0.3em] text-cyan-400/70">Admin Feed Console</p>
                    <h1 class="mt-3 text-base uppercase tracking-widest text-cyan-200 sm:text-xl animate-pulse">
                        Notification_Command_Board
                    </h1>
                </div>

                <Link
                    :href="route('quests.index')"
                    class="inline-flex items-center justify-center border border-slate-600 bg-slate-900/40 px-3 py-2 text-[9px] uppercase text-slate-300 transition-colors hover:text-white sm:text-[10px]"
                >
                    [Back_to_Quest_Menu]
                </Link>
            </div>

            <div class="grid grid-cols-12 gap-8">
                <div class="col-span-12 lg:col-span-4 space-y-6">
                    <section class="rpg-panel border-indigo-500/50">
                        <h2 class="mb-6 uppercase tracking-tighter text-indigo-300">>> FEED_STATUS</h2>

                        <div class="grid gap-3">
                            <div class="border border-cyan-500/40 bg-cyan-500/10 p-4">
                                <p class="text-[8px] uppercase text-cyan-200">UNREAD_ALERTS</p>
                                <p class="mt-3 text-[26px] font-sans font-black text-white">{{ unreadCount }}</p>
                            </div>
                            <div class="border border-emerald-500/40 bg-emerald-500/10 p-4">
                                <p class="text-[8px] uppercase text-emerald-200">READ_LOGS</p>
                                <p class="mt-3 text-[26px] font-sans font-black text-white">{{ readCount }}</p>
                            </div>
                            <div class="border border-amber-500/40 bg-amber-500/10 p-4">
                                <p class="text-[8px] uppercase text-amber-200">TOTAL_RECORDS</p>
                                <p class="mt-3 text-[26px] font-sans font-black text-white">{{ totalCount }}</p>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="mt-5 w-full border-2 border-cyan-400 py-3 uppercase transition-colors"
                            :class="canMarkAllRead ? 'text-cyan-400 hover:bg-cyan-400 hover:text-black' : 'cursor-not-allowed border-slate-700 text-slate-600'"
                            :disabled="!canMarkAllRead"
                            @click="markAllAsRead"
                        >
                            {{ canMarkAllRead ? 'MARK_ALL_AS_READ' : 'ALL_CLEAR' }}
                        </button>
                    </section>

                    <section class="rpg-panel border-emerald-500/50">
                        <h2 class="mb-6 uppercase tracking-tighter text-emerald-300">>> CATEGORY_BREAKDOWN</h2>

                        <div v-if="categorySummary.length > 0" class="space-y-3">
                            <div
                                v-for="entry in categorySummary"
                                :key="entry.category"
                                class="border-l-4 border-emerald-500 bg-slate-900/60 p-3"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-[8px] uppercase text-emerald-200">{{ entry.label }}</span>
                                    <span class="text-[16px] font-sans font-black text-white">{{ entry.count }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-else class="border border-dashed border-slate-700 p-6 text-center text-[8px] uppercase text-slate-500">
                            FEED_EMPTY
                        </div>
                    </section>
                </div>

                <div class="col-span-12 lg:col-span-8">
                    <section class="rpg-panel border-slate-700 h-full">
                        <div class="mb-6 flex flex-col gap-3 border-b border-slate-700 pb-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-white uppercase tracking-tighter">>> LIVE_OPERATOR_TIMELINE</h2>
                                <p class="mt-2 text-[8px] uppercase text-slate-500">
                                    Submission, grade, chat, dan announcement untuk akun admin/mentor ini.
                                </p>
                            </div>
                            <p class="text-[8px] uppercase text-slate-500">
                                PAGE {{ notificationMeta.current_page || 1 }} / {{ notificationMeta.last_page || 1 }}
                            </p>
                        </div>

                        <div class="space-y-4 max-h-[620px] overflow-y-auto pr-2 custom-scroll">
                            <article
                                v-for="notification in notificationItems"
                                :key="notification.id"
                                class="border-l-4 bg-slate-900/50 p-4 transition-all hover:bg-slate-800/80"
                                :class="adminRowAccentClass(notification.accent)"
                            >
                                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2 text-[7px] uppercase tracking-tighter">
                                            <span class="text-slate-500">ID: {{ notification.id.slice(0, 8) }}</span>
                                            <span class="text-cyan-300">TYPE: {{ categoryText(notification) }}</span>
                                            <span v-if="!notification.read_at" class="text-amber-400">[UNREAD]</span>
                                            <span class="text-slate-600">{{ formatTime(notification.created_at) }}</span>
                                        </div>

                                        <div class="mt-2 flex items-start gap-3">
                                            <i :class="['fi', notification.icon || 'fi-rr-bell']" class="mt-0.5 text-[12px] text-cyan-300"></i>
                                            <div class="min-w-0 flex-1">
                                                <h3 class="text-[10px] uppercase text-white">{{ notification.title }}</h3>
                                                <p class="mt-3 text-[8px] leading-loose text-slate-300">
                                                    {{ notification.message }}
                                                </p>
                                                <p v-if="resourceReference(notification)" class="mt-3 text-[7px] uppercase text-emerald-300">
                                                    REF: {{ resourceReference(notification) }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex shrink-0 flex-wrap gap-2 md:w-[180px] md:justify-end">
                                        <button
                                            v-if="!notification.read_at"
                                            type="button"
                                            class="border px-3 py-2 text-[8px] uppercase transition-all border-amber-700 text-amber-300 hover:bg-amber-500 hover:text-black"
                                            @click="markAsRead(notification.id)"
                                        >
                                            [MARK_READ]
                                        </button>
                                        <Link
                                            :href="resolvedActionUrl(notification)"
                                            class="border px-3 py-2 text-[8px] uppercase transition-all"
                                            :class="actionToneClass(notification.accent)"
                                        >
                                            [{{ notification.action_label || 'Buka' }}]
                                        </Link>
                                    </div>
                                </div>
                            </article>

                            <div
                                v-if="notificationItems.length === 0"
                                class="flex min-h-[260px] items-center justify-center border-2 border-dashed border-slate-800 p-6 text-center"
                            >
                                <div>
                                    <p class="text-[12px] uppercase text-slate-600">NO_SIGNAL</p>
                                    <p class="mt-3 text-[8px] uppercase text-slate-500">
                                        Belum ada riwayat notifikasi untuk operator ini.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <p class="text-[8px] uppercase text-slate-500">
                                TOTAL {{ totalCount }} | RANGE {{ notificationMeta.from || 0 }}-{{ notificationMeta.to || 0 }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="border px-3 py-1 text-[8px] uppercase transition-all border-slate-700 text-slate-300 hover:bg-slate-700 hover:text-white"
                                    :disabled="!notificationLinks.prev"
                                    :class="{ 'cursor-not-allowed opacity-40': !notificationLinks.prev }"
                                    @click="goToPage(notificationLinks.prev)"
                                >
                                    PREV
                                </button>
                                <button
                                    type="button"
                                    class="border px-3 py-1 text-[8px] uppercase transition-all border-slate-700 text-slate-300 hover:bg-slate-700 hover:text-white"
                                    :disabled="!notificationLinks.next"
                                    :class="{ 'cursor-not-allowed opacity-40': !notificationLinks.next }"
                                    @click="goToPage(notificationLinks.next)"
                                >
                                    NEXT
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.rpg-panel {
    background-color: #1a1c2c;
    border-width: 4px;
    padding: 1rem;
    position: relative;
    box-shadow: 8px 8px 0px 0px rgba(0, 0, 0, 0.5);
}

.custom-scroll::-webkit-scrollbar {
    width: 6px;
}

.custom-scroll::-webkit-scrollbar-track {
    background: #0d1117;
}

.custom-scroll::-webkit-scrollbar-thumb {
    background: #334155;
    border-radius: 999px;
}

.custom-scroll::-webkit-scrollbar-thumb:hover {
    background: #4ed4d4;
}
</style>
