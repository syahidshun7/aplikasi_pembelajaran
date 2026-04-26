<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from '@/Utils/Alert';

const props = defineProps({
    groups: Object,
    filters: Object,
});

const form = useForm({
    search: props.filters?.search || '',
});

const joinForm = useForm({
    study_group_uuid: '',
});

const leaveForm = useForm({});
const activeJoinUuid = ref('');
const activeLeaveUuid = ref('');
const page = usePage();
const isStaffPlayMode = computed(() => Boolean(page.props?.auth?.user?.staff_play_mode));
const normalizedUserRole = computed(() => String(page.props?.auth?.user?.role || '').trim().toLowerCase());
const isMentor = computed(() => normalizedUserRole.value === 'mentor');
const canManageMembership = computed(() => !isStaffPlayMode.value || isMentor.value);

const groupItems = computed(() => props.groups?.data || []);
const paginationLinks = computed(() => props.groups?.links || []);

const applySearch = () => {
    router.get(route('groups.index'), form.data(), {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetSearch = () => {
    form.search = '';
    applySearch();
};

const goToPage = (url) => {
    if (!url) return;
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const shortText = (text, max = 120) => {
    const value = String(text || '').replace(/\s+/g, ' ').trim();
    if (value === '') return '-';
    if (value.length <= max) return value;
    return `${value.slice(0, max)}...`;
};

const memberBadgeClass = (group) => {
    if (group.is_member) return 'text-emerald-400 border-emerald-900 bg-emerald-900/20';
    if (group.join_request_status === 'pending') return 'text-yellow-300 border-yellow-900 bg-yellow-900/20';
    return 'text-cyan-400 border-cyan-900 bg-cyan-900/20';
};

const memberBadgeText = (group) => {
    if (group.is_member) return 'Member';
    if (group.join_request_status === 'pending') return 'Pending_Request';
    return 'Open_Access';
};

const requestAccess = (group) => {
    if (joinForm.processing || leaveForm.processing) return;
    if (!canManageMembership.value) {
        toast.error('STAFF_PLAY_MODE', 'Admin/super admin tidak bisa join kelas student di mode preview.');
        return;
    }
    joinForm.study_group_uuid = group.uuid;
    activeJoinUuid.value = group.uuid;

    joinForm.post(route('groups.join'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('REQUEST_SENT', 'Join request sent. Waiting for admin approval.');
        },
        onError: (errors) => {
            const firstMessage = Object.values(errors || {})[0] || 'Unable to send join request.';
            toast.error('REQUEST_FAILED', String(firstMessage));
        },
        onFinish: () => {
            activeJoinUuid.value = '';
            joinForm.reset('study_group_uuid');
        },
    });
};

const leaveGroup = (group) => {
    if (joinForm.processing || leaveForm.processing) return;
    if (!canManageMembership.value) {
        toast.error('STAFF_PLAY_MODE', 'Membership kelas student dimatikan untuk admin/super admin di mode preview.');
        return;
    }

    toast.confirm('LEAVE_PARTY?', `Leave ${group.name}?`).then((result) => {
        if (!result.isConfirmed) return;

        activeLeaveUuid.value = group.uuid;
        leaveForm.post(route('groups.leave', group.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('LEFT_PARTY', 'You left the party.');
            },
            onError: (errors) => {
                const firstMessage = Object.values(errors || {})[0] || 'Unable to leave this party.';
                toast.error('LEAVE_FAILED', String(firstMessage));
            },
            onFinish: () => {
                activeLeaveUuid.value = '';
            },
        });
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="PARTY_GUILD_REGISTRY" />

        <div class="p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
            <div class="max-w-7xl mx-auto space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b-4 border-emerald-900 pb-4">
                    <h1 class="text-base sm:text-lg md:text-xl uppercase tracking-widest">Party_Guild_Registry</h1>
                    <Link
                        :href="route('lobby')"
                        class="inline-flex items-center justify-center px-3 py-2 border-2 border-slate-700 bg-slate-900/40 text-slate-300 hover:text-white hover:border-emerald-400 uppercase text-[9px] sm:text-[10px] whitespace-nowrap"
                    >
                        [Back_to_Home]
                    </Link>
                </div>

                <div class="rpg-panel border-slate-700 flex flex-col min-h-[540px]">
                    <div
                        v-if="isStaffPlayMode && !isMentor"
                        class="mb-4 border border-cyan-500/50 bg-cyan-500/10 p-3 text-[9px] uppercase leading-relaxed text-cyan-100"
                    >
                        Staff play mode aktif. Kamu tetap bisa melihat daftar kelas, tetapi admin/super admin tidak bisa join atau leave kelas student.
                    </div>
                    <div
                        v-else-if="isStaffPlayMode && isMentor"
                        class="mb-4 border border-cyan-500/50 bg-cyan-500/10 p-3 text-[9px] uppercase leading-relaxed text-cyan-100"
                    >
                        Mentor play mode: kamu bisa join/leave kelas sebagai observer. Membership mentor tidak dihitung slot pemain atau absensi event.
                    </div>
                    <form @submit.prevent="applySearch" class="mb-4 flex flex-col md:flex-row gap-2">
                        <input
                            v-model="form.search"
                            type="text"
                            placeholder="SEARCH PARTY / UUID / DESCRIPTION"
                            class="flex-1 bg-black border-2 border-slate-700 p-2 text-cyan-400 uppercase outline-none"
                        />
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                class="px-4 py-2 border-2 border-cyan-400 text-cyan-400 hover:bg-cyan-400 hover:text-black uppercase"
                            >
                                Search
                            </button>
                            <button
                                type="button"
                                @click="resetSearch"
                                class="px-4 py-2 border-2 border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white uppercase"
                            >
                                Reset
                            </button>
                        </div>
                    </form>

                    <div class="md:hidden space-y-3 flex-1">
                        <div v-for="group in groupItems" :key="`m-${group.uuid}`" class="p-3 bg-black/40 border border-slate-800">
                            <p class="text-white uppercase text-[11px] break-words">{{ group.name }}</p>
                            <div class="mt-2 flex flex-wrap gap-1">
                                <span class="px-2 py-1 border text-[8px] uppercase" :class="memberBadgeClass(group)">
                                    {{ memberBadgeText(group) }}
                                </span>
                                <span class="px-2 py-1 border text-[8px] uppercase border-slate-700 text-slate-300">
                                    {{ group.users_count || 0 }}/{{ group.max_members || 0 }} Members
                                </span>
                                <span class="px-2 py-1 border text-[8px] uppercase border-yellow-900 bg-yellow-900/20 text-yellow-300">
                                    Pending: {{ group.pending_requests_count || 0 }}
                                </span>
                            </div>
                            <p class="mt-2 text-slate-400 font-sans text-[11px]" :title="group.description || ''">
                                {{ shortText(group.description, 130) }}
                            </p>
                            <p class="mt-2 text-slate-500 text-[8px] uppercase">
                                UUID: {{ group.uuid }}
                            </p>
                            <div class="mt-3">
                                <button
                                    v-if="group.is_member"
                                    type="button"
                                    class="inline-block px-3 py-1 border border-red-700 text-red-400 hover:bg-red-600 hover:text-white uppercase text-[8px] disabled:opacity-50"
                                    :disabled="leaveForm.processing || !canManageMembership"
                                    @click="leaveGroup(group)"
                                >
                                    {{ activeLeaveUuid === group.uuid && leaveForm.processing ? 'Leaving...' : 'Leave_Party' }}
                                </button>
                                <button
                                    v-else-if="group.join_request_status === 'pending'"
                                    type="button"
                                    disabled
                                    class="inline-block px-3 py-1 border border-slate-700 text-slate-400 uppercase text-[8px] cursor-not-allowed"
                                >
                                    Request_Pending
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="inline-block px-3 py-1 border border-emerald-700 text-emerald-400 hover:bg-emerald-500 hover:text-black uppercase text-[8px] disabled:opacity-50"
                                    :disabled="joinForm.processing || !canManageMembership"
                                    @click="requestAccess(group)"
                                >
                                    {{ activeJoinUuid === group.uuid && joinForm.processing ? 'Sending...' : 'Request_Access' }}
                                </button>
                            </div>
                        </div>

                        <div v-if="groupItems.length === 0" class="py-12 text-center text-slate-500 uppercase">
                            No_Party_Available
                        </div>
                    </div>

                    <div class="hidden md:block overflow-x-auto flex-1">
                        <table class="w-full min-w-[1080px] text-left">
                            <thead class="border-b border-slate-700 text-slate-500 text-[8px] uppercase">
                                <tr>
                                    <th class="py-3 px-2">Party</th>
                                    <th class="py-3 px-2">Description</th>
                                    <th class="py-3 px-2">Members</th>
                                    <th class="py-3 px-2">Pending</th>
                                    <th class="py-3 px-2">Status</th>
                                    <th class="py-3 px-2 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="group in groupItems" :key="group.uuid" class="border-b border-slate-800 hover:bg-slate-900/40">
                                    <td class="py-3 px-2">
                                        <p class="text-white uppercase break-words">{{ group.name }}</p>
                                        <p class="mt-2 text-slate-500 text-[8px] uppercase break-all">ID: {{ group.uuid }}</p>
                                    </td>
                                    <td class="py-3 px-2 text-slate-400 font-sans max-w-[420px]" :title="group.description || ''">
                                        {{ shortText(group.description, 180) }}
                                    </td>
                                    <td class="py-3 px-2 text-yellow-300">
                                        {{ group.users_count || 0 }}/{{ group.max_members || 0 }}
                                    </td>
                                    <td class="py-3 px-2 text-yellow-300">
                                        {{ group.pending_requests_count || 0 }}
                                    </td>
                                    <td class="py-3 px-2">
                                        <span class="px-2 py-1 border text-[8px] uppercase" :class="memberBadgeClass(group)">
                                            {{ memberBadgeText(group) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-2 text-right">
                                        <button
                                            v-if="group.is_member"
                                            type="button"
                                            class="inline-block px-3 py-1 border border-red-700 text-red-400 hover:bg-red-600 hover:text-white uppercase text-[8px] disabled:opacity-50"
                                            :disabled="leaveForm.processing || !canManageMembership"
                                            @click="leaveGroup(group)"
                                        >
                                            {{ activeLeaveUuid === group.uuid && leaveForm.processing ? 'Leaving...' : 'Leave_Party' }}
                                        </button>
                                        <button
                                            v-else-if="group.join_request_status === 'pending'"
                                            type="button"
                                            disabled
                                            class="inline-block px-3 py-1 border border-slate-700 text-slate-400 uppercase text-[8px] cursor-not-allowed"
                                        >
                                            Request_Pending
                                        </button>
                                        <button
                                            v-else
                                            type="button"
                                            class="inline-block px-3 py-1 border border-emerald-700 text-emerald-400 hover:bg-emerald-500 hover:text-black uppercase text-[8px] disabled:opacity-50"
                                            :disabled="joinForm.processing || !canManageMembership"
                                            @click="requestAccess(group)"
                                        >
                                            {{ activeJoinUuid === group.uuid && joinForm.processing ? 'Sending...' : 'Request_Access' }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="groupItems.length === 0">
                                    <td colspan="6" class="py-8 text-center text-slate-500 uppercase">No_Party_Available</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2 pt-4 border-t border-slate-800">
                        <button
                            v-for="(link, idx) in paginationLinks"
                            :key="`${idx}-${link.label}`"
                            @click="goToPage(link.url)"
                            :disabled="!link.url"
                            class="px-3 py-1 border text-[8px] uppercase transition-all"
                            :class="[
                                link.active
                                    ? 'border-emerald-400 text-emerald-300 bg-emerald-900/20'
                                    : 'border-slate-700 text-slate-300 hover:bg-slate-700 hover:text-white',
                                !link.url ? 'opacity-40 cursor-not-allowed' : ''
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.rpg-panel {
    background-color: #1a1c2c;
    border-width: 4px;
    padding: 1rem;
    box-shadow: 8px 8px 0 0 rgba(0, 0, 0, 0.5);
}
</style>
