<?php
// notifications.php

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Send email notification when a new card is created
function alba_board_notify_on_new_card($post_id, $post, $update) {
    if (get_post_type($post_id) !== 'alba_card' || $update) return;

    $options = get_option('alba_board_notifications');
    if (empty($options['notify_on_card'])) return;

    $author = get_userdata($post->post_author);
    if (!$author || empty($author->user_email) || !is_email($author->user_email)) return;

    $subject = sprintf(
        /* translators: %s: Card title */
        __('New card created: %s', 'alba-board'),
        $post->post_title
    );

    $message = sprintf(
        /* translators: 1: Author name, 2: Card title, 3: Card content, 4: Card link */
        __("Hello %s,\n\nA new card has been created in the Alba Board system.\n\nTitle: %s\nContent: %s\n\nYou can view it here: %s", 'alba-board'),
        $author->display_name,
        $post->post_title,
        wp_strip_all_tags($post->post_content),
        esc_url(get_permalink($post_id))
    );

    // Send notification
    wp_mail(sanitize_email($author->user_email), $subject, $message);
}
add_action('wp_insert_post', 'alba_board_notify_on_new_card', 10, 3);