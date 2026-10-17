<?php
/**
 * Editable Gutenberg learning-resource blocks.
 * They use the same block editor as all other resource content.
 */
if (!defined('ABSPATH')) exit;
function md_register_resource_blocks() {
    wp_register_script(
        'md-resource-blocks-editor',
        get_template_directory_uri() . '/assets/js/resource-blocks.js',
        array('wp-blocks','wp-element','wp-components','wp-block-editor'),
        MD_THEME_VERSION,
        true
    );
    register_block_type('morvarid/resource-tip', array(
        'api_version' => 2,
        'editor_script_handles' => array('md-resource-blocks-editor'),
        'attributes' => array(
            'title'=>array('type'=>'string','default'=>'نکته مهم'),
            'text'=>array('type'=>'string','default'=>''),
        ),
        'render_callback' => function($a) {
            $title = sanitize_text_field($a['title'] ?? 'نکته مهم');
            $text = sanitize_textarea_field($a['text'] ?? '');
            if (!$title && !$text) return '';
            return '<aside class="md-resource-tip"><span class="md-resource-tip-icon" aria-hidden="true">💡</span><div>'
                . ($title ? '<h3>'.esc_html($title).'</h3>' : '')
                . ($text ? wpautop(esc_html($text)) : '') . '</div></aside>';
        },
    ));
    register_block_type('morvarid/resource-steps', array(
        'api_version' => 2,
        'editor_script_handles' => array('md-resource-blocks-editor'),
        'attributes' => array(
            'title'=>array('type'=>'string','default'=>'مراحل تمرین'),
            'steps'=>array('type'=>'array','default'=>array('گام اول را بنویسید','گام دوم را انجام دهید')),
        ),
        'render_callback' => function($a) {
            $steps = isset($a['steps']) && is_array($a['steps']) ? $a['steps'] : array();
            $steps = array_values(array_filter(array_map('sanitize_text_field',$steps)));
            if (!$steps) return '';
            $html = '<section class="md-resource-steps"><h3>'.esc_html($a['title'] ?? 'مراحل تمرین').'</h3><ol>';
            foreach($steps as $step) $html .= '<li>'.esc_html($step).'</li>';
            return $html.'</ol></section>';
        },
    ));
}
add_action('init','md_register_resource_blocks',25);
add_filter('block_categories_all',function($categories,$context){
    $post_type = isset($context->post) ? $context->post->post_type : '';
    if ($post_type !== 'md_resource' && $post_type !== 'md_academy_item') return $categories;
    array_unshift($categories,array('slug'=>'morvarid-resource','title'=>'بلوک‌های منابع آموزشی مروارید','icon'=>'welcome-learn-more'));
    return $categories;
},10,2);
