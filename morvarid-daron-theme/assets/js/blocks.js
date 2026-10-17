/**
 * Morvarid Daron block library — academy, challenges and media.
 */
(function (wp) {
    'use strict';

    var el = wp.element.createElement;
    var register = wp.blocks.registerBlockType;
    var Inspector = wp.blockEditor.InspectorControls;
    var ServerSideRender = wp.serverSideRender;
    var PanelBody = wp.components.PanelBody;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var SelectControl = wp.components.SelectControl;
    var RangeControl = wp.components.RangeControl;
    var ToggleControl = wp.components.ToggleControl;
    var Button = wp.components.Button;

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

    function preview(name, props, definition) {
        return el('div', wp.blockEditor.useBlockProps({ className: 'md-block-editor-preview' }),
            el(Inspector, {},
                el(PanelBody, { title: 'تنظیمات بلوک', initialOpen: true },
                    definition.fields(props)
                )
            ),
            el('div', { className: 'md-block-label' }, '◆ بلوک‌های قالب مروارید'),
            el('div', { className: 'md-block-preview-inner' },
                ServerSideRender
                    ? el(ServerSideRender, { block: name, attributes: props.attributes })
                    : el('p', {}, 'پیش‌نمایش پس از ذخیره نمایش داده می‌شود.')
            )
        );
    }

    var attributeMap = {
        'course-grid': {
            title: { type: 'string', default: 'دوره‌های آکادمی' },
            subtitle: { type: 'string', default: '' },
            count: { type: 'number', default: 3 },
            columns: { type: 'number', default: 3 },
            category: { type: 'string', default: '' },
            level: { type: 'string', default: '' }
        },
        'challenge-grid': {
            title: { type: 'string', default: 'چالش‌های مروارید درون' },
            subtitle: { type: 'string', default: '' },
            count: { type: 'number', default: 3 },
            status: { type: 'string', default: 'all' }
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
            kind: { type: 'string', default: '' }
        },
        'service-grid': {
            title: { type: 'string', default: 'خدمات کوچینگ' },
            subtitle: { type: 'string', default: '' },
            count: { type: 'number', default: 3 }
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
                ];
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
                            { label: 'پیش‌رو', value: 'upcoming' }
                        ],
                        onChange: function (value) { props.setAttributes({ status: value }); }
                    })
                ];
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
                ];
            }
        },
        {
            slug: 'service-grid',
            title: 'شبکه خدمات کوچینگ',
            icon: 'heart',
            description: 'نمایش خدمات کوچینگ به‌صورت کارت.',
            fields: function (props) {
                return [
                    field(props, 'title', 'عنوان بخش'),
                    field(props, 'subtitle', 'توضیح کوتاه'),
                    el(RangeControl, {
                        label: 'تعداد خدمات',
                        value: props.attributes.count,
                        min: 1,
                        max: 12,
                        onChange: function (value) { props.setAttributes({ count: value }); }
                    })
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
