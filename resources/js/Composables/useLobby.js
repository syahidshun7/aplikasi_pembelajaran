import { computed } from 'vue';
import { usePage, router, useForm } from '@inertiajs/vue3';
import { swal, toast } from '@/Utils/Alert';

export function useLobby(props) {
    const page = usePage();

    const joinForm = useForm({
        study_group_uuid: '',
        reason: '',
    });

    const handleLeave = (uuid) => {
        toast.confirm('LEAVE PARTY?', 'Are you sure you want to desert your comrades?')
            .then((result) => {
                if (result.isConfirmed) {
                    router.post(route('groups.leave', uuid), {}, {
                        onSuccess: () => {
                            if (toast.success) toast.success('LEFT', 'You left the party.');
                        }
                    });
                }
            });
    };

    const handleJoin = (group) => {
        const groupUuid = typeof group === 'string' ? group : String(group?.uuid || '');
        const groupName = typeof group === 'string' ? 'party ini' : String(group?.name || 'party ini');

        if (!groupUuid) {
            toast.error('JOIN_FAILED', 'Party tidak valid.');
            return;
        }

        swal.fire({
            title: 'ALASAN_GABUNG_PARTY',
            text: `Tuliskan alasan kamu ingin bergabung ke ${groupName}.`,
            input: 'textarea',
            inputPlaceholder: 'Tulis alasan singkat...',
            inputAttributes: {
                maxlength: 500,
            },
            inputClass: 'rpg-alert-textarea',
            inputValidator: (value) => {
                const reason = String(value || '').trim();
                if (reason.length < 10) {
                    return 'Alasan minimal 10 karakter.';
                }

                return null;
            },
            showCancelButton: true,
            confirmButtonText: 'KIRIM_REQUEST',
            cancelButtonText: 'BATAL',
        }).then((result) => {
            if (!result.isConfirmed) return;

            joinForm.study_group_uuid = groupUuid;
            joinForm.reason = String(result.value || '').trim();

            joinForm.post(route('groups.join'), {
                onSuccess: () => {
                    joinForm.reset();
                    if (toast.success) {
                        toast.success('SUCCESS', 'Join request sent. Waiting for admin approval.');
                    } else {
                        console.log('Join request sent');
                    }
                },
                onError: (errors) => {
                    const errorMsg = Object.values(errors || {})[0] || 'Unknown error';
                    toast.error('JOIN_FAILED', String(errorMsg));
                }
            });
        });
    };

    const auth = computed(() => page.props.auth);
    const players = computed(() => props.players || []);
    const quests = computed(() => props.quests || []);
    const studyGroups = computed(() => props.studyGroups || []); 
    const guides = computed(() => props.materi || []);
    const events = computed(() => props.events || []);

    const handleLogout = () => {
        toast.confirm('QUIT GAME?', 'Are you sure you want to exit?')
            .then((result) => {
                if (result.isConfirmed) {
                    router.post(route('logout'));
                }
            });
    };

    return {
        joinForm,
        handleLeave,
        handleJoin,
        auth,
        players,
        quests,
        studyGroups,
        guides,
        events,
        handleLogout
    };
}
