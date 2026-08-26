jQuery(document).ready(function($) {
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

    // Custom dropdown (native <select> can't style individual options with
    // colored dots the way the design calls for).
    var dropdown = document.querySelector('[data-tap-dropdown]');
    if (dropdown) {
        var trigger = dropdown.querySelector('[data-dropdown-trigger]');
        var list = dropdown.querySelector('[data-dropdown-list]');
        var dot = dropdown.querySelector('[data-dropdown-dot]');
        var label = dropdown.querySelector('[data-dropdown-label]');
        var options = dropdown.querySelectorAll('.bftl-tap-dropdown-option');
        var publishBtn = document.querySelector('[data-tap-publish]');
        var cancelLink = document.querySelector('[data-tap-cancel]');
        var placeholderText = label.textContent;

        function openList() {
            list.hidden = false;
            dropdown.setAttribute('data-open', '');
            trigger.setAttribute('aria-expanded', 'true');
        }

        function closeList() {
            list.hidden = true;
            dropdown.removeAttribute('data-open');
            trigger.setAttribute('aria-expanded', 'false');
        }

        function selectOption(option) {
            var value = option.getAttribute('data-value');
            var category = option.getAttribute('data-category') || '';
            trigger.dataset.value = value;
            label.textContent = option.textContent.trim();
            dot.setAttribute('data-category', category);
            trigger.classList.add('has-value');
            options.forEach(function(o) { o.classList.remove('is-active'); });
            option.classList.add('is-active');
            publishBtn.disabled = false;
        }

        function resetSelection() {
            delete trigger.dataset.value;
            label.textContent = placeholderText;
            dot.removeAttribute('data-category');
            trigger.classList.remove('has-value');
            options.forEach(function(o) { o.classList.remove('is-active'); });
            publishBtn.disabled = true;
        }

        trigger.addEventListener('click', function() {
            if (list.hidden) {
                openList();
            } else {
                closeList();
            }
        });

        options.forEach(function(option) {
            option.addEventListener('click', function() {
                selectOption(option);
                closeList();
            });
        });

        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target)) {
                closeList();
            }
        });

        if (cancelLink) {
            cancelLink.addEventListener('click', function(e) {
                e.preventDefault();
                resetSelection();
                closeList();
            });
        }

        if (publishBtn) {
            publishBtn.addEventListener('click', function() {
                var tapId = trigger.dataset.value;
                if (!tapId || publishBtn.disabled) {
                    return;
                }
                publishBtn.disabled = true;
                assignTap(tapId, BeerSingle.beer_id).then(function() {
                    window.location.reload();
                }).catch(function() {
                    publishBtn.disabled = false;
                    window.alert('Greška prilikom objave piva na pipu. Pokušajte ponovno.');
                });
            });
        }
    }

    $('.bftl-tap-remove-btn').on('click', function() {
        var $btn = $(this);
        var tapId = $btn.data('tap');
        $btn.prop('disabled', true);
        assignTap(tapId, 0).then(function() {
            window.location.reload();
        }).catch(function() {
            $btn.prop('disabled', false);
            window.alert('Greška prilikom uklanjanja piva s pipe. Pokušajte ponovno.');
        });
    });
});
