{{-- All messages (success / failed / validation errors) are shown as toasts - see partials/notify-js --}}
@php
    $flashData = [
        'success' => session('success'),
        'error'   => session('error'),
        'errors'  => $errors->all(),
    ];
@endphp
<script>
    window.__flash = @json($flashData);
</script>
