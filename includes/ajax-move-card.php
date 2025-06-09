<?php
// ajax-move-card.php

add_action('wp_ajax_alba_move_card', 'alba_board_ajax_move_card');

function alba_board_ajax_move_card() {
    // 1. Nonce check (CSRF)
    if (! check_ajax_referer('alba_move_card_nonce', 'nonce', false)) {
        wp_send_json_error([ 'message' => __('Invalid nonce.', 'alba-board') ]);
    }

    // 2. Permission check (important!)
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_send_json_error([ 'message' => __('Permission denied.', 'alba-board') ]);
    }

    // 3. Sanitize input
    $card_id     = intval($_POST['card_id'] ?? 0);
    $new_list_id = intval($_POST['new_list_id'] ?? 0);
    $order       = $_POST['order'] ?? []; // array of IDs in new order

    if (!$card_id || !$new_list_id) {
        wp_send_json_error([ 'message' => __('Incomplete data.', 'alba-board') ]);
    }

    // 4. Update the parent list of the dragged card
    update_post_meta($card_id, 'alba_list_parent', $new_list_id);

    // 5. Loop through the "order" array and update menu_order of each card
    if (is_array($order)) {
        foreach ($order as $position => $id) {
            $pos = intval($position);
            $cid = intval($id);
            if ($cid) {
                wp_update_post([
                    'ID'         => $cid,
                    'menu_order' => $pos,
                ]);
            }
        }
    }

    wp_send_json_success([ 'message' => __('Card moved successfully.', 'alba-board') ]);
}