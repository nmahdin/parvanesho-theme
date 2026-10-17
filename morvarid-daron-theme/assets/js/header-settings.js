(function ($) {
  'use strict';
  $(function () {
    const $id = $('#md-header-logo-id');
    const $preview = $('#md-header-logo-preview');
    if (!$id.length || typeof wp === 'undefined' || !wp.media) return;
    let picker;
    $('#md-header-logo-choose').on('click', function (event) {
      event.preventDefault();
      if (!picker) {
        picker = wp.media({
          title: 'انتخاب لوگوی هدر',
          button: { text: 'استفاده از این تصویر' },
          library: { type: 'image' },
          multiple: false
        });
        picker.on('select', function () {
          const selected = picker.state().get('selection').first();
          if (!selected) return;
          const attachment = selected.toJSON();
          $id.val(attachment.id);
          const src = (attachment.sizes && attachment.sizes.thumbnail)
            ? attachment.sizes.thumbnail.url : attachment.url;
          $preview.attr('src', src).show();
        });
      }
      picker.open();
    });
    $('#md-header-logo-clear').on('click', function (event) {
      event.preventDefault();
      $id.val('0');
      $preview.attr('src', '').hide();
    });
  });
})(jQuery);
