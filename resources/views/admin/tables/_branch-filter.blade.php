@push('scripts')
<script>
    // A branch <select data-filters="targetId"> hides target options whose data-branch differs.
    (function () {
        document.querySelectorAll('select[data-filters]').forEach(function (branch) {
            var target = document.getElementById(branch.dataset.filters);
            if (!target) { return; }
            function sync() {
                Array.prototype.forEach.call(target.options, function (option) {
                    if (!option.dataset.branch) { return; }
                    option.hidden = option.dataset.branch !== branch.value;
                    if (option.hidden && option.selected) { target.value = ''; }
                });
            }
            branch.addEventListener('change', sync);
            sync();
        });
    })();
</script>
@endpush
