<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from '@/Utils/Alert';

const props = defineProps({
    roadmaps: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
    mentors: { type: Array, default: () => [] },
    enrollments: { type: Array, default: () => [] },
});

const activeTab = ref('list');
const selectedRoadmapUuid = ref(String(props.roadmaps[0]?.uuid || ''));
const memberSearch = ref('');
const editingEnrollment = ref(null);

const roadmapEnrollments = computed(() => props.enrollments.filter(
    (item) => String(item.roadmap?.uuid || '') === selectedRoadmapUuid.value,
));
const enrolledMemberIds = computed(() => new Set(roadmapEnrollments.value.map((item) => Number(item.member?.id || 0))));
const availableMembers = computed(() => {
    const keyword = memberSearch.value.trim().toLowerCase();
    return props.members.filter((member) => {
        if (enrolledMemberIds.value.has(Number(member.id))) return false;
        if (!keyword) return true;
        return [member.name, member.username, member.email].some((value) => String(value || '').toLowerCase().includes(keyword));
    });
});
const allVisibleSelected = computed(() => availableMembers.value.length > 0
    && availableMembers.value.every((member) => addForm.user_ids.includes(Number(member.id))));

const addForm = useForm({
    roadmap_uuid: selectedRoadmapUuid.value,
    user_ids: [],
    mentor_user_id: null,
    review_mode: 'manual',
});
const editForm = useForm({
    roadmap_uuid: '',
    student_user_id: null,
    mentor_user_id: null,
    status: 'active',
    review_mode: 'manual',
});

watch(selectedRoadmapUuid, (uuid) => {
    addForm.roadmap_uuid = uuid;
    addForm.user_ids = [];
    editingEnrollment.value = null;
});

const toggleAllVisible = () => {
    const visibleIds = availableMembers.value.map((member) => Number(member.id));
    if (allVisibleSelected.value) {
        addForm.user_ids = addForm.user_ids.filter((id) => !visibleIds.includes(Number(id)));
        return;
    }
    addForm.user_ids = [...new Set([...addForm.user_ids.map(Number), ...visibleIds])];
};

const submitMembers = () => {
    addForm.roadmap_uuid = selectedRoadmapUuid.value;
    addForm.post(route('dooplab.roadmap-management.assignments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('ROADMAP_MEMBERS_ADDED', 'Member berhasil ditambahkan.');
            addForm.reset('user_ids', 'mentor_user_id');
            activeTab.value = 'list';
        },
        onError: () => toast.error('ROADMAP_MEMBER_ADD_FAILED', 'Periksa member dan mentor yang dipilih.'),
    });
};

const startEdit = (enrollment) => {
    editingEnrollment.value = enrollment;
    editForm.clearErrors();
    editForm.roadmap_uuid = String(enrollment.roadmap?.uuid || '');
    editForm.student_user_id = Number(enrollment.member?.id || 0) || null;
    editForm.mentor_user_id = Number(enrollment.mentor?.id || 0) || null;
    editForm.status = String(enrollment.status || 'active');
    editForm.review_mode = String(enrollment.review_mode || 'manual');
};

const submitEdit = () => {
    if (!editingEnrollment.value?.uuid) return;
    editForm.patch(route('dooplab.roadmaps.enrollments.assignment.update', editingEnrollment.value.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('ROADMAP_UPDATED', 'Assignment berhasil diperbarui.');
            editingEnrollment.value = null;
        },
    });
};

