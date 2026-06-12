<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminNavbar from '@/Components/AdminNavbar.vue';
import { swal } from '@/Utils/Alert';

const props = defineProps({
    group: Object,
    members: Array,
    requests: Array,
    questCounts: Object,
});

const approveRequest = (requestId) => {
    router.post(route('groups.requests.approve', { uuid: props.group.uuid, requestId }), {}, {
        preserveScroll: true,
    });
};

const rejectRequest = async (requestItem) => {
    const result = await swal.fire({
        title: 'REJECT_REQUEST',
        text: `Kamu bisa isi alasan reject untuk ${requestItem.user?.username || requestItem.user?.name || 'user'} (opsional).`,
        input: 'textarea',
        inputPlaceholder: 'Alasan reject (opsional)',
        inputClass: 'rpg-alert-textarea',
        inputAttributes: {
            maxlength: 500,
        },
        showCancelButton: true,
        confirmButtonText: 'REJECT',
        cancelButtonText: 'BATAL',
    });

    if (!result.isConfirmed) {
        return;
    }

    router.post(route('groups.requests.reject', { uuid: props.group.uuid, requestId: requestItem.id }), {
        reason: String(result.value || '').trim() || null,
    }, {
        preserveScroll: true,
    });
};

const removeMember = (member) => {
    swal.fire({
        title: 'REMOVE_MEMBER?',
        text: `Keluarkan ${member.username || member.name} dari group ini?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'YES_REMOVE',
        cancelButtonText: 'CANCEL',
    }).then((result) => {
        if (!result.isConfirmed) return;
        router.delete(route('groups.members.remove', { uuid: props.group.uuid, userId: member.id }), {
            preserveScroll: true,
        });
    });
};
</script>

<template>
    <Head title="GROUP_DETAIL" />

    <div class="min-h-screen bg-[#0d1117] p-4 md:p-8 font-['Press_Start_2P'] text-[#4ed4d4] text-[10px]">
        <div class="max-w-7xl mx-auto space-y-8">
            <AdminNavbar />

            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-b-4 border-emerald-900 pb-4">
                <div>
                    <h1 class="text-base sm:text-xl uppercase tracking-widest">Group_Detail</h1>
                    <p class="text-[8px] text-slate-500 mt-2 uppercase">
                        {{ group.name }} | ID: {{ group.uuid?.substring(0, 8) }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <a :href="route('groups.export-recap', { uuid: group.uuid })" class="inline-flex items-center justify-center px-3 py-2 border border-emerald-600 bg-emerald-900/40 text-emerald-300 hover:text-white uppercase text-[9px] sm:text-[10px]">[↓ Download Rekap CSV]</a>
                    <Link :href="route('groups.manage')" class="inline-flex items-center justify-center px-3 py-2 border border-slate-600 bg-slate-900/40 text-slate-300 hover:text-white uppercase text-[9px] sm:text-[10px]">[Back]</Link>
                </div>
            </div>

            <!-- Quest Stats -->
            <div class="flex flex-wrap gap-4">
                <div class="border border-slate-700 bg-slate-900/50 px-4 py-3 text-center min-w-[100px]">
                    <p class="text-[7px] text-slate-400 uppercase mb-1">Total Quest</p>
                    <p class="text-lg text-white">{{ questCounts?.total ?? 0 }}</p>
                </div>
                <div class="border border-cyan-700 bg-cyan-900/20 px-4 py-3 text-center min-w-[100px]">
                    <p class="text-[7px] text-cyan-400 uppercase mb-1">Main Quest</p>
                    <p class="text-lg text-cyan-300">{{ questCounts?.main ?? 0 }}</p>
                </div>
                <div class="border border-purple-700 bg-purple-900/20 px-4 py-3 text-center min-w-[100px]">
                    <p class="text-[7px] text-purple-400 uppercase mb-1">Optional Quest</p>
                    <p class="text-lg text-purple-300">{{ questCounts?.optional ?? 0 }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <section class="rpg-panel border-orange-500/50">
                    <h2 class="text-orange-400 mb-4 uppercase">Pending_Join_Requests [{{ requests.length }}]</h2>

                    <div v-if="requests.length === 0" class="text-slate-500 uppercase text-[8px] py-4">
                        NO_PENDING_REQUEST
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="r in requests" :key="r.id" class="p-3 bg-black/40 border border-slate-800">
                            <p class="text-white uppercase">{{ r.user?.username || r.user?.name }}</p>
                            <p class="text-[8px] text-slate-500 mb-3">{{ r.user?.email }}</p>
                            <div class="mb-3 flex flex-wrap gap-2">
                                <span class="px-2 py-1 border border-cyan-800 bg-cyan-900/20 text-cyan-200 text-[8px] uppercase">User LVL {{ r.user_level || 1 }}</span>
                                <span class="px-2 py-1 border border-yellow-800 bg-yellow-900/20 text-yellow-200 text-[8px] uppercase">Need LVL {{ group.min_level || 1 }}</span>
                            </div>
                            <div class="mb-3 border border-cyan-900/60 bg-cyan-950/20 p-2">
                                <p class="text-[8px] uppercase text-cyan-300">Reason</p>
                                <p class="mt-1 font-sans text-[12px] leading-relaxed text-slate-200 break-words">
                                    {{ r.reason || '-' }}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <button @click="approveRequest(r.id)"
                                    class="px-3 py-2 border border-emerald-500 text-emerald-400 hover:bg-emerald-500 hover:text-black uppercase text-[8px]">
                                    Approve
                                </button>
                                <button @click="rejectRequest(r)"
                                    class="px-3 py-2 border border-red-500 text-red-400 hover:bg-red-600 hover:text-white uppercase text-[8px]">
                                    Reject
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rpg-panel border-cyan-500/50">
                <h2 class="text-cyan-400 mb-4 uppercase">
                    Members [{{ members.length }} / {{ group.max_members }}]
                </h2>
                <p class="text-[8px] text-slate-400 uppercase mb-4">Min Join Level: {{ group.min_level || 1 }}</p>

                    <div v-if="members.length === 0" class="text-slate-500 uppercase text-[8px] py-4">
                        NO_MEMBER
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="m in members" :key="m.id" class="p-3 bg-black/40 border border-slate-800 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-white uppercase">{{ m.username || m.name }}</p>
                                <p class="text-[8px] text-slate-500">{{ m.email }}</p>
                            </div>
                            <button @click="removeMember(m)"
                                class="px-3 py-2 border border-red-600 text-red-500 hover:bg-red-600 hover:text-white uppercase text-[8px]">
                                Remove
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
.rpg-panel {
    background-color: #1a1c2c;
    border-width: 4px;
    padding: 1rem;
    box-shadow: 8px 8px 0 0 rgba(0, 0, 0, 0.5);
}
</style>
