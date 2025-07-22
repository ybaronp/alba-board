<?php
// includes/notifications.php

if ( ! defined( 'ABSPATH' ) ) exit;

// Send email notification when a new card is created
function alba_board_notify_on_new_card($post_id, $post, $update) {
    // Only notify for alba_card post type and only when it's a new post (not update)
    if (get_post_type($post_id) !== 'alba_card' || $update) return;

    // Check if notification is enabled in plugin settings
    $options = get_option('alba_board_notifications');
    if (empty($options['notify_on_card'])) return;

    // Validate author user and email
    $author = get_userdata($post->post_author);
    if (!$author || empty($author->user_email) || !is_email($author->user_email)) return;

    // Prepare email subject and body
    /* translators: %s: The card title. */
    $subject = sprintf(
        __('New card created: %s', 'alba-board'),
        $post->post_title
    );

    /* translators: 1: User display name. 2: Card title. 3: Card content. 4: Card URL. */
    $message = sprintf(
        __("Hello %1\$s,\n\nA new card has been created in the Alba Board system.\n\nTitle: %2\$s\nContent: %3\$s\n\nYou can view it here: %4\$s", 'alba-board'),
        $author->display_name,
        $post->post_title,
        wp_strip_all_tags($post->post_content),
        get_permalink($post_id)
    );

    // Allow filtering of subject and message by add-ons or other plugins
    $subject = apply_filters('alba_board_notification_subject', $subject, $post_id, $post);
    $message = apply_filters('alba_board_notification_message', $message, $post_id, $post);

    // Send the email notification (sanitize email just in case)
    wp_mail(
        sanitize_email($author->user_email),
        wp_strip_all_tags($subject),
        $message
    );
}
add_action('wp_insert_post', 'alba_board_notify_on_new_card', 10, 3);