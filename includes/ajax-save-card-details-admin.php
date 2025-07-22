<?php
// includes/ajax-save-card-details-admin.php

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('wp_ajax_alba_save_card_details_admin', 'alba_save_card_details_admin');

function alba_save_card_details_admin() {
    // 1. Nonce validation (sanitize and verify)
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'alba_save_card_details_admin')) {
        wp_send_json_error(['message' => esc_html__('Invalid security token.', 'alba-board')]);
    }

    // 2. Permission check (edit_others_posts covers editing any card)
    if (!is_user_logged_in() || !current_user_can('edit_others_posts')) {
        wp_send_json_error(['message' => esc_html__('Permission denied.', 'alba-board')]);
    }

    // 3. Sanitize all input
    $card_id      = isset($_POST['card_id'])      ? absint($_POST['card_id']) : 0;
    $card_title   = isset($_POST['card_title'])   ? sanitize_text_field(wp_unslash($_POST['card_title'])) : '';
    $card_author  = isset($_POST['card_author'])  ? absint($_POST['card_author']) : 0;
    $card_content = isset($_POST['card_content']) ? wp_kses_post(wp_unslash($_POST['card_content'])) : '';

    // 4. Validate card
    $card = get_post($card_id);
    if (!$card || $card->post_type !== 'alba_card') {
        wp_send_json_error(['message' => esc_html__('Invalid card.', 'alba-board')]);
    }

    // 5. Update card post
    $update_result = wp_update_post([
        'ID'           => $card_id,
        'post_title'   => $card_title,
        'post_author'  => $card_author,
        'post_content' => $card_content,
    ], true);

    if (is_wp_error($update_result)) {
        wp_send_json_error(['message' => esc_html__('Failed to update card.', 'alba-board')]);
    }

    // 6. Save new comment in alba_comments (custom meta)
    $raw_comment = '';
    if (array_key_exists('new_comment', $_POST)) {
        $raw_comment = sanitize_textarea_field(wp_unslash($_POST['new_comment']));
    }
    if ($raw_comment !== '') {
        $user = wp_get_current_user();
        $author = $user->display_name ?: $user->user_login;
        $date = current_time('Y-m-d H:i');
        // Load previous comments
        $comments = get_post_meta($card_id, 'alba_comments', true);
        if (!is_array($comments)) {
            $comments = @unserialize($comments);
            if (!is_array($comments)) $comments = [];
        }
        $comments[] = [
            'author' => $author,
            'date'   => $date,
            'text'   => $raw_comment,
        ];
        update_post_meta($card_id, 'alba_comments', $comments);
    }

    // 7. Return updated modal HTML (refresh modal content)
    ob_start();
    alba_output_card_details_admin_modal($card_id, $card_author);
    $html = ob_get_clean();

    wp_send_json_success([
        'message' => esc_html__('Card saved.', 'alba-board'),
        'html'    => $html,
    ]);
}