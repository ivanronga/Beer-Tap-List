document.addEventListener('DOMContentLoaded', function() {
    if (typeof qrcode === 'undefined') {
        return;
    }

    function slugifyFilename(name) {
        return name
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'beer';
    }

    // Renders the small on-page QR preview for every card
    function renderCardCodes() {
        document.querySelectorAll('.bftl-qr-item').forEach(function(item) {
            var url = item.getAttribute('data-permalink');
            var target = item.querySelector('.bftl-qr-item-code');
            if (!url || !target) {
                return;
            }
            var qr = qrcode(0, 'M');
            qr.addData(url);
            qr.make();
            target.innerHTML = qr.createSvgTag();
        });
    }

    // Builds a single PNG — category above the QR code, beer name + brewer
    // below it — and hands it back via callback(dataUrl). Used by both
    // Download and Print so what you print matches what you'd download.
    function generateComposedPng(url, beerName, brewerName, categoryName, callback) {
        var qr = qrcode(0, 'M');
        qr.addData(url);
        qr.make();

        var img = new Image();
        img.onload = function() {
            var padding = 20;
            var categoryLineHeight = categoryName ? 24 : 0;
            var nameLineHeight = 26;
            var brewerLineHeight = brewerName ? 22 : 0;
            var bottomTextHeight = nameLineHeight + brewerLineHeight + 10;

            var measureCtx = document.createElement('canvas').getContext('2d');
            measureCtx.font = 'bold 20px sans-serif';
            var nameWidth = measureCtx.measureText(beerName).width;
            measureCtx.font = '15px sans-serif';
            var brewerWidth = brewerName ? measureCtx.measureText(brewerName).width : 0;
            measureCtx.font = 'bold 14px sans-serif';
            var categoryWidth = categoryName ? measureCtx.measureText(categoryName).width : 0;
            var textWidth = Math.max(nameWidth, brewerWidth, categoryWidth);

            var canvas = document.createElement('canvas');
            canvas.width = Math.max(img.width, textWidth + padding * 2);
            canvas.height = categoryLineHeight + img.height + bottomTextHeight + padding;

            var ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.textAlign = 'center';

            if (categoryName) {
                ctx.font = 'bold 14px sans-serif';
                ctx.fillStyle = '#555555';
                ctx.fillText(categoryName.toUpperCase(), canvas.width / 2, categoryLineHeight - 6);
            }

            ctx.drawImage(img, (canvas.width - img.width) / 2, categoryLineHeight);

            ctx.fillStyle = '#000000';
            ctx.font = 'bold 20px sans-serif';
            ctx.fillText(beerName, canvas.width / 2, categoryLineHeight + img.height + nameLineHeight);

            if (brewerName) {
                ctx.font = '15px sans-serif';
                ctx.fillStyle = '#444444';
                ctx.fillText(brewerName, canvas.width / 2, categoryLineHeight + img.height + nameLineHeight + brewerLineHeight);
            }

            callback(canvas.toDataURL('image/png'));
        };
        img.src = qr.createDataURL(8, 8);
    }

    function getItemData(button) {
        var item = button.closest('.bftl-qr-item');
        return {
            url: item.getAttribute('data-permalink'),
            name: item.querySelector('.bftl-qr-item-name').textContent,
            brewer: item.getAttribute('data-brewer') || '',
            category: item.getAttribute('data-category') || ''
        };
    }

    // Preview modal
    var modal = document.getElementById('bftl-qr-modal');
    var modalCode = modal.querySelector('.bftl-qr-modal-code');
    var modalName = modal.querySelector('.bftl-qr-modal-name');
    var modalBrewer = modal.querySelector('.bftl-qr-modal-brewer');
    var modalCategory = modal.querySelector('.bftl-qr-modal-category');

    function openPreview(url, name, brewer, category) {
        var qr = qrcode(0, 'M');
        qr.addData(url);
        qr.make();
        modalCategory.textContent = category;
        modalCode.innerHTML = qr.createSvgTag(6);
        modalName.textContent = name;
        modalBrewer.textContent = brewer;
        modal.classList.add('is-open');
    }

    function closePreview() {
        modal.classList.remove('is-open');
    }

    modal.querySelector('.bftl-qr-modal-close').addEventListener('click', closePreview);
    modal.querySelector('.bftl-qr-modal-backdrop').addEventListener('click', closePreview);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closePreview();
    });

    document.querySelectorAll('.bftl-qr-item-code-clickable').forEach(function(code) {
        code.addEventListener('click', function() {
            var data = getItemData(code);
            openPreview(data.url, data.name, data.brewer, data.category);
        });
        code.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                code.click();
            }
        });
    });

    document.querySelectorAll('.bftl-qr-download').forEach(function(button) {
        button.addEventListener('click', function() {
            var data = getItemData(button);
            generateComposedPng(data.url, data.name, data.brewer, data.category, function(dataUrl) {
                var link = document.createElement('a');
                link.href = dataUrl;
                link.download = slugifyFilename(data.name) + '-qr.png';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        });
    });

    // Prints via a hidden same-page iframe instead of window.open() — a
    // popup can be blocked even when triggered from a click handler, an
    // iframe never can be.
    function printQr(url, beerName, brewerName, categoryName) {
        generateComposedPng(url, beerName, brewerName, categoryName, function(dataUrl) {
            var iframe = document.createElement('iframe');
            iframe.style.position = 'fixed';
            iframe.style.right = '0';
            iframe.style.bottom = '0';
            iframe.style.width = '0';
            iframe.style.height = '0';
            iframe.style.border = '0';
            document.body.appendChild(iframe);

            var doc = iframe.contentWindow.document;
            doc.open();
            doc.write(
                '<html><head><title>' + beerName + '</title></head>' +
                '<body style="margin:0;display:flex;align-items:center;justify-content:center;height:100vh;">' +
                '<img src="' + dataUrl + '" style="max-width:100%;">' +
                '</body></html>'
            );
            doc.close();

            setTimeout(function() {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
                setTimeout(function() {
                    document.body.removeChild(iframe);
                }, 1000);
            }, 250);
        });
    }

    document.querySelectorAll('.bftl-qr-print').forEach(function(button) {
        button.addEventListener('click', function() {
            var data = getItemData(button);
            printQr(data.url, data.name, data.brewer, data.category);
        });
    });

    var searchInput = document.getElementById('bftl-qr-search');
    var categoryFilter = document.getElementById('bftl-qr-category-filter');

    function applyFilters() {
        var term = searchInput ? searchInput.value.trim().toLowerCase() : '';
        var category = categoryFilter ? categoryFilter.value : '';
        document.querySelectorAll('.bftl-qr-item').forEach(function(item) {
            var name = item.querySelector('.bftl-qr-item-name').textContent.toLowerCase();
            var matchesName = name.indexOf(term) !== -1;
            var matchesCategory = !category || item.getAttribute('data-category') === category;
            item.style.display = (matchesName && matchesCategory) ? '' : 'none';
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }
    if (categoryFilter) {
        categoryFilter.addEventListener('change', applyFilters);
    }

    renderCardCodes();
});
