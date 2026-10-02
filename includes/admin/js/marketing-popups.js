document.addEventListener('DOMContentLoaded', function () {
    var selectButton = document.getElementById('bftl-popup-select-image');
    var imageIdInput = document.getElementById('bftl-popup-image-id');
    var preview = document.getElementById('bftl-popup-image-preview');
    if (!selectButton) return;

    var frame;
    selectButton.addEventListener('click', function (e) {
        e.preventDefault();
        if (frame) {
            frame.open();
            return;
        }
        frame = wp.media({
            title: 'Select Ad Image',
            button: { text: 'Use this image' },
            multiple: false,
            library: { type: 'image' }
        });
        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            imageIdInput.value = attachment.id;
            preview.src = (attachment.sizes && attachment.sizes.medium)
                ? attachment.sizes.medium.url
                : attachment.url;
            preview.style.display = 'block';
        });
        frame.open();
    });
});
