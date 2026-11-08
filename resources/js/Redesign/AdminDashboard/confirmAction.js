import Swal from 'sweetalert2';
import './confirmAction.css';

export async function confirmAction(message, action = 'Lanjutkan') {
    const permanent = /hapus permanen/i.test(action);
    const result = await Swal.fire({
        title: permanent ? 'Hapus permanen?' : 'Konfirmasi tindakan',
        text: message,
        showCancelButton: true,
        confirmButtonText: action,
        cancelButtonText: 'Batal',
        buttonsStyling: false,
        background: '#ffffff',
        color: '#19333d',
        customClass: {
            popup: 'redesign-confirm',
            title: permanent ? 'redesign-confirm-title danger' : 'redesign-confirm-title',
            htmlContainer: 'redesign-confirm-description',
            actions: 'redesign-confirm-actions',
            confirmButton: permanent ? 'redesign-confirm-button danger' : 'redesign-confirm-button',
            cancelButton: 'redesign-confirm-cancel',
        },
    });
    return result.isConfirmed;
}
