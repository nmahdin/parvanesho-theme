/**
 * Morvarid Daron block library — academy, challenges and media.
 */
(function boot(wp) {
    'use strict';

    // Only the two APIs that are guaranteed to exist in every block editor are
    // required. Everything else is optional, so a renamed package can never
    // stop the blocks from being registered.
    if (!wp || !wp.blocks || !wp.element) {
        window.__mdBlocksTries = (window.__mdBlocksTries || 0) + 1;
        if (window.__mdBlocksTries < 60) {
            window.setTimeout(function () { boot(window.wp); }, 200);
        }
        return;
    }

    // Safety guard: the theme also prints this file inline as a fallback, so a
    // second execution must never register the same block twice.
    if (typeof wp.blocks.getBlockType === 'function' && wp.blocks.getBlockType('morvarid/course-grid')) {
        return;
    }

    var el = wp.element.createElement;
    var register = wp.blocks.registerBlockType;
    var BE = wp.blockEditor || wp.editor || {};
    var C = wp.components || {};
    function fallback(tag) {
        return function (props) {
            return el(tag || 'div', { className: 'md-missing-control' },
                props && props.label ? el('span', {}, props.label) : null,
                props && props.children ? props.children : null);
        };
    }
    function pick(name, tag) { return C[name] || fallback(tag); }
    var useBlockProps = BE.useBlockProps || function (props) { return props || {}; };
    var Inspector = BE.InspectorControls || fallback('div');
    var ServerSideRender = wp.serverSideRender;
    var PanelBody = pick('PanelBody', 'div');
    var TextControl = pick('TextControl', 'div');
    var TextareaControl = pick('TextareaControl', 'div');
    var SelectControl = pick('SelectControl', 'div');
    var RangeControl = pick('RangeControl', 'div');
    var ToggleControl = pick('ToggleControl', 'div');
    var Button = pick('Button', 'button');

    function field(props, key, label) {
        return el(TextControl, {
            label: label,
            value: props.attributes[key] || '',
            onChange: function (value) {
                var update = {};
                update[key] = value;
                props.setAttributes(update);
            }
        });
    }

    function numberField(props, key, label) {
        return el(TextControl, {
            label: label,
            type: 'number',
            value: String(props.attributes[key] || 0),
            onChange: function (value) {
                var update = {};
                update[key] = parseInt(value, 10) || 0;
                props.setAttributes(update);
            }
        });
    }

    function mediaField(props, key, label, kind) {
        return el('div', { className: 'md-block-media-field' },
            el(TextControl, {
                label: label,
                value: String(props.attributes[key] || 0),
                onChange: function (value) {
                    var update = {};
                    update[key] = parseInt(value, 10) || 0;
                    props.setAttributes(update);
                }
            }),
            el(Button, {
                variant: 'secondary',
                onClick: function () {
                    if (!wp.media) {
                        return;
                    }
                    var frame = wp.media({
                        title: 'انتخاب از کتابخانه رسانه',
                        button: { text: 'استفاده از این فایل' },
                        multiple: false,
                        library: kind ? { type: kind } : {}
                    });
                    frame.on('select', function () {
                        var file = frame.state().get('selection').first().toJSON();
                        var update = {};
                        update[key] = file.id;
                        props.setAttributes(update);
                    });
                    frame.open();
                }
            }, 'انتخاب از رسانه')
        );
    }

    function backgroundField(props) {
        return el(SelectControl, {
            label: 'پس‌زمینهٔ بخش',
            value: props.attributes.background || '',
            options: [
                { label: 'بدون پس‌زمینه', value: '' },
                { label: 'کرم', value: 'cream' },
                { label: 'هلویی روشن', value: 'warm' },
                { label: 'سبز روشن', value: 'mint' }
            ],
            onChange: function (value) { props.setAttributes({ background: value }); }
        });
    }

    function linkFields(props) {
        return [
            field(props, 'linkLabel', 'متن پیوند «همه…» (اختیاری)'),
            field(props, 'linkUrl', 'نشانی پیوند (خالی = آرشیو خودکار)'),
            backgroundField(props)
        ];
    }

    function updateFeature(props, index, key, value) {
        var items = (props.attributes.items || []).slice();
        var patch = {};
        patch[key] = value;
        items[index] = Object.assign({}, items[index], patch);
        props.setAttributes({ items: items });
    }

    function featuresEditor(props) {
        var items = props.attributes.items || [];
        var rows = items.map(function (item, index) {
            return el('div', { key: 'feature-' + index, className: 'md-feature-row' },
                el(TextControl, {
                    label: 'نماد کارت ' + (index + 1),
                    value: item.icon || '',
                    onChange: function (value) { updateFeature(props, index, 'icon', value); }
                }),
                el(TextControl, {
                    label: 'عنوان',
                    value: item.title || '',
                    onChange: function (value) { updateFeature(props, index, 'title', value); }
                }),
                el(TextControl, {
                    label: 'توضیح',
                    value: item.text || '',
                    onChange: function (value) { updateFeature(props, index, 'text', value); }
                }),
                el(TextControl, {
                    label: 'پیوند',
                    value: item.url || '',
                    onChange: function (value) { updateFeature(props, index, 'url', value); }
                }),
                el(Button, {
                    variant: 'link',
                    isDestructive: true,
                    onClick: function () {
                        props.setAttributes({ items: items.filter(function (row, i) { return i !== index; }) });
                    }
                }, 'حذف این کارت')
            );
        });

        rows.push(el(Button, {
            key: 'add-feature',
            variant: 'secondary',
            onClick: function () {
                props.setAttributes({ items: items.concat([{ icon: '✦', title: '', text: '', url: '' }]) });
            }
        }, '+ افزودن کارت'));

        return el('div', { className: 'md-features-editor' }, rows);
    }

    function preview(name, props, definition) {
        return el('div', useBlockProps({ className: 'md-block-editor-preview' }),
            el(Inspector, {},
                el(PanelBody, { title: 'تنظیمات بلوک', initialOpen: true },
                    definition.fields(props)
                )
            ),
            el('div', { className: 'md-block-label' }, '◆ بلوک‌های قالب مروارید'),
            el('div', { className: 'md-block-preview-inner' },
                wp.serverSideRender
                    ? el(wp.serverSideRender, { block: name, attributes: props.attributes })
                    : el('p', {}, 'پیش‌نمایش پس از ذخیره نمایش داده می‌شود.')
            )
        );
    }

    var requestFieldTypes = [
        { label: 'متن کوتاه', value: 'text' },
        { label: 'ایمیل', value: 'email' },
        { label: 'شماره تماس', value: 'tel' },
        { label: 'عدد', value: 'number' },
        { label: 'نشانی وب', value: 'url' },
        { label: 'تاریخ', value: 'date' },
        { label: 'انتخابی', value: 'select' },
        { label: 'متن بلند', value: 'textarea' }
    ];

    function updateRequestField(props, index, key, value) {
        var fields = (props.attributes.fields || []).slice();
        var patch = {};
        patch[key] = value;
        fields[index] = Object.assign({}, fields[index], patch);
        props.setAttributes({ fields: fields });
    }

    function addRequestField(props) {
        var fields = (props.attributes.fields || []).slice();
        fields.push({ label: 'فیلد تازه', type: 'text', required: false, placeholder: '', options: '' });
        props.setAttributes({ fields: fields });
    }

    function removeRequestField(props, index) {
        var fields = (props.attributes.fields || []).slice();
        fields.splice(index, 1);
        props.setAttributes({ fields: fields });
    }

    function requestFieldsEditor(props) {
        var fields = props.attributes.fields || [];
        var rows = fields.map(function (item, index) {
            var controls = [
                el(TextControl, {
                    key: 'label',
                    label: 'عنوان فیلد ' + (index + 1),
                    value: item.label || '',
                    onChange: function (value) { updateRequestField(props, index, 'label', value); }
                }),
                el(SelectControl, {
                    key: 'type',
                    label: 'نوع فیلد',
                    value: item.type || 'text',
                    options: requestFieldTypes,
                    onChange: function (value) { updateRequestField(props, index, 'type', value); }
                }),
                el(ToggleControl, {
                    key: 'required',
                    label: 'فیلد الزامی باشد',
                    checked: !!item.required,
                    onChange: function (value) { updateRequestField(props, index, 'required', value); }
                }),
                el(TextControl, {
                    key: 'placeholder',
                    label: 'متن راهنمای داخل فیلد',
                    value: item.placeholder || '',
                    onChange: function (value) { updateRequestField(props, index, 'placeholder', value); }
                })
            ];

            if ('select' === item.type) {
                controls.push(el(TextareaControl, {
                    key: 'options',
                    label: 'گزینه‌ها (هر خط یک مورد)',
                    value: item.options || '',
                    onChange: function (value) { updateRequestField(props, index, 'options', value); }
                }));
            }

            controls.push(el(Button, {
                key: 'remove',
                variant: 'link',
                isDestructive: true,
                onClick: function () { removeRequestField(props, index); }
            }, 'حذف این فیلد'));

            return el('div', { key: 'request-field-' + index, className: 'md-form-field-row' }, controls);
        });

        rows.push(el(Button, {
            key: 'add-request-field',
            variant: 'secondary',
            onClick: function () { addRequestField(props); }
        }, '+ افزودن فیلد'));

        return el('div', { className: 'md-form-fields-editor' },
            el('p', { className: 'components-base-control__help' }, 'فیلدهای فرم را همین‌جا مدیریت کنید؛ هر فیلد در سایت نمایش داده می‌شود.'),
            rows
        );
    }

    var attributeMap = {
        'course-grid': {
            title: { type: 'string', default: 'دوره‌های آکادمی' },
            subtitle: { type: 'string', default: '' },
            count: { type: 'number', default: 3 },
            columns: { type: 'number', default: 3 },
            category: { type: 'string', default: '' },
            level: { type: 'string', default: '' },
            linkLabel: { type: 'string', default: '' },
            linkUrl: { type: 'string', default: '' },
            background: { type: 'string', default: '' }
        },
        'challenge-grid': {
            title: { type: 'string', default: 'چالش‌های مروارید درون' },
            subtitle: { type: 'string', default: '' },
            count: { type: 'number', default: 3 },
            status: { type: 'string', default: 'all' },
            linkLabel: { type: 'string', default: '' },
            linkUrl: { type: 'string', default: '' },
            background: { type: 'string', default: '' }
        },
        'challenge-days': {
            title: { type: 'string', default: 'روزهای چالش' },
            subtitle: { type: 'string', default: '' },
            challengeId: { type: 'number', default: 0 },
            showSummary: { type: 'boolean', default: true }
        },
        'audio-player': {
            title: { type: 'string', default: '' },
            subtitle: { type: 'string', default: 'پادکست مروارید درون' },
            mediaId: { type: 'number', default: 0 },
            mediaUrl: { type: 'string', default: '' }
        },
        'video-player': {
            title: { type: 'string', default: '' },
            posterId: { type: 'number', default: 0 },
            mediaId: { type: 'number', default: 0 },
            mediaUrl: { type: 'string', default: '' }
        },
        'lesson-list': {
            title: { type: 'string', default: 'سرفصل‌های دوره' },
            courseId: { type: 'number', default: 0 }
        },
        'resource-grid': {
            title: { type: 'string', default: 'تمرین‌ها و منابع رایگان' },
            subtitle: { type: 'string', default: '' },
            count: { type: 'number', default: 3 },
            kind: { type: 'string', default: '' },
            linkLabel: { type: 'string', default: '' },
            linkUrl: { type: 'string', default: '' },
            background: { type: 'string', default: '' }
        },
        'features': {
            background: { type: 'string', default: '' },
            items: {
                type: 'array',
                default: [
                    { icon: '▤', title: 'مجله رشد', text: 'مقاله، داستان و راهکارهای کاربردی', url: '' },
                    { icon: '✦', title: 'آکادمی آموزشی', text: 'دوره و کارگاه برای رشد آگاهانه', url: '' }
                ]
            }
        },
        'media-grid': {
            title: { type: 'string', default: 'کتابخانه ویدیو و پادکست' },
            subtitle: { type: 'string', default: 'محتوای چندرسانه‌ای و جلسه‌های آموزشی' },
            count: { type: 'number', default: 3 },
            linkLabel: { type: 'string', default: 'همه پادکست‌ها' },
            linkUrl: { type: 'string', default: '' },
            background: { type: 'string', default: '' }
        },
        'newsletter': {
            eyebrow: { type: 'string', default: 'همراه مسیر رشد' },
            title: { type: 'string', default: 'عضو خانواده مروارید درون شوید' },
            text: { type: 'string', default: '' },
            buttonLabel: { type: 'string', default: 'عضویت در خبرنامه' },
            buttonUrl: { type: 'string', default: '' }
        },
        'request-form': {
            title: { type: 'string', default: 'درخواست جلسه' },
            intro: { type: 'string', default: '' },
            submitLabel: { type: 'string', default: 'ارسال درخواست' },
            successMessage: { type: 'string', default: '' },
            fields: {
                type: 'array',
                default: [
                    { label: 'نام و نام خانوادگی', type: 'text', required: true, placeholder: 'نام شما', options: '' },
                    { label: 'شماره تماس', type: 'tel', required: true, placeholder: '۰۹۱۲…', options: '' },
                    { label: 'ایمیل', type: 'email', required: false, placeholder: '', options: '' },
                    { label: 'خدمت مورد نظر', type: 'select', required: false, placeholder: '', options: 'مشاوره فردی\nمشاوره خانواده\nهمراهی نوجوان' },
                    { label: 'توضیح درخواست', type: 'textarea', required: true, placeholder: 'کمی از موقعیت خود و زمان مناسب بنویسید…', options: '' }
                ]
            }
        }
    };

    var definitions = [
        {
            slug: 'course-grid',
            title: 'شبکه دوره‌ها',
            icon: 'welcome-learn-more',
            description: 'نمایش دوره‌های آکادمی با فیلتر دسته و سطح.',
            fields: function (props) {
                return [
                    field(props, 'title', 'عنوان بخش'),
                    field(props, 'subtitle', 'توضیح کوتاه'),
                    el(RangeControl, {
                        label: 'تعداد دوره',
                        value: props.attributes.count,
                        min: 1,
                        max: 12,
                        onChange: function (value) { props.setAttributes({ count: value }); }
                    }),
                    el(RangeControl, {
                        label: 'تعداد ستون',
                        value: props.attributes.columns,
                        min: 1,
                        max: 4,
                        onChange: function (value) { props.setAttributes({ columns: value }); }
                    }),
                    field(props, 'category', 'نامک دسته آکادمی (اختیاری)'),
                    el(SelectControl, {
                        label: 'سطح',
                        value: props.attributes.level,
                        options: [
                            { label: 'همه سطوح', value: '' },
                            { label: 'مقدماتی', value: 'beginner' },
                            { label: 'متوسط', value: 'intermediate' },
                            { label: 'پیشرفته', value: 'advanced' }
                        ],
                        onChange: function (value) { props.setAttributes({ level: value }); }
                    })
                ].concat(linkFields(props));
            }
        },
        {
            slug: 'challenge-grid',
            title: 'شبکه چالش‌ها',
            icon: 'flag',
            description: 'کارت چالش‌های در حال برگزاری، آینده یا همه.',
            fields: function (props) {
                return [
                    field(props, 'title', 'عنوان بخش'),
                    field(props, 'subtitle', 'توضیح کوتاه'),
                    el(RangeControl, {
                        label: 'تعداد چالش',
                        value: props.attributes.count,
                        min: 1,
                        max: 12,
                        onChange: function (value) { props.setAttributes({ count: value }); }
                    }),
                    el(SelectControl, {
                        label: 'وضعیت',
                        value: props.attributes.status,
                        options: [
                            { label: 'همه چالش‌ها', value: 'all' },
                            { label: 'در حال برگزاری', value: 'active' },
                            { label: 'شروع‌نشده و در حال برگزاری', value: 'current' },
                            { label: 'پیش‌رو', value: 'upcoming' }
                        ],
                        onChange: function (value) { props.setAttributes({ status: value }); }
                    })
                ].concat(linkFields(props));
            }
        },
        {
            slug: 'challenge-days',
            title: 'روزهای چالش',
            icon: 'calendar-alt',
            description: 'فهرست روزهای یک چالش همراه با وضعیت قفل و تاریخ باز شدن.',
            fields: function (props) {
                return [
                    field(props, 'title', 'عنوان بخش'),
                    field(props, 'subtitle', 'توضیح کوتاه'),
                    numberField(props, 'challengeId', 'شناسه چالش (۰ = چالش جاری یا آخرین چالش)'),
                    el(ToggleControl, {
                        label: 'نمایش خلاصه روزهای باز',
                        checked: !!props.attributes.showSummary,
                        onChange: function (value) { props.setAttributes({ showSummary: value }); }
                    })
                ];
            }
        },
        {
            slug: 'audio-player',
            title: 'پخش‌کننده صوت',
            icon: 'format-audio',
            description: 'پخش‌کننده اختصاصی قالب برای فایل صوتی یا پادکست.',
            fields: function (props) {
                return [
                    field(props, 'title', 'عنوان صوت'),
                    field(props, 'subtitle', 'برچسب بالای عنوان'),
                    mediaField(props, 'mediaId', 'شناسه فایل صوتی', 'audio'),
                    field(props, 'mediaUrl', 'یا نشانی مستقیم فایل صوتی')
                ];
            }
        },
        {
            slug: 'video-player',
            title: 'پخش‌کننده ویدیو',
            icon: 'video-alt3',
            description: 'پخش‌کننده اختصاصی ویدیو؛ لینک آپارات و یوتیوب هم پذیرفته می‌شود.',
            fields: function (props) {
                return [
                    field(props, 'title', 'عنوان ویدیو'),
                    mediaField(props, 'mediaId', 'شناسه فایل ویدیو', 'video'),
                    mediaField(props, 'posterId', 'شناسه تصویر پوستر', 'image'),
                    field(props, 'mediaUrl', 'یا نشانی ویدیو (mp4 یا لینک آپارات/یوتیوب)')
                ];
            }
        },
        {
            slug: 'lesson-list',
            title: 'سرفصل‌های دوره',
            icon: 'list-view',
            description: 'فهرست جلسه‌های یک دوره با شماره و مدت.',
            fields: function (props) {
                return [
                    field(props, 'title', 'عنوان بخش'),
                    numberField(props, 'courseId', 'شناسه دوره (۰ = دوره جاری)')
                ];
            }
        },
        {
            slug: 'resource-grid',
            title: 'شبکه منابع و تمرین‌ها',
            icon: 'download',
            description: 'نمایش تمرین‌های رایگان با فیلتر نوع.',
            fields: function (props) {
                return [
                    field(props, 'title', 'عنوان بخش'),
                    field(props, 'subtitle', 'توضیح کوتاه'),
                    el(RangeControl, {
                        label: 'تعداد منبع',
                        value: props.attributes.count,
                        min: 1,
                        max: 12,
                        onChange: function (value) { props.setAttributes({ count: value }); }
                    }),
                    el(SelectControl, {
                        label: 'نوع',
                        value: props.attributes.kind,
                        options: [
                            { label: 'همه انواع', value: '' },
                            { label: 'تأمل و خودشناسی', value: 'reflection' },
                            { label: 'تمرین عملی', value: 'practice' },
                            { label: 'کاربرگ دانلودی', value: 'download' }
                        ],
                        onChange: function (value) { props.setAttributes({ kind: value }); }
                    })
                ].concat(linkFields(props));
            }
        },
        {
            slug: 'features',
            title: 'کارت‌های معرفی',
            icon: 'grid-view',
            description: 'ردیف کارت‌های معرفی (مجله، آکادمی و…) با نماد، عنوان، توضیح و پیوند.',
            fields: function (props) {
                return [
                    featuresEditor(props),
                    backgroundField(props)
                ];
            }
        },
        {
            slug: 'media-grid',
            title: 'کتابخانه ویدیو و پادکست',
            icon: 'format-video',
            description: 'آخرین جلسه‌ها و پادکست‌ها در یک شبکه.',
            fields: function (props) {
                return [
                    field(props, 'title', 'عنوان بخش'),
                    field(props, 'subtitle', 'توضیح کوتاه'),
                    el(RangeControl, {
                        label: 'تعداد آیتم',
                        value: props.attributes.count,
                        min: 1,
                        max: 12,
                        onChange: function (value) { props.setAttributes({ count: value }); }
                    })
                ].concat(linkFields(props));
            }
        },
        {
            slug: 'newsletter',
            title: 'دعوت به خبرنامه',
            icon: 'email',
            description: 'نوار عضویت در خبرنامه با متن و دکمهٔ دلخواه.',
            fields: function (props) {
                return [
                    field(props, 'eyebrow', 'برچسب بالای عنوان'),
                    field(props, 'title', 'عنوان'),
                    el(TextareaControl, {
                        label: 'توضیح',
                        value: props.attributes.text || '',
                        onChange: function (value) { props.setAttributes({ text: value }); }
                    }),
                    field(props, 'buttonLabel', 'متن دکمه'),
                    field(props, 'buttonUrl', 'نشانی دکمه')
                ];
            }
        },
        {
            slug: 'request-form',
            title: 'فرم ثبت درخواست',
            icon: 'feedback',
            description: 'فرم درخواست جلسه با فیلدهای کاملاً قابل ویرایش.',
            fields: function (props) {
                return [
                    field(props, 'title', 'عنوان فرم'),
                    el(TextareaControl, {
                        label: 'توضیح بالای فرم',
                        value: props.attributes.intro || '',
                        onChange: function (value) { props.setAttributes({ intro: value }); }
                    }),
                    field(props, 'submitLabel', 'متن دکمه ارسال'),
                    el(TextareaControl, {
                        label: 'پیام پس از ثبت موفق',
                        value: props.attributes.successMessage || '',
                        onChange: function (value) { props.setAttributes({ successMessage: value }); }
                    }),
                    requestFieldsEditor(props)
                ];
            }
        }
    ];

    definitions.forEach(function (definition) {
        var name = 'morvarid/' + definition.slug;
        register(name, {
            apiVersion: 2,
            title: definition.title,
            description: definition.description,
            icon: definition.icon,
            category: 'morvarid-theme',
            supports: { html: false, anchor: true, align: ['wide', 'full'] },
            attributes: attributeMap[definition.slug] || {},
            edit: function (props) {
                return preview(name, props, definition);
            },
            save: function () {
                return null;
            }
        });
    });
})(window.wp);
