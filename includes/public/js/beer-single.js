jQuery(document).ready(function($) {
    "use strict";

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

    // ---------- Tap picker (only present when not yet published) ----------
    var select = document.getElementById('bftlTapSelect');
    if (select) {
        var selectValue = document.getElementById('bftlTapSelectValue');
        var sheet = document.getElementById('bftlTapSheet');
        var sheetClose = document.getElementById('bftlTapSheetClose');
        var backdrop = document.getElementById('bftlTapBackdrop');
        var list = document.getElementById('bftlTapList');
        var options = list.querySelectorAll('.bftl-tap-option');
        var publishBtn = document.getElementById('bftlPublishBtn');
        var cancelBtn = document.getElementById('bftlCancelBtn');
        var placeholderText = selectValue.textContent.trim();

        var WIDE = window.matchMedia('(min-width: 768px)');

        function isOpen() {
            return sheet.classList.contains('is-open');
        }

        // On wide screens the list hangs off the field like a real dropdown.
        // Coordinates are computed here because the field lives inside a
        // scrolling container that would otherwise clip a CSS-only panel.
        function anchorSheet() {
            if (!WIDE.matches) {
                sheet.classList.remove('is-anchored');
                sheet.style.cssText = '';
                return;
            }
            sheet.classList.add('is-anchored');

            var r = select.getBoundingClientRect();
            var shell = document.querySelector('.bftl-beer-single-app').getBoundingClientRect();
            var gap = 8;
            var pad = 12;
            var below = shell.bottom - r.bottom - gap - pad;
            var above = r.top - shell.top - gap - pad;
            var dropDown = below >= 220 || below >= above;

            sheet.style.left = r.left + 'px';
            sheet.style.width = r.width + 'px';

            if (dropDown) {
                sheet.style.top = (r.bottom + gap) + 'px';
                sheet.style.bottom = 'auto';
                sheet.style.maxHeight = Math.max(180, Math.min(420, below)) + 'px';
            } else {
                sheet.style.top = 'auto';
                sheet.style.bottom = (window.innerHeight - r.top + gap) + 'px';
                sheet.style.maxHeight = Math.max(180, Math.min(420, above)) + 'px';
            }
        }

        function openSheet() {
            sheet.classList.add('is-open');
            backdrop.classList.add('is-open');
            select.setAttribute('aria-expanded', 'true');
            anchorSheet();
            var active = list.querySelector('[aria-selected="true"]') || list.firstElementChild;
            if (active) active.focus();
        }

        function closeSheet(refocus) {
            sheet.classList.remove('is-open');
            backdrop.classList.remove('is-open');
            select.setAttribute('aria-expanded', 'false');
            if (refocus) select.focus();
        }

        window.addEventListener('resize', function() { if (isOpen()) anchorSheet(); });
        var screen = document.querySelector('.bftl-beer-single-screen');
        if (screen) {
            screen.addEventListener('scroll', function() { if (isOpen()) anchorSheet(); });
        }

        function selectOption(option) {
            var value = option.getAttribute('data-value');
            var category = option.getAttribute('data-category') || '';
            var text = option.textContent.trim();

            select.dataset.value = value;
            select.dataset.category = category;
            selectValue.innerHTML = '';
            var dot = document.createElement('span');
            dot.className = 'bftl-tap-dot';
            dot.setAttribute('data-category', category);
            var label = document.createElement('span');
            label.textContent = text;
            selectValue.appendChild(dot);
            selectValue.appendChild(label);
            selectValue.classList.add('has-value');

            options.forEach(function(o) { o.setAttribute('aria-selected', 'false'); });
            option.setAttribute('aria-selected', 'true');

            publishBtn.disabled = false;
        }

        function resetSelection() {
            delete select.dataset.value;
            delete select.dataset.category;
            selectValue.innerHTML = '<span>' + placeholderText + '</span>';
            selectValue.classList.remove('has-value');
            options.forEach(function(o) { o.setAttribute('aria-selected', 'false'); });
            publishBtn.disabled = true;
        }

        select.addEventListener('click', function() {
            isOpen() ? closeSheet(true) : openSheet();
        });
        sheetClose.addEventListener('click', function() { closeSheet(true); });
        backdrop.addEventListener('click', function() { closeSheet(true); });

        options.forEach(function(option, i) {
            option.dataset.index = String(i);
            option.addEventListener('click', function() {
                selectOption(option);
                closeSheet(true);
            });
        });

        if (cancelBtn) {
            cancelBtn.addEventListener('click', resetSelection);
        }

        document.addEventListener('keydown', function(e) {
            if (!isOpen()) return;
            if (e.key === 'Escape') { closeSheet(true); return; }
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                var items = Array.prototype.slice.call(options);
                var cur = items.indexOf(document.activeElement);
                var next = e.key === 'ArrowDown' ? cur + 1 : cur - 1;
                if (next < 0) next = items.length - 1;
                if (next >= items.length) next = 0;
                items[next].focus();
            }
        });

        if (publishBtn) {
            publishBtn.addEventListener('click', function() {
                if (!select.dataset.value || publishBtn.disabled) return;
                openConfirm('publish', publishBtn);
            });
        }
    }

    // ---------- Remove (only present when published) ----------
    var removeBtn = document.getElementById('bftlRemoveBtn');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            openConfirm('remove', removeBtn);
        });
    }

    // ---------- Confirmation dialog ----------
    var scrim = document.getElementById('bftlConfirmScrim');
    var dialog = document.getElementById('bftlConfirmDialog');
    var dialogTitle = document.getElementById('bftlConfirmTitle');
    var dialogBody = document.getElementById('bftlConfirmBody');
    var confirmNo = document.getElementById('bftlConfirmNo');
    var confirmYes = document.getElementById('bftlConfirmYes');

    var loadingOverlay = document.getElementById('bftlLoadingOverlay');
    var loadingText = document.getElementById('bftlLoadingText');

    function showLoading(text) {
        loadingText.textContent = text;
        loadingOverlay.classList.add('is-open');
    }
    function hideLoading() {
        loadingOverlay.classList.remove('is-open');
    }

    var CONFIRM = {
        publish: {
            title: 'Objava piva',
            body: 'Jesi li siguran da je pivo spremno i spojeno na odgovarajuću pipu',
            loadingText: 'Objavljujem pivo...',
            yes: function(trigger) {
                var tapId = select.dataset.value;
                trigger.disabled = true;
                showLoading(this.loadingText);
                assignTap(tapId, BeerSingle.beer_id).then(function() {
                    window.location.reload();
                }).catch(function() {
                    hideLoading();
                    trigger.disabled = false;
                    window.alert('Greška prilikom objave piva na pipu. Pokušajte ponovno.');
                });
            }
        },
        remove: {
            title: 'Uklanjanje piva',
            body: 'Jesi li siguran da želiš maknuti ovo pivo sa pipe?',
            loadingText: 'Uklanjam pivo...',
            // Clears every tap this beer is currently active on, not just the
            // one shown -- self-heals the rare legacy case where a beer was
            // left active on more than one tap before this page enforced a
            // single tap per beer.
            yes: function(trigger) {
                trigger.disabled = true;
                showLoading(this.loadingText);
                var tapIds = BeerSingle.current_tap_ids || [];
                var chain = Promise.resolve();
                tapIds.forEach(function(tapId) {
                    chain = chain.then(function() { return assignTap(tapId, 0); });
                });
                chain.then(function() {
                    window.location.reload();
                }).catch(function() {
                    hideLoading();
                    trigger.disabled = false;
                    window.alert('Greška prilikom uklanjanja piva s pipe. Pokušajte ponovno.');
                });
            }
        }
    };

    var pendingYes = null;
    var returnFocus = null;

    function openConfirm(kind, trigger) {
        var cfg = CONFIRM[kind];
        if (!cfg) return;

        dialogTitle.textContent = cfg.title;
        dialogBody.textContent = cfg.body;
        pendingYes = function() { cfg.yes(trigger); };
        returnFocus = trigger || null;

        scrim.classList.add('is-open');
        dialog.classList.add('is-open');
        confirmNo.focus();
    }

    function closeConfirm() {
        scrim.classList.remove('is-open');
        dialog.classList.remove('is-open');
        pendingYes = null;
        if (returnFocus && !returnFocus.hidden && !returnFocus.disabled) returnFocus.focus();
        returnFocus = null;
    }

    function confirmOpen() {
        return dialog.classList.contains('is-open');
    }

    confirmNo.addEventListener('click', closeConfirm);
    scrim.addEventListener('click', closeConfirm);
    confirmYes.addEventListener('click', function() {
        var run = pendingYes;
        closeConfirm();
        if (run) run();
    });

    document.addEventListener('keydown', function(e) {
        if (!confirmOpen()) return;
        if (e.key === 'Escape') { e.preventDefault(); closeConfirm(); return; }
        if (e.key === 'Tab') {
            e.preventDefault();
            (document.activeElement === confirmNo ? confirmYes : confirmNo).focus();
        }
    });
});
