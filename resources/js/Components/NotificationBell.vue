<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { initEcho } from '@/lib/echo';
import { resolveNotificationActionUrl } from '@/Utils/notificationAction';

const props = defineProps({
    variant: {
        type: String,
        default: 'user',
    },
});

const page = usePage();
const authUser = computed(() => page.props?.auth?.user || null);
const dropdownOpen = ref(false);
const bellRoot = ref(null);
const items = ref([]);
const unreadCount = ref(0);
let channel = null;
let echoInstance = null;
let pollTimer = null;
let refreshInFlight = null;

const applySummary = (summary) => {
    items.value = Array.isArray(summary?.items) ? summary.items : [];
    unreadCount.value = Number(summary?.unread_count || 0);
};

watch(
    () => page.props?.notificationCenter,
    (summary) => applySummary(summary),
    { immediate: true, deep: true },
);

const formatTime = (value) => {
    if (!value) return '';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const badgeLabel = computed(() => {
    if (unreadCount.value <= 0) return '';
    return unreadCount.value > 99 ? '99+' : String(unreadCount.value);
});

const buttonClass = computed(() => {
    if (props.variant === 'redesign') return 'redesign-bell-button';
    if (props.variant === 'admin') {
        return 'nav-item relative inline-flex items-center gap-2 border border-slate-600 px-3 py-1 hover:border-cyan-400 hover:text-cyan-300';
    }

    return 'nav-action nav-action--notifications relative z-20';
});

const badgeClass = computed(() => {
    if (props.variant === 'redesign') return 'redesign-bell-badge';
    if (props.variant === 'admin') {
        return 'absolute -top-2 -right-2 min-w-[18px] rounded-full border border-cyan-200 bg-cyan-400 px-1 py-[1px] text-center text-[8px] font-bold text-black';
    }

    return 'pointer-events-none absolute left-1/2 -top-2.5 z-20 min-w-[18px] translate-x-[6px] rounded-full border border-orange-200 bg-orange-300 px-1 py-[1px] text-center text-[8px] font-bold text-black shadow-[0_0_12px_rgba(249,115,22,0.45)]';
});

const dropdownClass = computed(() => {
    if (props.variant === 'redesign') return 'redesign-bell-dropdown';
    return props.variant === 'admin'
        ? 'absolute right-0 mt-2 w-[min(92vw,360px)] border-2 border-slate-700 bg-[#0f101a] shadow-xl z-[160] p-2'
        : 'absolute right-0 mt-2 w-[min(92vw,360px)] border-2 border-[#3d415f] bg-[#10131c]/95 shadow-2xl z-[160] p-2';
});

const accentClass = (accent) => {
    return {
        amber: 'border-l-amber-400',
        emerald: 'border-l-emerald-400',
        fuchsia: 'border-l-fuchsia-400',
        cyan: 'border-l-cyan-400',
    }[accent] || 'border-l-cyan-400';
};

const toggleDropdown = () => {
    dropdownOpen.value = !dropdownOpen.value;
    if (dropdownOpen.value) refreshSummary().catch(() => {});
};

const closeDropdown = () => {
    dropdownOpen.value = false;
};

const refreshSummary = () => {
    if (refreshInFlight) return refreshInFlight;
    refreshInFlight = window.axios.get(route('notifications.feed'))
        .then((response) => applySummary(response.data?.summary))
        .finally(() => { refreshInFlight = null; });
    return refreshInFlight;
};

const refreshWhenVisible = () => {
    if (authUser.value?.id && !document.hidden) refreshSummary().catch(() => {});
};

const markAsRead = async (notificationId) => {
    const response = await window.axios.post(route('notifications.read', notificationId));
    applySummary(response.data?.summary);
};

const markAllAsRead = async () => {
    const response = await window.axios.post(route('notifications.read-all'));
    applySummary(response.data?.summary);
};

const resolvedActionUrl = (notification) => {
    const currentRole = String(authUser.value?.role || '').toLowerCase();

    return resolveNotificationActionUrl(notification, {
        isStaff: ['super_admin', 'admin', 'mentor'].includes(currentRole),
        isAdmin: ['super_admin', 'admin'].includes(currentRole),
        preferRedesign: props.variant === 'redesign',
        routeFn: route,
        currentUrl: page.url,
    });
};

const openNotification = async (notification) => {
    if (notification?.id && !notification?.read_at && !notification?.is_ephemeral) {
        try {
            await markAsRead(notification.id);
        } catch (error) {
            // Tetap lanjutkan navigasi agar aksi user tidak terasa "macet" saat notif realtime belum persisted.
            console.warn('Failed to mark notification as read:', error);
        }
    }

    closeDropdown();

    router.visit(resolvedActionUrl(notification));
};

const handleDocumentClick = (event) => {
    if (!bellRoot.value?.contains(event.target)) {
        closeDropdown();
    }
};

const handleRealtimeNotification = (payload = {}) => {
    const source = payload && typeof payload.data === 'object'
        ? {
            ...payload.data,
            id: payload.id ?? payload.data.id,
            created_at: payload.created_at ?? payload.data.created_at,
            read_at: payload.read_at ?? payload.data.read_at,
        }
        : payload;

    const notificationId = String(source.id || '').trim();
    const isEphemeral = notificationId === '';

    const normalized = {
        id: notificationId || `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        type: String(source.type || 'general'),
        category: String(source.category || 'general'),
        event: String(source.event || 'created'),
        title: String(source.title || 'Notification'),
        message: String(source.message || ''),
        action_url: String(source.action_url || route('notifications.index')),
        action_label: String(source.action_label || 'Buka'),
        icon: String(source.icon || 'fi-rr-bell'),
        accent: String(source.accent || 'cyan'),
        meta: source.meta || {},
        resource: source.resource || {},
        read_at: source.read_at ? String(source.read_at) : null,
        created_at: source.created_at ? String(source.created_at) : new Date().toISOString(),
        is_ephemeral: isEphemeral,
    };

    const alreadyExists = items.value.some((item) => item.id === normalized.id);
    items.value = [normalized, ...items.value.filter((item) => item.id !== normalized.id)].slice(0, 6);
    if (!normalized.read_at && !alreadyExists) {
        unreadCount.value += 1;
    }
};

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);

    if (!authUser.value?.id) {
        return;
    }

    document.addEventListener('visibilitychange', refreshWhenVisible);
    pollTimer = window.setInterval(refreshWhenVisible, 20_000);
    refreshWhenVisible();

    echoInstance = initEcho();
    if (!echoInstance) {
        return;
    }

    channel = echoInstance.private(`App.Models.User.${authUser.value.id}`);
    channel.notification((payload) => {
        handleRealtimeNotification(payload);
    });
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('visibilitychange', refreshWhenVisible);
    if (pollTimer) window.clearInterval(pollTimer);

    if (echoInstance && authUser.value?.id) {
        echoInstance.leave(`App.Models.User.${authUser.value.id}`);
    }
});
</script>

<template>
    <div
        ref="bellRoot"
        :class="variant === 'redesign' ? 'redesign-bell' : variant === 'admin' ? 'relative' : 'relative z-20'"
    >
        <button type="button" :class="buttonClass" @click="toggleDropdown">
            <i class="fi fi-rr-bell text-[10px] leading-none"></i>
            <span v-if="variant === 'admin'">NOTIFS</span><span v-if="variant === 'redesign'">Notifikasi</span>
            <span v-if="badgeLabel" :class="badgeClass">{{ badgeLabel }}</span>
        </button>

        <div v-if="dropdownOpen && variant === 'redesign'" :class="dropdownClass">
            <div class="redesign-bell-head"><strong>Notifikasi</strong><button v-if="unreadCount" type="button" @click="markAllAsRead">Tandai semua dibaca</button></div>
            <div class="redesign-bell-list"><button v-for="notification in items" :key="notification.id" type="button" class="redesign-bell-item" :class="{ unread: !notification.read_at }" @click="openNotification(notification)"><strong>{{ notification.title }}</strong><span>{{ notification.message }}</span><small>{{ formatTime(notification.created_at) }}</small></button><p v-if="!items.length" class="redesign-bell-empty">Belum ada notifikasi.</p></div>
            <Link class="redesign-bell-history" :href="route('redesign.notifications')" @click="closeDropdown">Lihat semua notifikasi →</Link>
        </div>
        <div v-else-if="dropdownOpen" :class="dropdownClass">
            <div class="mb-2 flex items-center justify-between gap-3 px-1">
                <div class="text-[9px] uppercase tracking-wide text-cyan-200">
                    Notifications
                </div>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    class="text-[8px] uppercase text-amber-300 hover:text-amber-200"
                    @click="markAllAsRead"
                >
                    Mark all read
                </button>
            </div>

            <div class="max-h-[320px] space-y-2 overflow-y-auto pr-1">
                <button
                    v-for="notification in items"
                    :key="notification.id"
                    type="button"
                    class="w-full border-l-4 bg-black/20 px-3 py-3 text-left transition-colors hover:bg-white/5"
                    :class="accentClass(notification.accent)"
                    @click="openNotification(notification)"
                >
                    <div class="flex items-start gap-3">
                        <i :class="['fi', notification.icon || 'fi-rr-bell']" class="mt-[2px] text-[12px] text-cyan-300"></i>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-[9px] uppercase text-slate-100">
                                    {{ notification.title }}
                                </p>
                                <span v-if="!notification.read_at" class="shrink-0 text-[8px] uppercase text-amber-300">
                                    New
                                </span>
                            </div>
                            <p class="mt-1 text-[9px] leading-relaxed text-slate-400">
                                {{ notification.message }}
                            </p>
                            <p class="mt-2 text-[8px] uppercase tracking-wide text-slate-500">
                                {{ formatTime(notification.created_at) }}
                            </p>
                        </div>
                    </div>
                </button>

                <div v-if="items.length === 0" class="border border-dashed border-slate-700 px-3 py-6 text-center text-[9px] uppercase text-slate-500">
                    Belum ada notifikasi.
                </div>
            </div>

            <div class="mt-3 border-t border-slate-700 pt-2">
                <div class="flex items-center justify-between gap-3">
                    <Link class="text-[8px] uppercase text-cyan-300 hover:text-cyan-200" :href="route('notifications.index')" @click="closeDropdown">
                        Buka riwayat
                    </Link>
                    <button type="button" class="text-[8px] uppercase text-slate-400 hover:text-slate-300" @click="refreshSummary">
                        Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
<style scoped>.redesign-bell{position:relative;z-index:120}.redesign-bell-button{position:relative;display:inline-flex;align-items:center;gap:8px;background:#fff;border:1px solid #dce6e8;border-radius:8px;color:#19333d;padding:9px 12px;font-family:Inter,ui-sans-serif,system-ui,sans-serif;font-size:12px;font-weight:700;cursor:pointer}.redesign-bell-badge{position:absolute;top:-7px;right:-7px;min-width:18px;height:18px;border-radius:99px;background:#00857c;color:white;display:grid;place-items:center;padding:0 4px;font-size:10px}.redesign-bell-dropdown{position:absolute;right:0;top:calc(100% + 9px);width:min(360px,calc(100vw - 28px));border:1px solid #dce6e8;border-radius:12px;background:#fff;box-shadow:0 16px 45px #10292e24;color:#19333d;font-family:Inter,ui-sans-serif,system-ui,sans-serif;overflow:hidden}.redesign-bell-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:16px;border-bottom:1px solid #e5edef;font-size:14px}.redesign-bell-head button{border:0;background:none;color:#00857c;font-size:11px;cursor:pointer}.redesign-bell-list{max-height:350px;overflow-y:auto}.redesign-bell-item{display:grid;gap:5px;width:100%;padding:13px 16px;text-align:left;border:0;border-bottom:1px solid #edf2f3;background:#fff;color:#19333d;cursor:pointer}.redesign-bell-item.unread{background:#f1faf8}.redesign-bell-item:hover{background:#e5f5f1}.redesign-bell-item strong{font-size:12px}.redesign-bell-item span,.redesign-bell-item small{font-size:11px;color:#70858c;line-height:1.4}.redesign-bell-empty{padding:24px;text-align:center;color:#70858c;font-size:12px}.redesign-bell-history{display:block;padding:13px 16px;color:#00857c;font-size:12px;font-weight:700;text-decoration:none;border-top:1px solid #e5edef}</style>
