<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import LogbookPanel from '@/Components/Dashboard/LogbookPanel.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from '@/Utils/Alert';

const props = defineProps({
    overview: { type: Object, default: () => ({}) },
    my_creation_stats: { type: Object, default: () => ({}) },
    recent_experiments: { type: Array, default: () => [] },
    collaboration: { type: Object, default: () => ({}) },
    mentors: { type: Array, default: () => [] },
    todos: { type: Array, default: () => [] },
    todo_permissions: { type: Object, default: () => ({}) },
    todo_assignable_users: { type: Array, default: () => [] },
    research_workspaces: { type: Array, default: () => [] },
    hireable_creations: { type: Array, default: () => [] },
    direct_mentors: { type: Array, default: () => [] },
    mentor_invites: { type: Array, default: () => [] },
    learning_paths: { type: Array, default: () => [] },
    logbooks: { type: Array, default: () => [] },
    logbook_assignable_users: { type: Array, default: () => [] },
});

const page = usePage();
const DOOPLAB_DASHBOARD_STATE_KEY = 'dooplab.dashboard.ui-state';
const safeReadDashboardState = () => {
    if (typeof window === 'undefined') return {};

    try {
        return JSON.parse(window.localStorage.getItem(DOOPLAB_DASHBOARD_STATE_KEY) || '{}') || {};
    } catch {
        return {};
    }
};
const initialDashboardState = safeReadDashboardState();
const authUser = computed(() => page.props?.auth?.user ?? null);
const canCreateMentorTodo = computed(() => Boolean(props.todo_permissions?.can_create_mentor));
const canOpenRoadmapLab = computed(() => {
    const role = String(authUser.value?.role || '').toLowerCase();
    return ['mentor', 'admin', 'super_admin'].includes(role);
});

const allTodos = computed(() => Array.isArray(props.todos) ? props.todos : []);
const mentors = computed(() => Array.isArray(props.mentors) ? props.mentors : []);
const mentorInvites = computed(() => Array.isArray(props.mentor_invites) ? props.mentor_invites : []);
const researchWorkspaces = computed(() => Array.isArray(props.research_workspaces) ? props.research_workspaces : []);
const hireableCreations = computed(() => Array.isArray(props.hireable_creations) ? props.hireable_creations : []);
const directMentors = computed(() => Array.isArray(props.direct_mentors) ? props.direct_mentors : []);
const selectedHireCreation = computed(() => {
    const creationId = Number(hireMentorForm.value.creation_id || 0);
    return hireableCreations.value.find((creation) => Number(creation?.id || 0) === creationId) || null;
});
const availableHireMentors = computed(() => {
    const unavailableMentorIds = new Set(
        [
            ...(selectedHireCreation.value?.hired_mentor_ids || []),
            ...(selectedHireCreation.value?.mentor_invites || [])
                .filter((invite) => String(invite?.status || '').toLowerCase() === 'pending')
                .map((invite) => Number(invite?.mentor_id || 0)),
            ...directMentors.value
                .filter((mentor) => ['pending', 'approved'].includes(String(mentor?.status || '').toLowerCase()))
                .map((mentor) => Number(mentor?.mentor_id || 0)),
        ]
            .map((id) => Number(id || 0))
            .filter((id) => id > 0)
    );

    return mentors.value.filter((mentor) => !unavailableMentorIds.has(Number(mentor?.id || 0)));
});
const selectedHireCreationMentors = computed(() => [
    ...directMentors.value
        .map((mentor) => ({
            id: `direct-${mentor.id}`,
            name: mentor.name,
            username: mentor.username,
            profile_photo: mentor.profile_photo,
            status: String(mentor.status || 'pending').toUpperCase(),
        })),
    ...(Array.isArray(selectedHireCreation.value?.mentor_invites) ? selectedHireCreation.value.mentor_invites : [])
        .map((invite) => ({
            id: `invite-${invite.id}`,
            name: invite.name,
            username: invite.username,
            profile_photo: invite.profile_photo,
            status: String(invite.status || 'pending').toUpperCase(),
        })),
    ...(Array.isArray(selectedHireCreation.value?.hired_mentors) ? selectedHireCreation.value.hired_mentors : [])
        .map((mentor) => ({
            id: `connected-${mentor.id}`,
            name: mentor.name,
            username: mentor.username,
            profile_photo: mentor.profile_photo,
            status: 'CONNECTED',
        })),
]);
const visibleResearchWorkspaces = computed(() => {
    if (!canCreateMentorTodo.value || todoForm.assignment_mode !== 'mentor') {
        return researchWorkspaces.value;
    }

    const ownerUserId = Number(todoForm.owner_user_id || 0);
    if (ownerUserId <= 0) return [];

    return researchWorkspaces.value.filter((workspace) => Number(workspace?.owner_user_id || 0) === ownerUserId);
});
const learningPaths = computed(() => Array.isArray(props.learning_paths) ? props.learning_paths : []);
const allLogbooks = computed(() => Array.isArray(props.logbooks) ? props.logbooks : []);
// Logbook milik mentor sendiri (untuk di-assign ke member saat buat todo type logbook)
const mentorLogbooks = computed(() => allLogbooks.value.filter(lb => lb.is_owner));
const todoSearch = ref(String(initialDashboardState.todoSearch || ''));
const todoFilter = ref(['all', 'self', 'mentor'].includes(String(initialDashboardState.todoFilter || ''))
    ? String(initialDashboardState.todoFilter)
    : 'all');
const panelMode = ref(['summary', 'todo', 'learning_paths', 'hire_mentor', 'mentor_invites', 'logbook'].includes(String(initialDashboardState.panelMode || ''))
    ? String(initialDashboardState.panelMode)
    : 'learning_paths');
const showTodoModal = ref(false);
const todoModalMode = ref('create');
const editingTodoUuid = ref(null);
const selectedTodoUuid = ref(initialDashboardState.selectedTodoUuid || null);
const hireMentorForm = ref({
    creation_id: initialDashboardState.hireCreationId || null,
    mentor_user_id: null,
});
const hiringMentor = ref(false);
const selectedTodo = computed(() => {
    const uuid = String(selectedTodoUuid.value || '');
    if (uuid === '') return null;

    return allTodos.value.find((item) => String(item?.uuid || '') === uuid) || null;
});

const persistDashboardState = () => {
    if (typeof window === 'undefined') return;

    window.localStorage.setItem(DOOPLAB_DASHBOARD_STATE_KEY, JSON.stringify({
        panelMode: panelMode.value,
        selectedTodoUuid: selectedTodoUuid.value,
        todoFilter: todoFilter.value,
        todoSearch: todoSearch.value,
        hireCreationId: hireMentorForm.value.creation_id,
        scrollY: window.scrollY || 0,
    }));
};

const todoForm = useForm({
    title: '',
    description: '',
    start_at: '',
    deadline: '',
    notify_deadline_email: false,
    assignment_mode: 'self',
    owner_user_id: null,
    creation_id: null,
    logbook_id: null,
    milestone_type: 'task',
});

const todoNoteForm = useForm({
    note: '',
    image: null,
});

const reviewForm = ref({
    show: false,
    decision: null,
    note: '',
});

const openReviewForm = (decision) => {
    reviewForm.value = { show: true, decision, note: '' };
};

const closeReviewForm = () => {
    reviewForm.value = { show: false, decision: null, note: '' };
};

// ── END LOGBOOK STATE ─────────────────────────────────────────────────────
const logbookPanelRef = ref(null);

