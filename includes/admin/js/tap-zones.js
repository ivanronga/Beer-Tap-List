// File: includes/admin/js/tap-zones.js
jQuery(document).ready(function($) {

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

    $('.bftl-save-zone').on('click', function(e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        var tapId = $row.data('tap');
        var category = $row.find('.bftl-zone-select').val();

        wp.apiFetch({
            url: BFTL.category_rest_url,
            method: 'POST',
            data: { tap_id: tapId, category: category }
        }).then(function() {
            showRowFeedback($row, 'Saved', false);
        }).catch(function(error) {
            showRowFeedback($row, (error && error.message) ? error.message : 'Error', true);
        });
    });
});
