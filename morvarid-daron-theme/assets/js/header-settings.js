(function ($) {
  'use strict';
  $(function () {
    if (typeof wp === 'undefined' || !wp.media) return;

    function imagePicker(settings) {
      const $id = $(settings.id);
      const $preview = $(settings.preview);
      if (!$id.length) return;
      let picker;
      $(settings.choose).on('click', function (event) {
        event.preventDefault();
        if (!picker) {
          picker = wp.media({
            title: settings.title,
            button: { text: 'انتخاب این تصویر' },
            library: { type: 'image' },
            multiple: false
          });
          picker.on('select', function () {
            const image = picker.state().get('selection').first();
            if (!image) return;
            const attachment = image.toJSON();
            $id.val(attachment.id);
            const source = attachment.sizes && attachment.sizes.medium
              ? attachment.sizes.medium.url : attachment.url;
            $preview.attr('src', source).show();
          });
        }
        picker.open();
      });
      $(settings.clear).on('click', function (event) {
        event.preventDefault();
        $id.val('0');
        $preview.removeAttr('src').hide();
      });
    }

    imagePicker({
      id: '#md-header-logo-id',
      preview: '#md-header-logo-preview',
      choose: '#md-header-logo-choose',
      clear: '#md-header-logo-clear',
      title: 'انتخاب لوگوی هدر'
    });
    imagePicker({
      id: '#md-home-person-id',
      preview: '#md-home-person-preview',
      choose: '#md-home-person-choose',
      clear: '#md-home-person-clear',
      title: 'بارگذاری یا انتخاب عکس شخص برای صفحه اصلی'
    });
  });
})(jQuery);
