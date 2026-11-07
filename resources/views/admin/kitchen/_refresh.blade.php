@push('scripts')
<script>
    // Refresh the queue periodically, but never while a kitchen action is being submitted.
    (function () {
        var submitting = false;
        document.querySelectorAll('form[data-kitchen-action]').forEach(function (form) {
            form.addEventListener('submit', function () {
                submitting = true;
                form.querySelectorAll('button').forEach(function (button) { button.disabled = true; });
            });
        });
        setInterval(function () {
            if (!submitting && document.visibilityState === 'visible') {
                window.location.reload();
            }
        }, 30000);
    })();
</script>
@endpush
