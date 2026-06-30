
<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { toast } from '@/Utils/Alert';

const props = defineProps({
    logbooks:        { type: Array, default: () => [] },
    assignableUsers: { type: Array, default: () => [] },
    mentors:         { type: Array, default: () => [] },
    canApproveMentor:{ type: Boolean, default: false },
});

const emit = defineEmits(['select', 'deselect']);

// ── Selected logbook ──────────────────────────────────────────────────────
const selectedLogbookUuid = ref(null);
const selectedLogbook = computed(() => props.logbooks.find(lb => lb.uuid === selectedLogbookUuid.value) || null);

// ── Search & Pagination ───────────────────────────────────────────────────
const PAGE_SIZE = 30;
const entrySearch = ref('');
const entryPage = ref(1);
const entryMonthFilter = ref(''); // format: 'YYYY-MM'

const availableMonths = computed(() => {
    const entries = selectedLogbook.value?.entries || [];
    const set = new Set(entries.map(e => (e.activity_date || '').slice(0, 7)).filter(Boolean));
    return Array.from(set).sort().reverse(); // terbaru dulu
});

const filteredEntries = computed(() => {
    const q = entrySearch.value.trim().toLowerCase();
    const m = entryMonthFilter.value;
    const entries = selectedLogbook.value?.entries || [];
    return entries.filter(e => {
        const matchMonth = !m || (e.activity_date || '').startsWith(m);
        const matchSearch = !q ||
            (e.activity || '').toLowerCase().includes(q) ||
            (e.purpose  || '').toLowerCase().includes(q) ||
            (e.result   || '').toLowerCase().includes(q) ||
            (e.activity_date || '').includes(q);
        return matchMonth && matchSearch;
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredEntries.value.length / PAGE_SIZE)));

const pagedEntries = computed(() => {
    const start = (entryPage.value - 1) * PAGE_SIZE;
    return filteredEntries.value.slice(start, start + PAGE_SIZE);
});

const onEntrySearch = () => { entryPage.value = 1; };
const onMonthFilter = () => { entryPage.value = 1; };

// expose selectedLogbook uuid so parent can show toolbar
// ── Logbook CRUD ──────────────────────────────────────────────────────────
const logbookForm = useForm({ title: '', description: '' });
const logbookAssignForm = useForm({ title: '', description: '', member_ids: [], mentor_ids: [] });
const showLogbookModal = ref(false);
const logbookModalMode = ref('create'); // 'create' | 'edit' | 'assign'
const editingLogbookUuid = ref(null);
const editingLogbook = computed(() => props.logbooks.find(lb => lb.uuid === editingLogbookUuid.value) || null);

const openLogbookModal = (logbook = null) => {
    if (logbook) {
        logbookModalMode.value = 'edit';
        editingLogbookUuid.value = String(logbook.uuid || '');
        logbookAssignForm.title = String(logbook.title || '');
        logbookAssignForm.description = String(logbook.description || '');
        logbookAssignForm.member_ids = (logbook.members || []).map(u => u.id);
        logbookAssignForm.mentor_ids = (logbook.mentors || []).map(u => u.id);
    } else {
        logbookModalMode.value = 'create';
        editingLogbookUuid.value = null;
        logbookForm.reset();
    }
    logbookForm.clearErrors();
    logbookAssignForm.clearErrors();
    showLogbookModal.value = true;
};

const openLogbookAssignModal = () => {
    logbookModalMode.value = 'assign';
    logbookAssignForm.reset();
    logbookAssignForm.member_ids = [];
    logbookAssignForm.mentor_ids = [];
    logbookAssignForm.clearErrors();
    showLogbookModal.value = true;
};

const closeLogbookModal = () => {
    showLogbookModal.value = false;
    logbookForm.clearErrors();
    logbookAssignForm.clearErrors();
};

const submitLogbook = () => {
    const isEdit = logbookModalMode.value === 'edit';
    const opts = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            closeLogbookModal();
            toast.success(
                isEdit ? 'LOGBOOK DIPERBARUI!' : 'LOGBOOK DIBUAT!',
                isEdit ? 'Perubahan logbook berhasil disimpan.' : 'Logbook baru berhasil dibuat.'
            );
        },
        onError: () => toast.error('GAGAL!', 'Terjadi kesalahan. Periksa kembali inputan.'),
    };
    if (logbookModalMode.value === 'assign') {
        logbookAssignForm.post(route('dooplab.logbooks.assign'), opts);
    } else if (logbookModalMode.value === 'edit') {
        logbookAssignForm.patch(route('dooplab.logbooks.update', editingLogbookUuid.value), opts);
    } else {
        logbookForm.post(route('dooplab.logbooks.store'), opts);
    }
};

