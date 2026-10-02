jQuery(document).ready(function ($) {
    "use strict";

    if (typeof BFTLAds === 'undefined' || !BFTLAds.ads || !BFTLAds.ads.length) {
        return; // popups off or no enabled ads -- nothing to schedule
    }

    var ads = BFTLAds.ads; // enabled ads in list order: {id, image_url, weight}
    var intervalMs = BFTLAds.interval_seconds * 1000;
    var durationMs = BFTLAds.duration_seconds * 1000;
    var pendingTimeout = null;
    var visibleAd = null; // the ad currently on screen, if any

    var adsById = {};
    var totalWeight = 0;
    ads.forEach(function (ad) {
        adsById[ad.id] = ad;
        totalWeight += ad.weight;
    });

    // Smooth weighted round-robin: every pick adds each ad's weight to its
    // running counter, takes the highest counter (ties go to the earlier ad in
    // the list), then subtracts the total weight from the winner. Ads appear in
    // proportion to their weight and are spread evenly instead of clumping.
    var counters = {};
    function pickNext() {
        var best = null;
        ads.forEach(function (ad) {
            counters[ad.id] = (counters[ad.id] || 0) + ad.weight;
            if (best === null || counters[ad.id] > counters[best.id]) {
                best = ad;
            }
        });
        counters[best.id] -= totalWeight;
        return best;
    }

    // This site's theme reloads the whole page on a fixed timer (a
    // resilience measure for the unattended venue display), which would
    // otherwise reset any in-memory countdown before a realistic ad
    // interval ever finished. Persisting the next ad, its due time and the
    // rotation counters in localStorage lets the schedule survive any number
    // of page reloads. The fingerprint resets it when ads/weights/interval change.
    var STORAGE_KEY = 'bftl_ads_schedule';
    var fingerprint = BFTLAds.interval_seconds + '|' + ads.map(function (a) { return a.id + ':' + a.weight; }).join(',');

    function readState() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            var state = JSON.parse(raw);
            if (!state || state.fingerprint !== fingerprint || !adsById[state.nextId] || !state.counters) {
                return null;
            }
            return state;
        } catch (e) {
            return null;
        }
    }

    function writeState(nextId, dueAt) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                fingerprint: fingerprint,
                nextId: nextId,
                dueAt: dueAt,
                counters: counters
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

    function scheduleNext(ad, dueAt) {
        writeState(ad.id, dueAt);
        var delayMs = Math.max(0, dueAt - Date.now());
        pendingTimeout = setTimeout(function () { showAd(ad); }, delayMs);
    }

    function showAd(ad) {
        visibleAd = ad;
        $image.attr('src', ad.image_url);
        $popup.addClass('is-open');

        // Auto-closes after the global duration on desktop and mobile; the
        // close button (always visible while a popup is showing) lets someone
        // dismiss it early, overriding the remaining wait.
        pendingTimeout = setTimeout(hideAd, durationMs);
    }

    function hideAd() {
        $popup.removeClass('is-open');
        visibleAd = null;

        // Cooldown starts now; the next ad is the next in the weighted rotation.
        scheduleNext(pickNext(), Date.now() + intervalMs);
    }

    $close.on('click', function () {
        if (visibleAd === null) return;
        clearTimeout(pendingTimeout); // cancel the pending auto-close -- this click overrides it
        hideAd();
    });

    var initial = readState();
    if (initial) {
        counters = initial.counters;
        scheduleNext(adsById[initial.nextId], initial.dueAt);
    } else {
        scheduleNext(pickNext(), Date.now() + intervalMs);
    }

    $(window).on('beforeunload', function () {
        if (pendingTimeout) {
            clearTimeout(pendingTimeout);
        }
    });
});
