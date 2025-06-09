<?php
// includes/ajax-card-details.php

add_action('wp_ajax_alba_get_card_details', 'alba_board_get_card_details_ajax');
add_action('wp_ajax_nopriv_alba_get_card_details', 'alba_board_get_card_details_ajax');

function alba_board_get_card_details_ajax() {
    $nonce = $_GET['nonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'alba_get_card_details')) {
        echo 'Invalid nonce';
        wp_die();
    }

    $card_id = isset($_GET['card_id']) ? intval($_GET['card_id']) : 0;
    if (!$card_id) {
        echo 'Invalid card id';
        wp_die();
    }

    $card = get_post($card_id);
    if (!$card || $card->post_type !== 'alba_card') {
        echo 'Card not found';
        wp_die();
    }

    // Title
    echo '<h2 style="margin-bottom:0.5em;">' . esc_html($card->post_title) . '</h2>';

    // Assignee (Author)
    $author_id = $card->post_author;
    $user = get_user_by('ID', $author_id);
    if ($user) {
        echo '<div style="margin-bottom:0.7em;font-size:0.98em;color:#666;">
                <strong>Assignee:</strong> ' . esc_html($user->display_name) .
                ' <span style="font-size:0.92em;color:#bbb;">(' . esc_html($user->user_email) . ')</span>
              </div>';
    }

    // Content
    echo '<p style="margin-top:0.2em;">' . esc_html($card->post_content) . '</p>';

    // Tags (labels)
    $tags = get_the_terms($card_id, 'alba_tag');
    if (!empty($tags) && !is_wp_error($tags)) {
        echo '<div style="margin-top: 10px; margin-bottom:10px;">';
        foreach ($tags as $tag) {
            $bg = get_term_meta($tag->term_id, 'alba_tag_bg_color', true) ?: '#eee';
            $text = get_term_meta($tag->term_id, 'alba_tag_text_color', true) ?: '#000';
            echo '<span style="background:' . esc_attr($bg) . '; color:' . esc_attr($text) . '; padding:2px 8px; border-radius:8px; font-size:0.85em; margin-right:6px;">' . esc_html($tag->name) . '</span>';
        }
        echo '</div>';
    }

    // --- COMMENTS SECTION ---
    $comments = get_post_meta($card_id, 'alba_comments', true);
    if (!is_array($comments)) {
        $comments = @unserialize($comments);
        if (!is_array($comments)) $comments = [];
    }
    // Block for comments with max-height and scroll (neumorphism light style)
    echo '<div style="margin-top:14px; margin-bottom:12px;"><strong>Comments:</strong>';
    echo '<div id="alba-comments-list" style="max-height:190px; overflow-y:auto; background:#f7f9fc; border-radius:14px; box-shadow: 0 2px 9px #e9ebf2, 0 -2px 4px #fff; padding:8px 2px 6px 2px;">';
    if (!empty($comments)) {
        foreach ($comments as $i => $c) {
            echo '<div class="alba-board-comment" data-comment-index="' . $i . '" style="
                margin-bottom:8px;
                padding:8px 10px;
                border-radius:7px;
                background:#f5f7fa;
                border:1px solid #e0e0e0;
                box-shadow: 0 1.5px 7px #ebedf7, 0 -1.5px 6px #fff;">';
            echo '<strong>' . esc_html($c['author']) . '</strong>';
            echo '<span style="color:#888; font-size:11px; margin-left:6px;">' . esc_html($c['date']) . '</span><br>';
            echo '<span class="alba-comment-text">' . esc_html($c['text']) . '</span>';
            echo '</div>';
        }
    } else {
        echo '<div style="color:#bbb;">No comments yet.</div>';
    }
    echo '</div>';

    // Add comment textarea and button (front end)
    echo '<div style="margin-top:8px;">';
    echo '<textarea id="alba-new-comment-text" data-card-id="' . esc_attr($card_id) . '" rows="2" style="width:98%;resize:vertical;" placeholder="Write a comment..."></textarea>';
    echo '<br><button id="alba-add-comment-btn" style="margin-top:4px;">Add comment</button>';
    echo '<div id="alba-comment-feedback" style="color:green;margin-top:2px;"></div>';
    echo '</div>';

    echo '</div>';

    // Custom fields (metadata)
    $custom_fields = get_post_meta($card_id);
    $excluded_keys = [
        '_edit_lock', '_edit_last', '_ai_suggestion', 'alba_list_parent', 'alba_comments'
    ];
    $output_any = false;
    foreach ($custom_fields as $key => $values) {
        if (strpos($key, '_') === 0 && !in_array($key, ['_ai_suggestion'])) continue;
        if (in_array($key, $excluded_keys)) continue;

        $output_any = true;
        $value = $values[0];
        echo '<div style="margin-bottom:4px;">
            <strong>' . esc_html($key) . ':</strong> ' . esc_html($value) . '
        </div>';
    }
    if (!$output_any) {
        echo '<div style="color:#bbb;font-size:0.95em;">No custom fields.</div>';
    }

    // Trash can always at the end, visible for every card (add id)
    echo '<div style="width:100%;display:flex;justify-content:flex-start;align-items:flex-end;margin-top:18px;min-height:40px;">';
    echo '<button id="alba-modal-delete" class="alba-modal-delete-btn" title="Delete" style="background:none;border:none;cursor:pointer;font-size:2em;color:#8f9aad;opacity:.85;transition:color .13s, opacity .13s;margin-left:6px;margin-bottom:0;padding:0;">&#128465;</button>';
    echo '</div>';

    wp_die();
}