<?php
// includes/ajax-move-card.php

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('wp_ajax_alba_move_card', 'alba_board_ajax_move_card');

function alba_board_ajax_move_card() {
    if (
        empty($_POST['nonce']) ||
        ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'alba_move_card_nonce')
    ) {
        wp_send_json_error([ 'message' => esc_html__('Invalid nonce.', 'alba-board') ], 403);
    }

    $card_id     = isset($_POST['card_id']) ? absint($_POST['card_id']) : 0;
    $new_list_id = isset($_POST['new_list_id']) ? absint($_POST['new_list_id']) : 0;
    $card        = $card_id ? get_post($card_id) : false;
    $new_list    = $new_list_id ? get_post($new_list_id) : false;

    if (!$card || 'alba_card' !== $card->post_type || 'publish' !== $card->post_status) {
        wp_send_json_error([ 'message' => esc_html__('Invalid card.', 'alba-board') ], 400);
    }
    if (!$new_list || 'alba_list' !== $new_list->post_type || 'publish' !== $new_list->post_status) {
        wp_send_json_error([ 'message' => esc_html__('Invalid destination list.', 'alba-board') ], 400);
    }
    if (!current_user_can('edit_card', $card_id) || !current_user_can('edit_list', $new_list_id)) {
        wp_send_json_error([ 'message' => esc_html__('Permission denied.', 'alba-board') ], 403);
    }

    $order_param = isset($_POST['order']) && is_array($_POST['order']) ? wp_unslash($_POST['order']) : [];
    $order       = [];
    $seen_ids    = [];
    $ordered_cards = [];

    foreach ($order_param as $position => $raw_card_id) {
        if (!is_scalar($position) || !preg_match('/^\d+$/', (string) $position) || !is_scalar($raw_card_id)) {
            wp_send_json_error([ 'message' => esc_html__('Invalid card order.', 'alba-board') ], 400);
        }

        $position        = absint($position);
        $ordered_card_id = absint($raw_card_id);
        $ordered_card    = $ordered_card_id ? get_post($ordered_card_id) : false;

        if (
            !$ordered_card ||
            'alba_card' !== $ordered_card->post_type ||
            'publish' !== $ordered_card->post_status ||
            ($ordered_card_id !== $card_id && (int) get_post_meta($ordered_card_id, 'alba_list_parent', true) !== $new_list_id) ||
            !current_user_can('edit_card', $ordered_card_id) ||
            isset($seen_ids[$ordered_card_id]) ||
            isset($order[$position])
        ) {
            wp_send_json_error([ 'message' => esc_html__('Invalid card order.', 'alba-board') ], 400);
        }

        $seen_ids[$ordered_card_id] = true;
        $order[$position] = $ordered_card_id;
        $ordered_cards[$ordered_card_id] = $ordered_card;
    }

    if (!$order || !isset($seen_ids[$card_id])) {
        wp_send_json_error([ 'message' => esc_html__('Invalid card order.', 'alba-board') ], 400);
    }

    ksort($order, SORT_NUMERIC);
    if (array_keys($order) !== range(0, count($order) - 1)) {
        wp_send_json_error([ 'message' => esc_html__('Invalid card order.', 'alba-board') ], 400);
    }

    update_post_meta($card_id, 'alba_list_parent', $new_list_id);

    foreach ($order as $position => $ordered_card_id) {
        if ( $ordered_card_id !== $card_id && (int) $ordered_cards[$ordered_card_id]->menu_order === $position ) {
            continue;
        }

        $updated = wp_update_post([
            'ID'         => $ordered_card_id,
            'menu_order' => $position,
        ], true);

        if (is_wp_error($updated) || !$updated) {
            wp_send_json_error([ 'message' => esc_html__('Could not save the card order.', 'alba-board') ], 500);
        }
    }

    wp_send_json_success([ 'message' => esc_html__('Card moved successfully.', 'alba-board') ]);
}
