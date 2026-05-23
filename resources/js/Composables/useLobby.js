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
            title: 'GABUNG GUILD',
            html: `<div style="text-align:center;margin-bottom:12px;"><img src="https://api.iconify.design/pixelarticons:users.svg" style="width:32px;height:32px;filter:invert(1) sepia(1) saturate(5) hue-rotate(140deg);margin:0 auto 10px;" alt=""><p style="font-family:'Press Start 2P',cursive;font-size:7px;line-height:1.8;color:#94a3b8;text-transform:uppercase;">Tulis alasan kamu ingin bergabung ke <span style="color:#4ed4d4;">${groupName}</span></p></div>`,
            input: 'textarea',
            inputPlaceholder: 'Alasan bergabung...',
            inputAttributes: {
                maxlength: 500,
            },
            inputClass: 'rpg-alert-textarea',
            customClass: {
                popup: 'rpg-popup-box rpg-guild-box',
                title: 'rpg-title',
                htmlContainer: 'rpg-text',
                confirmButton: 'btn-pixel-alert btn-confirm-rpg',
                cancelButton: 'btn-pixel-alert btn-cancel-rpg',
                actions: 'rpg-actions',
                validationMessage: 'rpg-alert-validation',
            },
            inputValidator: (value) => {
                const reason = String(value || '').trim();
                if (reason.length < 10) {
                    return 'Alasan minimal 10 karakter.';
                }

                return null;
            },
            showCancelButton: true,
            confirmButtonText: 'KIRIM REQUEST',
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
