<?php
/**
 * Comments template — RTL, Persian, matching the magazine layout.
 *
 * @package Morvarid_Daron
 */

if (post_password_required()) {
    return;
}

$md_comment_count = (int) get_comments_number();
?>
<section id="comments" class="md-comments">
    <?php if (have_comments()) : ?>
        <h2 class="md-comments-title">
            <?php
            printf(
                /* translators: %s: number of comments, in Persian digits. */
                esc_html('دیدگاه‌ها (%s)'),
                esc_html(md_fa_num($md_comment_count))
            );
            ?>
        </h2>

        <ol class="md-comment-list">
            <?php
            wp_list_comments(
                array(
                    'style'       => 'ol',
                    'short_ping'  => true,
                    'avatar_size' => 48,
                )
            );
            ?>
        </ol>

        <?php the_comments_navigation(); ?>

        <?php if (!comments_open()) : ?>
            <p class="md-comments-closed">دیدگاه‌ها برای این نوشته بسته شده است.</p>
        <?php endif; ?>
    <?php endif; ?>

    <?php
    if (!function_exists('md_comment_authenticated') || md_comment_authenticated()) {
    comment_form(
        array(
            'title_reply'          => 'دیدگاه خود را بنویسید',
            'title_reply_to'       => 'پاسخ به %s',
            'cancel_reply_link'    => 'لغو پاسخ',
            'label_submit'         => 'ارسال دیدگاه',
            'comment_notes_before' => '<p class="md-comment-notes">دیدگاه‌ها پس از تأیید مدیر منتشر می‌شوند. نیازی به ارائه ایمیل نیست.</p>',
            'comment_field'        => '<p class="comment-form-comment"><label for="comment">دیدگاه شما</label><textarea id="comment" name="comment" cols="45" rows="6" required></textarea></p>',
            'fields'               => (function_exists('md_comment_learner') && md_comment_learner()) ? array() : array(
                'author'  => '<p class="comment-form-author"><label for="author">نام <span class="required">*</span></label><input id="author" name="author" type="text" required autocomplete="name" /></p>',
                'url'     => '<p class="comment-form-url"><label for="url">وب‌سایت</label><input id="url" name="url" type="url" autocomplete="url" /></p>',

            ),
        )
    );
    } else {
        echo '<div class="md-comment-login-message"><p>برای ثبت دیدگاه، وارد پروفایل یادگیری خود شوید یا حساب جدید بسازید. ایمیل لازم نیست.</p>';
        if (class_exists('MD_Learning_Schema')) {
            $go = get_permalink();
            echo '<a class="btn btn-primary" href="' . esc_url(add_query_arg('redirect_to', $go, MD_Learning_Schema::url('login'))) . '">ورود به حساب</a> ';
            echo '<a class="btn btn-light" href="' . esc_url(MD_Learning_Schema::url('register')) . '">ساخت حساب</a>';
        }
        echo '</div>';
    }
    ?>
</section>
