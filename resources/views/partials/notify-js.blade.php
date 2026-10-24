<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
// ---------- Toaster messages (success / failed) ----------
toastr.options = {
    closeButton: true, progressBar: true, newestOnTop: true, preventDuplicates: true,
    escapeHtml: true, positionClass: 'toast-top-right', timeOut: 4500, extendedTimeOut: 1500
};

(function () {
    const f = window.__flash;
    if (!f) return;

    const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    if (f.success) toastr.success(f.success, 'Success');
    if (f.error)   toastr.error(f.error, 'Failed');

    if (f.errors && f.errors.length) {
        if (f.errors.length === 1) {
            toastr.error(f.errors[0], 'Failed');
        } else {
            const shown = f.errors.slice(0, 6).map(m => '&bull; ' + esc(m)).join('<br>');
            const more  = f.errors.length > 6 ? '<br>+ ' + (f.errors.length - 6) + ' more' : '';
            toastr.error(shown + more, 'Please fix the following', { escapeHtml: false, timeOut: 9000, extendedTimeOut: 3000 });
        }
    }
})();

// ---------- SweetAlert confirmation (delete, logout ...) ----------
// Any form with a data-confirm="message" attribute asks first.
// Optional: data-confirm-title, data-confirm-button, data-confirm-icon
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
    e.preventDefault();

    const text = form.getAttribute('data-confirm');
    const isDelete = /delete/i.test(text);

    Swal.fire({
        title: form.dataset.confirmTitle || 'Are you sure?',
        text: text,
        icon: form.dataset.confirmIcon || 'warning',
        showCancelButton: true,
        confirmButtonText: form.dataset.confirmButton || (isDelete ? 'Yes, delete it' : 'Yes, continue'),
        cancelButtonText: 'Cancel',
        confirmButtonColor: isDelete ? '#dc3545' : '#0d5df5',
        cancelButtonColor: '#6c757d',
        reverseButtons: true,
        focusCancel: true
    }).then(function (result) {
        if (result.isConfirmed) form.submit();
    });
});
</script>
