<?php
// includes/ajax-card-details-admin.php

function alba_output_card_details_admin_modal($card_id, $force_author = null) {
    $card_id = intval($card_id);
    if (!$card_id || get_post_type($card_id) !== 'alba_card') {
        echo esc_html__('Invalid card.', 'alba-board');
        return;
    }

    $card = get_post($card_id);
    if (!$card) {
        echo esc_html__('Card not found.', 'alba-board');
        return;
    }

    // Use forced author if provided, else use the post author
    $author_id = $force_author !== null ? intval($force_author) : intval($card->post_author);

    $tags = get_the_terms($card_id, 'alba_tag');
    $custom_fields = get_post_meta($card_id);

    echo '<form id="alba-card-details-form" style="margin-bottom:0">';
    echo '<input type="hidden" name="card_id" value="' . esc_attr($card_id) . '">';

    // Title
    echo '<div style="margin-bottom: 12px;">';
    echo '<label for="alba-card-title"><strong>' . esc_html__('Title:', 'alba-board') . '</strong></label><br>';
    echo '<input type="text" name="title" id="alba-card-title" value="' . esc_attr($card->post_title) . '" style="width:90%">';
    echo '</div>';

    // Content
    echo '<div style="margin-bottom: 12px;">';
    echo '<label for="alba-card-content"><strong>' . esc_html__('Description:', 'alba-board') . '</strong></label><br>';
    echo '<textarea name="content" id="alba-card-content" rows="3" style="width:90%;">' . esc_textarea($card->post_content) . '</textarea>';
    echo '</div>';

    // Tags (display only)
    if (!empty($tags) && !is_wp_error($tags)) {
        echo '<div style="margin: 12px 0; display: flex; gap: 6px; flex-wrap: wrap;">';
        foreach ($tags as $tag) {
            $bg = get_term_meta($tag->term_id, 'alba_tag_bg_color', true) ?: '#eee';
            $text = get_term_meta($tag->term_id, 'alba_tag_text_color', true);
            $style = 'background:' . esc_attr($bg) . ';';
            if ($text) {
                $style .= 'color:' . esc_attr($text) . ';';
            }
            echo '<span style="' . $style . 'padding:2px 6px; border-radius:4px; font-size:0.75em;">' . esc_html($tag->name) . '</span>';
        }
        echo '</div>';
    }

    // Assignee (ALL USERS, Select2)
    echo '<div style="margin-bottom: 12px;">';
    echo '<label for="alba-card-assignee"><strong>' . esc_html__('Assignee:', 'alba-board') . '</strong></label><br>';
    $users = get_users();
    echo '<select name="assignee" id="alba-card-assignee" class="alba-select2" style="width:90%">';
    foreach ($users as $user_option) {
        $selected = ($user_option->ID === $author_id) ? 'selected="selected"' : '';
        echo '<option value="' . esc_attr($user_option->ID) . '" ' . $selected . '>' . esc_html($user_option->display_name) . ' (' . esc_html($user_option->user_email) . ')</option>';
    }
    echo '</select>';
    echo '</div>';

    // Custom fields (editable) except 'alba_comments'
    if (!empty($custom_fields)) {
        echo '<div style="margin-bottom: 12px;"><strong>' . esc_html__('Custom Fields:', 'alba-board') . '</strong>';
        foreach ($custom_fields as $key => $values) {
            // Hide 'alba_comments' from this list
            if (strpos($key, '_') === 0 || $key === 'alba_comments') continue;
            $v = $values[0];
            echo '<div style="margin-bottom:5px;">';
            echo '<label>' . esc_html($key) . ': ';
            echo '<input type="text" name="custom_fields[' . esc_attr($key) . ']" value="' . esc_attr($v) . '" style="width:60%">';
            echo '</label>';
            echo '</div>';
        }
        echo '</div>';
    }

    // Comments (graphical!)
    $comments = get_post_meta($card_id, 'alba_comments', true);
    if (!is_array($comments)) {
        $comments = @unserialize($comments);
        if (!is_array($comments)) $comments = [];
    }

    echo '<div style="margin-bottom:12px;"><strong>' . esc_html__('Comments:', 'alba-board') . '</strong><div id="alba-comments-list">';
    if (!empty($comments)) {
        foreach ($comments as $c) {
            echo '<div style="
                margin-bottom:8px; 
                padding:8px 10px; 
                border-radius:7px; 
                background:#f5f7fa; 
                border:1px solid #e0e0e0;">
                <strong>' . esc_html($c['author']) . '</strong>
                <span style="color:#888; font-size:11px; margin-left:6px;">' . esc_html($c['date']) . '</span><br>
                <span>' . esc_html($c['text']) . '</span>
            </div>';
        }
    } else {
        echo '<div style="color:#bbb;">' . esc_html__('No comments yet.', 'alba-board') . '</div>';
    }
    echo '</div></div>';

    // New comment box + feedback
    echo '<div style="margin-bottom:14px;">';
    echo '<textarea name="new_comment" id="alba-new-comment" style="width:96%;" rows="2" placeholder="' . esc_attr__('Add a comment...', 'alba-board') . '"></textarea>';
    echo '<div id="alba-comment-feedback-admin" style="margin-top:6px; font-weight:600; color:#17A900;"></div>';
    echo '</div>';

    echo '<button id="alba-card-save-btn" type="button" class="button button-primary" style="margin-top:10px;">' . esc_html__('Save changes', 'alba-board') . '</button>';
    echo '<span id="alba-card-save-message" style="margin-left:16px; color:green; font-weight:600; display:none;">' . esc_html__('Saved!', 'alba-board') . '</span>';
    echo '</form>';
}