const showLogbook = () => {
    selectedTodoUuid.value = null;
    if (logbookPanelRef.value) logbookPanelRef.value.selectedLogbookUuid = null;
    panelMode.value = 'logbook';
    nextTick(() => {
        if (typeof window !== 'undefined' && todoChatPanelRef.value?.scrollIntoView) {
            todoChatPanelRef.value.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
};

const todoNoteImagePreview = ref('');
const selectedTodoChatStreamRef = ref(null);
const todoChatPanelRef = ref(null);
const currentTimeMs = ref(Date.now());
let currentTimeTicker = null;
let dashboardScrollHandler = null;

const overviewChips = computed(() => [
    { label: 'SYSTEM_CORE', value: String(props.overview?.system_core || 'OFFLINE'), tone: 'danger' },
    { label: 'TOTAL_MEMBER', value: Number(props.overview?.total_member || 0), tone: 'cyan' },
    { label: 'TOTAL_MENTOR', value: Number(props.overview?.total_mentor || 0), tone: 'violet' },
    { label: 'TOTAL_EXPERIMENT', value: Number(props.overview?.total_experiments || 0), tone: 'green' },
    { label: 'REVIEW_QUEUE', value: Number(props.overview?.incoming_review_queue || 0), tone: 'amber' },
]);

const sourceItems = computed(() => {
    return (props.recent_experiments || []).slice(0, 8).map((item) => ({
        id: Number(item?.id || 0),
        title: String(item?.title || 'Untitled Experiment'),
        status: String(item?.status || 'crafting'),
        updated_at: item?.updated_at || null,
        publication_status: String(item?.publication_status || 'draft'),
        is_open_for_collaboration: Boolean(item?.is_open_for_collaboration),
    }));
});

const filteredTodoItems = computed(() => {
    const search = todoSearch.value.trim().toLowerCase();

    return allTodos.value.filter((todo) => {
        const mode = String(todo.assignment_mode || 'self');

        if (todoFilter.value === 'self' && mode !== 'self') {
            return false;
        }

        if (todoFilter.value === 'mentor' && mode !== 'mentor') {
            return false;
        }

        if (search === '') {
            return true;
        }

        const haystack = [
            String(todo.title || ''),
            String(todo.description || ''),
            String(todo.owner?.name || ''),
            String(todo.owner?.username || ''),
            String(todo.mentor?.name || ''),
            String(todo.mentor?.username || ''),
        ].join(' ').toLowerCase();

        return haystack.includes(search);
    });
});

const todoCounters = computed(() => {
    const total = allTodos.value.length;
    const pending = allTodos.value.filter((todo) => !todo.is_completed).length;
    const completed = total - pending;
    const self = allTodos.value.filter((todo) => String(todo.assignment_mode) === 'self').length;
    const mentor = allTodos.value.filter((todo) => String(todo.assignment_mode) === 'mentor').length;

    return { total, pending, completed, self, mentor };
});

const canHireMentor = computed(() => {
    const role = String(authUser.value?.role || '').toLowerCase();
    return !['mentor', 'admin', 'super_admin'].includes(role);
});

const studioTiles = computed(() => ([
    {
        key: 'new_experiment',
        title: 'New Experiment',
        description: 'Mulai creation eksperimen baru',
        routeName: 'profile.creations.create',
        icon: 'fi fi-rr-plus',
    },
    {
        key: 'my_experiments',
        title: 'My Experiments',
        description: 'Kelola eksperimen milikmu',
        routeName: 'profile.creations',
        icon: 'fi fi-rr-folder-open',
    },
    {
        key: 'hall',
        title: 'Hall of Creations',
        description: 'Lihat karya publik komunitas',
        routeName: 'hall.creations.index',
        icon: 'fi fi-rr-lightbulb-on',
    },
    {
        key: 'inbox',
        title: 'Review Inbox',
        description: 'Pantau notifikasi kolaborasi',
        routeName: 'notifications.index',
        icon: 'fi fi-rr-bell',
    },
    ...(canOpenRoadmapLab.value
        ? [{
            key: 'roadmap_lab',
            title: 'Roadmap Lab',
            description: 'Editor visual roadmap mentor',
            routeName: 'dooplab.roadmaps.index',
            icon: 'fi fi-rr-route',
        }]
        : []),
]));

const pipelineSummary = computed(() => ([
    { label: 'All', value: Number(props.my_creation_stats?.total || 0) },
    { label: 'In Progress', value: Number(props.my_creation_stats?.in_progress || 0) },
    { label: 'Finished', value: Number(props.my_creation_stats?.finished || 0) },
    { label: 'Published', value: Number(props.my_creation_stats?.published || 0) },
    { label: 'Open Collab', value: Number(props.my_creation_stats?.open_for_collab || 0) },
]));

const dashboardMetrics = computed(() => ([
    {
        label: 'Total To-Do',
        value: todoCounters.total,
        hint: 'Semua item aktif',
        tone: 'cyan',
    },
    {
        label: 'Pending',
        value: todoCounters.pending,
        hint: 'Perlu tindak lanjut',
        tone: 'amber',
    },
    {
        label: 'Selesai',
        value: todoCounters.completed,
        hint: 'Sudah ditutup',
        tone: 'green',
    },
    {
        label: 'Review Queue',
        value: Number(props.overview?.incoming_review_queue || 0),
        hint: 'Menunggu cek',
        tone: 'violet',
    },
]));

const incomingQueue = computed(() => Number(props.collaboration?.incoming_pending || 0));
const outgoingQueue = computed(() => Number(props.collaboration?.outgoing_pending || 0));

const composerHint = computed(() => {
    if (incomingQueue.value > 0) {
        return `${incomingQueue.value} review request menunggu diproses`;
    }

    if (outgoingQueue.value > 0) {
        return `${outgoingQueue.value} request kolaborasi sedang menunggu respon`;
    }

    return 'Semua antrian kolaborasi saat ini sudah clear';
});

const formatDate = (value) => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';

    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).toUpperCase();
};

const assignmentModeLabel = (mode) => {
    return String(mode || '') === 'mentor' ? 'MENTOR' : 'SELF';
};

const milestoneTypeLabel = (type) => {
    const normalized = String(type || 'task');
    if (normalized === 'milestone') return 'MILESTONE';
    if (normalized === 'checkpoint') return 'CHECKPOINT';
    if (normalized === 'logbook') return 'LOGBOOK';
    return 'TASK';
};

const workflowStatusLabel = (status) => {
    const normalized = String(status || 'todo');
    if (normalized === 'ongoing') return 'ONGOING';
    if (normalized === 'blocked') return 'BLOCKED';
    if (normalized === 'pending_review') return 'PENDING_REVIEW';
    if (normalized === 'approved') return 'APPROVED';
    if (normalized === 'rejected') return 'REJECTED';
    if (normalized === 'done') return 'DONE';
    return 'TODO';
};

const workflowStatusClass = (status) => {
    const normalized = String(status || 'todo');
    if (normalized === 'ongoing') return 'is-ongoing';
    if (normalized === 'blocked') return 'is-blocked';
    if (normalized === 'pending_review') return 'is-pending-review';
    if (normalized === 'approved') return 'is-approved';
    if (normalized === 'rejected') return 'is-rejected';
    if (normalized === 'done') return 'is-done';
    return 'is-pending';
};

const authorRoleLabel = (role) => {
    const normalized = String(role || '').toLowerCase();
    if (normalized === 'mentor') return 'MENTOR';
    if (normalized === 'student') return 'MEMBER';
    if (normalized === 'admin' || normalized === 'super_admin') return 'STAFF';
    return normalized !== '' ? normalized.toUpperCase() : 'USER';
};

const formatDateTime = (value) => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';

    return date.toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDurationLabel = (milliseconds) => {
    const totalMinutes = Math.max(1, Math.floor(milliseconds / 60000));
    const days = Math.floor(totalMinutes / 1440);
    const hours = Math.floor((totalMinutes % 1440) / 60);
    const minutes = totalMinutes % 60;

    if (days > 0) {
        return hours > 0 ? `${days} hari ${hours} jam` : `${days} hari`;
    }

    if (hours > 0) {
        return minutes > 0 ? `${hours} jam ${minutes} menit` : `${hours} jam`;
    }

    return `${minutes} menit`;
};

const todoDeadlineDiff = (deadlineValue) => {
    if (!deadlineValue) return null;
    const deadline = new Date(deadlineValue);
    if (Number.isNaN(deadline.getTime())) return null;

    return deadline.getTime() - currentTimeMs.value;
};

const todoDeadlineLabel = (todo) => {
    if (todo?.is_completed) return 'Checklist selesai';

    const diff = todoDeadlineDiff(todo?.deadline);
    if (diff === null) return 'Tanpa deadline';

    if (diff < 0) {
        return `Lewat ${formatDurationLabel(Math.abs(diff))}`;
    }

    return `Sisa ${formatDurationLabel(diff)}`;
};

const todoDeadlineClass = (todo) => {
    if (todo?.is_completed) return 'is-done';

    const diff = todoDeadlineDiff(todo?.deadline);
    if (diff === null) return 'is-none';
    if (diff < 0) return 'is-overdue';
    if (diff <= 6 * 60 * 60 * 1000) return 'is-urgent';
    if (diff <= 24 * 60 * 60 * 1000) return 'is-soon';

    return 'is-safe';
};

const toDateTimeLocalInput = (value) => {
    if (!value) return '';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    const offsetMs = date.getTimezoneOffset() * 60 * 1000;
    return new Date(date.getTime() - offsetMs).toISOString().slice(0, 16);
};

const toIsoOrNull = (value) => {
    const raw = String(value || '').trim();
    if (raw === '') return null;

    const date = new Date(raw);
    if (Number.isNaN(date.getTime())) return null;

    return date.toISOString();
};

const todoAccessHint = (todo) => {
    const mode = String(todo?.assignment_mode || 'self');

    if (mode === 'mentor') {
        return todo?.can_toggle
            ? 'Checklist ini hanya bisa dicentang mentor penugas.'
            : 'Checklist ini dikontrol mentor. Kamu hanya bisa melihat progres.';
    }

    return 'Checklist pribadi, kamu yang kontrol centang.';
};

const openTodoModal = () => {
    todoModalMode.value = 'create';
    editingTodoUuid.value = null;
    todoForm.reset();
    todoForm.title = '';
    todoForm.description = '';
    todoForm.start_at = '';
    todoForm.deadline = '';
    todoForm.notify_deadline_email = false;
    todoForm.assignment_mode = 'self';
    todoForm.owner_user_id = null;
    todoForm.creation_id = Number(researchWorkspaces.value?.[0]?.id || 0) || null;
    todoForm.milestone_type = 'task';
    todoForm.clearErrors();
    showTodoModal.value = true;
};

const closeTodoModal = () => {
    showTodoModal.value = false;
    todoModalMode.value = 'create';
    editingTodoUuid.value = null;
    todoForm.clearErrors();
};

const openTodoEditModal = (todo) => {
    if (!todo?.can_edit) return;

    todoModalMode.value = 'edit';
    editingTodoUuid.value = String(todo?.uuid || '');
    todoForm.reset();
    todoForm.title = String(todo?.title || '');
    todoForm.description = String(todo?.description || '');
    todoForm.start_at = toDateTimeLocalInput(todo?.start_at || '');
    todoForm.deadline = toDateTimeLocalInput(todo?.deadline || '');
    todoForm.notify_deadline_email = Boolean(todo?.notify_deadline_email);
    todoForm.assignment_mode = String(todo?.assignment_mode || 'self');
    todoForm.owner_user_id = Number(todo?.owner?.id || 0) || null;
    todoForm.creation_id = Number(todo?.creation?.id || 0) || null;
    todoForm.logbook_id = Number(todo?.logbook_id || 0) || null;
    todoForm.milestone_type = String(todo?.milestone_type || 'task');
    todoForm.clearErrors();
    showTodoModal.value = true;
};

const openTodoDetail = (todo) => {
    panelMode.value = 'todo';
    selectedTodoUuid.value = String(todo?.uuid || '');
    nextTick(() => {
        if (typeof window !== 'undefined' && todoChatPanelRef.value?.scrollIntoView) {
            todoChatPanelRef.value.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
};

const clearSelectedTodo = () => {
    selectedTodoUuid.value = null;
    panelMode.value = 'summary';
};

const showLearningPaths = () => {
    selectedTodoUuid.value = null;
    panelMode.value = 'learning_paths';
    nextTick(() => {
        if (typeof window !== 'undefined' && todoChatPanelRef.value?.scrollIntoView) {
            todoChatPanelRef.value.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
};

const showMentorInvites = () => {
    selectedTodoUuid.value = null;
    panelMode.value = 'mentor_invites';
    nextTick(() => {
        if (typeof window !== 'undefined' && todoChatPanelRef.value?.scrollIntoView) {
            todoChatPanelRef.value.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
};

const showTodoList = () => {
    selectedTodoUuid.value = null;
    panelMode.value = 'summary';
    nextTick(() => {
        if (typeof window !== 'undefined' && todoChatPanelRef.value?.scrollIntoView) {
            todoChatPanelRef.value.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
};

const showHireMentor = () => {
    selectedTodoUuid.value = null;
    panelMode.value = 'hire_mentor';
    nextTick(() => {
        if (typeof window !== 'undefined' && todoChatPanelRef.value?.scrollIntoView) {
            todoChatPanelRef.value.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
};

const workPanelTitle = computed(() => {
    if (selectedTodo.value) return selectedTodo.value.title || 'Untitled To-Do';
    if (panelMode.value === 'learning_paths') return 'My Learning Path';
    if (panelMode.value === 'hire_mentor') return 'Hire Mentor';
    if (panelMode.value === 'mentor_invites') return 'Mentor Invites';
    if (panelMode.value === 'logbook') return logbookPanelRef.value?.selectedLogbook ? logbookPanelRef.value.selectedLogbook.title : 'Logbook';
    return 'To-Do List';
});

const workPanelSubtitle = computed(() => {
    if (selectedTodo.value) return 'Catatan dan bukti pengerjaan ditampilkan di bawah.';
    if (panelMode.value === 'learning_paths') return 'Roadmap mentoring yang ditugaskan untukmu.';
    if (panelMode.value === 'hire_mentor') return 'Hubungkan mentor langsung ke akun DoopLab kamu.';
    if (panelMode.value === 'mentor_invites') return 'Accept atau reject invite mentor dari member DoopLab.';
    if (panelMode.value === 'logbook') return logbookPanelRef.value?.selectedLogbook ? `${logbookPanelRef.value.selectedLogbook.entries?.length || 0} entri kegiatan` : 'Pilih logbook untuk melihat entri kegiatan.';
    return 'Pilih to-do dari daftar untuk mulai mencatat progres.';
});

const selectedTodoNotes = computed(() => {
    if (!selectedTodo.value) return [];

    const notes = Array.isArray(selectedTodo.value.notes) ? selectedTodo.value.notes : [];

    return [...notes].sort((a, b) => {
        const timeA = new Date(a?.created_at || 0).getTime();
        const timeB = new Date(b?.created_at || 0).getTime();
        return timeA - timeB;
    });
});

const clearTodoNoteForm = () => {
    if (todoNoteImagePreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(todoNoteImagePreview.value);
    }
    todoNoteForm.note = '';
    todoNoteForm.image = null;
    todoNoteForm.clearErrors();
    todoNoteImagePreview.value = '';
};

watch(selectedTodoUuid, () => {
    clearTodoNoteForm();
    nextTick(() => {
        if (typeof window !== 'undefined' && todoChatPanelRef.value?.scrollIntoView) {
            todoChatPanelRef.value.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        const list = selectedTodoChatStreamRef.value;
        if (!list) return;
        list.scrollTop = list.scrollHeight;
    });
});

watch([panelMode, selectedTodoUuid, todoFilter, todoSearch, () => hireMentorForm.value.creation_id], persistDashboardState);

watch(allTodos, () => {
    if (selectedTodoUuid.value && !selectedTodo.value) {
        selectedTodoUuid.value = null;
        panelMode.value = 'summary';
    }
});

watch(() => [todoForm.assignment_mode, todoForm.owner_user_id], () => {
    const creations = visibleResearchWorkspaces.value;
    const selectedWorkspaceId = Number(todoForm.creation_id || 0);

    if (selectedWorkspaceId > 0 && creations.some((creation) => Number(creation?.id || 0) === selectedWorkspaceId)) {
        return;
    }

    todoForm.creation_id = Number(creations?.[0]?.id || 0) || null;
});

watch(() => hireMentorForm.value.creation_id, () => {
    hireMentorForm.value.mentor_user_id = null;
});

watch(() => selectedTodoNotes.value.length, () => {
    nextTick(() => {
        const list = selectedTodoChatStreamRef.value;
        if (!list) return;
        list.scrollTop = list.scrollHeight;
    });
});

const submitTodo = () => {
    const isEditMode = todoModalMode.value === 'edit';

    if (isEditMode) {
        const targetUuid = String(editingTodoUuid.value || selectedTodo.value?.uuid || '');
        if (targetUuid === '') return;

        todoForm.transform(() => ({
            title: todoForm.title,
            description: todoForm.description,
            start_at: toIsoOrNull(todoForm.start_at),
            deadline: toIsoOrNull(todoForm.deadline),
            notify_deadline_email: Boolean(todoForm.notify_deadline_email),
            creation_id: todoForm.milestone_type !== 'logbook' ? (todoForm.creation_id || null) : null,
            logbook_id: todoForm.milestone_type === 'logbook' ? (todoForm.logbook_id || null) : null,
            milestone_type: todoForm.milestone_type,
        })).patch(route('dooplab.todos.update', targetUuid), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeTodoModal();
            },
        });

        return;
    }

    const payload = {
        title: todoForm.title,
        description: todoForm.description,
        start_at: toIsoOrNull(todoForm.start_at),
        deadline: toIsoOrNull(todoForm.deadline),
        notify_deadline_email: Boolean(todoForm.notify_deadline_email),
        creation_id: todoForm.milestone_type !== 'logbook' ? (todoForm.creation_id || null) : null,
        logbook_id: todoForm.milestone_type === 'logbook' ? (todoForm.logbook_id || null) : null,
        milestone_type: todoForm.milestone_type,
        // tipe logbook selalu mentor-assigned karena ditujukan ke member
        assignment_mode: (todoForm.milestone_type === 'logbook' || (canCreateMentorTodo.value && todoForm.assignment_mode === 'mentor')) ? 'mentor' : 'self',
        owner_user_id: todoForm.milestone_type === 'logbook'
            ? null  // backend handle broadcast ke semua member logbook
            : ((canCreateMentorTodo.value && todoForm.assignment_mode === 'mentor') ? todoForm.owner_user_id : null),
    };

    todoForm.transform(() => payload).post(route('dooplab.todos.store'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            closeTodoModal();
        },
    });
};

const hireMentor = async () => {
    const mentorUserId = Number(hireMentorForm.value.mentor_user_id || 0);

    if (!canHireMentor.value || mentorUserId <= 0 || hiringMentor.value) {
        return;
    }

    hiringMentor.value = true;

    try {
        await window.axios.post(
            route('api.dooplab.hire-mentor', {}, false),
            { mentor_user_id: mentorUserId }
        );
        hireMentorForm.value.mentor_user_id = null;
        toast.success('INVITE_SENT', 'Invite terkirim. Menunggu mentor accept.');
        router.reload({
            preserveScroll: true,
            preserveState: true,
            only: ['direct_mentors', 'mentor_invites', 'todo_assignable_users', 'logbook_assignable_users'],
        });
    } catch (error) {
        toast.error('HIRE_FAILED', error?.response?.data?.errors?.mentor_user_id?.[0] || error?.response?.data?.message || 'Gagal hire mentor.');
    } finally {
        hiringMentor.value = false;
    }
};

const respondMentorInvite = async (invite, decision) => {
    const inviteId = Number(invite?.id || 0);
    if (inviteId <= 0) return;

    try {
        await window.axios.post(route(`api.creations.mentor-invites.${decision}`, { collaborationRequest: inviteId }, false));
        toast.success(decision === 'accept' ? 'INVITE_ACCEPTED' : 'INVITE_REJECTED', decision === 'accept' ? 'Creation sudah terhubung.' : 'Invite sudah ditolak.');
        router.reload({
            preserveScroll: true,
            preserveState: true,
            only: ['mentor_invites', 'research_workspaces', 'hireable_creations', 'direct_mentors', 'todos', 'todo_assignable_users', 'logbook_assignable_users'],
        });
    } catch (error) {
        toast.error('ACTION_FAILED', error?.response?.data?.message || 'Gagal memproses invite.');
    }
};

const toggleTodo = (todo) => {
    if (!todo?.can_toggle) return;

    router.patch(route('dooplab.todos.toggle', todo.uuid), {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const deleteTodo = (todo) => {
    if (!todo?.can_delete) return;
    if (!window.confirm('Hapus to-do ini?')) return;
    if (String(selectedTodoUuid.value || '') === String(todo.uuid || '')) {
        clearSelectedTodo();
    }

    router.delete(route('dooplab.todos.destroy', todo.uuid), {
        preserveScroll: true,
        preserveState: true,
    });
};

const submitTodoForReview = (todo) => {
    if (!todo?.can_submit_review) return;

    router.patch(route('dooplab.todos.submit-review', todo.uuid), {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const reviewTodoCheckpoint = (todo, decision) => {
    if (!todo?.can_review) return;
    openReviewForm(decision);
};

const submitReviewCheckpoint = () => {
    if (!selectedTodo.value?.can_review || !reviewForm.value.decision) return;

    router.patch(route('dooplab.todos.review', selectedTodo.value.uuid), {
        decision: reviewForm.value.decision,
        review_note: reviewForm.value.note.trim() || null,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => closeReviewForm(),
    });
};

const onTodoNoteImageChange = (event) => {
    const input = event?.target;
    const file = input?.files?.[0] || null;
    if (todoNoteImagePreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(todoNoteImagePreview.value);
    }
    todoNoteForm.image = file;

    if (!file) {
        todoNoteImagePreview.value = '';
        return;
    }

    todoNoteImagePreview.value = URL.createObjectURL(file);
};

const removeTodoNoteImage = () => {
    if (todoNoteImagePreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(todoNoteImagePreview.value);
    }
    todoNoteForm.image = null;
    todoNoteImagePreview.value = '';
};

const submitTodoNote = () => {
    if (!selectedTodo.value) return;

    todoNoteForm.post(route('dooplab.todos.notes.store', selectedTodo.value.uuid), {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
        onSuccess: () => {
            clearTodoNoteForm();
        },
    });
};

onMounted(() => {
    if (typeof window === 'undefined') return;

    if (panelMode.value === 'hire_mentor' && !canHireMentor.value) {
        panelMode.value = 'learning_paths';
    }

    if (panelMode.value === 'mentor_invites' && !canCreateMentorTodo.value) {
        panelMode.value = 'learning_paths';
    }

    const restoredScrollY = Number(initialDashboardState.scrollY || 0);
    if (restoredScrollY > 0) {
        nextTick(() => window.scrollTo({ top: restoredScrollY, behavior: 'auto' }));
    }

    dashboardScrollHandler = () => persistDashboardState();
    window.addEventListener('scroll', dashboardScrollHandler, { passive: true });

    currentTimeTicker = window.setInterval(() => {
        currentTimeMs.value = Date.now();
    }, 60000);
});

onUnmounted(() => {
    if (currentTimeTicker !== null && typeof window !== 'undefined') {
        window.clearInterval(currentTimeTicker);
    }

    if (dashboardScrollHandler !== null && typeof window !== 'undefined') {
        window.removeEventListener('scroll', dashboardScrollHandler);
    }
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="DOOPTECH | DoopLab Dashboard">
            <meta head-key="description" name="description" content="Dashboard internal DoopLab oleh DOOPTECH." />
            <meta head-key="robots" name="robots" content="noindex,nofollow,noarchive" />
            <link head-key="canonical" rel="canonical" :href="route('landing')" />
        </Head>

        <div class="nb-root">
            <Teleport to="body">
                <div class="nb-aurora">
                    <img src="/images/Gerbang_lab_pixel_art_website (3).jpeg" alt="" class="hidden md:block" />
                </div>
            </Teleport>
            <div class="nb-shell">
                <header class="nb-topbar">
                    <div class="nb-title-wrap">
                        <div class="nb-logo">
                            <i class="fi fi-rr-apps"></i>
                        </div>
                        <div>
                            <p class="nb-eyebrow">DOOPLAB DASHBOARD</p>
                            <h1 class="nb-title">Halo, {{ authUser?.name || 'Researcher' }}</h1>
                            <p class="nb-subtitle">Kelola to-do, catatan, dan progres eksperimen dari satu tempat.</p>
                        </div>
                    </div>

                    <div class="nb-actions">
                        <Link :href="route('creations.index')" class="nb-btn nb-btn--solid">Buat Creation</Link>
                    </div>
                </header>

                <section class="nb-workbench">
                    <aside class="nb-panel nb-sources nb-todo-nav">
                        <button
                            v-if="canHireMentor"
                            type="button"
                            class="source-add-btn"
                            @click="showHireMentor"
                        >
                            <i class="fi fi-rr-user-add"></i>
                            Hire Mentor
                        </button>

                        <button
                            v-if="canCreateMentorTodo"
                            type="button"
                            class="source-add-btn"
                            @click="showMentorInvites"
                        >
                            <i class="fi fi-rr-envelope"></i>
                            Mentor Invites ({{ mentorInvites.length }})
                        </button>

                        <button
                            type="button"
                            class="source-add-btn"
                            @click="showLearningPaths"
                        >
                            <i class="fi fi-rr-road"></i>
                            My Learning Path
                        </button>

                        <button
                            type="button"
                            class="source-add-btn"
                            :class="{ 'is-active': panelMode === 'summary' }"
                            @click="showTodoList"
                        >
                            <i class="fi fi-rr-list-check"></i>
                            To-Do List
                        </button>

                        <button
                            type="button"
                            class="source-add-btn"
                            :class="{ 'is-active': panelMode === 'logbook' }"
                            @click="showLogbook"
                        >
                            <i class="fi fi-rr-book-alt"></i>
                            Logbook ({{ allLogbooks.length }})
                        </button>

                    </aside>

                    <main ref="todoChatPanelRef" class="nb-panel nb-chat">
                        <div class="panel-head panel-head--stacked">
                            <div>
                                <h2>{{ workPanelTitle }}</h2>
                                <p class="panel-subtitle">
                                    {{ workPanelSubtitle }}
                                </p>
                            </div>
                            <button
                                v-if="panelMode === 'summary' && !selectedTodo"
                                type="button"
                                class="source-add-btn todo-add-btn"
                                @click="openTodoModal"
                            >
                                <i class="fi fi-rr-plus"></i>
                                Tambahkan to-do
                            </button>
                            <button
                                v-if="panelMode === 'logbook' && !logbookPanelRef?.selectedLogbook"
                                type="button"
                                class="source-add-btn todo-add-btn"
                                @click="logbookPanelRef?.openLogbookModal()"
                            >
                                <i class="fi fi-rr-plus"></i>
                                Buat Logbook
                            </button>
                            <button
                                v-if="panelMode === 'logbook' && !logbookPanelRef?.selectedLogbook && canCreateMentorTodo"
                                type="button"
                                class="source-add-btn todo-add-btn"
                                style="background:var(--violet,#7c3aed)"
                                @click="logbookPanelRef?.openLogbookAssignModal()"
                            >
                                <i class="fi fi-rr-user-add"></i>
                                Assign ke User
                            </button>
                            <div v-if="panelMode === 'logbook' && logbookPanelRef?.selectedLogbook" class="logbook-toolbar">
                                <button type="button" class="chat-back-btn" @click="logbookPanelRef.selectedLogbookUuid = null">
                                    <i class="fi fi-rr-arrow-small-left"></i>
                                    Kembali
                                </button>
                                <button type="button" class="source-add-btn todo-add-btn" @click="logbookPanelRef?.openEntryModal()">
                                    <i class="fi fi-rr-plus"></i>
                                    Tambah Entri
                                </button>
                                <button
                                    v-if="logbookPanelRef?.selectedLogbook?.entries?.length"
                                    type="button"
                                    class="source-add-btn todo-add-btn"
                                    style="background:rgba(87,214,255,0.08);color:var(--cyan)"
                                    @click="logbookPanelRef?.exportEntryCsv()"
                                >
                                    <i class="fi fi-rr-file-csv"></i>
                                    Export CSV
                                </button>
                            </div>
                            <button v-if="selectedTodo" type="button" class="chat-back-btn" @click="clearSelectedTodo">
                                <i class="fi fi-rr-arrow-small-left"></i>
                                Kembali
                            </button>
                        </div>

                        <template v-if="panelMode === 'todo_form'">
                            <section class="todo-form-workspace custom-scroll">
                                <form class="todo-panel-form" @submit.prevent="submitTodo">
                                    <label class="todo-field">
                                        <span>Judul</span>
                                        <input v-model="todoForm.title" type="text" maxlength="160" required placeholder="Contoh: Review modul sistem">
                                        <small v-if="todoForm.errors.title" class="todo-error">{{ todoForm.errors.title }}</small>
                                    </label>

                                    <label class="todo-field">
                                        <span>Deskripsi (opsional)</span>
                                        <textarea
                                            v-model="todoForm.description"
                                            rows="3"
                                            maxlength="1000"
                                            placeholder="Catatan singkat to-do"
                                        ></textarea>
                                        <small v-if="todoForm.errors.description" class="todo-error">{{ todoForm.errors.description }}</small>
                                    </label>

                                    <div class="todo-date-grid">
                                        <label class="todo-field todo-field--date">
                                            <span>Start At (opsional)</span>
                                            <input v-model="todoForm.start_at" type="datetime-local">
                                            <small class="todo-error todo-error--slot" :class="{ 'is-hidden': !todoForm.errors.start_at }">
                                                {{ todoForm.errors.start_at || '\u00A0' }}
                                            </small>
                                        </label>

                                        <label class="todo-field todo-field--date">
                                            <span>Deadline (opsional)</span>
                                            <input v-model="todoForm.deadline" type="datetime-local">
                                            <small class="todo-error todo-error--slot" :class="{ 'is-hidden': !todoForm.errors.deadline }">
                                                {{ todoForm.errors.deadline || '\u00A0' }}
                                            </small>
                                        </label>
                                    </div>

                                    <div class="todo-date-grid">
                                        <label class="todo-field todo-field--date">
                                            <span>Tipe Item</span>
                                            <select v-model="todoForm.milestone_type">
                                                <option value="task">Task</option>
                                                <option value="milestone">Milestone</option>
                                                <option value="checkpoint">Checkpoint</option>
                                                <option value="logbook">Logbook</option>
                                            </select>
                                            <small v-if="todoForm.errors.milestone_type" class="todo-error">{{ todoForm.errors.milestone_type }}</small>
                                        </label>

                                        <label v-if="todoForm.milestone_type !== 'logbook'" class="todo-field todo-field--date">
                                            <span>Creation Riset</span>
                                            <select v-model="todoForm.creation_id">
                                                <option :value="null">Tanpa creation</option>
                                                <option v-for="creation in visibleResearchWorkspaces" :key="creation.id" :value="creation.id">
                                                    {{ creation.title }}<template v-if="creation.owner_name"> — {{ creation.owner_name }}</template>
                                                </option>
                                            </select>
                                            <small v-if="todoForm.assignment_mode === 'mentor' && todoForm.owner_user_id && !visibleResearchWorkspaces.length" class="todo-field-note">
                                                Member ini belum hire mentor ke creation.
                                            </small>
                                            <small v-if="todoForm.errors.creation_id" class="todo-error">{{ todoForm.errors.creation_id }}</small>
                                        </label>
                                        <label v-else class="todo-field todo-field--date">
                                            <span>Target Logbook</span>
                                            <select v-model="todoForm.logbook_id" required>
                                                <option :value="null">Pilih logbook</option>
                                                <option v-for="lb in mentorLogbooks" :key="lb.uuid" :value="lb.id">{{ lb.title }}</option>
                                            </select>
                                            <small v-if="todoForm.errors.logbook_id" class="todo-error">{{ todoForm.errors.logbook_id }}</small>
                                        </label>
                                    </div>

                                    <label class="todo-checkbox-field">
                                        <input v-model="todoForm.notify_deadline_email" type="checkbox">
                                        <span>Berikan notifikasi deadline di email</span>
                                    </label>
                                    <small class="todo-field-note">
                                        Jika tidak dicentang, pengingat deadline hanya muncul di notifikasi aplikasi.
                                    </small>
                                    <small v-if="todoForm.errors.notify_deadline_email" class="todo-error">{{ todoForm.errors.notify_deadline_email }}</small>

                                    <label v-if="canCreateMentorTodo" class="todo-field">
                                        <span>Jenis Penugasan</span>
                                        <select v-model="todoForm.assignment_mode">
                                            <option value="self">Self (saya centang sendiri)</option>
                                            <option value="mentor">Mentor Assigned (untuk member)</option>
                                        </select>
                                        <small v-if="todoForm.errors.assignment_mode" class="todo-error">{{ todoForm.errors.assignment_mode }}</small>
                                    </label>

                                    <label
                                        v-if="canCreateMentorTodo && (todoForm.assignment_mode === 'mentor')"
                                        class="todo-field"
                                    >
                                        <span>Target Member</span>
                                        <select v-model="todoForm.owner_user_id" required>
                                            <option :value="null">Pilih member</option>
                                            <option
                                                v-for="member in todo_assignable_users"
                                                :key="member.id"
                                                :value="member.id"
                                            >
                                                {{ member.name }} (@{{ member.username || '-' }})
                                            </option>
                                        </select>
                                        <small v-if="todoForm.errors.owner_user_id" class="todo-error">{{ todoForm.errors.owner_user_id }}</small>
                                    </label>

                                    <div class="todo-modal-actions">
                                        <button type="button" class="nb-btn nb-btn--ghost" @click="clearSelectedTodo">Batal</button>
                                        <button type="submit" class="nb-btn nb-btn--solid" :disabled="todoForm.processing">
                                            {{ todoForm.processing ? 'Menyimpan...' : 'Simpan To-Do' }}
                                        </button>
                                    </div>
                                </form>
                            </section>
                        </template>

                        <template v-else-if="selectedTodo">
                            <section ref="selectedTodoChatStreamRef" class="chat-stream custom-scroll">
                                <article class="chat-bubble">
                                    <p class="chat-role">Catatan & Bukti</p>
                                    <p v-if="!selectedTodoNotes.length" class="note-empty">
                                        Belum ada catatan untuk to-do ini.
                                    </p>

                                    <div class="todo-notes-list">
                                        <div
                                            v-for="(item, index) in selectedTodoNotes"
                                            :key="item.id"
                                            class="todo-note-item"
                                            :class="{ 'todo-note-item--latest': index === selectedTodoNotes.length - 1 }"
                                        >
                                            <div class="todo-note-head">
                                                <div class="todo-note-author">
                                                    <span class="todo-note-avatar">
                                                        {{ String(item.author?.name || 'U').slice(0, 1).toUpperCase() }}
                                                    </span>
                                                    <div>
                                                        <p>{{ item.author?.name || 'User' }}</p>
                                                        <span>{{ authorRoleLabel(item.author?.role) }} | {{ formatDateTime(item.created_at) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <p v-if="item.note" class="todo-note-text">{{ item.note }}</p>
                                            <a v-if="item.image_url" :href="item.image_url" target="_blank" rel="noopener noreferrer" class="todo-note-image-link">
                                                <img :src="item.image_url" alt="Bukti pengerjaan todo" class="todo-note-image">
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            </section>

                            <div v-if="reviewForm.show" class="review-inline-form">
                                <p class="review-inline-title">
                                    <i :class="reviewForm.decision === 'approve' ? 'fi fi-rr-badge-check' : 'fi fi-rr-cross-circle'"></i>
                                    {{ reviewForm.decision === 'approve' ? 'Approve Checkpoint' : 'Reject Checkpoint' }}
                                </p>
                                <textarea
                                    v-model="reviewForm.note"
                                    rows="3"
                                    maxlength="1200"
                                    class="todo-note-textarea"
                                    :placeholder="reviewForm.decision === 'approve' ? 'Catatan approval (opsional)' : 'Alasan reject/revisi (opsional)'"
                                ></textarea>
                                <div class="review-inline-actions">
                                    <button type="button" class="nb-btn nb-btn--ghost" @click="closeReviewForm">Batal</button>
                                    <button
                                        type="button"
                                        :class="reviewForm.decision === 'approve' ? 'nb-btn nb-btn--success' : 'nb-btn nb-btn--danger'"
                                        @click="submitReviewCheckpoint"
                                    >
                                        {{ reviewForm.decision === 'approve' ? 'Approve' : 'Reject' }}
                                    </button>
                                </div>
                            </div>

                            <div class="chat-composer chat-composer--todo">
                                <form class="todo-note-form" @submit.prevent="submitTodoNote">
                                    <div class="todo-note-form-head">
                                        <div>
                                            <label class="todo-note-label">Tambahkan catatan</label>
                                            <p class="todo-note-helper">Tulis progres, feedback mentor, atau lampirkan bukti pengerjaan.</p>
                                        </div>
                                    </div>
                                    <textarea
                                        v-model="todoNoteForm.note"
                                        rows="3"
                                        maxlength="2000"
                                        class="todo-note-textarea"
                                        placeholder="Tulis catatan, feedback mentor, atau progres member..."
                                    ></textarea>
                                    <small v-if="todoNoteForm.errors.note" class="todo-error">{{ todoNoteForm.errors.note }}</small>

                                    <div class="todo-note-upload-row">
                                        <div class="todo-note-upload-actions">
                                            <label class="todo-upload-btn">
                                                <input type="file" accept="image/png,image/jpeg,image/jpg,image/webp" @change="onTodoNoteImageChange">
                                                <i class="fi fi-rr-picture"></i>
                                                Lampirkan gambar bukti
                                            </label>
                                            <button
                                                v-if="todoNoteForm.image"
                                                type="button"
                                                class="todo-upload-remove"
                                                @click="removeTodoNoteImage"
                                            >
                                                <i class="fi fi-rr-trash"></i>
                                                Hapus gambar
                                            </button>
                                        </div>
                                        <button
                                            type="submit"
                                            class="todo-note-submit"
                                            :disabled="todoNoteForm.processing || !selectedTodo.can_add_note"
                                        >
                                            <i class="fi fi-rr-paper-plane"></i>
                                            {{ todoNoteForm.processing ? 'Mengirim...' : 'Kirim Catatan' }}
                                        </button>
                                    </div>

                                    <small v-if="todoNoteForm.errors.image" class="todo-error">{{ todoNoteForm.errors.image }}</small>
                                    <p v-if="!selectedTodo.can_add_note" class="note-empty">
                                        Kamu tidak punya izin menambahkan catatan pada to-do ini.
                                    </p>

                                    <div v-if="todoNoteImagePreview" class="todo-note-preview">
                                        <img :src="todoNoteImagePreview" alt="Preview bukti todo">
                                    </div>
                                </form>

                                <div class="todo-inline-actions todo-inline-actions--separate">
                                    <div class="todo-inline-actions-left">
                                        <button
                                            v-if="selectedTodo.can_edit"
                                            type="button"
                                            class="todo-icon-btn"
                                            title="Edit to-do"
                                            aria-label="Edit to-do"
                                            @click="openTodoEditModal(selectedTodo)"
                                        >
                                            <i class="fi fi-rr-pencil"></i>
                                        </button>
                                        <button
                                            v-if="selectedTodo.can_delete"
                                            type="button"
                                            class="todo-icon-btn todo-icon-btn--danger"
                                            title="Hapus to-do"
                                            aria-label="Hapus to-do"
                                            @click="deleteTodo(selectedTodo)"
                                        >
                                            <i class="fi fi-rr-trash"></i>
                                        </button>
                                    </div>

                                    <div class="todo-inline-actions-right">
                                        <button
                                            v-if="selectedTodo.can_submit_review"
                                            type="button"
                                            class="todo-icon-btn"
                                            title="Submit checkpoint ke mentor"
                                            aria-label="Submit checkpoint ke mentor"
                                            @click="submitTodoForReview(selectedTodo)"
                                        >
                                            <i class="fi fi-rr-paper-plane"></i>
                                        </button>
                                        <button
                                            v-if="selectedTodo.can_review && selectedTodo.workflow_status === 'pending_review'"
                                            type="button"
                                            class="todo-icon-btn todo-icon-btn--success"
                                            title="Approve checkpoint"
                                            aria-label="Approve checkpoint"
                                            @click="reviewTodoCheckpoint(selectedTodo, 'approve')"
                                        >
                                            <i class="fi fi-rr-badge-check"></i>
                                        </button>
                                        <button
                                            v-if="selectedTodo.can_review && selectedTodo.workflow_status === 'pending_review'"
                                            type="button"
                                            class="todo-icon-btn todo-icon-btn--danger"
                                            title="Reject checkpoint"
                                            aria-label="Reject checkpoint"
                                            @click="reviewTodoCheckpoint(selectedTodo, 'reject')"
                                        >
                                            <i class="fi fi-rr-cross-circle"></i>
                                        </button>
                                        <button
                                            type="button"
                                            class="todo-icon-btn"
                                            :class="selectedTodo.is_completed ? 'todo-icon-btn--danger' : 'todo-icon-btn--success'"
                                            :disabled="!selectedTodo.can_toggle"
                                            :title="selectedTodo.is_completed ? 'Batal centang' : 'Centang selesai'"
                                            :aria-label="selectedTodo.is_completed ? 'Batal centang' : 'Centang selesai'"
                                            @click="toggleTodo(selectedTodo)"
                                        >
                                            <i :class="selectedTodo.is_completed ? 'fi fi-rr-cross' : 'fi fi-rr-check'"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template v-else-if="panelMode === 'summary'">
                            <section class="todo-list-workspace custom-scroll">
                                <label class="source-search">
                                    <i class="fi fi-rr-search"></i>
                                    <input
                                        v-model="todoSearch"
                                        type="text"
                                        placeholder="Cari to-do"
                                    >
                                </label>

                                <div class="todo-filters">
                                    <button
                                        type="button"
                                        :class="['todo-filter', todoFilter === 'all' ? 'is-active' : '']"
                                        @click="todoFilter = 'all'"
                                    >
                                        Semua ({{ todoCounters.total }})
                                    </button>
                                    <button
                                        type="button"
                                        :class="['todo-filter', todoFilter === 'self' ? 'is-active' : '']"
                                        @click="todoFilter = 'self'"
                                    >
                                        Self ({{ todoCounters.self }})
                                    </button>
                                    <button
                                        type="button"
                                        :class="['todo-filter', todoFilter === 'mentor' ? 'is-active' : '']"
                                        @click="todoFilter = 'mentor'"
                                    >
                                        Mentor ({{ todoCounters.mentor }})
                                    </button>
                                </div>

                                <nav class="todo-nav-list" aria-label="Daftar to-do">
                                    <div class="todo-nav-header">
                                        <span>To-Do Aktif</span>
                                        <span>{{ filteredTodoItems.length }}</span>
                                    </div>

                                    <p v-if="!filteredTodoItems.length" class="source-empty">
                                        Belum ada to-do. Klik "Tambahkan to-do" untuk mulai tracking progres.
                                    </p>

                                    <article
                                        v-for="item in filteredTodoItems"
                                        :key="item.uuid"
                                        class="todo-nav-item"
                                        :class="{
                                            'is-completed': item.is_completed,
                                            'is-active': String(selectedTodoUuid || '') === String(item.uuid || ''),
                                        }"
                                        role="button"
                                        tabindex="0"
                                        @mousedown="$event.currentTarget._dragStartX = $event.clientX; $event.currentTarget._dragStartY = $event.clientY"
                                        @click="(Math.abs($event.clientX - ($event.currentTarget._dragStartX||$event.clientX)) + Math.abs($event.clientY - ($event.currentTarget._dragStartY||$event.clientY)) < 5) && openTodoDetail(item)"
                                        @keydown.enter.prevent="openTodoDetail(item)"
                                        @keydown.space.prevent="openTodoDetail(item)"
                                    >
                                        <button
                                            class="todo-nav-check"
                                            :class="{ 'is-done': item.is_completed }"
                                            type="button"
                                            :disabled="!item.can_toggle"
                                            :title="item.can_toggle ? 'Toggle status to-do' : 'Tidak punya izin untuk centang item ini'"
                                            @click.stop="item.can_toggle && toggleTodo(item)"
                                        >
                                            <i v-if="item.is_completed" class="fi fi-rr-check"></i>
                                        </button>

                                        <span class="todo-nav-body">
                                            <span class="todo-nav-title">{{ item.title }}</span>
                                            <span class="todo-nav-sub">
                                                <span class="todo-nav-meta">{{ item.owner?.name || '-' }}</span>
                                                <span
                                                    class="todo-nav-deadline"
                                                    :class="todoDeadlineClass(item)"
                                                >
                                                    <i class="fi fi-rr-clock-three"></i>
                                                    {{ todoDeadlineLabel(item) }}
                                                </span>
                                            </span>
                                            <span class="todo-nav-tags">
                                                <span class="todo-badge">{{ assignmentModeLabel(item.assignment_mode) }}</span>
                                                <span class="todo-badge">{{ milestoneTypeLabel(item.milestone_type) }}</span>
                                                <span class="todo-state" :class="workflowStatusClass(item.workflow_status)">
                                                    {{ workflowStatusLabel(item.workflow_status) }}
                                                </span>
                                            </span>
                                        </span>

                                        <i class="fi fi-rr-angle-small-right todo-nav-arrow"></i>
                                    </article>
                                </nav>
                            </section>
                        </template>

                        <template v-else-if="panelMode === 'learning_paths'">
                            <section class="learning-path-list custom-scroll">
                                <p v-if="!learningPaths.length" class="source-empty">
                                    Belum ada learning path yang ditugaskan.
                                </p>

                                <Link
                                    v-for="path in learningPaths"
                                    :key="path.uuid"
                                    :href="route('dooplab.roadmaps.enrollments.show', path.uuid)"
                                    class="learning-path-card"
                                >
                                    <div class="learning-path-body">
                                        <div class="learning-path-topline">
                                            <span>Learning Path</span>
                                            <strong>Status: {{ String(path.status || 'active').toUpperCase() }}</strong>
                                        </div>
                                        <h3>{{ path.roadmap?.title || 'Untitled Roadmap' }}</h3>
                                        <div class="learning-path-meta">
                                            <span>Mentor: {{ path.mentor_name || '-' }}</span>
                                        </div>
                                    </div>

                                    <span class="learning-path-cta">
                                        Open
                                        <i class="fi fi-rr-angle-small-right"></i>
                                    </span>
                                </Link>
                            </section>
                        </template>

                        <template v-else-if="panelMode === 'hire_mentor'">
                            <section class="hire-mentor-workspace">
                                <div class="hire-status-inline">
                                    <span v-if="!selectedHireCreationMentors.length" class="todo-field-note">Belum ada mentor yang terhubung.</span>
                                    <article
                                        v-for="mentor in selectedHireCreationMentors"
                                        :key="mentor.id"
                                        class="hire-status-pill"
                                        :class="`is-${String(mentor.status || '').toLowerCase()}`"
                                    >
                                        <span class="hire-status-avatar">
                                            <img v-if="mentor.profile_photo" :src="`/storage/${mentor.profile_photo}`" :alt="mentor.name">
                                            <span v-else>{{ String(mentor.name || 'M').slice(0, 1).toUpperCase() }}</span>
                                        </span>
                                        <span class="hire-status-info">
                                            <strong>{{ mentor.name || 'Mentor' }}</strong>
                                            <small>@{{ mentor.username || '-' }}</small>
                                        </span>
                                        <em>{{ mentor.status }}</em>
                                    </article>
                                </div>

                                <form v-if="canHireMentor" class="hire-mentor-card" @submit.prevent="hireMentor">
                                    <label class="todo-field">
                                        <span>Mentor</span>
                                        <select v-model="hireMentorForm.mentor_user_id" :disabled="hiringMentor || !availableHireMentors.length" required>
                                            <option :value="null">Pilih mentor</option>
                                            <option v-for="mentor in availableHireMentors" :key="mentor.id" :value="mentor.id">
                                                {{ mentor.name }} — {{ mentor.job_name || 'Mentor' }}
                                            </option>
                                        </select>
                                        <small v-if="!mentors.length" class="todo-field-note">Belum ada mentor aktif.</small>
                                        <small v-else-if="!availableHireMentors.length" class="todo-field-note">Semua mentor sudah terhubung atau sedang menunggu accept.</small>
                                    </label>

                                    <button type="submit" class="nb-btn nb-btn--solid" :disabled="hiringMentor || !hireMentorForm.mentor_user_id">
                                        {{ hiringMentor ? 'Menghubungkan...' : 'Hire Mentor' }}
                                    </button>
                                </form>

                            </section>
                        </template>

                        <template v-else-if="panelMode === 'mentor_invites'">
                            <section class="learning-path-list custom-scroll">
                                <p v-if="!mentorInvites.length" class="source-empty">
                                    Belum ada invite mentor yang perlu direspons.
                                </p>

                                <article v-for="invite in mentorInvites" :key="invite.id" class="learning-path-card">
                                    <div class="learning-path-body">
                                        <div class="learning-path-topline">
                                            <span>Mentor Invite</span>
                                            <strong>Status: PENDING</strong>
                                        </div>
                                        <h3>{{ invite.is_direct ? 'Direct Mentorship' : (invite.creation_title || 'Untitled Creation') }}</h3>
                                        <div class="learning-path-meta">
                                            <span>Owner: {{ invite.owner_name || '-' }} (@{{ invite.owner_username || '-' }})</span>
                                        </div>
                                    </div>

                                    <span class="learning-path-cta">
                                        <button type="button" class="nb-btn nb-btn--solid" @click="respondMentorInvite(invite, 'accept')">Accept</button>
                                        <button type="button" class="nb-btn nb-btn--ghost" @click="respondMentorInvite(invite, 'reject')">Reject</button>
                                    </span>
                                </article>
                            </section>
                        </template>

                        <template v-else-if="panelMode === 'logbook'">
                            <LogbookPanel
                                ref="logbookPanelRef"
                                :logbooks="allLogbooks"
                                :assignable-users="props.logbook_assignable_users"
                                :mentors="mentors"
                                :can-approve-mentor="canCreateMentorTodo"
                            />
                        </template>

                        <template v-else></template>
                    </main>

                    <aside class="nb-panel nb-studio">
                        <div class="panel-head panel-head--stacked">
                            <div>
                                <h2>Quick Access</h2>
                                <p class="panel-subtitle">Shortcut fitur utama DoopTech.</p>
                            </div>
                            <i class="fi fi-rr-apps-add"></i>
                        </div>

                        <div class="studio-grid custom-scroll">
                            <Link
                                v-for="tile in studioTiles"
                                :key="tile.key"
                                :href="route(tile.routeName)"
                                class="studio-tile"
                            >
                                <div class="studio-icon">
                                    <i :class="tile.icon"></i>
                                </div>
                                <div>
                                    <p class="studio-title">{{ tile.title }}</p>
                                    <p class="studio-desc">{{ tile.description }}</p>
                                </div>
                                <i class="fi fi-rr-angle-small-right"></i>
                            </Link>
                        </div>

                        <div class="mentor-zone">
                            <div class="mentor-zone-head">
                                <h3>Mentor Hub</h3>
                                <span>{{ mentors.length }}</span>
                            </div>

                            <p v-if="!mentors.length" class="mentor-empty">Belum ada mentor aktif.</p>

                            <article v-for="mentor in mentors.slice(0, 5)" :key="mentor.id" class="mentor-item">
                                <div class="mentor-avatar">
                                    <img v-if="mentor.profile_photo" :src="`/storage/${mentor.profile_photo}`" :alt="mentor.name">
                                    <span v-else>{{ String(mentor.name || 'M').slice(0, 1).toUpperCase() }}</span>
                                </div>
                                <div class="mentor-info">
                                    <p>{{ mentor.name }}</p>
                                    <span>{{ mentor.job_name || 'Mentor' }}</span>
                                </div>
                            </article>
                        </div>
                    </aside>
                </section>
            </div>
        </div>

        <Teleport to="body"><div v-if="showTodoModal" class="todo-modal" role="dialog" aria-modal="true">
                <div class="todo-modal-card">
                    <div class="todo-modal-head">
                        <h3>{{ todoModalMode === 'edit' ? 'Edit To-Do' : 'Buat To-Do Baru' }}</h3>
                        <button type="button" class="todo-modal-close" @click="closeTodoModal">
                            <i class="fi fi-rr-cross"></i>
                        </button>
                    </div>

                    <form class="todo-modal-form" @submit.prevent="submitTodo">
                        <label class="todo-field">
                            <span>Judul</span>
                            <input v-model="todoForm.title" type="text" maxlength="160" required placeholder="Contoh: Review modul sistem">
                            <small v-if="todoForm.errors.title" class="todo-error">{{ todoForm.errors.title }}</small>
                        </label>

                        <label class="todo-field">
                            <span>Deskripsi (opsional)</span>
                            <textarea
                                v-model="todoForm.description"
                                rows="3"
                                maxlength="1000"
                                placeholder="Catatan singkat to-do"
                            ></textarea>
                            <small v-if="todoForm.errors.description" class="todo-error">{{ todoForm.errors.description }}</small>
                        </label>

                        <div class="todo-date-grid">
                            <label class="todo-field todo-field--date">
                                <span>Start At (opsional)</span>
                                <input v-model="todoForm.start_at" type="datetime-local">
                                <small class="todo-error todo-error--slot" :class="{ 'is-hidden': !todoForm.errors.start_at }">
                                    {{ todoForm.errors.start_at || '\u00A0' }}
                                </small>
                            </label>

                            <label class="todo-field todo-field--date">
                                <span>Deadline (opsional)</span>
                                <input v-model="todoForm.deadline" type="datetime-local">
                                <small class="todo-error todo-error--slot" :class="{ 'is-hidden': !todoForm.errors.deadline }">
                                    {{ todoForm.errors.deadline || '\u00A0' }}
                                </small>
                            </label>
                        </div>


                        <div class="todo-date-grid">
                            <label class="todo-field todo-field--date">
                                <span>Tipe Item</span>
                                <select v-model="todoForm.milestone_type">
                                    <option value="task">Task</option>
                                    <option value="milestone">Milestone</option>
                                    <option value="checkpoint">Checkpoint</option>
                                    <option value="logbook">Logbook</option>
                                </select>
                                <small v-if="todoForm.errors.milestone_type" class="todo-error">{{ todoForm.errors.milestone_type }}</small>
                            </label>

                            <label v-if="todoForm.milestone_type !== 'logbook'" class="todo-field todo-field--date">
                                <span>Creation Riset</span>
                                <select v-model="todoForm.creation_id">
                                    <option :value="null">Tanpa creation</option>
                                    <option v-for="creation in visibleResearchWorkspaces" :key="creation.id" :value="creation.id">
                                        {{ creation.title }}<template v-if="creation.owner_name"> — {{ creation.owner_name }}</template>
                                    </option>
                                </select>
                                <small v-if="todoModalMode === 'create' && todoForm.assignment_mode === 'mentor' && todoForm.owner_user_id && !visibleResearchWorkspaces.length" class="todo-field-note">
                                    Member ini belum hire mentor ke creation.
                                </small>
                                <small v-if="todoForm.errors.creation_id" class="todo-error">{{ todoForm.errors.creation_id }}</small>
                            </label>
                            <label v-else class="todo-field todo-field--date">
                                <span>Target Logbook</span>
                                <select v-model="todoForm.logbook_id" required>
                                    <option :value="null">Pilih logbook</option>
                                    <option v-for="lb in mentorLogbooks" :key="lb.uuid" :value="lb.id">{{ lb.title }}</option>
                                </select>
                                <small v-if="todoForm.errors.logbook_id" class="todo-error">{{ todoForm.errors.logbook_id }}</small>
                            </label>
                        </div>



                        <label class="todo-checkbox-field">
                            <input v-model="todoForm.notify_deadline_email" type="checkbox">
                            <span>Berikan notifikasi deadline di email</span>
                        </label>
                        <small class="todo-field-note">
                            Jika tidak dicentang, pengingat deadline hanya muncul di notifikasi aplikasi.
                        </small>
                        <small v-if="todoForm.errors.notify_deadline_email" class="todo-error">{{ todoForm.errors.notify_deadline_email }}</small>

                        <label v-if="canCreateMentorTodo && todoForm.milestone_type !== 'logbook'" class="todo-field">
                            <span>Jenis Penugasan</span>
                            <select v-model="todoForm.assignment_mode">
                                <option value="self">Self (saya centang sendiri)</option>
                                <option value="mentor">Mentor Assigned (untuk member)</option>
                            </select>
                            <small v-if="todoForm.errors.assignment_mode" class="todo-error">{{ todoForm.errors.assignment_mode }}</small>
                        </label>

                        <label
                            v-if="canCreateMentorTodo && (todoForm.assignment_mode === 'mentor')"
                            class="todo-field"
                        >
                            <span>Target Member</span>
                            <select v-model="todoForm.owner_user_id" required>
                                <option :value="null">Pilih member</option>
                                <option
                                    v-for="member in todo_assignable_users"
                                    :key="member.id"
                                    :value="member.id"
                                >
                                    {{ member.name }} (@{{ member.username || '-' }})
                                </option>
                            </select>
                            <small v-if="todoForm.errors.owner_user_id" class="todo-error">{{ todoForm.errors.owner_user_id }}</small>
                        </label>

                        <div class="todo-modal-actions">
                            <button type="button" class="nb-btn nb-btn--ghost" @click="closeTodoModal">Batal</button>
                            <button type="submit" class="nb-btn nb-btn--solid" :disabled="todoForm.processing">
                                {{ todoForm.processing ? 'Menyimpan...' : (todoModalMode === 'edit' ? 'Simpan Perubahan' : 'Simpan To-Do') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

.nb-root {
    --bg-0: #090d14;
    --bg-1: #0f1726;
    --bg-2: #141f31;
    --line: rgba(255, 255, 255, 0.08);
    --line-strong: rgba(255, 255, 255, 0.14);
    --cyan: #57d6ff;
    --amber: #f8c65c;
    --green: #57f6b9;
    --violet: #b9a7ff;
    --danger: #ff7f8f;
    --text-dim: #8ea2bd;
    --radius: 12px;
    position: relative;
    min-height: calc(100vh - 80px);
    padding: 20px;
    color: #fff;
    font-family: 'Inter', sans-serif;
    background:
        radial-gradient(circle at 15% 14%, rgba(87, 214, 255, 0.06), transparent 30%),
        radial-gradient(circle at 84% 20%, rgba(248, 198, 92, 0.04), transparent 24%),
        linear-gradient(160deg, var(--bg-0), var(--bg-1) 40%, var(--bg-2));
}

.nb-aurora {
    position: fixed; inset: 0; z-index: -1;
}
.nb-aurora img {
    width: 100%; height: 100%;
    object-fit: cover; object-position: center;
    image-rendering: pixelated;
    opacity: 0.15;
    transform: translateZ(0);
    will-change: auto;
}

.nb-shell {
    position: relative;
    z-index: 1;
    max-width: 1400px;
    margin: 0 auto;
    display: grid;
    gap: 16px;
}

.nb-topbar,
.nb-panel {
    border: 1px solid var(--line);
    background: rgba(14, 20, 34, 0.85);
    backdrop-filter: blur(12px);
    border-radius: var(--radius);
}

.nb-topbar {
    min-height: 64px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.nb-title-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
}

.nb-logo {
    width: 38px;
    height: 38px;
    border: 1px solid var(--line);
    border-radius: 10px;
    display: grid;
    place-items: center;
    color: var(--cyan);
    font-size: 16px;
    background: rgba(87, 214, 255, 0.06);
}

.nb-eyebrow {
    margin: 0 0 4px;
    font-family: 'Inter', sans-serif;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 1px;
    color: var(--cyan);
    text-transform: uppercase;
}

.nb-title {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    line-height: 1.3;
}

.nb-subtitle {
    margin: 4px 0 0;
    color: var(--text-dim);
    font-size: 13px;
    font-weight: 400;
    line-height: 1.5;
}

.nb-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.nb-btn {
    text-decoration: none;
    font-family: 'Inter', sans-serif;
    font-size: 12px;
    font-weight: 600;
    padding: 10px 16px;
    border: 1px solid var(--line-strong);
    border-radius: 8px;
    transition: 0.2s ease;
    cursor: pointer;
}

.nb-btn--ghost {
    color: var(--cyan);
    background: rgba(87, 214, 255, 0.04);
}

.nb-btn--ghost:hover {
    background: rgba(87, 214, 255, 0.1);
}

.nb-btn--solid {
    color: #101317;
    border-color: rgba(0, 0, 0, 0.25);
    background: linear-gradient(120deg, #8be8ff, #7fffc9);
}

.nb-btn--solid:hover {
    opacity: 0.9;
}

.nb-btn--success {
    color: #0a1a10;
    border-color: rgba(0, 0, 0, 0.2);
    background: linear-gradient(120deg, #4ade80, #22c55e);
}

.nb-btn--success:hover {
    opacity: 0.9;
}

.nb-btn--danger {
    color: #1a0a0a;
    border-color: rgba(0, 0, 0, 0.2);
    background: linear-gradient(120deg, #f87171, #ef4444);
}

.nb-btn--danger:hover {
    opacity: 0.9;
}

.review-inline-form {
    border: 1px solid var(--line-strong);
    border-radius: var(--radius);
    padding: 14px 16px;
    background: rgba(10, 17, 30, 0.8);
    margin-bottom: 10px;
}

.review-inline-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 700;
    color: var(--cyan);
    margin: 0 0 10px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.review-inline-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 10px;
}

.nb-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
}

.metric-card {
    border: 1px solid var(--line);
    border-radius: var(--radius);
    background: rgba(14, 20, 34, 0.7);
    backdrop-filter: blur(8px);
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.metric-label {
    font-size: 12px;
    font-weight: 500;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.metric-value {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.2;
}

.metric-hint {
    font-size: 11px;
    color: var(--text-dim);
}

.metric-card--cyan .metric-value { color: var(--cyan); }
.metric-card--amber .metric-value { color: var(--amber); }
.metric-card--green .metric-value { color: var(--green); }
.metric-card--violet .metric-value { color: var(--violet); }

.metric-card--cyan { border-color: rgba(87, 214, 255, 0.15); }
.metric-card--amber { border-color: rgba(248, 198, 92, 0.15); }
.metric-card--green { border-color: rgba(87, 246, 185, 0.15); }
.metric-card--violet { border-color: rgba(185, 167, 255, 0.15); }

.nb-workbench {
    display: grid;
    grid-template-columns: 320px minmax(0, 1fr) 300px;
    gap: 16px;
}

.nb-panel {
    min-height: 680px;
    max-height: calc(100vh - 130px);
    display: flex;
    flex-direction: column;
    padding: 16px;
}

.panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 600;
    color: #c8d6eb;
}

.panel-head--stacked {
    align-items: flex-start;
}

.panel-subtitle {
    margin: 4px 0 0;
    color: #8ea2bd;
    font-size: 12px;
    line-height: 1.45;
    font-family: inherit;
    font-weight: 400;
}

.panel-tools {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.source-add-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 999px;
    color: #d6e1f2;
    background: rgba(87, 214, 255, 0.06);
    padding: 11px 12px;
    margin-bottom: 12px;
    font-size: 14px;
}

.source-add-btn:hover {
    border-color: rgba(87, 214, 255, 0.35);
    background: rgba(87, 214, 255, 0.14);
}

.todo-add-btn {
    width: auto;
    min-width: max-content;
    margin-bottom: 0;
    padding-inline: 16px;
}

.source-add-btn--link {
    text-decoration: none;
}

.todo-nav-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 30px;
    height: 22px;
    padding: 0 8px;
    border-radius: 999px;
    border: 1px solid rgba(87, 214, 255, 0.35);
    color: #b8ebff;
    background: rgba(87, 214, 255, 0.08);
    font-size: 12px;
}

.source-search {
    display: grid;
    grid-template-columns: 16px 1fr;
    align-items: center;
    gap: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 10px 12px;
    margin-bottom: 12px;
    color: var(--text-dim);
}

.source-search input {
    width: 100%;
    border: 0;
    outline: none;
    color: #d8e5f8;
    background: transparent;
    font-size: 14px;
}

.todo-filters {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}

.todo-filters--menu {
    margin-bottom: 10px;
}

.todo-filter {
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: #b5c6dd;
    background: rgba(255, 255, 255, 0.03);
    padding: 7px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 500;
}

.todo-filter.is-active {
    border-color: rgba(87, 214, 255, 0.5);
    color: #b7ecff;
    background: rgba(87, 214, 255, 0.12);
}

.source-list-wrap {
    min-height: 0;
    overflow-y: auto;
    padding-right: 6px;
}

.todo-nav-list {
    min-height: 0;
    overflow-y: auto;
    padding-right: 6px;
}

.todo-list-workspace {
    display: grid;
    grid-template-rows: auto auto minmax(0, 1fr);
    gap: 0;
    min-height: 0;
    overflow-y: auto;
    padding-right: 6px;
}

.source-list-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-family: 'Inter', sans-serif;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #a9bdd7;
    margin-bottom: 10px;
}

.todo-nav-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-family: 'Inter', sans-serif;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #a9bdd7;
    margin-bottom: 10px;
}

.source-empty {
    margin: 0;
    color: var(--text-dim);
    font-size: 13px;
    line-height: 1.6;
}

.todo-nav-item {
    width: 100%;
    display: grid;
    grid-template-columns: 30px minmax(0, 1fr) 16px;
    gap: 10px;
    align-items: start;
    text-align: left;
    border: 1px solid rgba(255, 255, 255, 0.06);
    background: rgba(8, 16, 30, 0.42);
    margin-bottom: 8px;
    padding: 11px;
    cursor: pointer;
    border-radius: 10px;
}

.todo-nav-item:hover {
    border-color: rgba(87, 214, 255, 0.38);
    background: rgba(12, 24, 42, 0.65);
}

.todo-nav-item.is-active {
    border-color: rgba(87, 214, 255, 0.7);
    background: rgba(16, 36, 60, 0.62);
    box-shadow: 0 0 0 1px rgba(87, 214, 255, 0.12) inset;
}

.todo-nav-item.is-completed .todo-nav-title {
    color: #8fa4bb;
    text-decoration: line-through;
}

.todo-nav-check {
    width: 30px;
    height: 30px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    display: grid;
    place-items: center;
    color: #d2e1f6;
    background: transparent;
    padding: 0;
    cursor: pointer;
}

.todo-nav-check.is-done {
    border-color: rgba(87, 246, 185, 0.6);
    background: rgba(87, 246, 185, 0.12);
}

.todo-nav-check:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.todo-nav-body {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.todo-nav-title {
    margin: 0;
    color: #ebf2ff;
    font-weight: 600;
    font-size: 14px;
    line-height: 1.45;
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.todo-nav-sub {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.todo-nav-meta {
    color: #8ea2bd;
    font-size: 12px;
}

.todo-nav-deadline {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    line-height: 1.35;
    color: #a8bdd8;
}

.todo-nav-deadline i {
    font-size: 11px;
}

.todo-nav-deadline.is-none {
    color: #8ea2bd;
}

.todo-nav-deadline.is-safe {
    color: #9de8ff;
}

.todo-nav-deadline.is-soon {
    color: #ffd18f;
}

.todo-nav-deadline.is-urgent {
    color: #ffb66e;
}

.todo-nav-deadline.is-overdue {
    color: #ff8f9f;
}

.todo-nav-deadline.is-done {
    color: #a9f9d4;
}

.todo-nav-tags {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}

.todo-nav-arrow {
    color: #88a8c7;
    font-size: 18px;
    margin-top: 1px;
}

.todo-item {
    display: grid;
    grid-template-columns: 28px 1fr;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(8, 16, 30, 0.45);
    margin-bottom: 8px;
    cursor: pointer;
}

.todo-item.is-completed .todo-title {
    text-decoration: line-through;
    color: #8fa4bb;
}

.todo-item:hover {
    border-color: rgba(87, 214, 255, 0.38);
    background: rgba(12, 24, 42, 0.65);
}

.todo-check {
    width: 28px;
    height: 28px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    display: grid;
    place-items: center;
    color: #d2e1f6;
    background: transparent;
    padding: 0;
    cursor: pointer;
}

.todo-check.is-done {
    border-color: rgba(87, 246, 185, 0.6);
    background: rgba(87, 246, 185, 0.12);
}

.todo-check:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.todo-content {
    min-width: 0;
}

.todo-title {
    margin: 0 0 5px;
    color: #ebf2ff;
    font-weight: 600;
    font-size: 13px;
    white-space: normal;
    overflow: visible;
    text-overflow: clip;
    overflow-wrap: anywhere;
    word-break: break-word;
    line-height: 1.35;
}

.todo-meta {
    margin: 0;
    color: #8ea2bd;
    font-size: 11px;
}

.todo-deadline {
    margin: 6px 0 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    line-height: 1.35;
    color: #a8bdd8;
}

.todo-deadline i {
    font-size: 11px;
}

.todo-deadline.is-none {
    color: #8ea2bd;
}

.todo-deadline.is-safe {
    color: #9de8ff;
}

.todo-deadline.is-soon {
    color: #ffd18f;
}

.todo-deadline.is-urgent {
    color: #ffb66e;
}

.todo-deadline.is-overdue {
    color: #ff8f9f;
}

.todo-deadline.is-done {
    color: #a9f9d4;
}

.todo-badges {
    display: flex;
    gap: 6px;
    align-items: center;
    margin-top: 6px;
}

.todo-badge {
    font-family: 'Inter', sans-serif;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.3px;
    padding: 4px 8px;
    color: #9edfff;
    border: 1px solid rgba(87, 214, 255, 0.35);
    background: rgba(87, 214, 255, 0.08);
    border-radius: 999px;
}

.todo-description {
    margin: 8px 0 0;
    color: #ccd8eb;
    font-size: 12px;
    line-height: 1.55;
}

.todo-hint {
    margin: 8px 0 0;
    color: #7fd6fb;
    font-size: 11px;
    line-height: 1.45;
}

.learning-path-list {
    display: grid;
    gap: 14px;
    overflow-y: auto;
    padding-right: 6px;
}

.hire-mentor-workspace {
    display: grid;
    gap: 16px;
}

.hire-mentor-card {
    display: grid;
    gap: 14px;
    max-width: 560px;
    padding: 16px;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: rgba(5, 12, 22, 0.58);
}

.hire-status-inline {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.hire-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    border: 1px solid rgba(87, 246, 185, 0.38);
    border-radius: 12px;
    background: rgba(87, 246, 185, 0.08);
    color: var(--green);
    padding: 8px 10px;
    text-transform: uppercase;
}

.hire-status-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 8px;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.05);
    color: #e8f6ff;
    font-size: 12px;
}

.hire-status-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.hire-status-info {
    display: grid;
    gap: 2px;
    min-width: 0;
    color: #e8f6ff;
}

.hire-status-info strong {
    font-size: 12px;
    line-height: 1.1;
}

.hire-status-info small {
    color: var(--text-dim);
    font-size: 10px;
    line-height: 1.1;
    text-transform: none;
}

.hire-status-pill em {
    margin-left: 4px;
    color: currentColor;
    font-size: 11px;
    font-style: normal;
}

.hire-status-pill.is-pending {
    border-color: rgba(248, 198, 92, 0.42);
    background: rgba(248, 198, 92, 0.08);
    color: var(--amber);
}

.hire-status-pill.is-rejected {
    border-color: rgba(255, 127, 143, 0.42);
    background: rgba(255, 127, 143, 0.08);
    color: var(--danger);
}

.learning-path-card {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 14px;
    border: 2px solid var(--panel-border);
    background: rgba(5, 10, 22, 0.72);
    color: #eef6ff;
    padding: 16px 18px;
    text-decoration: none;
    box-shadow: 6px 6px 0 rgba(0, 0, 0, 0.58), inset 0 0 0 1px rgba(255, 255, 255, 0.04);
    overflow: hidden;
    transition: transform 0.16s ease, border-color 0.16s ease, background 0.16s ease;
}

.learning-path-card::before {
    content: '';
    position: absolute;
    inset: 0 auto 0 0;
    width: 4px;
    background: linear-gradient(180deg, var(--accent-cyan), transparent);
    box-shadow: 0 0 18px rgba(87, 214, 255, 0.55);
}

.learning-path-card::after {
    content: '';
    position: absolute;
    right: -40px;
    top: -55px;
    width: 150px;
    height: 150px;
    border: 1px solid rgba(87, 214, 255, 0.12);
    transform: rotate(22deg);
    pointer-events: none;
}

.learning-path-card:hover {
    border-color: var(--accent-cyan);
    background: rgba(5, 10, 22, 0.86);
    transform: translate(-2px, -2px);
}

.learning-path-body {
    min-width: 0;
    z-index: 1;
}

.learning-path-topline {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 7px;
}

.learning-path-topline span,
.learning-path-topline strong {
    display: inline-flex;
    margin: 0;
    padding: 4px 7px;
    border: 1px solid rgba(87, 214, 255, 0.32);
    background: rgba(87, 214, 255, 0.08);
    color: var(--accent-cyan);
    font-size: 7px;
    line-height: 1;
    text-transform: uppercase;
}

.learning-path-card h3 {
    margin: 0;
    font-size: 15px;
    color: #ffffff;
    line-height: 1.4;
}

.learning-path-card p {
    margin: 8px 0 0;
    color: #b9c7dc;
    font-family: 'Inter', sans-serif;
    font-size: 12px;
    line-height: 1.5;
}

.learning-path-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.learning-path-meta span {
    display: block;
    margin: 0;
    color: #d9e7ff;
    font-size: 9px;
    text-transform: uppercase;
}

.learning-path-cta {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #0b1020;
    background: var(--accent-cyan);
    border: 2px solid rgba(255, 255, 255, 0.35);
    box-shadow: 3px 3px 0 rgba(0, 0, 0, 0.5);
    padding: 9px 11px;
    font-size: 8px;
    text-transform: uppercase;
    z-index: 1;
}

.todo-state {
    font-family: 'Inter', sans-serif;
    font-size: 10px;
    font-weight: 700;
    padding: 4px 8px;
    letter-spacing: 0.3px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 999px;
}

.todo-state.is-pending {
    color: #ffe7ad;
    border-color: rgba(248, 198, 92, 0.5);
    background: rgba(248, 198, 92, 0.1);
}

.todo-state.is-done {
    color: #bdffe2;
    border-color: rgba(87, 246, 185, 0.5);
    background: rgba(87, 246, 185, 0.1);
}

.todo-state.is-ongoing {
    color: #bdefff;
    border-color: rgba(87, 214, 255, 0.5);
    background: rgba(87, 214, 255, 0.12);
}

.todo-state.is-blocked {
    color: #ffd1d8;
    border-color: rgba(255, 127, 143, 0.55);
    background: rgba(255, 127, 143, 0.12);
}

.todo-state.is-pending-review {
    color: #ffe8b9;
    border-color: rgba(248, 198, 92, 0.55);
    background: rgba(248, 198, 92, 0.12);
}

.todo-state.is-approved {
    color: #c8ffea;
    border-color: rgba(87, 246, 185, 0.58);
    background: rgba(87, 246, 185, 0.14);
}

.todo-state.is-rejected {
    color: #ffc8d0;
    border-color: rgba(255, 127, 143, 0.58);
    background: rgba(255, 127, 143, 0.14);
}

.chat-hero {
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 16px;
    background: rgba(14, 20, 34, 0.6);
    margin-bottom: 14px;
}

.chat-hero-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 24px;
    padding: 0 8px;
    border-radius: 999px;
    background: rgba(87, 214, 255, 0.12);
    color: #e4f7ff;
    font-family: 'Inter', sans-serif;
    font-size: 10px;
    font-weight: 700;
    margin-bottom: 10px;
}

.chat-hero-tools {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}

.chat-back-btn {
    border: 1px solid rgba(87, 214, 255, 0.25);
    border-radius: 8px;
    background: rgba(87, 214, 255, 0.06);
    color: #b8ebff;
    font-size: 13px;
    font-weight: 500;
    padding: 8px 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.chat-back-btn:hover {
    background: rgba(87, 214, 255, 0.14);
}

.logbook-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

@media (max-width: 520px) {
    .logbook-toolbar {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
    }
    .logbook-toolbar > * {
        width: 100%;
        justify-content: center;
    }
}

.chat-hero h3 {
    margin: 0 0 8px;
    font-size: 22px;
    font-weight: 700;
}

.chat-hero p {
    margin: 0;
    color: #d3deef;
    font-size: 14px;
    line-height: 1.6;
}

.chat-hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
    font-size: 12px;
    color: var(--text-dim);
}

.chat-hero-meta span {
    padding: 4px 10px;
    border: 1px solid var(--line);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.03);
}

.chat-hero-meta .is-done {
    color: var(--green);
    border-color: rgba(87, 246, 185, 0.3);
}

.chat-hero-meta .is-pending {
    color: var(--amber);
    border-color: rgba(248, 198, 92, 0.3);
}

.chip-grid {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
}

.chip {
    border: 1px solid var(--line);
    font-family: 'Inter', sans-serif;
    font-size: 11px;
    font-weight: 600;
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(10, 18, 31, 0.6);
}

.chip--danger { border-color: rgba(255, 127, 143, 0.5); color: #ffb5bf; }
.chip--cyan { border-color: rgba(87, 214, 255, 0.5); color: #a5e8ff; }
.chip--violet { border-color: rgba(185, 167, 255, 0.5); color: #d9ceff; }
.chip--green { border-color: rgba(87, 246, 185, 0.5); color: #bffff0; }
.chip--amber { border-color: rgba(248, 198, 92, 0.5); color: #ffe5a1; }

.chat-stream {
    min-height: 0;
    flex: 1;
    overflow-y: auto;
    padding-right: 8px;
}

.chat-bubble {
    border: 1px solid var(--line);
    border-radius: 10px;
    background: rgba(10, 17, 30, 0.7);
    padding: 14px;
    margin-bottom: 12px;
}

.chat-role {
    margin: 0 0 8px;
    font-family: 'Inter', sans-serif;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--cyan);
}

.chat-text {
    margin: 0;
    color: #d5e1f5;
    font-size: 14px;
    line-height: 1.65;
}

.chat-composer {
    margin-top: 12px;
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 16px;
    background: rgba(10, 17, 30, 0.7);
}

.composer-placeholder {
    margin: 0 0 14px;
    color: var(--text-dim);
    font-size: 16px;
    font-weight: 400;
}

.composer-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #c2d2e9;
    font-size: 14px;
}

.composer-send {
    width: 38px;
    height: 38px;
    border-radius: 999px;
    border: 1px solid var(--line-strong);
    display: grid;
    place-items: center;
    text-decoration: none;
    color: #d9e6f9;
    background: rgba(255, 255, 255, 0.09);
}

.studio-grid {
    min-height: 0;
    overflow-y: auto;
    padding-right: 6px;
    display: grid;
    gap: 10px;
    margin-bottom: 14px;
}

.studio-tile {
    display: grid;
    grid-template-columns: 34px 1fr 16px;
    align-items: center;
    gap: 10px;
    border: 1px solid var(--line);
    border-radius: 10px;
    background: rgba(14, 20, 34, 0.5);
    text-decoration: none;
    color: #ebf2ff;
    padding: 12px;
}

.studio-tile:hover {
    border-color: rgba(87, 214, 255, 0.3);
    background: rgba(14, 20, 34, 0.7);
}

.studio-icon {
    width: 32px;
    height: 32px;
    border: 1px solid var(--line);
    border-radius: 8px;
    display: grid;
    place-items: center;
    color: #d7e9ff;
    background: rgba(10, 17, 30, 0.5);
}

.studio-title {
    margin: 0 0 5px;
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    font-weight: 600;
}

.studio-desc {
    margin: 0;
    color: #b5c7df;
    font-size: 12px;
    line-height: 1.4;
}

.mentor-zone {
    margin-top: auto;
    border-top: 1px solid var(--line);
    padding-top: 12px;
}

.mentor-zone-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    font-weight: 600;
    color: #d4e5fb;
}

.mentor-empty {
    margin: 0;
    color: var(--text-dim);
    font-size: 13px;
}

.mentor-item {
    display: grid;
    grid-template-columns: 38px 1fr;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px dashed rgba(255, 255, 255, 0.12);
}

.mentor-avatar {
    width: 36px;
    height: 36px;
    border: 1px solid var(--line);
    border-radius: 999px;
    display: grid;
    place-items: center;
    overflow: hidden;
    background: rgba(87, 214, 255, 0.12);
    color: #e7f4ff;
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    font-weight: 700;
}

.mentor-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.mentor-info p {
    margin: 0 0 4px;
    color: #e6f1ff;
    font-size: 13px;
    font-weight: 600;
}

.mentor-info span {
    color: var(--text-dim);
    font-size: 12px;
}







.chat-composer--todo {
    display: grid;
    gap: 12px;
}

.todo-inline-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.todo-inline-actions-left,
.todo-inline-actions-right {
    display: flex;
    align-items: center;
    gap: 8px;
}

.todo-icon-btn {
    width: 42px;
    height: 42px;
    border: 1px solid rgba(87, 214, 255, 0.42);
    background: rgba(87, 214, 255, 0.1);
    color: #bdeeff;
    display: grid;
    place-items: center;
    font-size: 15px;
    transition: border-color 0.2s ease, background-color 0.2s ease, transform 0.2s ease;
}

.todo-icon-btn:hover:not(:disabled) {
    border-color: rgba(87, 214, 255, 0.75);
    background: rgba(87, 214, 255, 0.2);
    transform: translateY(-1px);
}

.todo-icon-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.todo-icon-btn--danger {
    border-color: rgba(255, 127, 143, 0.48);
    background: rgba(255, 127, 143, 0.12);
    color: #ffc5ce;
}

.todo-icon-btn--danger:hover:not(:disabled) {
    border-color: rgba(255, 127, 143, 0.78);
    background: rgba(255, 127, 143, 0.2);
}

.todo-icon-btn--success {
    border-color: rgba(87, 246, 185, 0.44);
    background: rgba(87, 246, 185, 0.1);
    color: #c7ffea;
}

.todo-icon-btn--success:hover:not(:disabled) {
    border-color: rgba(87, 246, 185, 0.72);
    background: rgba(87, 246, 185, 0.18);
}

.note-empty {
    margin: 0;
    color: #8ea2bd;
    font-size: 13px;
}

.todo-notes-list {
    margin-top: 10px;
}

.todo-note-item {
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(7, 14, 26, 0.6);
    padding: 10px;
    margin-top: 10px;
}

.todo-note-item--latest {
    border-color: rgba(87, 214, 255, 0.45);
    box-shadow: inset 0 0 0 1px rgba(87, 214, 255, 0.15);
}

.todo-note-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.todo-note-author {
    display: flex;
    align-items: center;
    gap: 8px;
}

.todo-note-avatar {
    width: 28px;
    height: 28px;
    border: 1px solid rgba(87, 214, 255, 0.35);
    background: rgba(87, 214, 255, 0.1);
    display: grid;
    place-items: center;
    color: #b7ecff;
    font-size: 11px;
    font-weight: 600;
}

.todo-note-author p {
    margin: 0;
    color: #e6f2ff;
    font-size: 13px;
    font-weight: 600;
}

.todo-note-author span {
    color: #90a4be;
    font-size: 11px;
}

.todo-note-text {
    margin: 0;
    color: #d7e5f8;
    font-size: 13px;
    line-height: 1.6;
    white-space: pre-wrap;
}

.todo-note-image-link {
    margin-top: 10px;
    display: inline-block;
    text-decoration: none;
}

.todo-note-image {
    max-width: 100%;
    max-height: 260px;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.todo-note-form {
    display: grid;
    gap: 12px;
}

.todo-note-form-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.todo-note-label {
    font-size: 12px;
    color: #a7bbd6;
}

.todo-note-helper {
    margin: 6px 0 0;
    color: #8ea8bb;
    font-size: 11px;
    line-height: 1.5;
}

.todo-note-textarea {
    width: 100%;
    border: 1px solid rgba(255, 255, 255, 0.16);
    background: rgba(255, 255, 255, 0.03);
    color: #e2eefb;
    padding: 14px;
    resize: vertical;
    min-height: 112px;
    outline: none;
    font-size: 13px;
}

.todo-note-upload-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.todo-note-upload-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.todo-upload-btn {
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.06);
    color: #d6e7fd;
    min-height: 40px;
    padding: 10px 14px;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
}

.todo-upload-btn input {
    display: none;
}

.todo-upload-remove {
    border: 1px solid rgba(255, 127, 143, 0.35);
    background: rgba(255, 127, 143, 0.1);
    color: #ffc4cd;
    min-height: 40px;
    padding: 10px 12px;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.todo-note-submit {
    border: 1px solid rgba(87, 214, 255, 0.45);
    background: rgba(87, 214, 255, 0.14);
    color: #bff0ff;
    min-height: 40px;
    padding: 10px 16px;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-left: auto;
}

.todo-note-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.todo-note-preview img {
    max-width: 220px;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.custom-scroll::-webkit-scrollbar {
    width: 6px;
}

.custom-scroll::-webkit-scrollbar-thumb {
    background: rgba(97, 169, 208, 0.4);
}

@keyframes drift {
    0% {
        transform: translate3d(0, 0, 0);
    }
    100% {
        transform: translate3d(90px, 0, 0);
    }
}

@media (max-width: 1320px) {
    .nb-workbench {
        grid-template-columns: 280px minmax(0, 1fr);
    }

    .nb-studio {
        grid-column: span 2;
        min-height: 360px;
        max-height: none;
    }

    .studio-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 920px) {
    .nb-root {
        padding: 10px;
    }

    .nb-topbar {
        flex-direction: column;
        align-items: flex-start;
    }

    .nb-actions {
        width: 100%;
    }

    .nb-workbench {
        grid-template-columns: 1fr;
    }

    .nb-todo-nav {
        max-height: 360px;
    }

    .nb-panel {
        min-height: auto;
        max-height: none;
    }

    .nb-studio {
        grid-column: auto;
    }

    .studio-grid {
        grid-template-columns: 1fr;
    }

    .chat-hero h3 {
        font-size: 24px;
    }

    .composer-placeholder {
        font-size: 24px;
    }

    .todo-note-upload-row {
        flex-direction: column;
        align-items: stretch;
    }

    .todo-note-submit {
        margin-left: 0;
        width: 100%;
        justify-content: center;
    }

    .hire-mentor-card {
        max-width: 100%;
    }
}

@media (max-width: 620px) {
    .chip-grid {
        flex-direction: column;
    }

    .todo-inline-actions {
        flex-wrap: wrap;
        justify-content: flex-start;
    }

    .todo-inline-actions-right {
        width: 100%;
        justify-content: flex-start;
        flex-wrap: wrap;
    }

    .composer-foot {
        font-size: 16px;
    }

    .nb-topbar {
        padding: 12px;
    }

    .nb-title {
        font-size: 14px !important;
    }

    .nb-actions {
        flex-direction: column;
    }

    .nb-btn {
        width: 100%;
        text-align: center;
        justify-content: center;
    }

    .source-add-btn {
        font-size: 10px;
    }

    .todo-filters {
        gap: 6px;
    }

    .todo-filter {
        padding: 6px 10px;
        font-size: 10px;
    }

    .panel-head--stacked {
        flex-direction: column;
        gap: 8px;
    }

    .todo-add-btn {
        width: 100%;
        justify-content: center;
    }

    .chat-back-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<style>
.todo-modal {
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

.todo-modal-card {
    width: min(860px, 100%);
    max-height: calc(100dvh - clamp(20px, 5.2vw, 36px));
    border: 4px solid #3d415f;
    background: #1a1c2c;
    box-shadow: 8px 8px 0 rgba(0, 0, 0, 0.5);
    padding: 20px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.todo-modal-body {
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding-right: 4px;
}

.todo-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
    padding-bottom: 16px;
    border-bottom: 2px solid #3d415f;
}

.todo-modal-head h3 {
    margin: 0;
    font-family: "Press Start 2P", monospace;
    font-size: 13px;
    font-weight: 700;
    color: #fff;
}

.todo-modal-close {
    width: 32px;
    height: 32px;
    border: 2px solid #3d415f;
    background: #0d1117;
    color: #cbd5e1;
    font-size: 14px;
    display: grid;
    place-items: center;
}

.todo-modal-form {
    display: grid;
    gap: 14px;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 4px;
}

.todo-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 8px;
    padding-top: 4px;
}
.todo-field {
    display: grid;
    gap: 7px;
    min-width: 0;
}

.todo-date-grid {
    display: grid;
    align-items: start;
    gap: 10px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.todo-field--date {
    align-content: start;
}

.todo-field span {
    font-size: 10px;
    color: #8ea8bb;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.todo-field input,
.todo-field textarea,
.todo-field select {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    border: 2px solid #3d415f;
    background: #0d1117;
    color: #cbd5e1;
    padding: 12px;
    outline: none;
    font-family: "Press Start 2P", monospace;
    font-size: 11px;
}

.todo-field input[type="datetime-local"] {
    font-family: Inter, sans-serif;
    font-size: 13px;
    line-height: 1.2;
}

.todo-field textarea {
    resize: vertical;
}

.todo-checkbox-field {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #cbd5e1;
    font-size: 10px;
}

.todo-checkbox-field input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #57d6ff;
}

.todo-field-note {
    margin: -2px 0 0;
    color: #8ea8bb;
    font-size: 9px;
}

.todo-error {
    color: #ff9aa8;
    font-size: 9px;
}

.todo-error--slot {
    display: block;
    min-height: 34px;
    line-height: 1.35;
}

.todo-error--slot.is-hidden {
    visibility: hidden;
}

@media (max-width: 920px) {
    .todo-modal {
        padding: 12px;
    }

    .todo-modal-card {
        width: min(100%, 680px);
        max-height: calc(100dvh - 24px);
    }

    .todo-modal-actions {
        flex-wrap: wrap;
        justify-content: stretch;
    }

    .todo-modal-actions .nb-btn {
        flex: 1 1 180px;
    }
}

@media (max-width: 620px) {
    .learning-path-card {
        grid-template-columns: 1fr;
    }

    .learning-path-cta {
        grid-column: 1 / -1;
        justify-content: center;
    }

    .todo-modal {
        padding: 8px;
        padding-top: max(8px, env(safe-area-inset-top));
        align-items: start;
    }

    .todo-modal-card {
        width: 100%;
        max-height: calc(100dvh - 16px);
        padding: 14px 12px;
    }

    .todo-modal-head {
        margin-bottom: 14px;
        padding-bottom: 12px;
    }

    .todo-modal-head h3 {
        font-size: 9px;
        line-height: 1.5;
        max-width: calc(100% - 44px);
    }

    .todo-modal-body {
        gap: 12px;
    }

    .todo-date-grid {
        grid-template-columns: 1fr;
    }

    /* Prevent iOS auto-zoom on input focus */
    .todo-field input,
    .todo-field textarea,
    .todo-field select {
        font-size: 16px;
        padding: 10px;
    }

    .todo-field span {
        font-size: 9px;
    }
}

@media (max-width: 400px) {
    .todo-modal-card {
        padding: 10px;
    }

    .todo-modal-actions {
        flex-direction: column;
    }

    .todo-modal-actions .nb-btn {
        width: 100%;
        text-align: center;
    }
}

</style>

<style scoped>
/* ===== DoopLab Pixel Theme — Dooptech Home Style ===== */
.nb-root {
    --panel: #1a1c2c;
    --panel-border: #3d415f;
    --text-muted: #8ea8bb;
    font-family: "Press Start 2P", monospace !important;
    font-size: 9px;
    background: transparent !important;
    color: #cbd5e1;
}

.nb-root *,
.nb-root *::before,
.nb-root *::after {
    border-radius: 0 !important;
}

/* Panels — rpg-panel style */
.nb-topbar,
.nb-panel,
.studio-tile,
.todo-note-item,
.chat-composer,
.todo-modal-card,
.metric-card {
    background-color: rgba(26, 28, 44, 0.92) !important;
    border: 4px solid var(--panel-border) !important;
    box-shadow: none !important;
    backdrop-filter: none !important;
}

/* Buttons — btn-pixel style */
.nb-btn,
.source-add-btn,
.todo-filter,
.todo-icon-btn,
.todo-modal-actions button {
    border-bottom-width: 4px !important;
    border-right-width: 4px !important;
    border-color: var(--panel-border) !important;
    background: rgba(26, 28, 44, 0.95) !important;
    color: #cbd5e1 !important;
    font-family: "Press Start 2P", monospace !important;
    font-size: 7px !important;
    text-transform: uppercase !important;
    font-weight: bold !important;
    padding: 8px 12px !important;
    box-shadow: none !important;
    transition: all 0.15s !important;
}

.nb-btn:active,
.source-add-btn:active,
.todo-filter:active,
.todo-icon-btn:active,
.todo-modal-actions button:active {
    transform: translate(4px, 4px) !important;
    border-bottom-width: 0 !important;
    border-right-width: 0 !important;
}

.nb-btn--solid {
    background: #009999 !important;
    border-color: #006666 !important;
    color: #fff !important;
}

.nb-btn--ghost {
    background: transparent !important;
    border-color: var(--panel-border) !important;
    color: #57d6ff !important;
}

.nb-btn--ghost:hover {
    background: rgba(87, 214, 255, 0.08) !important;
}

.todo-filter.is-active {
    background: #009999 !important;
    border-color: #006666 !important;
    color: #fff !important;
}

/* Typography */
.nb-eyebrow { font-size: 8px !important; color: #57d6ff !important; letter-spacing: 2px !important; }
.nb-title { font-size: 14px !important; line-height: 1.4 !important; color: #fff !important; }
.nb-subtitle { font-size: 10px !important; color: var(--text-muted) !important; }

.nb-panel h1, .nb-panel h2, .nb-panel h3, .nb-panel h4 { font-size: 11px !important; color: #fff !important; }
.nb-panel p, .nb-panel span, .nb-panel li, .nb-panel a,
.nb-panel small, .nb-panel label { font-size: 10px; color: #cbd5e1; }

/* Metrics */
.metric-label { font-size: 9px !important; color: #57d6ff !important; text-transform: uppercase !important; letter-spacing: 1px !important; }
.metric-value { font-size: 13px !important; color: #fff !important; }
.metric-hint { font-size: 9px !important; color: var(--text-muted) !important; }

/* Studio tiles */
.studio-tile {
    border-width: 2px !important;
    box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.5) !important;
}
.studio-tile:hover { border-color: #009999 !important; }
.studio-title { font-size: 10px !important; }
.studio-desc { font-size: 9px !important; color: var(--text-muted) !important; }
.studio-icon { font-size: 12px !important; }

/* Mentor zone */
.mentor-zone-head { font-size: 10px !important; }
.mentor-empty, .mentor-info span { font-size: 9px !important; color: var(--text-muted) !important; }
.mentor-info p { font-size: 10px !important; }
.mentor-avatar { font-size: 10px !important; }

/* Todo modal */
.todo-modal-card {
    background: #1a1c2c !important;
    border: 4px solid #3d415f !important;
    box-shadow: 8px 8px 0 rgba(0, 0, 0, 0.5) !important;
}
.todo-modal-head h3 { font-size: 13px !important; color: #fff !important; }
.todo-modal-form input:not([type="checkbox"]),
.todo-modal-form textarea,
.todo-modal-form select {
    font-size: 11px !important;
    font-family: "Press Start 2P", monospace !important;
    border: 2px solid var(--panel-border) !important;
    background: #0d1117 !important;
    color: #cbd5e1 !important;
    padding: 12px !important;
}
.todo-modal-form input[type="datetime-local"] {
    font-family: Inter, sans-serif !important;
    font-size: 13px !important;
}
.todo-modal-form span { font-size: 10px !important; color: var(--text-muted) !important; text-transform: uppercase !important; letter-spacing: 1px !important; }
.todo-modal-form .todo-checkbox-field span,
.todo-modal-form .todo-field-note { text-transform: none !important; letter-spacing: 0 !important; font-size: 9px !important; }
.todo-modal-form .todo-checkbox-field { font-size: 10px !important; cursor: pointer !important; }
.todo-modal-form .todo-checkbox-field input[type="checkbox"] {
    flex: 0 0 16px !important;
    width: 16px !important;
    height: 16px !important;
    margin: 0 !important;
    cursor: pointer !important;
    accent-color: #57d6ff !important;
}
.todo-modal-actions button { font-size: 10px !important; padding: 10px 14px !important; }
.todo-modal-close {
    border: 2px solid var(--panel-border) !important;
    background: #0d1117 !important;
    font-size: 14px !important;
    width: 32px !important;
    height: 32px !important;
}

/* Todo notes */
.todo-note-item {
    border-width: 2px !important;
    box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.5) !important;
}
.todo-note-author p { font-size: 10px !important; }
.todo-note-author span { font-size: 9px !important; }
.todo-note-text { font-size: 11px !important; }
.todo-note-label { font-size: 9px !important; }
.todo-note-avatar { font-size: 9px !important; }
.note-empty { font-size: 10px !important; color: var(--text-muted) !important; }

/* Todo icon buttons */
.todo-icon-btn {
    width: 34px !important;
    height: 34px !important;
    font-size: 12px !important;
}

/* Composer */
.composer-placeholder,
.composer-foot { font-size: 10px !important; }

/* Search & filters */
.source-search input {
    font-family: "Press Start 2P", monospace !important;
    font-size: 10px !important;
    border: 2px solid var(--panel-border) !important;
    background: #0d1117 !important;
    color: #cbd5e1 !important;
}

.todo-nav-count {
    font-size: 11px !important;
    border: 2px solid var(--panel-border) !important;
}

/* Todo nav list - pixel font override */
.todo-nav-header,
.source-list-title {
    font-family: Inter, sans-serif !important;
    font-size: 11px !important;
}

.todo-nav-item {
    font-family: Inter, sans-serif !important;
    border-radius: 0 !important;
    border: 2px solid var(--panel-border) !important;
    box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.5) !important;
}

.todo-nav-title {
    font-family: Inter, sans-serif !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    line-height: 1.4 !important;
}

.todo-nav-meta {
    font-family: Inter, sans-serif !important;
    font-size: 11px !important;
    font-style: italic !important;
}

.todo-nav-deadline {
    font-family: Inter, sans-serif !important;
    font-size: 11px !important;
}

.todo-nav-tags {
    font-family: Inter, sans-serif !important;
    font-size: 9px !important;
}

.todo-nav-tags span {
    font-family: Inter, sans-serif !important;
    font-size: 9px !important;
}

.todo-nav-arrow {
    font-size: 12px !important;
}

.todo-nav-check {
    border-radius: 0 !important;
    border: 2px solid var(--panel-border) !important;
}

.todo-filter {
    font-family: Inter, sans-serif !important;
    font-size: 11px !important;
    font-weight: 600 !important;
}

.source-add-btn {
    font-family: Inter, sans-serif !important;
    font-size: 11px !important;
    font-weight: 600 !important;
}

/* Detail To-Do panel: smaller and non-pixel */
.panel-head h2 {
    font-family: Inter, sans-serif !important;
    font-size: 14px !important;
    font-weight: 700 !important;
}

.panel-subtitle {
    font-family: Inter, sans-serif !important;
    font-size: 11px !important;
}

.chat-hero {
    font-family: Inter, sans-serif !important;
    border: 2px solid var(--panel-border) !important;
    box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.5) !important;
    padding: 14px !important;
}

.chat-hero-icon {
    font-family: Inter, sans-serif !important;
    font-size: 9px !important;
    font-weight: 700 !important;
    height: 22px !important;
}

.chat-back-btn {
    font-family: Inter, sans-serif !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    padding: 6px 10px !important;
}

.chat-hero h3 {
    font-family: Inter, sans-serif !important;
    font-size: 14px !important;
    line-height: 1.35 !important;
    margin: 0 0 6px !important;
}

.chat-hero p {
    font-family: Inter, sans-serif !important;
    font-size: 12px !important;
    line-height: 1.55 !important;
}

.chat-hero-meta {
    font-family: Inter, sans-serif !important;
    font-size: 10px !important;
}

.chat-hero-meta span {
    font-size: 10px !important;
    padding: 3px 8px !important;
}

.chat-bubble {
    font-family: Inter, sans-serif !important;
    border: 2px solid var(--panel-border) !important;
}

.chat-role {
    font-family: Inter, sans-serif !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    letter-spacing: 0.5px !important;
}

.chat-text {
    font-family: Inter, sans-serif !important;
    font-size: 12px !important;
    line-height: 1.55 !important;
}

.todo-note-label {
    font-family: Inter, sans-serif !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    color: #cbd5e1 !important;
}

.todo-note-helper {
    font-family: Inter, sans-serif !important;
    font-size: 11px !important;
    color: var(--text-muted) !important;
}

.todo-note-textarea {
    font-family: Inter, sans-serif !important;
    font-size: 12px !important;
    line-height: 1.5 !important;
    padding: 14px !important;
    border: 2px solid var(--panel-border) !important;
}

.todo-upload-btn,
.todo-upload-remove,
.todo-note-submit {
    font-family: Inter, sans-serif !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    border-width: 2px !important;
}

.todo-note-item { padding: 10px !important; }
.todo-note-author p { font-family: Inter, sans-serif !important; font-size: 12px !important; font-weight: 600 !important; }
.todo-note-author span { font-family: Inter, sans-serif !important; font-size: 10px !important; }
.todo-note-text { font-family: Inter, sans-serif !important; font-size: 12px !important; line-height: 1.55 !important; }
.note-empty { font-family: Inter, sans-serif !important; font-size: 11px !important; }

.composer-placeholder { font-family: Inter, sans-serif !important; font-size: 12px !important; }
.composer-foot { font-family: Inter, sans-serif !important; font-size: 11px !important; }
.chip { font-family: Inter, sans-serif !important; font-size: 10px !important; padding: 4px 9px !important; }

/* Scrollbar */
.nb-root ::-webkit-scrollbar { width: 6px; }
.nb-root ::-webkit-scrollbar-track { background: #0d1117; }
.nb-root ::-webkit-scrollbar-thumb { background: #009999; border: 1px solid #1a1c2c; }

/* Mobile readability */
@media (max-width: 768px) {
    .todo-modal-card { padding: 16px !important; }
    .todo-modal-head h3 { font-size: 12px !important; }
    .todo-modal-form label,
    .todo-modal-form input,
    .todo-modal-form textarea,
    .todo-modal-form select { font-size: 12px !important; padding: 12px !important; }
    .todo-modal-form span { font-size: 11px !important; }
    .todo-modal-actions button { font-size: 11px !important; }
    .nb-title { font-size: 14px !important; }
    .nb-subtitle { font-size: 10px !important; }
    .nb-btn { font-size: 9px !important; }
    .metric-value { font-size: 14px !important; }
    .metric-label { font-size: 9px !important; }
    .metric-hint { font-size: 9px !important; }
    .nb-panel p, .nb-panel span, .nb-panel li, .nb-panel a,
    .nb-panel small, .nb-panel label { font-size: 10px !important; }
    .nb-panel h1, .nb-panel h2, .nb-panel h3, .nb-panel h4 { font-size: 11px !important; }
}

</style>
