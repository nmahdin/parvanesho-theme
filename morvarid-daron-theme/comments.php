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
    comment_form(
        array(
            'title_reply'          => 'دیدگاه خود را بنویسید',
            'title_reply_to'       => 'پاسخ به %s',
            'cancel_reply_link'    => 'لغو پاسخ',
            'label_submit'         => 'ارسال دیدگاه',
            'comment_notes_before' => '<p class="md-comment-notes">نشانی ایمیل شما منتشر نخواهد شد. بخش‌های موردنیاز علامت‌گذاری شده‌اند.</p>',
            'comment_field'        => '<p class="comment-form-comment"><label for="comment">دیدگاه شما</label><textarea id="comment" name="comment" cols="45" rows="6" required></textarea></p>',
            'fields'               => array(
                'author'  => '<p class="comment-form-author"><label for="author">نام <span class="required">*</span></label><input id="author" name="author" type="text" required autocomplete="name" /></p>',
                'email'   => '<p class="comment-form-email"><label for="email">ایمیل <span class="required">*</span></label><input id="email" name="email" type="email" required autocomplete="email" /></p>',
                'url'     => '<p class="comment-form-url"><label for="url">وب‌سایت</label><input id="url" name="url" type="url" autocomplete="url" /></p>',
                'cookies' => '<p class="comment-form-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes" /> <label for="wp-comment-cookies-consent">نام، ایمیل و وب‌سایت من را برای دیدگاه‌های بعدی ذخیره کن.</label></p>',
            ),
        )
    );
    ?>
</section>
