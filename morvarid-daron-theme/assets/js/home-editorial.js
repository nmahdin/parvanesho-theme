(function (wp) {
  'use strict';
  if (!wp || !wp.blocks || !wp.element || !wp.components || !wp.blockEditor) return;
  const e = wp.element.createElement;
  const B = wp.blocks;
  const C = wp.components;
  const useBlockProps = wp.blockEditor.useBlockProps;
  const InspectorControls = wp.blockEditor.InspectorControls;
  const rows = function (p) { return Array.isArray(p.attributes.items) ? p.attributes.items : []; };
  const field = (p, key, label, multi) => e(multi ? C.TextareaControl : C.TextControl, {
    key, label, rows: multi ? 4 : undefined,
    value: p.attributes[key] || '',
    onChange: value => p.setAttributes({ [key]: value })
  });
  const newItem = () => ({ icon: '✦', title: '', text: '' });

  function repeatFields(p) {
    const items = rows(p);
    const move = (from, delta) => {
      const to = from + delta;
      if (to < 0 || to >= items.length) return;
      const next = items.slice();
      [next[from], next[to]] = [next[to], next[from]];
      p.setAttributes({ items: next });
    };
    const change = (index, key, value) => p.setAttributes({
      items: items.map((item, i) => i === index ? { ...item, [key]: value } : item)
    });
    return e('div', { className: 'md-home-ref-editor-items' },
      ...items.map((item, i) => e('div', { key: i, className: 'md-home-ref-editor-item' },
        e('div', { className: 'md-home-ref-editor-item-actions' },
          e('strong', {}, 'مورد ' + (i + 1)),
          e(C.Button, { size: 'small', disabled: i === 0, onClick: () => move(i, -1) }, '↑'),
          e(C.Button, { size: 'small', disabled: i === items.length - 1, onClick: () => move(i, 1) }, '↓'),
          e(C.Button, { size: 'small', isDestructive: true,
            onClick: () => p.setAttributes({ items: items.filter((_, n) => n !== i) }) }, 'حذف')
        ),
        e(C.TextControl, { label: 'نماد کوتاه (اختیاری)', value: item.icon || '', onChange: v => change(i, 'icon', v) }),
        e(C.TextControl, { label: 'عنوان', value: item.title || '', onChange: v => change(i, 'title', v) }),
        e(C.TextareaControl, { label: 'توضیح', rows: 3, value: item.text || '', onChange: v => change(i, 'text', v) })
      )),
      e(C.Button, { variant: 'secondary', disabled: items.length >= 12,
        onClick: () => p.setAttributes({ items: [...items, newItem()] }) }, '+ افزودن مورد')
    );
  }

  function imageSelector(p) {
    const id = p.attributes.imageId || 0;
    return e('div', { className: 'md-home-ref-editor-image' },
      e('p', {}, id ? 'تصویر انتخاب‌شده: #' + id : 'بدون تصویر (چیدمان یک‌ستونه)'),
      e(C.Button, { variant: 'secondary', onClick: () => {
        if (!wp.media) return;
        const frame = wp.media({
          title: 'انتخاب تصویر داستان',
          button: { text: 'استفاده از این تصویر' },
          library: { type: 'image' }, multiple: false
        });
        frame.on('select', () => {
          const selected = frame.state().get('selection').first();
          if (selected) p.setAttributes({ imageId: selected.get('id') });
        });
        frame.open();
      } }, 'انتخاب از رسانه‌ها'),
      id ? e(C.Button, { isDestructive: true, onClick: () => p.setAttributes({ imageId: 0 }) }, 'حذف تصویر') : null
    );
  }

  const defs = [
    { slug: 'welcome', title: 'خوش‌آمد و نقل‌قول', icon: 'format-quote',
      fields: p => [field(p, 'eyebrow', 'برچسب'), field(p, 'title', 'عنوان'),
        field(p, 'text', 'متن خوش‌آمد', true), field(p, 'quote', 'متن نقل‌قول (اختیاری)', true)] },
    { slug: 'story', title: 'داستان و تصویر', icon: 'id-alt',
      fields: p => [field(p, 'eyebrow', 'برچسب'), field(p, 'title', 'عنوان'),
        field(p, 'text', 'متن داستان', true), imageSelector(p), field(p, 'caption', 'زیرنویس تصویر')] },
    { slug: 'principles', title: 'کارت‌های رویکرد', icon: 'grid-view',
      fields: p => [field(p, 'eyebrow', 'برچسب'), field(p, 'title', 'عنوان'),
        field(p, 'text', 'توضیح کوتاه', true), repeatFields(p)] },
    { slug: 'values', title: 'فهرست ارزش‌ها', icon: 'list-view',
      fields: p => [field(p, 'eyebrow', 'برچسب'), field(p, 'title', 'عنوان'),
        field(p, 'text', 'توضیح کوتاه', true), repeatFields(p)] },
  ];
  for (const d of defs) {
    // Both PHP and client registration reference the same schema. Do not
    // override the legacy home blocks (hero, features, grids, etc.).
    B.registerBlockType('morvarid/' + d.slug, {
      apiVersion: 2, title: d.title, category: 'morvarid-theme',
      description: 'بخش محتوایی قابل ویرایش با رنگ‌های فعلی سایت',
      icon: d.icon, supports: { html: false, anchor: true },
      attributes: {
        eyebrow: { type: 'string', default: '' },
        title: { type: 'string', default: '' },
        text: { type: 'string', default: '' },
        ...(d.slug === 'welcome' ? { quote: { type: 'string', default: '' } } : {}),
        ...(d.slug === 'story' ? {
          imageId: { type: 'number', default: 0 },
          caption: { type: 'string', default: '' },
        } : {}),
        ...(['principles', 'values'].includes(d.slug) ? { items: { type: 'array', default: [] } } : {}),
      },
      edit: p => e('div', useBlockProps({ className: 'md-home-ref-editor' }),
        e(InspectorControls, {}, e(C.PanelBody, { title: 'محتوای این بخش', initialOpen: true }, ...d.fields(p))),
        e('strong', {}, 'بلوک مروارید: ' + d.title),
        e('p', {}, 'برای افزودن، ویرایش، جابه‌جایی یا حذف موارد از تنظیمات کناری استفاده کنید.'),
        e('p', {}, 'نمایش واقعی بلوک در پیش‌نمایش صفحه و پس از انتشار دیده می‌شود.')
      ),
      save: () => null
    });
  }
})(window.wp);
