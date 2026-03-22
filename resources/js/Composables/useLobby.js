import { computed } from 'vue';
import { usePage, router, useForm } from '@inertiajs/vue3';
import { toast } from '@/Utils/Alert';

export function useLobby(props) {
    const page = usePage();

    const joinForm = useForm({
        study_group_uuid: '',
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

    const handleJoin = (groupUuid) => {
        joinForm.study_group_uuid = groupUuid;
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
                const errorMsg = Object.values(errors)[0] || 'Unknown error';
                alert('JOIN_FAILED: ' + errorMsg);
            }
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
