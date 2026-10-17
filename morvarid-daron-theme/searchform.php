<?php
/**
 * Search form — used on every screen that is not the magazine (the magazine
 * uses the compact md_pretty_search() variant through a get_search_form filter).
 *
 * @package Morvarid_Daron
 */

$md_search_id = wp_unique_id('md-search-field-');
?>
<form role="search" method="get" class="md-pretty-search md-searchform" action="<?php echo esc_url(home_url('/')); ?>">
    <label class="screen-reader-text" for="<?php echo esc_attr($md_search_id); ?>">جستجو در سایت</label>
    <span class="md-search-icon" aria-hidden="true">⌕</span>
    <input id="<?php echo esc_attr($md_search_id); ?>" type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="جستجو در سایت…" />
    <button class="md-search-button" type="submit">جستجو ←</button>
</form>