const deleteLogbook = async (logbook) => {
    if (!logbook?.can_delete) return;
    const result = await toast.confirm(
        'HAPUS LOGBOOK?',
        `"${logbook.title}" dan semua entrinya akan ikut terhapus.`,
        'YA, HAPUS'
    );
    if (!result.isConfirmed) return;
    if (selectedLogbookUuid.value === logbook.uuid) selectedLogbookUuid.value = null;
    router.delete(route('dooplab.logbooks.destroy', logbook.uuid), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => toast.success('LOGBOOK DIHAPUS!', 'Logbook berhasil dihapus.'),
        onError: () => toast.error('GAGAL!', 'Logbook gagal dihapus.'),
    });
};

// ── Entry CRUD ────────────────────────────────────────────────────────────
const entryForm = useForm({ activity_date: '', activity_time: '', activity: '', purpose: '', result: '', documentation: null });
const showEntryModal = ref(false);
const entryModalMode = ref('create');
const editingEntryUuid = ref(null);
const entryDocPreview = ref('');

const openEntryModal = (entry = null) => {
    if (entry) {
        entryModalMode.value = 'edit';
        editingEntryUuid.value = String(entry.uuid || '');
        entryForm.activity_date = String(entry.activity_date || '');
        entryForm.activity_time = String(entry.activity_time || '');
        entryForm.activity = String(entry.activity || '');
        entryForm.purpose = String(entry.purpose || '');
        entryForm.result = String(entry.result || '');
        entryForm.documentation = null;
    } else {
        entryModalMode.value = 'create';
        editingEntryUuid.value = null;
        entryForm.reset();
        entryForm.activity_date = new Date().toISOString().slice(0, 10);
        entryForm.activity_time = new Date().toTimeString().slice(0, 5);
    }
    entryForm.clearErrors();
    entryDocPreview.value = '';
    showEntryModal.value = true;
};

const closeEntryModal = () => {
    showEntryModal.value = false;
    entryForm.clearErrors();
    if (entryDocPreview.value.startsWith('blob:')) URL.revokeObjectURL(entryDocPreview.value);
    entryDocPreview.value = '';
};

const onEntryDocChange = (e) => {
    const file = e.target?.files?.[0] || null;
    if (entryDocPreview.value.startsWith('blob:')) URL.revokeObjectURL(entryDocPreview.value);
    entryForm.documentation = file;
    entryDocPreview.value = file ? URL.createObjectURL(file) : '';
};

const submitEntry = () => {
    if (!selectedLogbook.value) return;
    const isEdit = entryModalMode.value === 'edit';
    const opts = {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
        onSuccess: () => {
            closeEntryModal();
            toast.success(
                isEdit ? 'ENTRI DIPERBARUI!' : 'ENTRI DITAMBAHKAN!',
                isEdit ? 'Perubahan entri berhasil disimpan.' : 'Entri kegiatan berhasil dicatat.'
            );
        },
        onError: () => toast.error('GAGAL!', 'Terjadi kesalahan. Periksa kembali inputan.'),
    };
    if (isEdit) {
        entryForm.patch(route('dooplab.logbooks.entries.update', { logbook: selectedLogbook.value.uuid, entry: editingEntryUuid.value }), opts);
    } else {
        entryForm.post(route('dooplab.logbooks.entries.store', selectedLogbook.value.uuid), opts);
    }
};

const deleteEntry = async (entry) => {
    if (!selectedLogbook.value?.can_delete) return;
    const result = await toast.confirm('HAPUS ENTRI?', 'Entri ini akan dihapus permanen.', 'YA, HAPUS');
    if (!result.isConfirmed) return;
    router.delete(
        route('dooplab.logbooks.entries.destroy', { logbook: selectedLogbook.value.uuid, entry: entry.uuid }),
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => toast.success('ENTRI DIHAPUS!', 'Entri berhasil dihapus.'),
            onError: () => toast.error('GAGAL!', 'Entri gagal dihapus.'),
        }
    );
};

const approveEntry = (entry) => {
    if (!selectedLogbook.value) return;
    router.patch(
        route('dooplab.logbooks.entries.approve', { logbook: selectedLogbook.value.uuid, entry: entry.uuid }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => toast.success('ENTRI DIAPPROVE!', 'Status entri berhasil diperbarui.'),
            onError: () => toast.error('GAGAL!', 'Gagal approve entri.'),
        }
    );
};

