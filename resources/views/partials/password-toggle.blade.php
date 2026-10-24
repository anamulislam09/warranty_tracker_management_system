<style>
    input[type="password"].form-control, input[type="text"].has-eye { padding-right: 2.9rem; }
    .pw-wrap input.is-invalid { background-image: none !important; }   /* keep the red border, hide Bootstrap's icon so it does not overlap the eye */
    .pw-eye { text-decoration: none; }
</style>
<script>
// Adds a show / hide (eye) button to every password input. Works on all pages that use the layouts.
(function () {
    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        if (input.dataset.eye) return;
        input.dataset.eye = '1';

        const wrap = document.createElement('div');
        wrap.className = 'position-relative pw-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.tabIndex = -1;
        btn.className = 'btn btn-link pw-eye text-muted position-absolute top-0 end-0 h-100 pe-3 d-flex align-items-center';
        btn.setAttribute('aria-label', 'Show or hide password');
        btn.innerHTML = '<i class="bi bi-eye"></i>';
        btn.addEventListener('click', function () {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            input.classList.toggle('has-eye', show);
            btn.innerHTML = '<i class="bi ' + (show ? 'bi-eye-slash' : 'bi-eye') + '"></i>';
        });
        wrap.appendChild(btn);
    });
})();
</script>
