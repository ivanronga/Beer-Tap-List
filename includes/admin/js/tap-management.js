// File: includes/admin/js/tap-management.js
jQuery(document).ready(function($) {

    // Initialize Select2 on all beer dropdowns
    $('.bftl-select2').select2({
        width: 'resolve'
    });

    function showRowFeedback($row, message, isError) {
        var $feedback = $row.find('.bftl-row-feedback');
        $feedback
            .text(message)
            .removeClass('bftl-feedback-success bftl-feedback-error')
            .addClass(isError ? 'bftl-feedback-error' : 'bftl-feedback-success');
        setTimeout(function() {
            $feedback.text('');
        }, 4000);
    }

    function setRowDirty($row, isDirty) {
        var $saveButton = $row.find('.bftl-save-tap');
        $saveButton.prop('disabled', !isDirty);
        $saveButton.toggleClass('button-primary', isDirty);
    }

    function saveRow($row, beerId) {
        var tapId = $row.data('tap');

        wp.apiFetch({
            url: BFTL.assign_rest_url,
            method: 'POST',
            data: { tap_id: tapId, beer_id: beerId ? parseInt(beerId, 10) : 0 }
        }).then(function() {
            setRowDirty($row, false);
            showRowFeedback($row, 'Saved', false);
        }).catch(function(error) {
            showRowFeedback($row, (error && error.message) ? error.message : 'Error', true);
        });
    }

    // Mark the row as having unsaved changes once the beer selection changes
    $('.bftl-tap-beer').on('change', function() {
        var $row = $(this).closest('tr');
        setRowDirty($row, true);
    });

    // Handle "Save" button per row
    $('.bftl-save-tap').on('click', function(e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        var beerId = $row.find('.bftl-tap-beer').val();
        saveRow($row, beerId);
    });

    // Handle "Clear" tap button — immediately saves an empty assignment
    $('.bftl-clear-tap').on('click', function(e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        var tapId = $row.data('tap');

        wp.apiFetch({
            url: BFTL.assign_rest_url,
            method: 'POST',
            data: { tap_id: tapId, beer_id: 0 }
        }).then(function() {
            $row.find('select.bftl-select2').val('').trigger('change');
            setRowDirty($row, false);
            showRowFeedback($row, 'Cleared', false);
        }).catch(function(error) {
            showRowFeedback($row, (error && error.message) ? error.message : 'Error', true);
        });
    });

    $('input[name="beer_abv"]').on('input', function() {
        // Remove all except digits and dot
        let val = this.value.replace(/[^0-9.]/g, '');
        // Only allow one dot
        let parts = val.split('.');
        if (parts.length > 2) {
            val = parts[0] + '.' + parts.slice(1).join('');
        }
        this.value = val;
    });
});
