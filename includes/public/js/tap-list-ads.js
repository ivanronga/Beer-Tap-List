jQuery(document).ready(function ($) {
    "use strict";

    if (typeof BFTLAds === 'undefined' || !BFTLAds.ads || !BFTLAds.ads.length) {
        return; // no enabled ads -- nothing to schedule
    }

    var ads = BFTLAds.ads; // already display_order, already enabled-only
    var pendingTimeout = null;
    var visibleIndex = null; // the ad currently on screen, if any

    // This site's theme reloads the whole page on a fixed timer (a
    // resilience measure for the unattended venue display), which would
    // otherwise reset any in-memory countdown before a realistic ad
    // interval (e.g. 15 minutes) ever finished. Persisting the next-due
    // absolute timestamp in localStorage lets the wait correctly span any
    // number of page reloads in between.
    var STORAGE_KEY = 'bftl_ads_schedule';
    var adsFingerprint = ads.map(function (a) { return a.id + ':' + a.interval_seconds; }).join(',');

    function readState() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            var state = JSON.parse(raw);
            if (!state || state.fingerprint !== adsFingerprint || state.index >= ads.length) {
                return null; // ad list changed since this was saved -- start fresh
            }
            return state;
        } catch (e) {
            return null;
        }
    }

    function writeState(index, dueAt) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                fingerprint: adsFingerprint,
                index: index,
                dueAt: dueAt
            }));
        } catch (e) {
            // Private browsing / storage disabled -- scheduling still works
            // for this page load, it just won't survive the next reload.
        }
    }

    // Build the popup container once and append it to whichever document
    // this script is running in (the normal page, or the iframe wrapper's
    // own document in "iframe" frontend-wrapper mode).
    var $popup = $(
        '<div class="bftl-ad-popup" id="bftl-ad-popup">' +
        '  <div class="bftl-ad-popup-image-wrap">' +
        '    <img class="bftl-ad-popup-image" id="bftl-ad-popup-image" alt="">' +
        '    <button type="button" class="bftl-ad-popup-close" id="bftl-ad-popup-close" aria-label="Close">&times;</button>' +
        '  </div>' +
        '</div>'
    );
    $('body').append($popup);

    var $image = $popup.find('#bftl-ad-popup-image');
    var $close = $popup.find('#bftl-ad-popup-close');

    function scheduleNext(index, dueAt) {
        writeState(index, dueAt);
        var delayMs = Math.max(0, dueAt - Date.now());
        pendingTimeout = setTimeout(function () { showAd(index); }, delayMs);
    }

    function showAd(index) {
        var ad = ads[index];
        visibleIndex = index;
        $image.attr('src', ad.image_url);
        $popup.addClass('is-open');

        // Auto-closes after the ad's duration on both desktop and mobile;
        // the close button (always visible while a popup is showing) lets
        // someone dismiss it early, overriding the remaining wait.
        pendingTimeout = setTimeout(function () { hideAd(index); }, ad.duration_seconds * 1000);
    }

    function hideAd(index) {
        $popup.removeClass('is-open');
        visibleIndex = null;

        // Cooldown before the next ad uses that ad's own interval_seconds.
        var nextIndex = (index + 1) % ads.length;
        var nextDueAt = Date.now() + ads[nextIndex].interval_seconds * 1000;
        scheduleNext(nextIndex, nextDueAt);
    }

    $close.on('click', function () {
        if (visibleIndex === null) return;
        var idx = visibleIndex;
        clearTimeout(pendingTimeout); // cancel the pending auto-close -- this click overrides it
        hideAd(idx);
    });

    var initial = readState();
    if (initial) {
        scheduleNext(initial.index, initial.dueAt);
    } else {
        scheduleNext(0, Date.now() + ads[0].interval_seconds * 1000);
    }

    $(window).on('beforeunload', function () {
        if (pendingTimeout) {
            clearTimeout(pendingTimeout);
        }
    });
});
