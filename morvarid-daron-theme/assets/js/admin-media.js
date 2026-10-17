/**
 * Reusable media picker for the theme meta boxes.
 */
(function ($) {
    'use strict';

    $(document).on('click', '.md-media-select', function (event) {
        event.preventDefault();

        if (!window.wp || !wp.media) {
            return;
        }

        var $button = $(this);
        var target = $button.data('target');
        var type = $button.data('type') || '';
        if (!target) {
            return;
        }

        var frame = wp.media({
            title: 'انتخاب از کتابخانه رسانه',
            button: { text: 'استفاده از این فایل' },
            multiple: false,
            library: type ? { type: type } : {}
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#' + target).val(attachment.id);
            $('#' + target + '_name').text(attachment.filename || attachment.title || '');
        });

        frame.open();
    });

    $(document).on('click', '.md-media-remove', function (event) {
        event.preventDefault();
        var target = $(this).data('target');
        if (!target) {
            return;
        }
        $('#' + target).val('0');
        $('#' + target + '_name').text('');
    });
})(jQuery);
