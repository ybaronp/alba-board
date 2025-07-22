<?php
// includes/ajax-create-card.php

if ( ! defined( 'ABSPATH' ) ) exit;

// Register AJAX for both logged-in and not logged-in (if you want to allow public creation, otherwise remove nopriv)
add_action('wp_ajax_alba_create_card', 'alba_core_ajax_create_card');
// Remove the next line if only admins should use it
add_action('wp_ajax_nopriv_alba_create_card', 'alba_core_ajax_create_card');

function alba_core_ajax_create_card() {
    // 1. Nonce validation with wp_unslash and sanitize
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'alba_create_card_nonce')) {
        wp_send_json_error(['message' => esc_html__('Invalid security token.', 'alba-board')]);
    }

    // 2. Permission check (you may want to restrict public access!)
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_send_json_error(['message' => esc_html__('Permission denied.', 'alba-board')]);
    }

    // 3. Sanitize input
    $title   = isset($_POST['title'])   ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $list_id = isset($_POST['list_id']) ? absint($_POST['list_id']) : 0;

    if (empty($title) || !$list_id) {
        wp_send_json_error(['message' => esc_html__('Required data missing.', 'alba-board')]);
    }

    // 4. Optional: Check card limit per list (if limit set in options)
    $limit_config = get_option('alba_board_limits');
    $max_cards = isset($limit_config['limit_cards']) ? absint($limit_config['limit_cards']) : 0;
    if ($max_cards > 0) {
        $existing_cards = get_posts([
            'post_type'   => 'alba_card',
            'meta_key'    => 'alba_list_parent',
            'meta_value'  => $list_id,
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields'      => 'ids'
        ]);
        if (count($existing_cards) >= $max_cards) {
            wp_send_json_error(['message' => esc_html__('The card limit for this list has been reached.', 'alba-board')]);
        }
    }

    // 5. Insert new card
    $card_id = wp_insert_post([
        'post_type'    => 'alba_card',
        'post_title'   => $title,
        'post_status'  => 'publish',
        'post_author'  => get_current_user_id()
    ]);

    if (is_wp_error($card_id) || !$card_id) {
        wp_send_json_error(['message' => esc_html__('Error creating the card.', 'alba-board')]);
    }

    // 6. Assign card to list
    update_post_meta($card_id, 'alba_list_parent', $list_id);

    // 7. Return response
    wp_send_json_success([
        'message' => esc_html__('Card created successfully.', 'alba-board'),
        'card_id' => (int) $card_id
    ]);
}