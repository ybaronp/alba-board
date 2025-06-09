<?php
// ajax-update-card-assignee.php

add_action('wp_ajax_alba_update_card_assignee', 'alba_update_card_assignee');

function alba_update_card_assignee() {
    // 1. Check required parameters
    if (!isset($_POST['card_id'], $_POST['user_id'], $_POST['nonce'])) {
        wp_send_json_error(['message' => __('Missing parameters.', 'alba-board')]);
    }

    // 2. Nonce check
    if (!wp_verify_nonce($_POST['nonce'], 'alba_card_assignee_nonce')) {
        wp_send_json_error(['message' => __('Invalid nonce.', 'alba-board')]);
    }

    // 3. Permission check
    if (!is_user_logged_in() || !current_user_can('edit_others_posts')) {
        wp_send_json_error(['message' => __('Permission denied.', 'alba-board')]);
    }

    // 4. Sanitize input
    $card_id = intval($_POST['card_id']);
    $user_id = intval($_POST['user_id']);

    // 5. Validate card
    $card = get_post($card_id);
    if (!$card || $card->post_type !== 'alba_card') {
        wp_send_json_error(['message' => __('Invalid card.', 'alba-board')]);
    }

    // 6. Validate user
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        wp_send_json_error(['message' => __('Invalid user.', 'alba-board')]);
    }

    // 7. (Optional) Check capability for this specific card
    // if (!current_user_can('edit_post', $card_id)) {
    //     wp_send_json_error(['message' => __('You cannot edit this card.', 'alba-board')]);
    // }

    // 8. Update card assignee (author)
    wp_update_post([
        'ID' => $card_id,
        'post_author' => $user_id
    ]);

    wp_send_json_success(['message' => __('Assignee updated.', 'alba-board')]);
}