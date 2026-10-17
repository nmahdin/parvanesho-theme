<?php
/**
 * Magazine sidebar wrapper.
 *
 * Used by child themes and page builders that call get_sidebar(). The theme's
 * own templates render the sidebar through md_magazine_sidebar() so they can
 * also show curated fallback widgets when the area is empty.
 *
 * @package Morvarid_Daron
 */

if (!is_active_sidebar('md-magazine-sidebar')) {
    return;
}
?>
<aside id="secondary" class="md-magazine-sidebar" aria-label="سایدبار مجله">
    <?php dynamic_sidebar('md-magazine-sidebar'); ?>
</aside>
