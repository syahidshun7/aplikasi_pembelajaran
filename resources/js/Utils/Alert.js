import Swal from 'sweetalert2';

const RPGAlert = Swal.mixin({
    background: '#161b22',
    color: '#4ed4d4',
    // Mematikan style bawaan agar tidak jadi bulat/biru
    buttonsStyling: false, 
    customClass: {
        popup: 'rpg-popup-box',
        title: 'rpg-title',
        htmlContainer: 'rpg-text',
        confirmButton: 'btn-pixel-alert btn-confirm-rpg',
        cancelButton: 'btn-pixel-alert btn-cancel-rpg',
        actions: 'rpg-actions' // Tambahan untuk merapikan posisi tombol
    }
});

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
        return RPGAlert.fire({
            icon: 'success',
            title: title || 'LOGGED!',
            text: text,
        });
    },
    error: (title, text) => {
        return RPGAlert.fire({
            icon: 'error',
            title: title || 'FAILURE!',
            text: text,
        });
    }
};