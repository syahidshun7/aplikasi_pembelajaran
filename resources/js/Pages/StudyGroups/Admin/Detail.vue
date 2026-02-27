<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AdminNavbar from '@/Components/AdminNavbar.vue';

const props = defineProps({
    group: Object,
    members: Array,
    requests: Array,
});

const approveRequest = (requestId) => {
    router.post(route('groups.requests.approve', { uuid: props.group.uuid, requestId }), {}, {
        preserveScroll: true,
    });
};

const rejectRequest = (requestId) => {
    router.post(route('groups.requests.reject', { uuid: props.group.uuid, requestId }), {}, {
        preserveScroll: true,
    });
};

const removeMember = (member) => {
    Swal.fire({
        title: 'REMOVE_MEMBER?',
        text: `Keluarkan ${member.username || member.name} dari group ini?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'YES_REMOVE',
        cancelButtonText: 'CANCEL',
        background: '#1a1c2c',
        color: '#4ed4d4',
        confirmButtonColor: '#b91c1c',
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

            <div class="flex justify-between items-center border-b-4 border-emerald-900 pb-4">
                <div>
                    <h1 class="text-xl uppercase tracking-widest">Group_Detail</h1>
                    <p class="text-[8px] text-slate-500 mt-2 uppercase">
                        {{ group.name }} | CODE: {{ group.invite_code }}
                    </p>
                </div>
                <Link :href="route('groups.manage')" class="text-slate-500 hover:text-white uppercase">[Back]</Link>
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
                            <div class="flex gap-2">
                                <button @click="approveRequest(r.id)"
                                    class="px-3 py-2 border border-emerald-500 text-emerald-400 hover:bg-emerald-500 hover:text-black uppercase text-[8px]">
                                    Approve
                                </button>
                                <button @click="rejectRequest(r.id)"
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