const exportEntryCsv = () => {
    const lb = selectedLogbook.value;
    if (!lb?.entries?.length) return;
    const headers = ['No', 'Tanggal', 'Waktu', 'Kegiatan', 'Tujuan', 'Hasil', 'Status'];
    const rows = lb.entries.map((e, i) => [
        i + 1, e.activity_date || '', e.activity_time || '', e.activity || '', e.purpose || '', e.result || '', e.status || 'pending',
    ].map(v => `"${String(v).replace(/"/g, '""')}"`));
    const csv = [headers.join(','), ...rows.map(r => r.join(','))].join('\r\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `logbook-${lb.title.replace(/[^a-zA-Z0-9]/g, '_')}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};

// expose methods needed by parent toolbar
defineExpose({ selectedLogbook, selectedLogbookUuid, openLogbookModal, openLogbookAssignModal, openEntryModal, exportEntryCsv });
</script>

<template>
<div class="logbook-panel">
    <!-- List Logbook (buku) -->
    <section v-if="!selectedLogbook" class="learning-path-list custom-scroll">
        <p v-if="!logbooks.length" class="source-empty">
            Belum ada logbook. Klik "Buat Logbook" untuk mulai.
        </p>
        <article
            v-for="lb in logbooks"
            :key="lb.uuid"
            class="logbook-book-card"
            role="button"
            tabindex="0"
            @mousedown="$event.currentTarget._dx=$event.clientX;$event.currentTarget._dy=$event.clientY"
            @click="(Math.abs($event.clientX-($event.currentTarget._dx||$event.clientX))+Math.abs($event.clientY-($event.currentTarget._dy||$event.clientY))<5)&&(selectedLogbookUuid=lb.uuid)"
            @keydown.enter.prevent="selectedLogbookUuid=lb.uuid"
        >
            <div class="logbook-book-body">
                <i class="fi fi-rr-book-alt logbook-book-icon"></i>
                <div>
                    <p class="logbook-book-title">{{ lb.title }}</p>
                    <p v-if="lb.description" class="logbook-book-desc">{{ lb.description }}</p>
                    <span class="logbook-date">{{ lb.entries?.length || 0 }} entri</span>
                    <template v-if="lb.is_assigned">
                        <span v-for="m in lb.mentors" :key="m.id" class="logbook-badge logbook-badge--mentor">
                            <i class="fi fi-rr-user-graduate"></i>{{ m.name }}
                        </span>
                        <span v-for="m in lb.members" :key="m.id" class="logbook-badge logbook-badge--member">
                            <i class="fi fi-rr-user"></i>{{ m.name }}
                        </span>
                    </template>
                </div>
            </div>
            <div class="logbook-card-actions" @click.stop>
                <button v-if="lb.can_edit" type="button" class="todo-icon-btn" title="Edit" @click="openLogbookModal(lb)">
                    <i class="fi fi-rr-pencil"></i>
                </button>
                <button v-if="lb.can_delete" type="button" class="todo-icon-btn todo-icon-btn--danger" title="Hapus" @click="deleteLogbook(lb)">
                    <i class="fi fi-rr-trash"></i>
                </button>
            </div>
        </article>
    </section>

    <!-- Detail Logbook: list entri -->
    <section v-else class="logbook-entries-wrap">
        <!-- Search bar -->
        <div class="logbook-search-bar">
            <div class="logbook-search-input-wrap">
                <i class="fi fi-rr-search logbook-search-icon"></i>
                <input
                    v-model="entrySearch"
                    type="search"
                    placeholder="Cari kegiatan, tujuan, hasil..."
                    class="logbook-search-input"
                    @input="onEntrySearch"
                >
            </div>
            <select v-model="entryMonthFilter" class="logbook-month-select" @change="onMonthFilter">
                <option value="">Semua Bulan</option>
                <option v-for="m in availableMonths" :key="m" :value="m">
                    {{ new Date(m + '-01').toLocaleDateString('id-ID', { month: 'long', year: 'numeric' }) }}
                </option>
            </select>
            <span class="logbook-search-count">
                {{ filteredEntries.length }} entri
            </span>
        </div>

        <p v-if="!filteredEntries.length" class="source-empty">
            {{ entrySearch ? 'Tidak ada entri yang cocok.' : 'Belum ada entri. Klik "Tambah Entri" untuk mulai mencatat kegiatan.' }}
        </p>

        <div v-else class="logbook-table-wrap custom-scroll">
            <table class="logbook-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Waktu</th>
                        <th>Kegiatan</th>
                        <th>Tujuan</th>
                        <th>Hasil</th>
                        <th>Status</th>
                        <th>Dokumen</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="entry in pagedEntries" :key="entry.uuid">
                        <td class="logbook-td-date">{{ entry.activity_date }}</td>
                        <td class="logbook-td-time">{{ entry.activity_time || '-' }}</td>
                        <td class="logbook-td-main">{{ entry.activity }}</td>
                        <td class="logbook-td-text">{{ entry.purpose || '-' }}</td>
                        <td class="logbook-td-text">{{ entry.result || '-' }}</td>
                        <td class="logbook-td-sig">
                            <span
                                class="logbook-status-badge"
                                :class="entry.status === 'approved' ? 'logbook-status-badge--approved' : 'logbook-status-badge--pending'"
                            >{{ entry.status === 'approved' ? '✓ Approved' : '⏳ Pending' }}</span>
                            <button
                                v-if="entry.status === 'pending' && canApproveMentor"
                                type="button"
                                class="todo-icon-btn"
                                style="margin-left:4px;color:var(--green,#57f6b9)"
                                title="Approve entri ini"
                                @click="approveEntry(entry)"
                            ><i class="fi fi-rr-check"></i></button>
                        </td>
                        <td class="logbook-td-doc">
                            <a v-if="entry.documentation_url" :href="entry.documentation_url" target="_blank" rel="noopener noreferrer" class="logbook-doc-link" title="Lihat file">
                                <i class="fi fi-rr-file"></i>
                            </a>
                            <span v-else>-</span>
                        </td>
                        <td class="logbook-td-actions">
                            <button v-if="selectedLogbook.can_edit" type="button" class="todo-icon-btn" title="Edit" @click="openEntryModal(entry)">
                                <i class="fi fi-rr-pencil"></i>
                            </button>
                            <button v-if="selectedLogbook.can_delete" type="button" class="todo-icon-btn todo-icon-btn--danger" title="Hapus" @click="deleteEntry(entry)">
                                <i class="fi fi-rr-trash"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="logbook-pagination">
            <button class="logbook-page-btn" :disabled="entryPage === 1" @click="entryPage--">
                <i class="fi fi-rr-angle-left"></i>
            </button>
            <template v-for="p in totalPages" :key="p">
                <button
                    v-if="p === 1 || p === totalPages || Math.abs(p - entryPage) <= 1"
                    class="logbook-page-btn"
                    :class="{ 'is-active': p === entryPage }"
                    @click="entryPage = p"
                >{{ p }}</button>
                <span v-else-if="p === entryPage - 2 || p === entryPage + 2" class="logbook-page-ellipsis">…</span>
            </template>
            <button class="logbook-page-btn" :disabled="entryPage === totalPages" @click="entryPage++">
                <i class="fi fi-rr-angle-right"></i>
            </button>
        </div>
    </section>

    <!-- Modal: Buat/Edit Logbook -->
    <Teleport to="body">
        <div v-if="showLogbookModal" class="todo-modal logbook-modal" role="dialog" aria-modal="true">
            <div class="todo-modal-backdrop" @click="closeLogbookModal"></div>
            <div class="todo-modal-card">
                <div class="todo-modal-head">
                    <h3>{{ logbookModalMode === 'edit' ? 'Edit Logbook' : logbookModalMode === 'assign' ? 'Assign Logbook ke User' : 'Buat Logbook Baru' }}</h3>
                    <button type="button" class="todo-modal-close" @click="closeLogbookModal"><i class="fi fi-rr-cross"></i></button>
                </div>
                <!-- Form Assign -->
                <form v-if="logbookModalMode === 'assign'" class="todo-modal-body" @submit.prevent="submitLogbook">
                    <label class="todo-field">
                        <span>Pilih Users (Members) *</span>
                        <select v-model="logbookAssignForm.member_ids" multiple required class="todo-select" size="5" style="height:auto;min-height:80px;">
                            <option v-for="u in assignableUsers" :key="u.id" :value="u.id">{{ u.name }} ({{ u.username }})</option>
                        </select>
                        <small class="todo-field-note">Tahan Ctrl/Cmd untuk pilih banyak</small>
                        <small v-if="logbookAssignForm.errors.member_ids" class="todo-error">{{ logbookAssignForm.errors.member_ids }}</small>
                    </label>
                    <label class="todo-field">
                        <span>Pilih Mentors (opsional)</span>
                        <select v-model="logbookAssignForm.mentor_ids" multiple class="todo-select" size="4" style="height:auto;min-height:60px;">
                            <option v-for="m in mentors" :key="m.id" :value="m.id">{{ m.name }} ({{ m.username }})</option>
                        </select>
                        <small class="todo-field-note">Tahan Ctrl/Cmd untuk pilih banyak</small>
                        <small v-if="logbookAssignForm.errors.mentor_ids" class="todo-error">{{ logbookAssignForm.errors.mentor_ids }}</small>
                    </label>
                    <label class="todo-field">
                        <span>Nama Logbook *</span>
                        <input v-model="logbookAssignForm.title" type="text" maxlength="200" required placeholder="Contoh: PKL PT. ABC 2026">
                        <small v-if="logbookAssignForm.errors.title" class="todo-error">{{ logbookAssignForm.errors.title }}</small>
                    </label>
                    <label class="todo-field">
                        <span>Deskripsi (opsional)</span>
                        <textarea v-model="logbookAssignForm.description" rows="3" maxlength="1000" placeholder="Keterangan singkat tentang logbook ini"></textarea>
                        <small v-if="logbookAssignForm.errors.description" class="todo-error">{{ logbookAssignForm.errors.description }}</small>
                    </label>
                    <div class="todo-modal-actions">
                        <button type="button" class="nb-btn nb-btn--ghost" @click="closeLogbookModal">Batal</button>
                        <button type="submit" class="nb-btn nb-btn--solid" :disabled="logbookAssignForm.processing">
                            {{ logbookAssignForm.processing ? 'Menyimpan...' : 'Assign' }}
                        </button>
                    </div>
                </form>
                <!-- Form Buat/Edit -->
                <form v-else class="todo-modal-body" @submit.prevent="submitLogbook">
                    <label class="todo-field">
                        <span>Nama Logbook *</span>
                        <input
                            v-if="logbookModalMode === 'create'"
                            v-model="logbookForm.title"
                            type="text" maxlength="200" required
                            placeholder="Contoh: PKL PT. ABC 2026"
                        >
                        <template v-else>
                            <input
                                v-if="editingLogbook?.is_owner"
                                v-model="logbookAssignForm.title"
                                type="text" maxlength="200" required
                                placeholder="Contoh: PKL PT. ABC 2026"
                            >
                            <input v-else :value="logbookAssignForm.title" type="text" readonly disabled style="opacity:.55;cursor:not-allowed;">
                            <small v-if="!editingLogbook?.is_owner" class="todo-field-note">Nama logbook hanya bisa diubah oleh pemilik logbook.</small>
                        </template>
                        <small v-if="logbookModalMode === 'create' ? logbookForm.errors.title : logbookAssignForm.errors.title" class="todo-error">
                            {{ logbookModalMode === 'create' ? logbookForm.errors.title : logbookAssignForm.errors.title }}
                        </small>
                    </label>
                    <label class="todo-field">
                        <span>Deskripsi (opsional)</span>
                        <textarea
                            v-if="logbookModalMode === 'create'"
                            v-model="logbookForm.description"
                            rows="3" maxlength="1000"
                            placeholder="Keterangan singkat tentang logbook ini"
                        ></textarea>
                        <textarea
                            v-else
                            v-model="logbookAssignForm.description"
                            rows="3" maxlength="1000"
                            placeholder="Keterangan singkat tentang logbook ini"
                        ></textarea>
                        <small v-if="logbookModalMode === 'create' ? logbookForm.errors.description : logbookAssignForm.errors.description" class="todo-error">
                            {{ logbookModalMode === 'create' ? logbookForm.errors.description : logbookAssignForm.errors.description }}
                        </small>
                    </label>
                    <template v-if="logbookModalMode === 'edit' && editingLogbook?.is_owner && editingLogbook?.is_assigned">
                        <label class="todo-field">
                            <span>Members</span>
                            <select v-model="logbookAssignForm.member_ids" multiple class="todo-select" size="4" style="height:auto;min-height:70px;">
                                <option v-for="u in assignableUsers" :key="u.id" :value="u.id">{{ u.name }} ({{ u.username }})</option>
                            </select>
                            <small class="todo-field-note">Tahan Ctrl/Cmd untuk pilih banyak</small>
                            <small v-if="logbookAssignForm.errors.member_ids" class="todo-error">{{ logbookAssignForm.errors.member_ids }}</small>
                        </label>
                        <label class="todo-field">
                            <span>Mentors</span>
                            <select v-model="logbookAssignForm.mentor_ids" multiple class="todo-select" size="3" style="height:auto;min-height:55px;">
                                <option v-for="m in mentors" :key="m.id" :value="m.id">{{ m.name }} ({{ m.username }})</option>
                            </select>
                            <small class="todo-field-note">Tahan Ctrl/Cmd untuk pilih banyak</small>
                            <small v-if="logbookAssignForm.errors.mentor_ids" class="todo-error">{{ logbookAssignForm.errors.mentor_ids }}</small>
                        </label>
                    </template>
                    <div class="todo-modal-actions">
                        <button type="button" class="nb-btn nb-btn--ghost" @click="closeLogbookModal">Batal</button>
                        <button type="submit" class="nb-btn nb-btn--solid" :disabled="logbookModalMode === 'create' ? logbookForm.processing : logbookAssignForm.processing">
                            {{ (logbookModalMode === 'create' ? logbookForm.processing : logbookAssignForm.processing) ? 'Menyimpan...' : (logbookModalMode === 'edit' ? 'Simpan' : 'Buat') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- Modal: Tambah/Edit Entri -->
    <Teleport to="body">
        <div v-if="showEntryModal" class="todo-modal logbook-modal" role="dialog" aria-modal="true">
            <div class="todo-modal-backdrop" @click="closeEntryModal"></div>
            <div class="todo-modal-card">
                <div class="todo-modal-head">
                    <h3>{{ entryModalMode === 'edit' ? 'Edit Entri' : 'Tambah Entri Kegiatan' }}</h3>
                    <button type="button" class="todo-modal-close" @click="closeEntryModal"><i class="fi fi-rr-cross"></i></button>
                </div>
                <form class="todo-modal-body" @submit.prevent="submitEntry">
                    <div class="todo-date-grid">
                        <label class="todo-field todo-field--date">
                            <span>Tanggal *</span>
                            <input v-model="entryForm.activity_date" type="date" required>
                            <small v-if="entryForm.errors.activity_date" class="todo-error">{{ entryForm.errors.activity_date }}</small>
                        </label>
                        <label class="todo-field todo-field--date">
                            <span>Waktu</span>
                            <input v-model="entryForm.activity_time" type="time">
                        </label>
                    </div>
                    <label class="todo-field">
                        <span>Kegiatan *</span>
                        <input v-model="entryForm.activity" type="text" maxlength="500" required placeholder="Deskripsi singkat kegiatan">
                        <small v-if="entryForm.errors.activity" class="todo-error">{{ entryForm.errors.activity }}</small>
                    </label>
                    <label class="todo-field">
                        <span>Tujuan Kegiatan</span>
                        <textarea v-model="entryForm.purpose" rows="2" maxlength="2000" placeholder="Apa tujuan dari kegiatan ini?"></textarea>
                    </label>
                    <label class="todo-field">
                        <span>Hasil</span>
                        <textarea v-model="entryForm.result" rows="2" maxlength="2000" placeholder="Apa yang dicapai / dihasilkan?"></textarea>
                    </label>
                    <label class="todo-field">
                        <span>Dokumentasi (jpg/png/webp/pdf, maks 5MB)</span>
                        <input type="file" accept="image/png,image/jpeg,image/jpg,image/webp,application/pdf" @change="onEntryDocChange">
                        <small v-if="entryForm.errors.documentation" class="todo-error">{{ entryForm.errors.documentation }}</small>
                    </label>
                    <a v-if="entryDocPreview && !entryDocPreview.endsWith('.pdf')" :href="entryDocPreview" target="_blank" rel="noopener noreferrer">
                        <img :src="entryDocPreview" alt="Preview dokumentasi" class="todo-note-image" style="margin-top:6px;">
                    </a>
                    <div class="todo-modal-actions">
                        <button type="button" class="nb-btn nb-btn--ghost" @click="closeEntryModal">Batal</button>
                        <button type="submit" class="nb-btn nb-btn--solid" :disabled="entryForm.processing">
                            {{ entryForm.processing ? 'Menyimpan...' : (entryModalMode === 'edit' ? 'Simpan' : 'Tambah') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</div>
</template>


<style scoped>
.logbook-panel {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    min-width: 0;
    overflow-x: auto;
    overflow-y: visible;
}

/* ── Entries wrap ── */
.logbook-entries-wrap {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    gap: 10px;
}

/* ── Search bar ── */
.logbook-search-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.logbook-search-input-wrap {
    position: relative;
    flex: 1;
    min-width: 180px;
}
.logbook-search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #8ea8bb;
    font-size: 13px;
    pointer-events: none;
}
.logbook-search-input {
    width: 100%;
    box-sizing: border-box;
    background: #0d1117;
    border: 2px solid #3d415f;
    color: #cbd5e1;
    padding: 8px 10px 8px 32px;
    font-family: Inter, sans-serif;
    font-size: 13px;
    outline: none;
}
.logbook-search-input:focus { border-color: #57d6ff; }
.logbook-search-input::placeholder { color: #4a5568; }
.logbook-search-count {
    font-family: Inter, sans-serif;
    font-size: 11px;
    color: #8ea8bb;
    white-space: nowrap;
}

.logbook-month-select {
    background: #0d1117;
    border: 2px solid #3d415f;
    color: #cbd5e1;
    padding: 8px 10px;
    font-family: Inter, sans-serif;
    font-size: 13px;
    outline: none;
    cursor: pointer;
    min-width: 150px;
}
.logbook-month-select:focus { border-color: #57d6ff; }

/* ── Pagination ── */
.logbook-pagination {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
    padding-top: 4px;
}
.logbook-page-btn {
    min-width: 32px;
    height: 32px;
    padding: 0 8px;
    border: 2px solid #3d415f;
    background: #0d1117;
    color: #8ea8bb;
    font-family: Inter, sans-serif;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: border-color 0.15s, background 0.15s, color 0.15s;
}
.logbook-page-btn:hover:not(:disabled) {
    border-color: #57d6ff;
    color: #57d6ff;
    background: rgba(87,214,255,0.08);
}
.logbook-page-btn.is-active {
    border-color: #57d6ff;
    background: rgba(87,214,255,0.15);
    color: #57d6ff;
}
.logbook-page-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.logbook-page-ellipsis {
    color: #4a5568;
    font-size: 12px;
    padding: 0 2px;
    line-height: 32px;
}
.logbook-date {
    font-size: 10px;
    color: var(--cyan);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: block;
    margin-bottom: 4px;
}
.logbook-card-actions {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
}
.logbook-doc-link {
    font-size: 12px;
    color: var(--cyan);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.logbook-doc-link:hover { opacity: 0.8; }
.logbook-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    margin-left: 5px;
    font-size: 11px;
    padding: 2px 7px;
    border-radius: 999px;
}
.logbook-badge--mentor {
    background: rgba(124,58,237,0.15);
    color: #a78bfa;
    border: 1px solid rgba(124,58,237,0.3);
}
.logbook-badge--member {
    background: rgba(16,185,129,0.12);
    color: #6ee7b7;
    border: 1px solid rgba(16,185,129,0.25);
}
.logbook-book-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 14px 16px;
    background: rgba(10, 17, 30, 0.6);
    margin-bottom: 10px;
    cursor: pointer;
    transition: border-color 0.15s;
}
.logbook-book-card:hover { border-color: var(--cyan); }
.logbook-book-body {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    flex: 1;
    min-width: 0;
}
.logbook-book-icon {
    font-size: 20px;
    color: var(--cyan);
    flex-shrink: 0;
    margin-top: 2px;
}
.logbook-book-title { margin: 0 0 2px; font-size: 13px; font-weight: 600; color: var(--text); font-family: Inter, sans-serif; }
.logbook-book-desc  { margin: 0 0 4px; font-size: 11px; color: var(--text-dim); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: Inter, sans-serif; }

/* ── Table ── */
.logbook-table-wrap { overflow-x: auto; overflow-y: auto; flex: 1; width: 100%; }
.logbook-table {
    width: max-content;
    min-width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    font-family: Inter, sans-serif;
    color: var(--text);
    table-layout: auto;
}
.logbook-table thead tr  { border-bottom: 2px solid var(--line-strong); }
.logbook-table th {
    text-align: left;
    padding: 8px 12px;
    font-size: 10px;
    font-weight: 700;
    font-family: Inter, sans-serif;
    color: var(--cyan);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    white-space: nowrap;
}

/* Column widths via min/max */
.logbook-td-date    { white-space: nowrap; font-weight: 600; font-size: 12px; min-width: 95px; }
.logbook-td-time    { white-space: nowrap; color: var(--text-dim); font-size: 12px; min-width: 70px; }
.logbook-td-main    { min-width: 160px; max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.logbook-td-text    { min-width: 110px; max-width: 180px; color: var(--text-dim); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.logbook-td-sig     { white-space: nowrap; min-width: 110px; }
.logbook-td-doc     { text-align: center; min-width: 60px; }
.logbook-td-actions { white-space: nowrap; text-align: right; min-width: 56px; }

.logbook-status-badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 600;
    font-family: Inter, sans-serif;
    padding: 3px 8px;
    border-radius: 999px;
    white-space: nowrap;
}
.logbook-status-badge--approved { background: rgba(87,246,185,0.15); color: #57f6b9; border: 1px solid rgba(87,246,185,0.3); }
.logbook-status-badge--pending  { background: rgba(248,198,92,0.12); color: #f8c65c; border: 1px solid rgba(248,198,92,0.3); }

@media (max-width: 620px) {
    .logbook-table { table-layout: auto; }
    .logbook-table thead { display: none; }
    .logbook-table tbody tr {
        display: grid;
        grid-template-columns: 1fr auto;
        grid-template-rows: auto auto auto auto;
        gap: 4px 8px;
        padding: 12px 10px;
        border-bottom: 2px solid var(--line);
    }
    .logbook-table td      { padding: 0; overflow: visible; text-overflow: unset; white-space: normal; }
    .logbook-td-date       { grid-column: 1; grid-row: 1; font-size: 11px; color: var(--cyan); }
    .logbook-td-time       { grid-column: 2; grid-row: 1; text-align: right; font-size: 11px; }
    .logbook-td-main       { grid-column: 1 / -1; grid-row: 2; font-weight: 600; font-size: 13px; white-space: normal; }
    .logbook-td-text       { display: none; }
    .logbook-td-sig        { grid-column: 1; grid-row: 3; align-self: center; }
    .logbook-td-doc        { grid-column: 2; grid-row: 3; text-align: right; align-self: center; }
    .logbook-td-actions    { grid-column: 1 / -1; grid-row: 4; display: flex; gap: 6px; justify-content: flex-end; }
}
</style>

<style>
/* ── Font override: table & badges ── */
.logbook-table,
.logbook-table th,
.logbook-table td,
.logbook-status-badge,
.logbook-book-title,
.logbook-book-desc,
.logbook-badge {
    font-family: Inter, system-ui, sans-serif !important;
}

/* Allow horizontal scroll on parent panel */
.nb-panel:has(.logbook-table-wrap) {
    overflow-x: auto;
}

/* ── Modal shell ── */
.logbook-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(6, 10, 17, 0.72);
    backdrop-filter: blur(2px);
    display: grid;
    align-items: start;
    justify-items: center;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: clamp(10px, 2.6vw, 18px);
    padding-top: max(clamp(10px, 2.6vw, 18px), env(safe-area-inset-top));
    padding-bottom: max(clamp(10px, 2.6vw, 18px), env(safe-area-inset-bottom));
}
.logbook-modal .todo-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: -1;
}
.logbook-modal .todo-modal-card {
    width: min(860px, 100%);
    max-height: calc(100dvh - clamp(20px, 5.2vw, 36px));
    border: 4px solid #3d415f;
    background: #1a1c2c;
    box-shadow: 8px 8px 0 rgba(0,0,0,0.5);
    padding: 20px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.logbook-modal .todo-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
    padding-bottom: 16px;
    border-bottom: 2px solid #3d415f;
}
.logbook-modal .todo-modal-head h3 {
    margin: 0;
    font-family: Inter, sans-serif;
    font-size: 15px;
    font-weight: 700;
    color: #fff;
}
.logbook-modal .todo-modal-close {
    width: 32px;
    height: 32px;
    border: 2px solid #3d415f;
    background: #0d1117;
    color: #cbd5e1;
    font-size: 14px;
    display: grid;
    place-items: center;
    cursor: pointer;
    flex-shrink: 0;
}
.logbook-modal .todo-modal-close:hover { background: #1a2030; }
.logbook-modal .todo-modal-body {
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding-right: 4px;
}
.logbook-modal .todo-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 8px;
    padding-top: 4px;
}

/* ── Form fields ── */
.logbook-modal .todo-field {
    display: grid;
    gap: 7px;
    min-width: 0;
}
.logbook-modal .todo-date-grid {
    display: grid;
    align-items: start;
    gap: 10px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.logbook-modal .todo-field--date { align-content: start; }
.logbook-modal .todo-field span {
    font-family: Inter, sans-serif;
    font-size: 11px;
    font-weight: 600;
    color: #8ea8bb;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}
.logbook-modal .todo-field input,
.logbook-modal .todo-field textarea,
.logbook-modal .todo-field select {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    border: 2px solid #3d415f;
    background: #0d1117;
    color: #cbd5e1;
    padding: 10px 12px;
    outline: none;
    font-family: Inter, sans-serif;
    font-size: 14px;
}
.logbook-modal .todo-field input:focus,
.logbook-modal .todo-field textarea:focus,
.logbook-modal .todo-field select:focus {
    border-color: #57d6ff;
}
.logbook-modal .todo-field textarea { resize: vertical; }
.logbook-modal .todo-field-note {
    font-family: Inter, sans-serif;
    font-size: 11px;
    color: #8ea8bb;
    margin: -2px 0 0;
}
.logbook-modal .todo-error {
    font-family: Inter, sans-serif;
    font-size: 11px;
    color: #ff9aa8;
}
.logbook-modal .todo-select { height: auto; }

/* ── Buttons ── */
.logbook-panel .todo-icon-btn {
    display: inline-grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border: 1px solid rgba(87,214,255,0.42);
    background: rgba(87,214,255,0.1);
    color: #bdeeff;
    font-size: 13px;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    flex-shrink: 0;
}
.logbook-panel .todo-icon-btn + .todo-icon-btn { margin-left: 4px; }
.logbook-panel .todo-icon-btn:hover:not(:disabled) {
    border-color: rgba(87,214,255,0.75);
    background: rgba(87,214,255,0.2);
}
.logbook-panel .todo-icon-btn--danger {
    border-color: rgba(255,127,143,0.48);
    background: rgba(255,127,143,0.12);
    color: #ffc5ce;
}
.logbook-panel .todo-icon-btn--danger:hover:not(:disabled) {
    border-color: rgba(255,127,143,0.78);
    background: rgba(255,127,143,0.2);
}
.logbook-modal .nb-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: Inter, sans-serif;
    font-size: 13px;
    font-weight: 600;
    padding: 10px 18px;
    border: 1px solid rgba(255,255,255,0.14);
    cursor: pointer;
    transition: opacity 0.15s, background 0.15s;
    white-space: nowrap;
}
.logbook-modal .nb-btn--ghost {
    color: #57d6ff;
    background: rgba(87,214,255,0.06);
    border-color: rgba(87,214,255,0.3);
}
.logbook-modal .nb-btn--ghost:hover { background: rgba(87,214,255,0.14); }
.logbook-modal .nb-btn--solid {
    color: #101317;
    background: linear-gradient(120deg, #8be8ff, #7fffc9);
    border-color: transparent;
}
.logbook-modal .nb-btn--solid:hover { opacity: 0.88; }
.logbook-modal .nb-btn:disabled { opacity: 0.45; cursor: not-allowed; }

/* ── Mobile ── */
@media (max-width: 620px) {
    .logbook-modal .todo-modal-card {
        width: 100%;
        max-height: calc(100dvh - 16px);
        padding: 14px 12px;
    }
    .logbook-modal .todo-modal-head h3 { font-size: 13px; }
    .logbook-modal .todo-date-grid { grid-template-columns: 1fr; }
    .logbook-modal .todo-field input,
    .logbook-modal .todo-field textarea,
    .logbook-modal .todo-field select { font-size: 16px; }
    .logbook-modal .todo-modal-actions { flex-direction: column; }
    .logbook-modal .nb-btn { width: 100%; justify-content: center; }
}
</style>
