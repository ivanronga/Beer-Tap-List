document.addEventListener('DOMContentLoaded', function() {
    var items = document.querySelectorAll('.bftl-qr-item');
    items.forEach(function(item) {
        var url = item.getAttribute('data-permalink');
        var target = item.querySelector('.bftl-qr-item-code');
        if (!url || !target || typeof qrcode === 'undefined') {
            return;
        }
        var qr = qrcode(0, 'M');
        qr.addData(url);
        qr.make();
        target.innerHTML = qr.createSvgTag();
    });
});
