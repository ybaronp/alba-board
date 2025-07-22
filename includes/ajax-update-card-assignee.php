<?php
// includes/ajax-update-card-assignee.php

if ( ! defined( 'ABSPATH' ) ) exit;

// AJAX handler: Update card assignee (author)
add_action('wp_ajax_alba_update_card_assignee', 'alba_update_card_assignee');

function alba_update_card_assignee() {
    // Sanitize and check required parameters
    $card_id = isset($_POST['card_id']) ? absint($_POST['card_id']) : 0;
    $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
    $nonce   = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

    if (!$card_id || !$user_id || empty($nonce)) {
        wp_send_json_error(['message' => esc_html__('Missing parameters.', 'alba-board')]);
    }

    // Nonce check for security
    if (! wp_verify_nonce($nonce, 'alba_card_assignee_nonce')) {
        wp_send_json_error(['message' => esc_html__('Invalid nonce.', 'alba-board')]);
    }

    // Check capability for editing assignees
    if (!is_user_logged_in() || !current_user_can('edit_others_posts')) {
        wp_send_json_error(['message' => esc_html__('Permission denied.', 'alba-board')]);
    }

    // Validate card existence and post type
    $card = get_post($card_id);
    if (!$card || $card->post_type !== 'alba_card') {
        wp_send_json_error(['message' => esc_html__('Invalid card.', 'alba-board')]);
    }

    // Validate user existence
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        wp_send_json_error(['message' => esc_html__('Invalid user.', 'alba-board')]);
    }

    // Optionally: check if user can edit this card specifically
    // if (!current_user_can('edit_post', $card_id)) {
    //     wp_send_json_error(['message' => esc_html__('You cannot edit this card.', 'alba-board')]);
    // }

    // Update post author (assignee)
    $result = wp_update_post([
        'ID' => $card_id,
        'post_author' => $user_id
    ], true);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => esc_html__('Error updating assignee.', 'alba-board')]);
    }

    wp_send_json_success(['message' => esc_html__('Assignee updated.', 'alba-board')]);
}