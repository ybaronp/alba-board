<?php
// ajax-create-card.php

add_action('wp_ajax_alba_create_card', 'alba_core_ajax_create_card');
add_action('wp_ajax_nopriv_alba_create_card', 'alba_core_ajax_create_card');

function alba_core_ajax_create_card() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alba_create_card_nonce')) {
        wp_send_json_error(['message' => __('Invalid nonce.', 'alba-board')]);
    }

    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Permission denied.', 'alba-board')]);
    }

    $title = sanitize_text_field($_POST['title'] ?? '');
    $list_id = intval($_POST['list_id'] ?? 0);

    if (!$title || !$list_id) {
        wp_send_json_error(['message' => __('Required data missing.', 'alba-board')]);
    }

    // Check card limit per list
    $limit_config = get_option('alba_board_limits');
    $max_cards = isset($limit_config['limit_cards']) ? intval($limit_config['limit_cards']) : 0;
    if ($max_cards > 0) {
        $existing_cards = get_posts([
            'post_type' => 'alba_card',
            'meta_key' => 'alba_list_parent',
            'meta_value' => $list_id,
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids'
        ]);

        if (count($existing_cards) >= $max_cards) {
            wp_send_json_error(['message' => __('The card limit for this list has been reached.', 'alba-board')]);
        }
    }

    $card_id = wp_insert_post([
        'post_type'    => 'alba_card',
        'post_title'   => $title,
        'post_status'  => 'publish',
    ]);

    if (is_wp_error($card_id)) {
        wp_send_json_error(['message' => __('Error creating the card.', 'alba-board')]);
    }

    update_post_meta($card_id, 'alba_list_parent', $list_id);

    wp_send_json_success([
        'message' => __('Card created successfully.', 'alba-board'),
        'card_id' => (int) $card_id
    ]);
}