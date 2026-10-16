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

    // The board refreshes in place every few seconds. Rewriting a value that has
    // not changed still makes the browser re-layout and repaint, and replacing
    // the name's text node deleted the NEW! pill inside it (so it blinked back in
    // on every refresh). These helpers leave the page alone unless a value differs.
    function setText($els, value) {
        const text = String(value == null ? '' : value).trim();
        $els.each(function () {
            if (this.textContent.trim() !== text) {
                this.textContent = text;
            }
        });
    }

    function setAttr($els, name, value) {
        $els.each(function () {
            if (this.getAttribute(name) !== value) {
                this.setAttribute(name, value);
            }
        });
    }

    // Only the beer's own text node: the NEW! pill sits next to it in the same element.
    function setNameText($name, value) {
        const text = String(value == null ? '' : value).trim();
        const node = Array.prototype.find.call($name[0].childNodes, n => n.nodeType === 3);
        if (!node) {
            $name[0].insertBefore(document.createTextNode(text), $name[0].firstChild);
        } else if (node.nodeValue.trim() !== text) {
            node.nodeValue = text + ' ';
        }
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
                                setAttr($tap.find('.tap-number, .is-tap-number'), 'data-category', tap.zone_category || '');

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
                                setNameText($beerName, tap.beer_name);

                                // Update beer details
                                setAttr($tap.find('.tap-number, .is-tap-number'), 'data-category', tap.zone_category || '');
                                const $style = $tap.find('.beer-style');
                                setAttr($style, 'data-category', tap.beer_category || '');
                                setText($style, tap.beer_style);
                                setText($tap.find('.brewer-name'), tap.brewer_name);
                                setText($tap.find('.brewer-location'), tap.brewer_location);
                                setText($tap.find('.ibu--inner'), tap.ibu);
                                setText($tap.find('.abv--inner'), tap.abv + '%');

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

    // Safety net. The data stays live without reloading the page, so a reload is
    // only insurance against a page that has silently gone stale after hours of
    // running unattended. It waits for a quiet moment (no popup showing) and for
    // the server to answer first, so it never reloads into a browser error page.
    // In iframe mode the page that owns the board is the parent, so that is what
    // gets reloaded. Disabled with the bftl_safety_reload_minutes filter (0).
    let safetyTimer = null;

    function scheduleSafetyReload(delayMs) {
        safetyTimer = setTimeout(attemptSafetyReload, delayMs);
    }

    function attemptSafetyReload() {
        if (document.querySelector('.bftl-ad-popup.is-open')) {
            scheduleSafetyReload(60 * 1000);
            return;
        }

        const target = (window.parent && window.parent !== window) ? window.parent : window;
        let href;
        try {
            href = target.location.href;
        } catch (e) {
            return; // not reachable (cross-origin parent): leave the page alone
        }

        fetch(href, { method: 'HEAD', cache: 'no-store' })
            .then(response => {
                if (response.ok) {
                    target.location.reload();
                } else {
                    scheduleSafetyReload(2 * 60 * 1000);
                }
            })
            .catch(() => scheduleSafetyReload(2 * 60 * 1000));
    }

    const safetyMinutes = parseFloat(BFTLFront.safety_reload_minutes) || 0;
    if (container.length && safetyMinutes > 0) {
        scheduleSafetyReload(safetyMinutes * 60 * 1000);
    }

    // Cleanup on page navigation
    $(window).on('beforeunload', function() {
        if (autoRefresh) clearInterval(autoRefresh);
        if (safetyTimer) clearTimeout(safetyTimer);
        // Clear all timeouts
        Object.values(badgeTimeouts).forEach(timeout => clearTimeout(timeout));
    });
});
