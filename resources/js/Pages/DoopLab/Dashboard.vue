<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useUserTheme } from '@/Composables/useUserTheme';
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
const { themeMode } = useUserTheme();
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
const pendingMentorInviteCount = computed(() => mentorInvites.value.filter((invite) => String(invite?.status || '').toLowerCase() === 'pending').length);
const researchWorkspaces = computed(() => Array.isArray(props.research_workspaces) ? props.research_workspaces : []);
const hireableCreations = computed(() => Array.isArray(props.hireable_creations) ? props.hireable_creations : []);
const directMentors = computed(() => Array.isArray(props.direct_mentors) ? props.direct_mentors : []);
const activeDirectMentors = computed(() => directMentors.value.filter((mentor) => ['pending', 'approved'].includes(String(mentor?.status || '').toLowerCase())));
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
    ...activeDirectMentors.value
        .map((mentor) => ({
            id: `direct-${mentor.id}`,
            name: mentor.name,
            username: mentor.username,
            profile_photo: mentor.profile_photo,
            status: String(mentor.status || 'pending').toUpperCase(),
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
const isTodoNavActive = computed(() => ['summary', 'todo', 'todo_form'].includes(String(panelMode.value || '')));
const isLearningPathNavActive = computed(() => String(panelMode.value || '') === 'learning_paths');
const isMentorInvitesNavActive = computed(() => String(panelMode.value || '') === 'mentor_invites');
const isHireMentorNavActive = computed(() => String(panelMode.value || '') === 'hire_mentor');

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
const canChooseMentor = computed(() => canHireMentor.value && activeDirectMentors.value.length === 0);

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

    if (!canChooseMentor.value || mentorUserId <= 0 || hiringMentor.value) {
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

    if (decision === 'cancel') {
        const result = await toast.confirm(
            'BATALKAN MENTOR?',
            'Mentorship yang sudah terhubung akan dibatalkan. Lanjutkan?',
            'YA, BATALKAN'
        );

        if (!result.isConfirmed) {
            return;
        }
    }

    try {
        await window.axios.post(route(`api.creations.mentor-invites.${decision}`, { collaborationRequest: inviteId }, false));
        const successTitle = decision === 'accept'
            ? 'INVITE_ACCEPTED'
            : (decision === 'cancel' ? 'INVITE_CANCELED' : 'INVITE_REJECTED');
        const successMessage = decision === 'accept'
            ? 'Mentorship sudah terhubung.'
            : (decision === 'cancel' ? 'Mentorship dibatalkan.' : 'Invite sudah ditolak.');
        toast.success(successTitle, successMessage);
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

        <div class="nb-root" :class="{ 'nb-root--light': themeMode === 'light' }">
            <Teleport to="body">
                <div class="nb-aurora" :class="{ 'nb-aurora--light': themeMode === 'light' }">
                    <img src="/images/Gerbang_lab_pixel_art_website (3).jpeg" alt="" />
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
                        <Link :href="route('profile.creations.create')" class="nb-btn nb-btn--solid">Buat Creation</Link>
                    </div>
                </header>

                <section class="nb-workbench">
                    <aside class="nb-panel nb-sources nb-todo-nav">
                        <button
                            v-if="canHireMentor"
                            type="button"
                            class="source-add-btn source-add-btn--hire-mentor"
                            :class="{ 'is-active': isHireMentorNavActive }"
                            title="Hire Mentor"
                            aria-label="Hire Mentor"
                            @click="showHireMentor"
                        >
                            <i class="fi fi-rr-user-add"></i>
                            <span class="nav-label">Hire Mentor</span>
                        </button>

                        <button
                            v-if="canCreateMentorTodo"
                            type="button"
                            class="source-add-btn source-add-btn--mentor-invites"
                            :class="{ 'is-active': isMentorInvitesNavActive }"
                            title="Mentor Invites"
                            aria-label="Mentor Invites"
                            @click="showMentorInvites"
                        >
                            <i class="fi fi-rr-envelope"></i>
                            <span class="nav-label">Mentor Invites ({{ pendingMentorInviteCount }})</span>
                        </button>

                        <button
                            type="button"
                            class="source-add-btn source-add-btn--learning-paths"
                            :class="{ 'is-active': isLearningPathNavActive }"
                            title="My Learning Path"
                            aria-label="My Learning Path"
                            @click="showLearningPaths"
                        >
                            <i class="fi fi-rr-road"></i>
                            <span class="nav-label">My Learning Path</span>
                        </button>

                        <Link
                            v-if="canOpenRoadmapLab"
                            :href="route('dooplab.roadmaps.index')"
                            class="source-add-btn source-add-btn--link source-add-btn--roadmap-lab"
                            title="Roadmap Lab"
                            aria-label="Roadmap Lab"
                        >
                            <i class="fi fi-rr-route"></i>
                            <span class="nav-label">Roadmap Lab</span>
                        </Link>

                        <button
                            type="button"
                            class="source-add-btn source-add-btn--todo-list"
                            :class="{ 'is-active': isTodoNavActive }"
                            title="To-Do List"
                            aria-label="To-Do List"
                            @click="showTodoList"
                        >
                            <i class="fi fi-rr-list-check"></i>
                            <span class="nav-label">To-Do List</span>
                        </button>

                        <button
                            type="button"
                            class="source-add-btn source-add-btn--logbook"
                            :class="{ 'is-active': panelMode === 'logbook' }"
                            title="Logbook"
                            aria-label="Logbook"
                            @click="showLogbook"
                        >
                            <i class="fi fi-rr-book-alt"></i>
                            <span class="nav-label">Logbook ({{ allLogbooks.length }})</span>
                        </button>

                    </aside>

                    <main ref="todoChatPanelRef" class="nb-panel nb-chat">
                        <div
                            class="panel-head panel-head--stacked"
                            :class="{
                                'todo-panel-head': (panelMode === 'summary' && !selectedTodo)
                                    || panelMode === 'logbook',
                            }"
                        >
                            <div>
                                <h2>{{ workPanelTitle }}</h2>
                                <p class="panel-subtitle">
                                    {{ workPanelSubtitle }}
                                </p>
                            </div>
                            <button
                                v-if="panelMode === 'summary' && !selectedTodo"
                                type="button"
                                class="source-add-btn todo-add-btn todo-add-btn--primary"
                                style="background:#009999 !important;color:#ffffff !important;border:1px solid #006f6f !important;box-shadow:3px 3px 0 #006f6f !important;text-shadow:none !important;"
                                @click="openTodoModal"
                            >
                                <i class="fi fi-rr-plus" style="color:#ffffff !important;"></i>
                                <span class="todo-add-label">Tambahkan to-do</span>
                            </button>
                            <button
                                v-if="panelMode === 'logbook' && !logbookPanelRef?.selectedLogbook"
                                type="button"
                                class="source-add-btn todo-add-btn todo-add-btn--primary"
                                style="background:#009999 !important;color:#ffffff !important;border:1px solid #006f6f !important;box-shadow:3px 3px 0 #006f6f !important;text-shadow:none !important;"
                                @click="logbookPanelRef?.openLogbookModal()"
                            >
                                <i class="fi fi-rr-plus" style="color:#ffffff !important;"></i>
                                <span class="todo-add-label">Buat Logbook</span>
                            </button>
                            <div v-if="panelMode === 'logbook' && logbookPanelRef?.selectedLogbook" class="logbook-toolbar">
                                <button
                                    type="button"
                                    class="chat-back-btn logbook-detail-action"
                                    title="Kembali ke daftar logbook"
                                    aria-label="Kembali ke daftar logbook"
                                    @click="logbookPanelRef.backToLogbookList()"
                                >
                                    <i class="fi fi-rr-arrow-small-left"></i>
                                    <span class="logbook-action-label">Kembali</span>
                                </button>
                                <button
                                    type="button"
                                    class="source-add-btn todo-add-btn logbook-detail-action logbook-detail-action--primary"
                                    style="background:#009999 !important;color:#ffffff !important;border:1px solid #006f6f !important;box-shadow:3px 3px 0 #006f6f !important;text-shadow:none !important;"
                                    title="Tambah entri"
                                    aria-label="Tambah entri"
                                    @click="logbookPanelRef?.openEntryModal()"
                                >
                                    <i class="fi fi-rr-plus" style="color:#ffffff !important;"></i>
                                    <span class="logbook-action-label">Tambah Entri</span>
                                </button>
                                <button
                                    v-if="logbookPanelRef?.selectedLogbook?.entries?.length"
                                    type="button"
                                    class="source-add-btn todo-add-btn logbook-detail-action logbook-detail-action--primary"
                                    style="background:#009999 !important;color:#ffffff !important;border:1px solid #006f6f !important;box-shadow:3px 3px 0 #006f6f !important;text-shadow:none !important;"
                                    title="Export CSV"
                                    aria-label="Export CSV"
                                    @click="logbookPanelRef?.exportEntryCsv()"
                                >
                                    <i class="fi fi-rr-file-csv" style="color:#ffffff !important;"></i>
                                    <span class="logbook-action-label">Export CSV</span>
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
                                    <div class="todo-nav-header dooplab-light-section-label">
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
                                <div class="mentor-user-list">
                                    <span v-if="!selectedHireCreationMentors.length" class="todo-field-note">Belum ada mentor yang terhubung.</span>
                                    <article
                                        v-for="mentor in selectedHireCreationMentors"
                                        :key="mentor.id"
                                        class="mentor-user-card"
                                    >
                                        <span class="mentor-invite-avatar">
                                            <img v-if="mentor.profile_photo" :src="`/storage/${mentor.profile_photo}`" :alt="mentor.name">
                                            <span v-else>{{ String(mentor.name || 'M').slice(0, 1).toUpperCase() }}</span>
                                        </span>

                                        <div class="mentor-user-info">
                                            <h3>{{ mentor.name || 'Mentor' }}</h3>
                                            <p>@{{ mentor.username || '-' }}</p>
                                            <small>DoopLab mentor</small>
                                        </div>

                                        <div class="mentor-user-actions">
                                            <div class="mentor-user-status-row">
                                                <span class="mentor-user-status" :class="`is-${String(mentor.status || '').toLowerCase()}`">
                                                    {{ String(mentor.status || 'pending').toUpperCase() }}
                                                </span>
                                            </div>
                                        </div>
                                    </article>
                                </div>

                                <form v-if="canChooseMentor" class="hire-mentor-card" @submit.prevent="hireMentor">
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
                            <section class="mentor-user-list custom-scroll">
                                <p v-if="!mentorInvites.length" class="source-empty">
                                    Belum ada user yang hire kamu sebagai mentor.
                                </p>

                                <article v-for="invite in mentorInvites" :key="invite.id" class="mentor-user-card">
                                    <span class="mentor-invite-avatar">
                                        <img v-if="invite.owner_profile_photo" :src="`/storage/${invite.owner_profile_photo}`" :alt="invite.owner_name">
                                        <span v-else>{{ String(invite.owner_name || 'U').slice(0, 1).toUpperCase() }}</span>
                                    </span>

                                    <div class="mentor-user-info">
                                        <h3>{{ invite.owner_name || 'Member DoopLab' }}</h3>
                                        <p>@{{ invite.owner_username || '-' }}</p>
                                        <small>{{ invite.is_direct ? 'DoopLab mentor' : (invite.creation_title || 'Creation mentor') }}</small>
                                    </div>

                                    <div class="mentor-user-actions">
                                        <div class="mentor-user-status-row">
                                            <span class="mentor-user-status" :class="`is-${String(invite.status || 'pending').toLowerCase()}`">
                                                {{ String(invite.status || 'pending').toUpperCase() }}
                                            </span>
                                        </div>
                                        <div class="mentor-user-button-row">
                                            <button
                                                v-if="String(invite.status || '').toLowerCase() === 'pending'"
                                                type="button"
                                                class="nb-btn nb-btn--solid"
                                                @click="respondMentorInvite(invite, 'accept')"
                                            >
                                                Accept
                                            </button>
                                            <button
                                                v-if="String(invite.status || '').toLowerCase() === 'pending'"
                                                type="button"
                                                class="nb-btn nb-btn--danger"
                                                @click="respondMentorInvite(invite, 'reject')"
                                            >
                                                Reject
                                            </button>
                                            <button
                                                v-if="String(invite.status || '').toLowerCase() === 'approved'"
                                                type="button"
                                                class="nb-btn nb-btn--ghost"
                                                @click="respondMentorInvite(invite, 'cancel')"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
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
                            <div class="mentor-zone-head dooplab-light-section-label">
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

        <Teleport to="body"><div v-if="showTodoModal" class="todo-modal" :class="{ 'todo-modal--light': themeMode === 'light' }" role="dialog" aria-modal="true">
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

.source-add-btn.is-active {
    border-color: #006666;
    background: #009999;
    color: #fff;
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

.mentor-user-list {
    display: grid;
    gap: 10px;
    max-height: 100%;
    overflow-y: auto;
    padding-right: 6px;
}

.mentor-user-card {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border: 2px solid var(--panel-border);
    background: rgba(5, 10, 22, 0.72);
    box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.45);
}

.mentor-user-info {
    min-width: 0;
}

.mentor-user-info h3 {
    margin: 0;
    color: #fff;
    font-family: Inter, sans-serif;
    font-size: 14px;
    font-weight: 700;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

.mentor-user-info p,
.mentor-user-info small {
    display: block;
    margin: 3px 0 0;
    color: var(--text-muted);
    font-family: Inter, sans-serif;
    font-size: 11px;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

.mentor-user-actions {
    display: grid;
    justify-items: end;
    gap: 8px;
}

.mentor-user-status-row,
.mentor-user-button-row {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
}

.mentor-user-button-row {
    flex-wrap: wrap;
}

.mentor-user-button-row .nb-btn {
    min-height: 32px;
    padding: 7px 11px;
    font-size: 11px;
}

.mentor-user-status {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 5px 8px;
    border: 1px solid rgba(248, 198, 92, 0.42);
    background: rgba(248, 198, 92, 0.08);
    color: var(--amber);
    font-size: 8px;
    font-weight: 700;
}

.mentor-user-status.is-approved {
    border-color: rgba(87, 246, 185, 0.38);
    background: rgba(87, 246, 185, 0.08);
    color: var(--green);
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

.mentor-invite-requester {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    margin-top: 10px;
}

.mentor-invite-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 34px;
    width: 34px;
    height: 34px;
    border: 1px solid rgba(87, 214, 255, 0.32);
    background: rgba(87, 214, 255, 0.08);
    color: #e8f6ff;
    font-size: 12px;
    overflow: hidden;
}

.mentor-invite-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.mentor-invite-requester > span:last-child {
    display: grid;
    gap: 3px;
    min-width: 0;
}

.mentor-invite-requester strong,
.mentor-invite-requester small {
    overflow-wrap: anywhere;
}

.mentor-invite-requester strong {
    color: #d9e7ff;
    font-family: Inter, sans-serif;
    font-size: 12px;
}

.mentor-invite-requester small {
    color: var(--text-muted);
    font-family: Inter, sans-serif;
    font-size: 11px;
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

.mentor-invite-status {
    color: #d9e7ff;
    background: rgba(87, 214, 255, 0.08);
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

<style scoped>
/* Final light-theme precedence over the legacy pixel-theme overrides above. */
.nb-root.nb-root--light {
    --panel: #f7f7f7;
    --panel-border: #8eaaaa;
    --text-muted: #596464;
    color: #202020 !important;
}

.nb-root--light .nb-topbar,
.nb-root--light .nb-panel,
.nb-root--light .studio-tile,
.nb-root--light .todo-note-item,
.nb-root--light .chat-composer,
.nb-root--light .metric-card,
.nb-root--light .todo-item,
.nb-root--light .todo-nav-item,
.nb-root--light .chat-bubble,
.nb-root--light .learning-path-card,
.nb-root--light .hire-mentor-card,
.nb-root--light .mentor-item,
.nb-root--light .mentor-user-card,
.nb-root--light .mentor-empty,
.nb-root--light .note-empty,
.nb-root--light .review-inline-form {
    border-color: #9eb8b8 !important;
    background: rgba(247, 247, 247, 0.97) !important;
    color: #202020 !important;
    box-shadow: 4px 4px 0 rgba(32, 32, 32, 0.16) !important;
}

.nb-root--light .nb-title,
.nb-root--light .panel-head h2,
.nb-root--light .studio-title,
.nb-root--light .todo-title,
.nb-root--light .todo-nav-title,
.nb-root--light .learning-path-card h3,
.nb-root--light strong {
    color: #202020 !important;
    text-shadow: none !important;
}

.nb-root--light .nb-subtitle,
.nb-root--light .panel-subtitle,
.nb-root--light .studio-desc,
.nb-root--light .todo-description,
.nb-root--light .todo-meta,
.nb-root--light .todo-deadline,
.nb-root--light .todo-nav-sub,
.nb-root--light .todo-nav-meta,
.nb-root--light .metric-label,
.nb-root--light .metric-hint,
.nb-root--light .chat-text,
.nb-root--light .composer-placeholder,
.nb-root--light .learning-path-meta,
.nb-root--light .todo-note-helper,
.nb-root--light .todo-field-note {
    color: #596464 !important;
    text-shadow: none !important;
}

.nb-root--light .nb-eyebrow,
.nb-root--light .metric-value,
.nb-root--light .chat-role,
.nb-root--light .source-list-title {
    color: #007f7f !important;
    text-shadow: none !important;
}

.nb-root--light .source-add-btn,
.nb-root--light .nb-btn--ghost,
.nb-root--light .chat-back-btn,
.nb-root--light .todo-add-btn,
.nb-root--light .todo-icon-btn,
.nb-root--light .todo-upload-btn {
    border-color: #8eaaaa !important;
    background: #fff !important;
    color: #006f6f !important;
    box-shadow: none !important;
}

.nb-root--light .source-add-btn:hover,
.nb-root--light .source-add-btn.is-active,
.nb-root--light .nb-btn--ghost:hover,
.nb-root--light .chat-back-btn:hover,
.nb-root--light .todo-add-btn:hover,
.nb-root--light .nb-btn--solid,
.nb-root--light .composer-send,
.nb-root--light .todo-note-submit {
    border-color: #006f6f !important;
    background: #009999 !important;
    color: #fff !important;
    text-shadow: none !important;
    box-shadow: none !important;
}

.nb-root--light .todo-field input,
.nb-root--light .todo-field textarea,
.nb-root--light .todo-field select,
.nb-root--light .source-search,
.nb-root--light .todo-filter,
.nb-root--light .todo-note-textarea,
.nb-root--light .chat-composer textarea {
    border-color: #8eaaaa !important;
    background: #fff !important;
    color: #202020 !important;
    box-shadow: none !important;
}

.nb-root--light .todo-item:hover,
.nb-root--light .todo-nav-item:hover,
.nb-root--light .todo-nav-item.is-active,
.nb-root--light .studio-tile:hover,
.nb-root--light .learning-path-card:hover {
    border-color: #009999 !important;
    background: #edf8f8 !important;
}

.nb-root--light .chip,
.nb-root--light .todo-badge,
.nb-root--light .todo-state,
.nb-root--light .hire-status-pill,
.nb-root--light .mentor-user-status,
.nb-root--light .mentor-invite-status {
    border-color: #9ebcbc !important;
    background: #e3f3f3 !important;
    color: #006f6f !important;
}

@media (max-width: 768px) {
    .nb-root--light .nb-topbar,
    .nb-root--light .nb-chat,
    .nb-root--light .nb-todo-nav,
    .nb-root--light .nb-studio {
        border-color: #9eb8b8 !important;
        background: rgba(247, 247, 247, 0.98) !important;
        color: #202020 !important;
    }

    .nb-root.nb-root--light.nb-root--light {
        background: transparent !important;
    }

    .nb-root.nb-root--light.nb-root--light .nb-topbar,
    .nb-root.nb-root--light.nb-root--light .nb-sources,
    .nb-root.nb-root--light.nb-root--light .nb-chat,
    .nb-root.nb-root--light.nb-root--light .nb-studio {
        border: 2px solid #087f7f !important;
        outline: 1px solid #8dcaca !important;
        outline-offset: 0 !important;
        box-shadow: 3px 3px 0 rgba(32, 32, 32, 0.16) !important;
    }

    .nb-root.nb-root--light.nb-root--light .nb-topbar {
        border-left-width: 5px !important;
    }

    .nb-root.nb-root--light.nb-root--light .studio-tile,
    .nb-root.nb-root--light.nb-root--light .metric-card,
    .nb-root.nb-root--light.nb-root--light .todo-item,
    .nb-root.nb-root--light.nb-root--light .todo-nav-item,
    .nb-root.nb-root--light.nb-root--light .learning-path-card,
    .nb-root.nb-root--light.nb-root--light .hire-mentor-card,
    .nb-root.nb-root--light.nb-root--light .mentor-item,
    .nb-root.nb-root--light.nb-root--light .mentor-user-card {
        border-left-width: 3px !important;
        box-shadow: 2px 2px 0 rgba(32, 32, 32, 0.12) !important;
    }

    .nb-root.nb-root--light.nb-root--light .studio-tile:hover,
    .nb-root.nb-root--light.nb-root--light .todo-item:hover,
    .nb-root.nb-root--light.nb-root--light .todo-nav-item:hover,
    .nb-root.nb-root--light.nb-root--light .learning-path-card:hover {
        transform: none;
    }
}

/* Profile-light depth and hierarchy. */
.nb-root--light .nb-topbar {
    border: 4px solid #202020 !important;
    border-left: 8px solid #009999 !important;
    background: #f7f7f7 !important;
    box-shadow: 8px 8px 0 rgba(32, 32, 32, 0.32) !important;
}

.nb-root--light .nb-sources,
.nb-root--light .nb-chat,
.nb-root--light .nb-studio {
    border: 4px solid #202020 !important;
    box-shadow: 8px 8px 0 rgba(32, 32, 32, 0.32) !important;
}

.nb-root--light .nb-sources,
.nb-root--light .nb-studio {
    background: rgba(240, 246, 246, 0.98) !important;
}

.nb-root--light .nb-chat {
    border-top-color: #009999 !important;
    background: rgba(247, 247, 247, 0.98) !important;
}

.nb-root--light .panel-head {
    border-bottom: 2px solid #9eb8b8 !important;
    padding-bottom: 12px !important;
}

.nb-root--light .nb-eyebrow,
.nb-root--light .source-list-title {
    border-left: 4px solid #009999;
    padding-left: 8px;
}

.nb-root--light .source-add-btn,
.nb-root--light .nb-btn,
.nb-root--light .chat-back-btn,
.nb-root--light .todo-icon-btn,
.nb-root--light .todo-upload-btn {
    border-width: 2px !important;
    border-right-width: 4px !important;
    border-bottom-width: 4px !important;
}

.nb-root--light .source-add-btn.is-active,
.nb-root--light .nb-btn--solid,
.nb-root--light .composer-send,
.nb-root--light .todo-note-submit {
    border-color: #006666 !important;
    border-right-color: #202020 !important;
    border-bottom-color: #202020 !important;
}

.nb-root--light .studio-tile,
.nb-root--light .metric-card,
.nb-root--light .todo-item,
.nb-root--light .todo-nav-item,
.nb-root--light .learning-path-card,
.nb-root--light .hire-mentor-card,
.nb-root--light .mentor-item,
.nb-root--light .mentor-user-card {
    border: 2px solid #9eb8b8 !important;
    border-left: 4px solid #009999 !important;
    background: #fff !important;
    box-shadow: 4px 4px 0 rgba(32, 32, 32, 0.16) !important;
}

.nb-root--light .studio-tile:hover,
.nb-root--light .todo-item:hover,
.nb-root--light .todo-nav-item:hover,
.nb-root--light .todo-nav-item.is-active,
.nb-root--light .learning-path-card:hover {
    border-color: #007f7f !important;
    border-left-color: #202020 !important;
    background: #e8f6f6 !important;
    box-shadow: 5px 5px 0 rgba(0, 102, 102, 0.24) !important;
    transform: translate(-1px, -1px);
}

.nb-root--light .studio-icon,
.nb-root--light .todo-check,
.nb-root--light .todo-nav-check,
.nb-root--light .mentor-avatar,
.nb-root--light .mentor-invite-avatar,
.nb-root--light .hire-status-avatar {
    border: 2px solid #007f7f !important;
    background: #e3f3f3 !important;
    color: #006f6f !important;
    box-shadow: 3px 3px 0 rgba(32, 32, 32, 0.16) !important;
}

.nb-root--light .chip,
.nb-root--light .todo-badge,
.nb-root--light .todo-state,
.nb-root--light .hire-status-pill,
.nb-root--light .mentor-user-status,
.nb-root--light .mentor-invite-status {
    border: 1px solid #007f7f !important;
    background: #e3f3f3 !important;
}

.nb-root--light .todo-field input:focus,
.nb-root--light .todo-field textarea:focus,
.nb-root--light .todo-field select:focus,
.nb-root--light .source-search:focus,
.nb-root--light .todo-filter:focus,
.nb-root--light .todo-note-textarea:focus {
    border-color: #009999 !important;
    outline: 2px solid rgba(0, 153, 153, 0.18) !important;
    outline-offset: 2px;
}

@media (max-width: 768px) {
    .nb-root--light .nb-topbar,
    .nb-root--light .nb-sources,
    .nb-root--light .nb-chat,
    .nb-root--light .nb-studio {
        border-width: 3px !important;
        box-shadow: 5px 5px 0 rgba(32, 32, 32, 0.28) !important;
    }

    .nb-root--light .nb-topbar {
        border-left-width: 6px !important;
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

.todo-form-workspace {
    padding-right: 4px;
}

.todo-panel-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
    min-height: 0;
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

.todo-panel-form .todo-field span,
.todo-panel-form .todo-checkbox-field span {
    font-family: Inter, sans-serif !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    color: #8ea8bb !important;
    text-transform: uppercase !important;
    letter-spacing: 0.8px !important;
}

.todo-panel-form .todo-field input,
.todo-panel-form .todo-field textarea,
.todo-panel-form .todo-field select {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    border: 2px solid #3d415f !important;
    background: #0d1117 !important;
    color: #cbd5e1 !important;
    padding: 10px 12px !important;
    outline: none !important;
    font-family: Inter, sans-serif !important;
    font-size: 14px !important;
}

.todo-panel-form .todo-field input[type="datetime-local"] {
    font-family: Inter, sans-serif !important;
    font-size: 14px !important;
    line-height: 1.2 !important;
}

.todo-panel-form .todo-field input:focus,
.todo-panel-form .todo-field textarea:focus,
.todo-panel-form .todo-field select:focus {
    border-color: #57d6ff !important;
}

.todo-panel-form .todo-field-note,
.todo-panel-form .todo-error {
    font-family: Inter, sans-serif !important;
    font-size: 11px !important;
}

.todo-panel-form .todo-checkbox-field {
    font-family: Inter, sans-serif !important;
    font-size: 11px !important;
    color: #cbd5e1 !important;
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

/* Light mode keeps the DoopLab environment intact and only reskins the workspace. */
.nb-root--light {
    --line: #cbdada;
    --line-strong: #8eaaaa;
    --cyan: #007f7f;
    --amber: #9a6200;
    --green: #176b43;
    --violet: #6644a3;
    --danger: #b42334;
    --text-dim: #596464;
    color: #202020;
}

.nb-root--light .nb-topbar,
.nb-root--light .nb-panel {
    border-color: #9eb8b8;
    background: rgba(247, 247, 247, 0.96);
    color: #202020;
    box-shadow: 5px 5px 0 rgba(32, 32, 32, 0.16);
    backdrop-filter: none;
}

.nb-root--light .nb-logo,
.nb-root--light .nb-btn--solid,
.nb-root--light .composer-send,
.nb-root--light .todo-note-submit {
    border-color: #006f6f;
    background: #009999;
    color: #fff;
    box-shadow: none;
}

.nb-root--light .nb-title,
.nb-root--light .panel-head h2,
.nb-root--light .studio-title,
.nb-root--light .todo-title,
.nb-root--light .todo-nav-title,
.nb-root--light .learning-path-card h3,
.nb-root--light .mentor-info strong,
.nb-root--light .mentor-user-info strong,
.nb-root--light .hire-status-info strong {
    color: #202020;
}

.nb-root--light .nb-eyebrow,
.nb-root--light .id-role,
.nb-root--light .metric-value,
.nb-root--light .chat-role,
.nb-root--light .source-list-title {
    color: #007f7f;
}

.nb-root--light .nb-subtitle,
.nb-root--light .panel-subtitle,
.nb-root--light .studio-desc,
.nb-root--light .todo-description,
.nb-root--light .todo-meta,
.nb-root--light .todo-deadline,
.nb-root--light .todo-nav-sub,
.nb-root--light .todo-nav-meta,
.nb-root--light .metric-label,
.nb-root--light .metric-hint,
.nb-root--light .chat-text,
.nb-root--light .composer-placeholder,
.nb-root--light .learning-path-meta,
.nb-root--light .todo-note-helper,
.nb-root--light .todo-field-note {
    color: #596464;
}

.nb-root--light .source-add-btn,
.nb-root--light .nb-btn--ghost,
.nb-root--light .chat-back-btn,
.nb-root--light .todo-add-btn,
.nb-root--light .todo-icon-btn,
.nb-root--light .todo-upload-btn {
    border-color: #8eaaaa;
    background: #fff;
    color: #006f6f;
    box-shadow: none;
}

.nb-root--light .source-add-btn:hover,
.nb-root--light .source-add-btn.is-active,
.nb-root--light .nb-btn--ghost:hover,
.nb-root--light .chat-back-btn:hover,
.nb-root--light .todo-add-btn:hover {
    border-color: #006f6f;
    background: #009999;
    color: #fff;
}

.nb-root--light .metric-card,
.nb-root--light .studio-tile,
.nb-root--light .todo-item,
.nb-root--light .todo-nav-item,
.nb-root--light .chat-bubble,
.nb-root--light .learning-path-card,
.nb-root--light .hire-mentor-card,
.nb-root--light .mentor-item,
.nb-root--light .mentor-user-card,
.nb-root--light .mentor-empty,
.nb-root--light .note-empty,
.nb-root--light .todo-note-item,
.nb-root--light .review-inline-form {
    border-color: #cbdada;
    background: #fff;
    color: #202020;
    box-shadow: none;
}

.nb-root--light .todo-item:hover,
.nb-root--light .todo-nav-item:hover,
.nb-root--light .todo-nav-item.is-active,
.nb-root--light .studio-tile:hover,
.nb-root--light .learning-path-card:hover {
    border-color: #009999;
    background: #edf8f8;
}

.nb-root--light .todo-field input,
.nb-root--light .todo-field textarea,
.nb-root--light .todo-field select,
.nb-root--light .source-search,
.nb-root--light .todo-filter,
.nb-root--light .todo-note-textarea,
.nb-root--light .chat-composer {
    border-color: #8eaaaa;
    background: #fff;
    color: #202020;
}

.nb-root--light input::placeholder,
.nb-root--light textarea::placeholder {
    color: #747c7c;
    opacity: 1;
}

.nb-root--light .chip,
.nb-root--light .todo-badge,
.nb-root--light .todo-state,
.nb-root--light .hire-status-pill,
.nb-root--light .mentor-user-status,
.nb-root--light .mentor-invite-status {
    background: #e3f3f3;
    border-color: #9ebcbc;
    color: #006f6f;
}

.nb-root--light .todo-check,
.nb-root--light .todo-nav-check,
.nb-root--light .todo-note-avatar,
.nb-root--light .mentor-avatar,
.nb-root--light .mentor-invite-avatar,
.nb-root--light .hire-status-avatar {
    border-color: #009999;
    background: #e3f3f3;
    color: #006f6f;
}

.nb-root--light .nb-btn--danger,
.nb-root--light .todo-icon-btn--danger,
.nb-root--light .todo-upload-remove {
    border-color: #b42334;
    background: #fff1f2;
    color: #b42334;
}

.nb-root--light .nb-btn--success,
.nb-root--light .todo-icon-btn--success {
    border-color: #176b43;
    background: #eefbf5;
    color: #176b43;
}

.todo-modal--light {
    background: rgba(32, 32, 32, 0.52) !important;
}

.todo-modal--light .todo-modal-card {
    border-color: #087f7f !important;
    background: #f7f7f7 !important;
    color: #202020 !important;
    box-shadow: 7px 7px 0 rgba(32, 32, 32, 0.24) !important;
}

.todo-modal--light .todo-modal-head h3,
.todo-modal--light .todo-modal-form span {
    color: #202020 !important;
}

.todo-modal--light .todo-modal-form input:not([type='checkbox']),
.todo-modal--light .todo-modal-form textarea,
.todo-modal--light .todo-modal-form select {
    border-color: #8eaaaa !important;
    background: #fff !important;
    color: #202020 !important;
}

.todo-modal--light .todo-modal-close,
.todo-modal--light .nb-btn--ghost {
    border-color: #8eaaaa !important;
    background: #fff !important;
    color: #202020 !important;
}

.todo-modal--light .nb-btn--solid {
    border-color: #006f6f !important;
    background: #009999 !important;
    color: #fff !important;
}

@media (max-width: 768px) {
    .nb-root--light .nb-topbar,
    .nb-root--light .nb-chat,
    .nb-root--light .nb-todo-nav,
    .nb-root--light .nb-studio {
        border-color: #9eb8b8 !important;
        background: rgba(247, 247, 247, 0.97) !important;
        color: #202020 !important;
        box-shadow: 4px 4px 0 rgba(32, 32, 32, 0.16) !important;
    }

    .nb-root--light .nb-todo-nav .source-add-btn.is-active {
        border-color: #006f6f !important;
        background: #009999 !important;
        color: #fff !important;
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

.source-add-btn.is-active {
    background: #009999 !important;
    border-color: #006666 !important;
    color: #fff !important;
    opacity: 1 !important;
}

.source-add-btn.is-active i,
.source-add-btn.is-active .nav-label {
    color: inherit !important;
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
    font-size: 14px !important;
    font-family: Inter, sans-serif !important;
    border: 2px solid var(--panel-border) !important;
    background: #0d1117 !important;
    color: #cbd5e1 !important;
    padding: 10px 12px !important;
}
.todo-modal-form input[type="datetime-local"] {
    font-family: Inter, sans-serif !important;
    font-size: 14px !important;
}
.todo-modal-form input:focus,
.todo-modal-form textarea:focus,
.todo-modal-form select:focus {
    border-color: #57d6ff !important;
}
.todo-modal-form span {
    font-family: Inter, sans-serif !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    color: var(--text-muted) !important;
    text-transform: uppercase !important;
    letter-spacing: 0.8px !important;
}
.todo-modal-form .todo-checkbox-field span,
.todo-modal-form .todo-field-note {
    font-family: Inter, sans-serif !important;
    text-transform: none !important;
    letter-spacing: 0 !important;
    font-size: 11px !important;
}
.todo-modal-form .todo-error {
    font-family: Inter, sans-serif !important;
    font-size: 11px !important;
}
.todo-modal-form .todo-checkbox-field {
    font-family: Inter, sans-serif !important;
    font-size: 11px !important;
    cursor: pointer !important;
}
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
    .todo-modal-form select {
        font-family: Inter, sans-serif !important;
        font-size: 16px !important;
        padding: 10px 12px !important;
    }
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

/* Mobile focus mode: let the active workspace lead, keep navigation secondary. */
@media (max-width: 768px) {
    .nb-root {
        min-height: calc(100dvh - 52px) !important;
        padding: 8px !important;
        padding-bottom: calc(88px + env(safe-area-inset-bottom)) !important;
        overflow-x: hidden !important;
        background:
            linear-gradient(180deg, rgba(9, 13, 20, 0.96), rgba(13, 22, 35, 0.98)) !important;
    }

    .nb-shell {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        gap: 8px !important;
    }

    .nb-topbar {
        min-height: 0 !important;
        padding: 10px !important;
        border-width: 2px !important;
        background: rgba(13, 17, 27, 0.86) !important;
    }

    .nb-title-wrap {
        width: 100% !important;
        align-items: flex-start !important;
        gap: 8px !important;
    }

    .nb-logo {
        width: 30px !important;
        height: 30px !important;
        flex: 0 0 30px !important;
        font-size: 12px !important;
    }

    .nb-eyebrow {
        font-size: 7px !important;
        letter-spacing: 1px !important;
        margin-bottom: 3px !important;
    }

    .nb-title {
        font-family: Inter, sans-serif !important;
        font-size: 13px !important;
        line-height: 1.35 !important;
    }

    .nb-subtitle,
    .nb-actions {
        display: none !important;
    }

    .nb-workbench {
        display: flex !important;
        flex-direction: column !important;
        min-width: 0 !important;
        gap: 8px !important;
    }

    .nb-panel,
    .nb-topbar,
    .nb-chat,
    .nb-todo-nav,
    .nb-studio {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }

    .nb-chat {
        order: 1 !important;
        min-height: calc(100dvh - 132px) !important;
        padding: 12px !important;
        border-width: 2px !important;
        background: rgba(14, 20, 34, 0.96) !important;
    }

    .nb-todo-nav {
        order: 2 !important;
        position: fixed !important;
        left: 8px !important;
        right: 8px !important;
        bottom: max(8px, env(safe-area-inset-bottom)) !important;
        z-index: 80 !important;
        min-height: 0 !important;
        max-height: none !important;
        padding: 10px !important;
        border-width: 2px !important;
        background: rgba(14, 20, 34, 0.94) !important;
        backdrop-filter: blur(12px) !important;
        box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.36) !important;
    }

    .nb-studio {
        order: 3 !important;
        min-height: 0 !important;
        max-height: none !important;
        padding: 10px !important;
        border-width: 2px !important;
        background: rgba(14, 20, 34, 0.62) !important;
    }

    .nb-todo-nav {
        display: grid !important;
        grid-auto-flow: column !important;
        grid-auto-columns: max-content !important;
        gap: 8px !important;
        overflow-x: auto !important;
        padding-bottom: 12px !important;
    }

    .nb-todo-nav .source-add-btn {
        width: auto !important;
        min-width: 128px !important;
        min-height: 38px !important;
        margin: 0 !important;
        padding: 8px 10px !important;
        white-space: nowrap !important;
        border-width: 2px !important;
        opacity: 0.78 !important;
    }

    .nb-todo-nav .source-add-btn i {
        font-size: 14px !important;
        line-height: 1 !important;
    }

    .nb-todo-nav .source-add-btn.is-active {
        opacity: 1 !important;
        background: #009999 !important;
        border-color: #006666 !important;
        color: #fff !important;
    }

    .nb-todo-nav .source-add-btn--hire-mentor,
    .nb-todo-nav .source-add-btn--mentor-invites {
        order: 1 !important;
    }

    .nb-todo-nav .source-add-btn--learning-paths {
        order: 2 !important;
    }

    .nb-todo-nav .source-add-btn--todo-list {
        order: 3 !important;
    }

    .nb-todo-nav .source-add-btn--logbook {
        order: 4 !important;
    }

    .nb-todo-nav .source-add-btn--roadmap-lab {
        order: 99 !important;
    }

    .panel-head {
        margin-bottom: 10px !important;
    }

    .panel-head--stacked {
        display: grid !important;
        grid-template-columns: minmax(0, 1fr) auto !important;
        align-items: start !important;
        gap: 8px !important;
    }

    .panel-head h2 {
        font-size: 13px !important;
        line-height: 1.3 !important;
        overflow-wrap: anywhere !important;
    }

    .panel-subtitle {
        font-size: 10px !important;
        line-height: 1.45 !important;
        overflow-wrap: anywhere !important;
    }

    .todo-add-btn,
    .chat-back-btn {
        width: auto !important;
        min-width: 38px !important;
        min-height: 34px !important;
        padding: 7px 9px !important;
        margin: 0 !important;
        white-space: nowrap !important;
    }

    .todo-list-workspace,
    .learning-path-list,
    .hire-mentor-workspace,
    .chat-stream {
        width: 100% !important;
        min-width: 0 !important;
        min-height: 0 !important;
        max-height: calc(100dvh - 292px) !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
        padding-right: 2px !important;
    }

    .source-empty,
    .learning-path-card,
    .learning-path-card *,
    .mentor-user-card,
    .mentor-user-card *,
    .hire-mentor-card,
    .hire-mentor-card *,
    .chat-hero,
    .chat-hero *,
    .todo-note-item,
    .todo-note-item * {
        max-width: 100% !important;
        min-width: 0 !important;
        white-space: normal !important;
        overflow-wrap: anywhere !important;
        word-break: normal !important;
    }

    .source-search {
        padding: 9px 10px !important;
        margin-bottom: 8px !important;
    }

    .source-search input {
        font-family: Inter, sans-serif !important;
        font-size: 12px !important;
    }

    .todo-filters {
        flex-wrap: nowrap !important;
        overflow-x: auto !important;
        padding-bottom: 4px !important;
        margin-bottom: 8px !important;
    }

    .todo-filter {
        flex: 0 0 auto !important;
        font-size: 10px !important;
        padding: 7px 10px !important;
    }

    .todo-nav-item,
    .learning-path-card,
    .mentor-user-card,
    .chat-hero,
    .chat-composer,
    .todo-note-item {
        border-width: 2px !important;
        box-shadow: none !important;
    }

    .todo-nav-item {
        grid-template-columns: 28px minmax(0, 1fr) 12px !important;
        gap: 8px !important;
        padding: 10px !important;
    }

    .nb-panel .todo-nav-item .todo-nav-title {
        font-family: Inter, sans-serif !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        line-height: 1.45 !important;
        color: #ebf2ff !important;
    }

    .todo-nav-sub,
    .todo-nav-tags {
        gap: 5px !important;
    }

    .nb-panel .todo-nav-item .todo-nav-meta,
    .nb-panel .todo-nav-item .todo-nav-deadline {
        font-size: 11px !important;
    }

    .nb-panel .todo-nav-tags .todo-badge,
    .nb-panel .todo-nav-tags .todo-state {
        font-size: 8px !important;
        line-height: 1.2 !important;
        letter-spacing: 0.2px !important;
        padding: 3px 6px !important;
        border-radius: 999px !important;
    }

    .studio-grid {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 8px !important;
        max-height: none !important;
        overflow: visible !important;
    }

    .studio-tile {
        min-height: 54px !important;
        padding: 9px !important;
    }

    .studio-desc,
    .mentor-zone {
        display: none !important;
    }

    .mentor-user-card {
        grid-template-columns: 38px minmax(0, 1fr) !important;
    }

    .mentor-user-actions {
        grid-column: 1 / -1 !important;
        justify-items: stretch !important;
    }

    .mentor-user-status-row,
    .mentor-user-button-row {
        width: 100% !important;
    }

    .mentor-user-status-row {
        justify-content: flex-start !important;
    }

    .mentor-user-button-row {
        justify-content: stretch !important;
    }

    .mentor-user-status {
        flex: 0 0 auto !important;
        justify-content: center !important;
    }

    .mentor-user-button-row .nb-btn {
        flex: 1 1 0 !important;
        justify-content: center !important;
    }
}

@media (max-width: 520px) {
    .nb-studio {
        display: none !important;
    }

    .nb-root {
        padding: 6px !important;
        padding-bottom: calc(68px + env(safe-area-inset-bottom)) !important;
    }

    .nb-topbar,
    .nb-chat,
    .nb-todo-nav {
        padding: 8px !important;
    }

    .panel-head--stacked {
        grid-template-columns: 1fr !important;
    }

    .todo-add-btn,
    .chat-back-btn {
        justify-self: stretch !important;
        justify-content: center !important;
        width: 100% !important;
    }

    .todo-list-workspace,
    .learning-path-list,
    .hire-mentor-workspace,
    .chat-stream {
        max-height: calc(100dvh - 286px) !important;
    }

    .nb-todo-nav {
        left: 6px !important;
        right: 6px !important;
        bottom: max(6px, env(safe-area-inset-bottom)) !important;
        display: grid !important;
        grid-template-columns: repeat(auto-fit, minmax(44px, 1fr)) !important;
        grid-auto-flow: row !important;
        grid-auto-columns: initial !important;
        overflow-x: hidden !important;
        gap: 6px !important;
        padding-bottom: 8px !important;
    }

    .nb-todo-nav .source-add-btn {
        width: 100% !important;
        min-width: 0 !important;
        min-height: 42px !important;
        padding: 0 !important;
        gap: 0 !important;
        white-space: nowrap !important;
        line-height: 1.25 !important;
        overflow-wrap: anywhere !important;
    }

    .nb-todo-nav .source-add-btn .nav-label {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        margin: -1px !important;
        padding: 0 !important;
        overflow: hidden !important;
        clip: rect(0, 0, 0, 0) !important;
        white-space: nowrap !important;
        border: 0 !important;
    }

    .nb-todo-nav .source-add-btn i {
        flex: 0 0 auto !important;
        font-size: 15px !important;
    }
}

</style>

<style scoped>
/* Must remain last: the legacy dashboard skin above uses important declarations. */
.nb-root.nb-root--light .todo-note-item {
    border: 2px solid #9eb8b8 !important;
    border-left: 4px solid #009999 !important;
    background: #fff !important;
    color: #202020 !important;
    box-shadow: 3px 3px 0 rgba(32, 32, 32, 0.12) !important;
}

.nb-root.nb-root--light .todo-note-item--latest {
    border-color: #087f7f !important;
    border-left-color: #202020 !important;
    background: #edf8f8 !important;
}

.nb-root.nb-root--light .todo-note-avatar {
    border: 2px solid #087f7f !important;
    background: #e3f3f3 !important;
    color: #006f6f !important;
    opacity: 1 !important;
    text-shadow: none !important;
}

.nb-root.nb-root--light .todo-note-author p {
    color: #202020 !important;
    opacity: 1 !important;
    text-shadow: none !important;
}

.nb-root.nb-root--light .todo-note-author span {
    color: #4f5959 !important;
    opacity: 1 !important;
    text-shadow: none !important;
}

.nb-root.nb-root--light .todo-note-text {
    color: #202020 !important;
    opacity: 1 !important;
    text-shadow: none !important;
}

.nb-root.nb-root--light .todo-nav-header {
    border-left: 4px solid #009999;
    padding-left: 8px;
    color: #006f6f !important;
    opacity: 1 !important;
}

.nb-root.nb-root--light .todo-nav-item {
    border-color: #7fa2a2 !important;
    border-left-color: #009999 !important;
    background: #fff !important;
}

.nb-root.nb-root--light .todo-nav-item.is-active {
    border-color: #006f6f !important;
    border-left-color: #202020 !important;
    background: #e3f5f5 !important;
    box-shadow: 4px 4px 0 rgba(0, 111, 111, 0.2) !important;
}

.nb-root.nb-root--light .todo-nav-title {
    color: #202020 !important;
    opacity: 1 !important;
    text-shadow: none !important;
}

.nb-root.nb-root--light .todo-nav-item.is-completed .todo-nav-title {
    color: #4f5959 !important;
}

.nb-root.nb-root--light .todo-nav-meta {
    color: #4f5959 !important;
    opacity: 1 !important;
}

.nb-root.nb-root--light .todo-nav-deadline.is-none {
    color: #557070 !important;
}

.nb-root.nb-root--light .todo-nav-deadline.is-safe {
    color: #006f6f !important;
}

.nb-root.nb-root--light .todo-nav-deadline.is-soon {
    color: #946000 !important;
}

.nb-root.nb-root--light .todo-nav-deadline.is-urgent {
    color: #a84600 !important;
}

.nb-root.nb-root--light .todo-nav-deadline.is-overdue {
    color: #b42334 !important;
}

.nb-root.nb-root--light .todo-nav-deadline.is-done {
    color: #176b43 !important;
}

.nb-root.nb-root--light .todo-nav-check {
    border: 2px solid #087f7f !important;
    background: #f7f7f7 !important;
    color: #006f6f !important;
    opacity: 1 !important;
}

.nb-root.nb-root--light .todo-nav-check.is-done {
    border-color: #21824b !important;
    background: #e7f6ed !important;
    color: #176238 !important;
}

.nb-root.nb-root--light .todo-badge,
.nb-root.nb-root--light .todo-state {
    border: 1px solid #5c9999 !important;
    background: #e3f3f3 !important;
    color: #006f6f !important;
    opacity: 1 !important;
}

.nb-root.nb-root--light .todo-state.is-done,
.nb-root.nb-root--light .todo-state.is-approved {
    border-color: #21824b !important;
    background: #e7f6ed !important;
    color: #176238 !important;
}

.nb-root.nb-root--light .todo-nav-arrow {
    color: #007f7f !important;
    opacity: 1 !important;
}

.nb-root.nb-root--light .chat-bubble {
    border: 2px solid #7fa2a2 !important;
    border-left: 4px solid #009999 !important;
    background: #fff !important;
    color: #202020 !important;
    box-shadow: 4px 4px 0 rgba(32, 32, 32, 0.14) !important;
}

.nb-root.nb-root--light .chat-role,
.nb-root.nb-root--light .todo-note-label {
    color: #006f6f !important;
    opacity: 1 !important;
    text-shadow: none !important;
}

.nb-root.nb-root--light .note-empty,
.nb-root.nb-root--light .todo-note-helper {
    color: #4f5959 !important;
    opacity: 1 !important;
    text-shadow: none !important;
}

.nb-root.nb-root--light .chat-composer--todo {
    border: 3px solid #7fa2a2 !important;
    border-top-color: #009999 !important;
    background: #f7f7f7 !important;
    box-shadow: 5px 5px 0 rgba(32, 32, 32, 0.16) !important;
}

.nb-root.nb-root--light .todo-note-textarea {
    border: 2px solid #668b8b !important;
    background: #fff !important;
    color: #202020 !important;
}

.nb-root.nb-root--light .todo-note-textarea::placeholder {
    color: #6a7474 !important;
    opacity: 1 !important;
}

.nb-root.nb-root--light .todo-upload-btn {
    border-color: #087f7f !important;
    background: #fff !important;
    color: #006f6f !important;
}

.nb-root.nb-root--light .todo-note-submit {
    border-color: #006666 !important;
    border-right-color: #202020 !important;
    border-bottom-color: #202020 !important;
    background: #009999 !important;
    color: #fff !important;
}

.nb-root.nb-root--light .source-search {
    border: 2px solid #668b8b !important;
    background: #fff !important;
    color: #006f6f !important;
    box-shadow: inset 0 -2px 0 #e3eeee !important;
}

.nb-root.nb-root--light .source-search i {
    color: #006f6f !important;
    opacity: 1 !important;
}

.nb-root.nb-root--light .source-search input {
    border: 0 !important;
    background: #fff !important;
    color: #202020 !important;
    box-shadow: none !important;
    outline: 0 !important;
}

.nb-root.nb-root--light .source-search input::placeholder {
    color: #626c6c !important;
    opacity: 1 !important;
}

.nb-root.nb-root--light .source-search:focus-within {
    border-color: #009999 !important;
    box-shadow: inset 0 -2px 0 #009999, 0 0 0 2px rgba(0, 153, 153, 0.14) !important;
}

.nb-root.nb-root--light .custom-scroll,
.nb-root.nb-root--light .todo-list-workspace,
.nb-root.nb-root--light .todo-nav-list,
.nb-root.nb-root--light .source-list-wrap,
.nb-root.nb-root--light .studio-grid {
    scrollbar-color: #009999 #dceaea !important;
    scrollbar-width: thin;
}

.nb-root.nb-root--light ::-webkit-scrollbar-track {
    background: #dceaea !important;
    border-left: 1px solid #bfd2d2;
}

.nb-root.nb-root--light ::-webkit-scrollbar-thumb {
    border: 1px solid #006f6f !important;
    background: #009999 !important;
}

.nb-root.nb-root--light ::-webkit-scrollbar-thumb:hover {
    background: #007f7f !important;
}

.nb-root.nb-root--light .mentor-user-info h3 {
    color: #202020 !important;
    opacity: 1 !important;
    text-shadow: none !important;
}

.nb-root.nb-root--light .mentor-user-info p {
    color: #006f6f !important;
    opacity: 1 !important;
    text-shadow: none !important;
}

.nb-root.nb-root--light .mentor-user-info small {
    color: #4f5959 !important;
    opacity: 1 !important;
    text-shadow: none !important;
}

.nb-root.nb-root--light .mentor-user-status.is-approved {
    border: 2px solid #21824b !important;
    background: #e7f6ed !important;
    color: #145a32 !important;
    opacity: 1 !important;
    text-shadow: none !important;
}

.nb-root.nb-root--light .nb-topbar,
.nb-root.nb-root--light .nb-sources,
.nb-root.nb-root--light .nb-chat,
.nb-root.nb-root--light .nb-studio {
    outline: 1px solid #8dcaca !important;
    outline-offset: 2px;
}

.nb-aurora--light::after {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(247, 247, 247, 0.3);
    pointer-events: none;
}

.nb-root.nb-root--light .nb-chat {
    border-top-color: #202020 !important;
}

.nb-root.nb-root--light .source-add-btn:not(.is-active) {
    border-color: #668b8b !important;
    background: #f7f7f7 !important;
    color: #006f6f !important;
    opacity: 1 !important;
}

.nb-root.nb-root--light .source-add-btn:not(.is-active) i,
.nb-root.nb-root--light .source-add-btn:not(.is-active) .nav-label {
    color: #006f6f !important;
    opacity: 1 !important;
    text-shadow: none !important;
}

.nb-root.nb-root--light .learning-path-topline span {
    border-color: #55aaaa !important;
    background: #e3f5f5 !important;
    color: #006f6f !important;
    text-shadow: none !important;
}

.nb-root.nb-root--light .learning-path-topline strong {
    border-color: #21824b !important;
    background: #edf9f2 !important;
    color: #176238 !important;
    text-shadow: none !important;
}

.nb-root.nb-root--light .learning-path-card h3 {
    color: #202020 !important;
}

.nb-root.nb-root--light .learning-path-card p,
.nb-root.nb-root--light .learning-path-meta span {
    color: #4f5959 !important;
    opacity: 1 !important;
    text-shadow: none !important;
}

.nb-root.nb-root--light .learning-path-cta {
    border-color: #006666 !important;
    border-right: 4px solid #202020 !important;
    border-bottom: 4px solid #202020 !important;
    background: #009999 !important;
    color: #fff !important;
    box-shadow: none !important;
    text-shadow: none !important;
}

.nb-root.nb-root--light .studio-title,
.nb-root.nb-root--light .studio-desc,
.nb-root.nb-root--light .mentor-zone-head,
.nb-root.nb-root--light .mentor-info p,
.nb-root.nb-root--light .mentor-info span {
    opacity: 1 !important;
    text-shadow: none !important;
}

.nb-root.nb-root--light .studio-title,
.nb-root.nb-root--light .mentor-info p {
    color: #202020 !important;
}

.nb-root.nb-root--light .studio-desc,
.nb-root.nb-root--light .mentor-info span {
    color: #4f5959 !important;
}

.nb-root.nb-root--light .mentor-zone-head {
    border-bottom: 1px solid #8eaaaa;
    padding-bottom: 8px;
    color: #006f6f !important;
}

.nb-root.nb-root--light .nb-topbar {
    border: 4px solid #087f7f !important;
    border-left: 8px solid #009999 !important;
    background: #f7f7f7 !important;
    box-shadow: 8px 8px 0 rgba(32, 32, 32, 0.2) !important;
}

.nb-root.nb-root--light .nb-sources,
.nb-root.nb-root--light .nb-chat,
.nb-root.nb-root--light .nb-studio {
    border: 4px solid #087f7f !important;
    box-shadow: 8px 8px 0 rgba(32, 32, 32, 0.2) !important;
}

.nb-root.nb-root--light .nb-sources,
.nb-root.nb-root--light .nb-studio {
    background: rgba(240, 246, 246, 0.98) !important;
}

.nb-root.nb-root--light .nb-chat {
    border-top-color: #087f7f !important;
    background: rgba(247, 247, 247, 0.98) !important;
}

.nb-root.nb-root--light .panel-head {
    border-bottom: 2px solid #9eb8b8 !important;
    padding-bottom: 12px !important;
}

.nb-root.nb-root--light .source-add-btn,
.nb-root.nb-root--light .nb-btn,
.nb-root.nb-root--light .chat-back-btn,
.nb-root.nb-root--light .todo-icon-btn,
.nb-root.nb-root--light .todo-upload-btn {
    border-width: 2px !important;
    border-right-width: 4px !important;
    border-bottom-width: 4px !important;
}

.nb-root.nb-root--light .source-add-btn.is-active,
.nb-root.nb-root--light .nb-btn--solid,
.nb-root.nb-root--light .composer-send,
.nb-root.nb-root--light .todo-note-submit {
    border-color: #006666 !important;
    border-right-color: #202020 !important;
    border-bottom-color: #202020 !important;
}

.nb-root.nb-root--light .studio-tile,
.nb-root.nb-root--light .metric-card,
.nb-root.nb-root--light .todo-item,
.nb-root.nb-root--light .todo-nav-item,
.nb-root.nb-root--light .learning-path-card,
.nb-root.nb-root--light .hire-mentor-card,
.nb-root.nb-root--light .mentor-item,
.nb-root.nb-root--light .mentor-user-card {
    border: 2px solid #9eb8b8 !important;
    border-left: 4px solid #009999 !important;
    background: #fff !important;
    box-shadow: 4px 4px 0 rgba(32, 32, 32, 0.16) !important;
}

.nb-root.nb-root--light .studio-tile:hover,
.nb-root.nb-root--light .todo-item:hover,
.nb-root.nb-root--light .todo-nav-item:hover,
.nb-root.nb-root--light .todo-nav-item.is-active,
.nb-root.nb-root--light .learning-path-card:hover {
    border-color: #007f7f !important;
    border-left-color: #202020 !important;
    background: #e8f6f6 !important;
    box-shadow: 5px 5px 0 rgba(0, 102, 102, 0.24) !important;
    transform: translate(-1px, -1px);
}

.nb-root.nb-root--light .studio-icon,
.nb-root.nb-root--light .todo-check,
.nb-root.nb-root--light .todo-nav-check,
.nb-root.nb-root--light .mentor-avatar,
.nb-root.nb-root--light .mentor-invite-avatar,
.nb-root.nb-root--light .hire-status-avatar {
    border: 2px solid #007f7f !important;
    background: #e3f3f3 !important;
    color: #006f6f !important;
    box-shadow: 3px 3px 0 rgba(32, 32, 32, 0.16) !important;
}

.nb-root.nb-root--light .todo-field input:focus,
.nb-root.nb-root--light .todo-field textarea:focus,
.nb-root.nb-root--light .todo-field select:focus,
.nb-root.nb-root--light .source-search:focus,
.nb-root.nb-root--light .todo-filter:focus,
.nb-root.nb-root--light .todo-note-textarea:focus {
    border-color: #009999 !important;
    outline: 2px solid rgba(0, 153, 153, 0.18) !important;
    outline-offset: 2px;
}

.nb-root.nb-root--light {
    --panel: #f7f7f7;
    --panel-border: #8eaaaa;
    --text-muted: #596464;
    color: #202020 !important;
}

.nb-root--light .nb-topbar,
.nb-root--light .nb-panel,
.nb-root--light .studio-tile,
.nb-root--light .metric-card,
.nb-root--light .todo-item,
.nb-root--light .todo-nav-item,
.nb-root--light .chat-bubble,
.nb-root--light .chat-composer,
.nb-root--light .todo-note-item,
.nb-root--light .learning-path-card,
.nb-root--light .hire-mentor-card,
.nb-root--light .mentor-item,
.nb-root--light .mentor-user-card,
.nb-root--light .mentor-empty,
.nb-root--light .note-empty,
.nb-root--light .review-inline-form {
    border-color: #9eb8b8 !important;
    background: rgba(247, 247, 247, 0.97) !important;
    color: #202020 !important;
    box-shadow: 4px 4px 0 rgba(32, 32, 32, 0.16) !important;
    text-shadow: none !important;
}

.nb-root--light .nb-title,
.nb-root--light .panel-head h2,
.nb-root--light .studio-title,
.nb-root--light .todo-title,
.nb-root--light .todo-nav-title,
.nb-root--light .learning-path-card h3,
.nb-root--light strong {
    color: #202020 !important;
    text-shadow: none !important;
}

.nb-root--light .nb-subtitle,
.nb-root--light .panel-subtitle,
.nb-root--light .studio-desc,
.nb-root--light .todo-description,
.nb-root--light .todo-meta,
.nb-root--light .todo-deadline,
.nb-root--light .todo-nav-sub,
.nb-root--light .todo-nav-meta,
.nb-root--light .metric-label,
.nb-root--light .metric-hint,
.nb-root--light .chat-text,
.nb-root--light .composer-placeholder,
.nb-root--light .learning-path-meta,
.nb-root--light .todo-note-helper,
.nb-root--light .todo-field-note {
    color: #596464 !important;
    text-shadow: none !important;
}

.nb-root--light .nb-eyebrow,
.nb-root--light .metric-value,
.nb-root--light .chat-role,
.nb-root--light .source-list-title {
    color: #007f7f !important;
    text-shadow: none !important;
}

.nb-root--light .source-add-btn,
.nb-root--light .nb-btn--ghost,
.nb-root--light .chat-back-btn,
.nb-root--light .todo-add-btn,
.nb-root--light .todo-icon-btn,
.nb-root--light .todo-upload-btn {
    border-color: #8eaaaa !important;
    background: #fff !important;
    color: #006f6f !important;
    box-shadow: none !important;
}

.nb-root--light .source-add-btn:hover,
.nb-root--light .source-add-btn.is-active,
.nb-root--light .nb-btn--ghost:hover,
.nb-root--light .chat-back-btn:hover,
.nb-root--light .todo-add-btn:hover,
.nb-root--light .nb-btn--solid,
.nb-root--light .composer-send,
.nb-root--light .todo-note-submit {
    border-color: #006f6f !important;
    background: #009999 !important;
    color: #fff !important;
    box-shadow: none !important;
    text-shadow: none !important;
}

.nb-root--light .todo-field input,
.nb-root--light .todo-field textarea,
.nb-root--light .todo-field select,
.nb-root--light .source-search,
.nb-root--light .todo-filter,
.nb-root--light .todo-note-textarea,
.nb-root--light .chat-composer textarea {
    border-color: #8eaaaa !important;
    background: #fff !important;
    color: #202020 !important;
    box-shadow: none !important;
}

.nb-root--light .todo-item:hover,
.nb-root--light .todo-nav-item:hover,
.nb-root--light .todo-nav-item.is-active,
.nb-root--light .studio-tile:hover,
.nb-root--light .learning-path-card:hover {
    border-color: #009999 !important;
    background: #edf8f8 !important;
}

.nb-root--light .chip,
.nb-root--light .todo-badge,
.nb-root--light .todo-state,
.nb-root--light .hire-status-pill,
.nb-root--light .mentor-user-status,
.nb-root--light .mentor-invite-status {
    border-color: #9ebcbc !important;
    background: #e3f3f3 !important;
    color: #006f6f !important;
}

@media (max-width: 768px) {
    .nb-root--light .nb-topbar,
    .nb-root--light .nb-chat,
    .nb-root--light .nb-todo-nav,
    .nb-root--light .nb-studio {
        border-color: #9eb8b8 !important;
        background: rgba(247, 247, 247, 0.98) !important;
        color: #202020 !important;
    }

    .todo-panel-head {
        grid-template-columns: minmax(0, 1fr) auto !important;
        align-items: center !important;
        border-bottom: 1px solid #8eaaaa !important;
        padding-bottom: 7px !important;
        margin-bottom: 8px !important;
    }

    .todo-panel-head .panel-subtitle {
        margin-top: 2px !important;
        font-size: 8px !important;
        line-height: 1.35 !important;
    }

    .todo-panel-head .todo-add-btn {
        width: 34px !important;
        min-width: 34px !important;
        min-height: 32px !important;
        padding: 0 !important;
        border: 1px solid #006f6f !important;
        background: #009999 !important;
        color: #fff !important;
        box-shadow: none !important;
        text-shadow: none !important;
    }

    .todo-panel-head .todo-add-btn:hover,
    .todo-panel-head .todo-add-btn:focus-visible {
        background: #007f7f !important;
        color: #fff !important;
    }

    .todo-panel-head .todo-add-btn i {
        margin: 0 !important;
        font-size: 12px !important;
    }

    .todo-panel-head .todo-add-label {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        overflow: hidden !important;
        clip: rect(0, 0, 0, 0) !important;
        white-space: nowrap !important;
    }

    .todo-list-workspace .source-search {
        height: 32px !important;
        min-height: 32px !important;
        padding: 4px 8px !important;
        border-width: 1px !important;
        margin-bottom: 6px !important;
        box-shadow: none !important;
    }

    .todo-list-workspace .source-search i {
        font-size: 10px !important;
        line-height: 1 !important;
    }

    .todo-list-workspace .source-search input {
        height: 22px !important;
        min-height: 22px !important;
        padding: 0 !important;
        font-size: 10px !important;
        line-height: 22px !important;
    }

    .todo-list-workspace .todo-filters {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 4px !important;
        overflow: visible !important;
        padding: 0 !important;
        margin-bottom: 7px !important;
    }

    .todo-list-workspace .todo-filter {
        width: 100% !important;
        min-width: 0 !important;
        padding: 6px 3px !important;
        border-width: 1px !important;
        font-size: 8px !important;
        box-shadow: none !important;
    }
}

/* The DoopLab work panel can be narrow even while the browser viewport is wide. */
.nb-root .todo-panel-head .todo-add-btn {
    width: 34px !important;
    min-width: 34px !important;
    min-height: 32px !important;
    padding: 0 !important;
    border: 1px solid #006f6f !important;
    background: #009999 !important;
    color: #fff !important;
    box-shadow: 3px 3px 0 #006f6f !important;
    text-shadow: none !important;
}

.nb-root .todo-panel-head .todo-add-btn:hover,
.nb-root .todo-panel-head .todo-add-btn:focus-visible {
    border-color: #005f5f !important;
    background: #007f7f !important;
    color: #fff !important;
    box-shadow: 2px 2px 0 #005f5f !important;
}

.nb-root .todo-panel-head .todo-add-btn i {
    margin: 0 !important;
    font-size: 12px !important;
}

.nb-root .todo-panel-head .todo-add-label {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
}

.nb-root .logbook-toolbar {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    justify-content: flex-end !important;
    justify-self: end !important;
    gap: 6px !important;
    width: max-content !important;
    min-width: max-content !important;
}

.nb-root .logbook-toolbar .logbook-detail-action {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 34px !important;
    min-width: 34px !important;
    min-height: 32px !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 1px solid #6f9292 !important;
    background: #fff !important;
    color: #006f6f !important;
    box-shadow: 2px 2px 0 #9eb8b8 !important;
    text-shadow: none !important;
}

.nb-root .logbook-toolbar .logbook-detail-action--primary {
    border-color: #006f6f !important;
    background: #009999 !important;
    color: #fff !important;
    box-shadow: 3px 3px 0 #006f6f !important;
}

.nb-root .logbook-toolbar .logbook-detail-action i {
    margin: 0 !important;
    font-size: 12px !important;
    line-height: 1 !important;
}

.nb-root .logbook-toolbar .logbook-detail-action:hover,
.nb-root .logbook-toolbar .logbook-detail-action:focus-visible {
    border-color: #006f6f !important;
    background: #e3f3f3 !important;
    color: #005f5f !important;
}

.nb-root .logbook-toolbar .logbook-detail-action--primary:hover,
.nb-root .logbook-toolbar .logbook-detail-action--primary:focus-visible {
    background: #007f7f !important;
    color: #fff !important;
}

.nb-root .logbook-toolbar .logbook-action-label {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
}

@media (max-width: 640px) {
    /* Mobile readability pass for the DoopLab workspace and its modals. */
    .nb-root p,
    .todo-modal p {
        font-size: 11px !important;
    }

    .nb-root small,
    .todo-modal small {
        font-size: 9px !important;
        line-height: 1.55 !important;
    }

    .nb-root .nb-eyebrow {
        font-size: 10px !important;
    }

    .nb-root .nb-subtitle,
    .nb-root .studio-title,
    .nb-root .chat-role {
        font-size: 12px !important;
    }

    .nb-root .panel-subtitle,
    .nb-root .todo-note-helper,
    .nb-root .note-empty {
        font-size: 13px !important;
    }

    .nb-root .todo-note-text {
        font-size: 14px !important;
    }

    .nb-root .studio-desc,
    .nb-root .mentor-empty {
        font-size: 11px !important;
    }

    .nb-root h1,
    .todo-modal h1 {
        font-size: 20px !important;
        line-height: 1.3 !important;
    }

    .nb-root h2,
    .todo-modal h2 {
        font-size: 16px !important;
        line-height: 1.35 !important;
    }

    .nb-root h3,
    .todo-modal h3 {
        font-size: 13px !important;
        line-height: 1.4 !important;
    }

    .nb-root .todo-list-workspace .source-search input {
        font-size: 12px !important;
    }

    .nb-root .todo-list-workspace .todo-filter {
        font-size: 10px !important;
    }

    .nb-root .todo-list-workspace .todo-badge,
    .nb-root .todo-list-workspace .todo-state {
        font-size: 9px !important;
    }

    .nb-root .todo-list-workspace .todo-nav-header {
        font-size: 10px !important;
    }

    .nb-root .todo-list-workspace .todo-nav-title {
        font-size: 13px !important;
        line-height: 1.45 !important;
    }

    .nb-root .todo-list-workspace .todo-nav-meta,
    .nb-root .todo-list-workspace .todo-nav-deadline {
        font-size: 12px !important;
        line-height: 1.45 !important;
    }

    .nb-root button,
    .todo-modal button,
    .nb-root .todo-upload-btn {
        min-height: 42px !important;
    }

    .nb-root .chat-composer--todo .todo-note-upload-row {
        display: grid !important;
        grid-template-columns: minmax(0, 1fr) !important;
        align-items: stretch !important;
        gap: 10px !important;
    }

    .nb-root .chat-composer--todo .todo-note-upload-actions {
        display: flex !important;
        width: 100% !important;
        gap: 8px !important;
    }

    .nb-root .chat-composer--todo .todo-upload-btn,
    .nb-root .chat-composer--todo .todo-upload-remove {
        min-height: 44px !important;
        padding: 10px 12px !important;
    }

    .nb-root .chat-composer--todo .todo-note-submit {
        width: 100% !important;
        min-height: 46px !important;
        justify-content: center !important;
    }

    .nb-root .chat-composer--todo .todo-inline-actions {
        display: flex !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 10px !important;
        width: 100% !important;
        padding-top: 10px !important;
        border-top: 1px solid rgba(111, 146, 146, 0.7) !important;
    }

    .nb-root .chat-composer--todo .todo-inline-actions-left,
    .nb-root .chat-composer--todo .todo-inline-actions-right {
        display: flex !important;
        width: auto !important;
        align-items: center !important;
        justify-content: flex-start !important;
        flex-wrap: wrap !important;
        gap: 8px !important;
    }

    .nb-root .chat-composer--todo .todo-inline-actions-right {
        margin-left: auto !important;
        justify-content: flex-end !important;
    }

    .nb-root .chat-composer--todo .todo-icon-btn,
    .nb-root .todo-panel-head .todo-add-btn,
    .nb-root .logbook-toolbar .logbook-detail-action {
        width: 42px !important;
        min-width: 42px !important;
        height: 42px !important;
        min-height: 42px !important;
    }

    .nb-root .chat-composer--todo .todo-icon-btn i,
    .nb-root .todo-panel-head .todo-add-btn i,
    .nb-root .logbook-toolbar .logbook-detail-action i {
        font-size: 15px !important;
        line-height: 1 !important;
    }
}
</style>
