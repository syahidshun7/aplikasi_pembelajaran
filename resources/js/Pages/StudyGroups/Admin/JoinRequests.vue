<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminNavbar from '@/Components/AdminNavbar.vue';
import { swal } from '@/Utils/Alert';

const props = defineProps({
    group: Object,
    requests: {
        type: Array,
        default: () => [],
    },
    members: {
        type: Array,
        default: () => [],
    },
});

const approveRequest = (requestId) => {
    router.post(route('groups.requests.approve', { uuid: props.group.uuid, requestId }), {}, {
        preserveScroll: true,
    });
};

const rejectRequest = async (requestItem) => {
    const result = await swal.fire({
        title: 'REJECT_REQUEST',
        text: `Alasan penolakan untuk ${requestItem.user?.username || requestItem.user?.name || 'user'} (opsional).`,
        input: 'textarea',
        inputPlaceholder: 'Alasan reject (opsional)',
        inputClass: 'rpg-alert-textarea',
        inputAttributes: { maxlength: 500 },
        showCancelButton: true,
        confirmButtonText: 'REJECT',
        cancelButtonText: 'BATAL',
    });

    if (!result.isConfirmed) return;

    router.post(route('groups.requests.reject', {
        uuid: props.group.uuid,
        requestId: requestItem.id,
    }), {
        reason: String(result.value || '').trim() || null,
    }, {
        preserveScroll: true,
    });
};

const removeMember = async (member) => {
    const result = await swal.fire({
        title: 'REMOVE_MEMBER?',
        text: `Keluarkan ${member.username || member.name} dari Study Group ini?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'REMOVE',
        cancelButtonText: 'CANCEL',
    });

    if (!result.isConfirmed) return;

    router.delete(route('groups.members.remove', {
        uuid: props.group.uuid,
        userId: member.id,
    }), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`MEMBERSHIP | ${group.name}`" />

    <div class="min-h-screen bg-[#0d1117] p-4 font-['Press_Start_2P'] text-[10px] text-[#4ed4d4] md:p-8">
        <div class="mx-auto max-w-5xl space-y-6">
            <AdminNavbar />

            <header class="rpg-panel border-orange-500/50">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-[8px] uppercase text-orange-300">Class_Operations</p>
                        <h1 class="mt-3 text-base uppercase text-white md:text-xl">Membership_Management</h1>
                        <p class="mt-3 font-sans text-[13px] text-slate-400">{{ group.name }} / {{ requests.length }} pending / {{ members.length }} active</p>
                    </div>
                    <Link :href="route('groups.detail', group.uuid)" class="border border-slate-600 px-3 py-2 text-[8px] uppercase text-slate-300 hover:border-cyan-400 hover:text-white">
                        Back_To_Class
                    </Link>
                </div>
            </header>

            <section class="rpg-panel border-orange-500/50">
                <div class="mb-5 border-b border-slate-700 pb-4">
                    <h2 class="text-[11px] uppercase text-orange-300">Pending_Requests [{{ requests.length }}]</h2>
                </div>
                <div v-if="requests.length === 0" class="border border-slate-800 bg-black/30 p-6 text-center text-[8px] uppercase text-slate-500">
                    No_Pending_Request
                </div>
                <div v-else class="grid gap-4 md:grid-cols-2">
                    <article v-for="requestItem in requests" :key="requestItem.id" class="border border-slate-700 bg-black/35 p-4">
                        <p class="break-words text-[10px] uppercase text-white">{{ requestItem.user?.username || requestItem.user?.name }}</p>
                        <p class="mt-2 break-words font-sans text-[12px] text-slate-500">{{ requestItem.user?.email }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span class="border border-cyan-800 bg-cyan-900/20 px-2 py-1 text-[8px] uppercase text-cyan-200">User LVL {{ requestItem.user_level || 1 }}</span>
                            <span class="border border-yellow-800 bg-yellow-900/20 px-2 py-1 text-[8px] uppercase text-yellow-200">Need LVL {{ group.min_level || 1 }}</span>
                        </div>
                        <div class="mt-4 border border-cyan-900/60 bg-cyan-950/20 p-3">
                            <p class="text-[8px] uppercase text-cyan-300">Reason</p>
                            <p class="mt-2 break-words font-sans text-[12px] leading-relaxed text-slate-200">{{ requestItem.reason || '-' }}</p>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <button type="button" class="border border-emerald-500 px-3 py-2 text-[8px] uppercase text-emerald-400 hover:bg-emerald-500 hover:text-black" @click="approveRequest(requestItem.id)">Approve</button>
                            <button type="button" class="border border-red-500 px-3 py-2 text-[8px] uppercase text-red-400 hover:bg-red-600 hover:text-white" @click="rejectRequest(requestItem)">Reject</button>
                        </div>
                    </article>
                </div>
            </section>

            <section class="rpg-panel border-cyan-500/50">
                <div class="mb-5 border-b border-slate-700 pb-4">
                    <h2 class="text-[11px] uppercase text-cyan-300">Active_Members [{{ members.length }}]</h2>
                    <p class="mt-2 text-[7px] uppercase text-slate-500">Minimum Join Level: {{ group.min_level || 1 }}</p>
                </div>

                <div v-if="members.length === 0" class="border border-slate-800 bg-black/30 p-6 text-center text-[8px] uppercase text-slate-500">
                    No_Active_Member
                </div>
                <div v-else class="overflow-x-auto border border-slate-700 bg-black/25">
                    <table class="min-w-full border-collapse">
                        <thead>
                            <tr class="border-b-2 border-slate-700 bg-slate-950/70 text-left text-[8px] uppercase text-slate-400">
                                <th class="w-[64px] p-3 text-center">No</th>
                                <th class="min-w-[180px] p-3">Username</th>
                                <th class="min-w-[240px] p-3">Nama_Lengkap</th>
                                <th class="min-w-[300px] p-3">Email</th>
                                <th class="w-[130px] p-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(member, index) in members"
                                :key="member.id"
                                class="border-b border-slate-800/80 transition-colors last:border-b-0 hover:bg-cyan-500/5"
                            >
                                <td class="p-3 text-center text-[8px] text-slate-500">{{ index + 1 }}</td>
                                <td class="p-3 text-[9px] uppercase text-cyan-300">@{{ member.username || 'user' }}</td>
                                <td class="p-3 font-sans text-[13px] font-semibold text-white">{{ member.name }}</td>
                                <td class="p-3 font-sans text-[12px] text-slate-400">{{ member.email }}</td>
                                <td class="p-3 text-right">
                                    <button
                                        type="button"
                                        class="border border-red-600 px-3 py-2 text-[8px] uppercase text-red-400 hover:bg-red-600 hover:text-white"
                                        @click="removeMember(member)"
                                    >
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.rpg-panel { @apply border-4 bg-[#1a1c2c] p-5; box-shadow: 8px 8px 0 rgba(0, 0, 0, .5); }
</style>