const removeEnrollment = (enrollment) => {
    if (!window.confirm(`Hapus ${enrollment.member?.name || 'member'} dari roadmap ini?`)) return;
    router.delete(route('dooplab.roadmaps.enrollments.destroy', enrollment.uuid), {
        preserveScroll: true,
        onSuccess: () => toast.success('ROADMAP_MEMBER_REMOVED', 'Member berhasil dihapus.'),
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Roadmap Management" />
        <main class="management-page">
            <header class="page-header">
                <div>
                    <p class="eyebrow">DOOPLAB ADMIN</p>
                    <h1>Roadmap Management</h1>
                    <p>Hubungkan member pemilik DoopLab ID Card dengan roadmap dan mentor.</p>
                </div>
                <Link :href="route('dooplab.dashboard')" class="icon-link" title="Kembali ke Dashboard" aria-label="Kembali ke Dashboard">
                    <i class="fi fi-rr-arrow-left"></i>
                </Link>
            </header>

            <section class="toolbar">
                <label>
                    <span>Roadmap</span>
                    <select v-model="selectedRoadmapUuid">
                        <option v-for="roadmap in roadmaps" :key="roadmap.uuid" :value="roadmap.uuid">
                            {{ roadmap.title }} · {{ roadmap.is_published ? 'Published' : 'Draft' }}
                        </option>
                    </select>
                </label>
                <div class="tabs" role="tablist">
                    <button type="button" :class="{ active: activeTab === 'list' }" @click="activeTab = 'list'">
                        Assignment ({{ roadmapEnrollments.length }})
                    </button>
                    <button type="button" :class="{ active: activeTab === 'add' }" @click="activeTab = 'add'">
                        Tambah Member
                    </button>
                </div>
            </section>

            <section v-if="activeTab === 'list'" class="content-panel">
                <div class="section-heading">
                    <h2>Daftar Assignment</h2>
                    <span>{{ roadmapEnrollments.length }} member</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Member</th><th>Email</th><th>Mentor</th><th>Status</th><th>Review</th><th class="actions">Aksi</th></tr></thead>
                        <tbody>
                            <tr v-for="item in roadmapEnrollments" :key="item.uuid">
                                <td><strong>{{ item.member?.name || '-' }}</strong><small>@{{ item.member?.username || '-' }}</small></td>
                                <td>{{ item.member?.email || '-' }}</td>
                                <td>{{ item.mentor?.name || '-' }}</td>
                                <td><span class="status">{{ item.status }}</span></td>
                                <td>{{ item.review_mode }}</td>
                                <td class="actions">
                                    <button type="button" class="icon-button" title="Edit assignment" @click="startEdit(item)"><i class="fi fi-rr-pencil"></i></button>
                                    <button type="button" class="icon-button danger" title="Hapus member" @click="removeEnrollment(item)"><i class="fi fi-rr-trash"></i></button>
                                </td>
                            </tr>
                            <tr v-if="!roadmapEnrollments.length"><td colspan="6" class="empty">Belum ada member pada roadmap ini.</td></tr>
                        </tbody>
                    </table>
                </div>

                <form v-if="editingEnrollment" class="edit-form" @submit.prevent="submitEdit">
                    <div class="section-heading"><h2>Edit {{ editingEnrollment.member?.name }}</h2><button type="button" class="icon-button" title="Tutup" @click="editingEnrollment = null"><i class="fi fi-rr-cross-small"></i></button></div>
                    <div class="form-grid">
                        <label><span>Roadmap Tujuan</span><select v-model="editForm.roadmap_uuid" required><option v-for="roadmap in roadmaps" :key="roadmap.uuid" :value="roadmap.uuid">{{ roadmap.title }}</option></select></label>
                        <label><span>Mentor</span><select v-model="editForm.mentor_user_id" required><option v-for="mentor in mentors" :key="mentor.id" :value="mentor.id">{{ mentor.name }} (@{{ mentor.username || '-' }})</option></select></label>
                        <label><span>Status</span><select v-model="editForm.status"><option value="active">Active</option><option value="ended">Ended</option></select></label>
                        <label><span>Review Mode</span><select v-model="editForm.review_mode"><option value="manual">Manual</option><option value="auto">Auto</option></select></label>
                    </div>
                    <button class="primary-button" type="submit" :disabled="editForm.processing">{{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}</button>
                </form>
            </section>

            <section v-else class="content-panel">
                <div class="section-heading"><h2>Pilih Member Ber-ID Card</h2><span>{{ addForm.user_ids.length }} dipilih</span></div>
                <div class="add-controls">
                    <label><span>Cari Member</span><input v-model="memberSearch" type="search" placeholder="Nama, username, atau email"></label>
                    <label><span>Mentor</span><select v-model="addForm.mentor_user_id" required><option :value="null">Pilih mentor</option><option v-for="mentor in mentors" :key="mentor.id" :value="mentor.id">{{ mentor.name }} (@{{ mentor.username || '-' }})</option></select></label>
                    <label><span>Review Mode</span><select v-model="addForm.review_mode"><option value="manual">Manual</option><option value="auto">Auto</option></select></label>
                </div>
                <form @submit.prevent="submitMembers">
                    <div class="table-wrap member-table">
                        <table>
                            <thead><tr><th class="check"><input type="checkbox" :checked="allVisibleSelected" aria-label="Pilih semua member" @change="toggleAllVisible"></th><th>Nama</th><th>Username</th><th>Email</th><th>Access</th></tr></thead>
                            <tbody>
                                <tr v-for="member in availableMembers" :key="member.id">
                                    <td class="check"><input v-model="addForm.user_ids" type="checkbox" :value="member.id" :aria-label="`Pilih ${member.name}`"></td>
                                    <td><strong>{{ member.name }}</strong></td><td>@{{ member.username || '-' }}</td><td>{{ member.email }}</td><td><span class="id-card">DOOPLAB ID CARD</span></td>
                                </tr>
                                <tr v-if="!availableMembers.length"><td colspan="5" class="empty">Tidak ada member ID Card yang tersedia.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="addForm.errors.user_ids" class="error">{{ addForm.errors.user_ids }}</p>
                    <button class="primary-button" type="submit" :disabled="addForm.processing || !addForm.user_ids.length || !addForm.mentor_user_id">{{ addForm.processing ? 'Menambahkan...' : `Tambahkan ${addForm.user_ids.length} Member` }}</button>
                </form>
            </section>
        </main>
    </AuthenticatedLayout>
</template>

<style scoped>
.management-page { min-height: 100vh; padding: 24px; background: #f4f7f8; color: #17212b; font-family: Inter, sans-serif; }
.page-header, .toolbar, .section-heading, .tabs, .actions { display: flex; align-items: center; }
.page-header { justify-content: space-between; max-width: 1400px; margin: 0 auto 18px; }
.page-header h1 { margin: 3px 0 6px; font-size: 24px; letter-spacing: 0; }
.page-header p { margin: 0; color: #5d6b78; font-size: 13px; }
.eyebrow { color: #087f8c !important; font-size: 10px !important; font-weight: 800; }
.icon-link, .icon-button { width: 36px; height: 36px; display: inline-grid; place-items: center; border: 1px solid #9fb6ba; background: #fff; color: #18333a; cursor: pointer; }
.toolbar, .content-panel { max-width: 1400px; margin: 0 auto 16px; border: 1px solid #b9c9cc; background: #fff; }
.toolbar { justify-content: space-between; gap: 18px; padding: 14px; }
label { display: grid; gap: 6px; font-size: 11px; font-weight: 700; color: #52636d; }
select, input[type="search"] { min-height: 38px; border: 1px solid #93aaae; background: #fff; padding: 8px 10px; color: #17212b; font: inherit; }
.toolbar select { min-width: min(480px, 60vw); }
.tabs { gap: 0; }
.tabs button { min-height: 38px; border: 1px solid #93aaae; padding: 0 14px; background: #edf2f3; cursor: pointer; }
.tabs button.active { background: #087f8c; border-color: #087f8c; color: #fff; }
.content-panel { padding: 16px; }
.section-heading { justify-content: space-between; gap: 12px; margin-bottom: 14px; }
.section-heading h2 { margin: 0; font-size: 16px; letter-spacing: 0; }
.section-heading span { color: #63747e; font-size: 12px; }
.table-wrap { overflow-x: auto; border: 1px solid #c3d0d2; }
table { width: 100%; border-collapse: collapse; min-width: 820px; }
th, td { padding: 11px 12px; border-bottom: 1px solid #d8e1e2; text-align: left; font-size: 12px; vertical-align: middle; }
th { background: #eaf0f1; color: #40545d; font-size: 10px; text-transform: uppercase; }
td strong, td small { display: block; }
td small { margin-top: 4px; color: #718089; }
.actions { justify-content: flex-end; gap: 6px; white-space: nowrap; }
.danger { color: #b42318; border-color: #e1aaa5; }
.status, .id-card { display: inline-block; border: 1px solid #75aeb4; padding: 4px 6px; color: #087f8c; font-size: 9px; font-weight: 800; text-transform: uppercase; }
.check { width: 44px; text-align: center; }
input[type="checkbox"] { width: 17px; height: 17px; accent-color: #087f8c; }
.empty { padding: 28px; text-align: center; color: #718089; }
.add-controls, .form-grid { display: grid; grid-template-columns: minmax(240px, 1fr) minmax(220px, 320px) minmax(160px, 220px); gap: 12px; margin-bottom: 14px; }
.form-grid { grid-template-columns: repeat(4, minmax(160px, 1fr)); }
.edit-form { margin-top: 16px; padding: 14px; border: 1px solid #99b7bb; background: #f7fafb; }
.primary-button { margin-top: 14px; min-height: 40px; border: 1px solid #056671; background: #087f8c; color: #fff; padding: 0 16px; font-weight: 800; cursor: pointer; }
.primary-button:disabled { opacity: .5; cursor: not-allowed; }
.error { color: #b42318; font-size: 11px; }
@media (max-width: 800px) { .management-page { padding: 14px; } .toolbar { align-items: stretch; flex-direction: column; } .toolbar select { min-width: 0; width: 100%; } .tabs button { flex: 1; } .add-controls, .form-grid { grid-template-columns: 1fr; } }
</style>
