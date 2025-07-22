<?php
// includes/ajax-move-card.php

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('wp_ajax_alba_move_card', 'alba_board_ajax_move_card');

function alba_board_ajax_move_card() {
    // 1. Nonce check (CSRF)
    if (
        empty($_POST['nonce']) ||
        ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'alba_move_card_nonce')
    ) {
        wp_send_json_error([ 'message' => esc_html__('Invalid nonce.', 'alba-board') ]);
    }

    // 2. Permission check
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_send_json_error([ 'message' => esc_html__('Permission denied.', 'alba-board') ]);
    }

    // 3. Sanitize input
    $card_id     = isset($_POST['card_id']) ? absint($_POST['card_id']) : 0;
    $new_list_id = isset($_POST['new_list_id']) ? absint($_POST['new_list_id']) : 0;

    // STRICT SANITIZATION FOR 'order'
    $order = [];
    $order_param = filter_input(INPUT_POST, 'order', FILTER_SANITIZE_NUMBER_INT, FILTER_REQUIRE_ARRAY);
    if (!is_array($order_param)) {
        $order_param = [];
    }
    foreach ($order_param as $k => $v) {
        $order[absint($k)] = absint($v);
    }

    if (!$card_id || !$new_list_id) {
        wp_send_json_error([ 'message' => esc_html__('Incomplete data.', 'alba-board') ]);
    }

    // 4. Update the parent list of the dragged card
    update_post_meta($card_id, 'alba_list_parent', $new_list_id);

    // 5. Update menu_order for all cards in the target list
    if (!empty($order)) {
        foreach ($order as $position => $cid) {
            if ($cid > 0) {
                wp_update_post([
                    'ID'         => $cid,
                    'menu_order' => $position,
                ]);
            }
        }
    }

    wp_send_json_success([ 'message' => esc_html__('Card moved successfully.', 'alba-board') ]);
}