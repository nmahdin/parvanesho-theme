(function (wp) {
  'use strict';
  if (!wp || !wp.blocks || !wp.element || !wp.blockEditor || !wp.components) { return; }
  const el = wp.element.createElement;
  const useEffect = wp.element.useEffect;
  const registerBlockType = wp.blocks.registerBlockType;
  const useBlockProps = wp.blockEditor.useBlockProps;
  const InspectorControls = wp.blockEditor.InspectorControls;
  const { PanelBody, TextControl, TextareaControl, SelectControl, ToggleControl, Button, Notice } = wp.components;
  const HEX = /^[a-f0-9]{32}$/;

  function id32() {
    const bytes = new Uint8Array(16);
    if (typeof crypto !== 'undefined' && crypto.getRandomValues) {
      crypto.getRandomValues(bytes);
    } else {
      for (let i = 0; i < bytes.length; i++) { bytes[i] = Math.floor(Math.random() * 256); }
    }
    return Array.from(bytes).map(n => n.toString(16).padStart(2, '0')).join('');
  }
  function newQuestion(text) {
    return { id: id32(), text: text || '', hint: '', type: 'long', required: false };
  }

  registerBlockType('morvarid/learning-questions', {
    apiVersion: 2,
    title: 'پرسش‌های آموزشی مروارید',
    description: 'پرسش تأملی یا پرسش همراه با ذخیره پاسخ خصوصی یادگیرنده',
    category: 'widgets',
    icon: 'editor-help',
    keywords: ['پرسش', 'تمرین', 'خودشناسی', 'academy'],
    attributes: {
      blockId: { type: 'string', default: '' },
      title: { type: 'string', default: 'مکثی برای خودشناسی' },
      description: { type: 'string', default: '' },
      mode: { type: 'string', default: 'reflection' },
      appearance: { type: 'string', default: 'card' },
      allowEdit: { type: 'boolean', default: true },
      questions: { type: 'array', default: [] }
    },
    supports: { html: false, multiple: true },
    edit: function (props) {
      const a = props.attributes;
      const questions = Array.isArray(a.questions) ? a.questions : [];
      const interactive = a.mode === 'save';

      // IDs are content identities, not secrets. Persist once on insertion.
      // PHP save normalization also resolves IDs duplicated by block cloning.
      useEffect(function () {
        const needsId = !HEX.test(a.blockId || '');
        const needsQuestions = !questions.length;
        const needsItemIds = questions.some(q => !q || !HEX.test(q.id || ''));
        if (!needsId && !needsQuestions && !needsItemIds) { return; }
        const changes = {};
        if (needsId) { changes.blockId = id32(); }
        if (needsQuestions) {
          changes.questions = [newQuestion('چه چیزی در این آموزش برای شما مهم‌تر بود؟')];
        } else if (needsItemIds) {
          changes.questions = questions.map(q => Object.assign({}, q || {}, {
            id: HEX.test(q && q.id || '') ? q.id : id32()
          }));
        }
        props.setAttributes(changes);
      }, [a.blockId, JSON.stringify(questions)]);

      function updateQuestion(index, patch) {
        props.setAttributes({
          questions: questions.map((q, i) => i === index ? Object.assign({}, q, patch) : q)
        });
      }
      function move(index, delta) {
        const next = questions.slice();
        const other = index + delta;
        if (other < 0 || other >= next.length) { return; }
        [next[index], next[other]] = [next[other], next[index]];
        props.setAttributes({ questions: next });
      }
      function insertCopy(index) {
        const next = questions.slice();
        next.splice(index + 1, 0, Object.assign({}, questions[index], { id: id32() }));
        props.setAttributes({ questions: next });
      }
      const controls = el(InspectorControls, {},
        el(PanelBody, { title: 'تنظیمات بلوک پرسش', initialOpen: true },
          el(SelectControl, {
            label: 'حالت پاسخ‌دهی',
            value: a.mode || 'reflection',
            options: [
              { label: 'تأملی؛ بدون ثبت پاسخ', value: 'reflection' },
              { label: 'تعاملی؛ ذخیره پاسخ', value: 'save' }
            ],
            onChange: mode => props.setAttributes({ mode })
          }),
          el(SelectControl, {
            label: 'ظاهر نمایش',
            value: a.appearance || 'card',
            options: [
              { label: 'کارت برجسته', value: 'card' },
              { label: 'ساده و بدون قاب', value: 'minimal' }
            ],
            onChange: appearance => props.setAttributes({ appearance })
          }),
          interactive ? el(ToggleControl, {
            label: 'امکان ویرایش پاسخ پس از ثبت نهایی',
            checked: a.allowEdit !== false,
            onChange: allowEdit => props.setAttributes({ allowEdit })
          }) : null,
          el('p', {}, 'بلوک را مانند متن، صوت و ویدیو جابه‌جا کنید. برای تکثیر کل بلوک از ابزارهای ویرایشگر وردپرس استفاده کنید.')
        )
      );

      return el('div', Object.assign({}, useBlockProps({ className: 'md-learning-question-editor' }), { dir: 'rtl' }),
        controls,
        el('div', { className: 'md-learning-q-editor-header' },
          el('span', { className: 'dashicons dashicons-editor-help' }),
          el('strong', {}, 'بلوک پرسش آموزشی'),
          el('span', { className: 'md-learning-q-mode' }, interactive ? 'پاسخ قابل ذخیره' : 'تأملی')
        ),
        el(TextControl, {
          label: 'عنوان مجموعه پرسش‌ها',
          value: a.title || '',
          onChange: title => props.setAttributes({ title })
        }),
        el(TextareaControl, {
          label: 'توضیح کوتاه / راهنمای مجموعه (اختیاری)',
          rows: 2,
          value: a.description || '',
          onChange: description => props.setAttributes({ description })
        }),
        el('div', { className: 'md-learning-q-editor-list' },
          questions.map((q, i) => el('div', { className: 'md-learning-q-editor-item', key: q.id || i },
            el('div', { className: 'md-learning-q-editor-item-head' },
              el('strong', {}, 'پرسش ' + (i + 1)),
              el('div', { className: 'md-learning-q-editor-actions' },
                el(Button, { variant: 'tertiary', size: 'small', disabled: i === 0, onClick: () => move(i, -1) }, '↑'),
                el(Button, { variant: 'tertiary', size: 'small', disabled: i === questions.length - 1, onClick: () => move(i, 1) }, '↓'),
                el(Button, { variant: 'tertiary', size: 'small', onClick: () => insertCopy(i) }, 'تکثیر'),
                el(Button, { isDestructive: true, size: 'small', disabled: questions.length <= 1,
                  onClick: () => props.setAttributes({ questions: questions.filter((_, j) => j !== i) }) }, 'حذف')
              )
            ),
            el(TextareaControl, {
              label: 'متن پرسش',
              rows: 3,
              value: q.text || '',
              onChange: text => updateQuestion(i, { text })
            }),
            el(TextareaControl, {
              label: 'توضیح یا راهنمای پاسخ (اختیاری)',
              rows: 2,
              value: q.hint || '',
              onChange: hint => updateQuestion(i, { hint })
            }),
            interactive ? el('div', { className: 'md-learning-q-editor-options' },
              el(SelectControl, {
                label: 'نوع پاسخ',
                value: q.type || 'long',
                options: [
                  { label: 'متن بلند', value: 'long' },
                  { label: 'متن کوتاه', value: 'short' }
                ],
                onChange: type => updateQuestion(i, { type })
              }),
              el(ToggleControl, {
                label: 'الزامی برای ثبت نهایی',
                checked: !!q.required,
                onChange: required => updateQuestion(i, { required })
              })
            ) : null
          ))
        ),
        el(Button, {
          variant: 'secondary',
          className: 'md-learning-q-add',
          onClick: () => props.setAttributes({ questions: questions.concat([newQuestion('')]) })
        }, '+ افزودن پرسش'),
        el(Notice, { status: 'info', isDismissible: false },
          interactive
            ? 'پاسخ‌ها فقط برای یادگیرنده واردشده قابل مشاهده و در دیتابیس خصوصی ذخیره می‌شوند. پیش‌نویس و ثبت نهایی دو عمل جدا هستند.'
            : 'در حالت تأملی، پرسش‌ها فقط نمایش داده می‌شوند و هیچ پاسخی ذخیره نمی‌شود.'
        )
      );
    },
    save: function () { return null; }
  });
})(window.wp);