// AJAX: show card details
add_action('wp_ajax_alba_get_card_details_admin', function() {
    $nonce = $_GET['nonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'alba_get_card_details_admin')) {
        echo esc_html__('Invalid nonce.', 'alba-board');
        wp_die();
    }
    $card_id = isset($_GET['card_id']) ? intval($_GET['card_id']) : 0;
    alba_output_card_details_admin_modal($card_id);
    wp_die();
});

// AJAX: save card details
add_action('wp_ajax_alba_save_card_details_admin', function() {
    $nonce = $_POST['nonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'alba_save_card_details_admin')) {
        wp_send_json_error(['message' => esc_html__('Invalid nonce.', 'alba-board')]);
    }

    $card_id = isset($_POST['card_id']) ? intval($_POST['card_id']) : 0;
    if (!$card_id || get_post_type($card_id) !== 'alba_card') {
        wp_send_json_error(['message' => esc_html__('Invalid card', 'alba-board')]);
    }

    $update_args = [
        'ID' => $card_id,
    ];
    $something_to_update = false;
    $force_author = null;

    // Save title
    if (isset($_POST['title'])) {
        $update_args['post_title'] = sanitize_text_field($_POST['title']);
        $something_to_update = true;
    }

    // Save content
    if (isset($_POST['content'])) {
        $update_args['post_content'] = sanitize_textarea_field($_POST['content']);
        $something_to_update = true;
    }

    // Save assignee (author)
    if (isset($_POST['assignee'])) {
        $assignee = intval($_POST['assignee']);
        if ($assignee > 0) {
            $update_args['post_author'] = $assignee;
            $something_to_update = true;
            $force_author = $assignee;
        }
    }

    // Only update post if something actually changed
    if ($something_to_update) {
        $result = wp_update_post($update_args, true);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => esc_html__('Failed to update card: ', 'alba-board') . $result->get_error_message()]);
        }
    }

    // Save custom fields, but skip alba_comments (it's managed below)
    if (!empty($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
        foreach ($_POST['custom_fields'] as $meta_key => $meta_value) {
            if ($meta_key === 'alba_comments') continue;
            update_post_meta($card_id, sanitize_text_field($meta_key), sanitize_text_field($meta_value));
        }
    }

    // Handle new comment
    $comment_added = false;
    if (isset($_POST['new_comment']) && strlen(trim($_POST['new_comment']))) {
        $comments = get_post_meta($card_id, 'alba_comments', true);
        if (!is_array($comments)) {
            $comments = @unserialize($comments);
            if (!is_array($comments)) $comments = [];
        }
        $user = wp_get_current_user();
        $comments[] = [
            'author' => $user->display_name ?: $user->user_login,
            'date'   => date_i18n('Y-m-d H:i'),
            'text'   => sanitize_text_field($_POST['new_comment']),
        ];
        update_post_meta($card_id, 'alba_comments', $comments);
        $comment_added = true;
    }

    wp_cache_delete($card_id, 'posts');

    ob_start();
    alba_output_card_details_admin_modal($card_id, $force_author);
    $html = ob_get_clean();

    // Show correct message!
    $msg = $comment_added
        ? __('Comment added!', 'alba-board')
        : __('Saved!', 'alba-board');

    wp_send_json_success(['html' => $html, 'message' => $msg]);
});