// File: includes/admin/js/tap-management.js
jQuery(document).ready(function($) {

    // Initialize Select2 on all beer dropdowns
    $('.bftl-select2').select2({
        width: 'resolve'
    });

    // Handle "Clear" tap button
    $('.bftl-clear-tap').on('click', function(e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        $row.find('select.bftl-select2').val('').trigger('change');
    });

    // Handle Save All Changes
    $('#bftl-tap-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var formData = $form.serializeArray();
        var tapBeer = {};

        // Gather tap assignments
        formData.forEach(function(item) {
            // tap_beer[1], tap_beer[2], ...
            var match = item.name.match(/^tap_beer\[(\d+)\]$/);
            if (match) {
                tapBeer[match[1]] = item.value;
            }
        });

        // Show loading indicator (optional)
        var $feedback = $('#bftl-admin-feedback');
        $feedback.html('<div class="notice notice-info"><p>Saving...</p></div>');

        wp.apiFetch({
            url: BFTL.taps_rest_url,
            method: 'POST',
            data: { tap_beer: tapBeer }
        }).then(function(response) {
            $feedback.html('<div class="notice notice-success"><p>' + response.message + '</p></div>');
        }).catch(function(error) {
            $feedback.html('<div class="notice notice-error"><p>' + (error && error.message ? error.message : 'An error occurred.') + '</p></div>');
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
