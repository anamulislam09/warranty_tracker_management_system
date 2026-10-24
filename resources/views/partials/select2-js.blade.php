<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
// Turns EVERY <select> into a Select2 box. Add class "no-select2" to a select to skip it.
// Call window.initSelect2(element) after adding new selects dynamically.
window.initSelect2 = function (root) {
    jQuery(root || document).find('select').not('.no-select2').each(function () {
        const $el = jQuery(this);
        if ($el.hasClass('select2-hidden-accessible')) return;
        const small = $el.hasClass('form-select-sm');

        $el.select2({
            theme: 'bootstrap-5',
            width: '100%',
            minimumResultsForSearch: 6,            // search box appears when there are more than 6 options
            selectionCssClass: small ? 'select2--small' : '',
            dropdownCssClass: small ? 'select2--small' : ''
        });

        // Select2 only fires jQuery events; our page scripts listen to normal browser events
        $el.on('select2:select', function () {
            this.dispatchEvent(new Event('input', { bubbles: true }));
            this.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
};
window.initSelect2();
</script>
