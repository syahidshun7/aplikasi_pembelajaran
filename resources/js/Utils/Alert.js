import Swal from 'sweetalert2';

const RPGAlert = Swal.mixin({
    background: '#161b22',
    color: '#4ed4d4',
    buttonsStyling: false,
    customClass: {
        popup: 'rpg-popup-box',
        title: 'rpg-title',
        htmlContainer: 'rpg-text',
        confirmButton: 'btn-pixel-alert btn-confirm-rpg',
        cancelButton: 'btn-pixel-alert btn-cancel-rpg',
        actions: 'rpg-actions'
    }
});

const RPGToast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timerProgressBar: true,
    background: '#101826',
    color: '#d9f8ff',
    customClass: {
        popup: 'border-2 border-cyan-900 font-mono text-[10px]',
    },
});

export const swal = RPGAlert;

export const toast = {
    confirm: (title, text, confirmText = 'YES, EXECUTE') => {
        return RPGAlert.fire({
            title: title || 'ARE YOU SURE?',
            text: text || 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: 'CANCEL',
        });
    },
    success: (title, text) => {
        return RPGToast.fire({
            icon: 'success',
            iconColor: '#34d399',
            title: title || 'SUCCESS!',
            text: text,
            timer: 2500,
        });
    },
    error: (title, text) => {
        return RPGToast.fire({
            icon: 'error',
            iconColor: '#f87171',
            title: title || 'FAILURE!',
            text: text,
            timer: 3500,
        });
    }
};
