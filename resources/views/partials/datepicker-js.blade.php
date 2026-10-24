<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script>
// Every <input type="date"> becomes a date picker that SHOWS dd/mm/yy
// but still SENDS yyyy-mm-dd to the server (so the database / validation do not change).
// Call window.initDatePickers(element) after adding new date inputs dynamically.
window.initDatePickers = function (root) {
    (root || document).querySelectorAll('input[type="date"]').forEach(function (el) {
        if (el._flatpickr) return;

        const readonly = el.hasAttribute('readonly');
        const required = el.required;
        if (!el.getAttribute('placeholder')) el.setAttribute('placeholder', 'dd/mm/yy');

        const fp = flatpickr(el, {
            dateFormat: 'Y-m-d',      // value that is submitted
            altInput: true,
            altFormat: 'd/m/y',       // value that the user sees  -> 05/11/26
            allowInput: true,         // typing 05/11/26 also works
            disableMobile: true,      // keep dd/mm/yy on phones too (no native picker)
            clickOpens: !readonly
        });

        if (readonly) fp.altInput.readOnly = true;
        if (required) { fp.altInput.required = true; el.required = false; }
    });
};

// helpers for scripts that change a date by code
window.setDateValue = function (el, value) {
    if (el._flatpickr) el._flatpickr.setDate(value, false); else el.value = value;
};
window.setDateEditable = function (el, editable) {
    if (el._flatpickr) { el._flatpickr.set('clickOpens', editable); el._flatpickr.altInput.readOnly = !editable; }
    else el.readOnly = !editable;
};

window.initDatePickers();
</script>
