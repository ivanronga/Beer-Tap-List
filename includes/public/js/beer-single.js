jQuery(document).ready(function($) {
    function showFeedback(message, isError) {
        var $feedback = $('#bftl-beer-assign-feedback');
        $feedback.text(message).css('color', isError ? '#e05252' : '#7bdc7b');
        setTimeout(function() {
            $feedback.text('');
        }, 4000);
    }

    function assignTap(tapId, beerId) {
        return fetch(BeerSingle.assign_rest_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tap_id: tapId, beer_id: beerId })
        }).then(function(response) {
            if (!response.ok) {
                throw new Error('Request failed');
            }
            return response.json();
        });
    }

    $('#bftl-assign-tap-button').on('click', function() {
        var tapId = $('#bftl-assign-tap-select').val();
        assignTap(tapId, BeerSingle.beer_id).then(function() {
            showFeedback('Assigned to tap #' + tapId, false);
            setTimeout(function() { window.location.reload(); }, 800);
        }).catch(function() {
            showFeedback('Error assigning tap', true);
        });
    });

    $('.bftl-remove-tap').on('click', function() {
        var tapId = $(this).data('tap');
        assignTap(tapId, 0).then(function() {
            showFeedback('Removed from tap #' + tapId, false);
            setTimeout(function() { window.location.reload(); }, 800);
        }).catch(function() {
            showFeedback('Error removing from tap', true);
        });
    });
});
