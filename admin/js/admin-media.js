jQuery(function ($) {
    // メディアライブラリ選択
    $('.kapm-media-select').on('click', function (e) {
        e.preventDefault();
        var button = $(this);
        var targetId = button.data('target');
        var input = $('#' + targetId);
        var preview = button.siblings('.kapm-image-preview');

        var frame = wp.media({
            title: 'メディアを選択',
            button: { text: '選択' },
            multiple: false
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            input.val(attachment.url);
            var img = document.createElement('img');
            img.src = attachment.url;
            img.style.cssText = 'max-width:150px;margin-top:8px;';
            preview.empty().append(img);
        });

        frame.open();
    });
});

// ツールチップ（jQuery不要、即時実行）
(function () {
    var popup = document.createElement('div');
    popup.id = 'kapm-tooltip-popup';
    document.body.appendChild(popup);

    document.addEventListener('mouseover', function (e) {
        var trigger = e.target.closest('.kapm-tooltip');
        if (!trigger) return;
        var content = trigger.querySelector('.kapm-tooltip-content');
        if (!content) return;
        var rect = trigger.getBoundingClientRect();
        popup.innerHTML = content.innerHTML;
        popup.style.top = (rect.bottom + 8) + 'px';
        popup.style.left = Math.min(rect.left, window.innerWidth - 400) + 'px';
        popup.style.display = 'block';
    });

    document.addEventListener('mouseout', function (e) {
        var trigger = e.target.closest('.kapm-tooltip');
        if (!trigger) return;
        if (!trigger.contains(e.relatedTarget)) {
            popup.style.display = 'none';
        }
    });
})();
