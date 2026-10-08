jQuery(document).ready(function($) {
    const container = $('.bftl-tap-list');
    let autoRefresh = null;
    let badgeTimeouts = {};

    // Same card structure the server renders (see render_tap_list()), so a tap
    // that gets a beer after page load looks identical to one rendered with it.
    function buildTapBody() {
        const label = key => $('<div>').text(container.data('label-' + key) || '').html();
        return `
            <div class="tap-body">
                <div class="tap-item--inner beer-name" data-label="${label('name')}"></div>
                <div class="tap-item--inner is-beer-style" data-label="${label('style')}"><div class="beer-style"></div></div>
                <div class="tap-meta">
                    <div class="tap-item--inner brewer-name" data-label="${label('brewer')}"></div>
                    <div class="tap-item--inner brewer-location" data-label="${label('location')}"></div>
                </div>
                <div class="tap-stats">
                    <div class="tap-item--inner ibu"><i class="icon icon--hops"></i> <span class="stat-label">IBU</span> <span class="ibu--inner"></span></div>
                    <div class="tap-item--inner abv"><i class="icon icon--flask"></i> <span class="stat-label">ABV</span> <span class="abv--inner"></span></div>
                </div>
            </div>`;
    }

    function refreshTapList() {
        fetch(BFTLFront.rest_url)
            .then(response => response.json())
            .then(data => {
                data.forEach(tap => {
                        const tapId = tap.tap_id;
                        const $tap = $(`#tap-${tapId}`);

                        if ($tap.length) {
                            if (tap.is_empty) {
                                // Clear all content except tap number
                                $tap.find('.tap-body, .beer-name, .beer-style, .brewer-name, .brewer-location, .ibu, .abv, .new-indicator').remove();
                                $tap.removeClass('new-beer');
                                $tap.find('.tap-number, .is-tap-number').attr('data-category', tap.zone_category || '');

                                // Add empty tap message if it doesn't exist
                                if (!$tap.find('.beer-details.empty-tap').length) {
                                    $tap.append('<div class="tap-item--inner beer-details empty-tap"><div class="empty-tap--inner">No beer assigned</div></div>');
                                }
                            } else {
                                // Remove empty tap message
                                $tap.find('.beer-details.empty-tap').remove();
                                
                                // Ensure beer details structure exists
                                if (!$tap.find('.beer-name').length) {
                                    $tap.append(buildTapBody());
                                }
                                
                                // Update beer name
                                const $beerName = $tap.find('.beer-name');
                                $beerName.text(tap.beer_name);

                                // Update beer details
                                $tap.find('.tap-number, .is-tap-number').attr('data-category', tap.zone_category || '');
                                $tap.find('.beer-style').attr('data-category', tap.beer_category || '').text(tap.beer_style);
                                $tap.find('.brewer-name').text(tap.brewer_name);
                                $tap.find('.brewer-location').text(tap.brewer_location);
                                $tap.find('.ibu--inner').text(' ' + tap.ibu);
                                $tap.find('.abv--inner').text(' ' + tap.abv + '%');

                                // Handle new beer indicator independently for each tap
                                if (tap.is_new) {
                                    // Only add new indicator if it doesn't exist
                                    if (!$tap.find('.new-indicator--name').length) {
                                        // Two copies, same as the server renders: beside the name
                                        // (wide screens) and in the IBU/ABV row (mobile).
                                        $beerName.append(' <div class="new-indicator new-indicator--name">NEW!</div>');
                                        if (!$tap.find('.new-indicator--stats').length) {
                                            $tap.find('.tap-stats').append('<div class="new-indicator new-indicator--stats">NEW!</div>');
                                        }
                                        $tap.addClass('new-beer');
                                        
                                        // Set timeout only if this is a new indicator
                                        if (badgeTimeouts[tapId]) {
                                            clearTimeout(badgeTimeouts[tapId]);
                                        }
                                        badgeTimeouts[tapId] = setTimeout(() => {
                                            $tap.removeClass('new-beer');
                                            $tap.find('.new-indicator').remove();
                                            delete badgeTimeouts[tapId]; // Clean up the timeout reference
                                        }, BFTLFront.new_duration * 1000);
                                    }
                                } else {
                                    // Only remove if it was previously marked as new
                                    if ($tap.hasClass('new-beer')) {
                                        $tap.removeClass('new-beer');
                                        $tap.find('.new-indicator').remove();
                                        if (badgeTimeouts[tapId]) {
                                            clearTimeout(badgeTimeouts[tapId]);
                                            delete badgeTimeouts[tapId]; // Clean up the timeout reference
                                        }
                                    }
                                }
                            }
                        } else {
                            console.warn(`Tap element #tap-${tapId} not found`);
                        }
                });
            })
            .catch(error => {
                console.error('Tap list refresh error:', error);
            });
    }

    // Initial setup
    if (container.length) {
        refreshTapList(); // Initial call
        autoRefresh = setInterval(refreshTapList, BFTLFront.refresh_interval * 1000);
    } else {
        console.warn('Tap list container not found');
    }

    // Cleanup on page navigation
    $(window).on('beforeunload', function() {
        if (autoRefresh) clearInterval(autoRefresh);
        // Clear all timeouts
        Object.values(badgeTimeouts).forEach(timeout => clearTimeout(timeout));
    });
});
