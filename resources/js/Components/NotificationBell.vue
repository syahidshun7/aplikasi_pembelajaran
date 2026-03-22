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
    if (props.variant === 'admin') {
        return 'nav-item relative inline-flex items-center gap-2 border border-slate-600 px-3 py-1 hover:border-cyan-400 hover:text-cyan-300';
    }

    return 'nav-action nav-action--profile relative';
});

const badgeClass = computed(() => {
    if (props.variant === 'admin') {
        return 'absolute -top-2 -right-2 min-w-[18px] rounded-full border border-cyan-200 bg-cyan-400 px-1 py-[1px] text-center text-[8px] font-bold text-black';
    }

    return 'absolute -top-2 -right-2 min-w-[18px] rounded-full border border-cyan-200 bg-cyan-300 px-1 py-[1px] text-center text-[8px] font-bold text-black';
});

const dropdownClass = computed(() => {
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
};

const closeDropdown = () => {
    dropdownOpen.value = false;
};

const refreshSummary = async () => {
    const response = await window.axios.get(route('notifications.feed'));
    applySummary(response.data?.summary);
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

onMounted(async () => {
    document.addEventListener('click', handleDocumentClick);

    if (!authUser.value?.id) {
        return;
    }

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

    if (echoInstance && authUser.value?.id) {
        echoInstance.leave(`App.Models.User.${authUser.value.id}`);
    }
});
</script>

<template>
    <div ref="bellRoot" class="relative">
        <button type="button" :class="buttonClass" @click="toggleDropdown">
            <i class="fi fi-rr-bell text-[10px] leading-none"></i>
            <span v-if="variant === 'admin'">NOTIFS</span>
            <span v-if="badgeLabel" :class="badgeClass">{{ badgeLabel }}</span>
        </button>

        <div v-if="dropdownOpen" :class="dropdownClass">
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
