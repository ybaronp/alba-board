<?php
// includes/ajax-get-admin-board-view.php

if ( ! defined( 'ABSPATH' ) ) exit;

// AJAX: Get Admin Board View (Kanban)
add_action('wp_ajax_alba_get_admin_board_view', 'alba_ajax_get_admin_board_view');

function alba_ajax_get_admin_board_view() {
    // Nonce security check
    if (
        ! isset($_POST['nonce']) ||
        ! wp_verify_nonce($_POST['nonce'], 'alba_get_admin_board_view')
    ) {
        wp_send_json_error(['message' => __('Invalid nonce.', 'alba-board')]);
    }

    // Permission check
    if ( ! current_user_can('edit_posts') ) {
        wp_send_json_error(['message' => __('Permission denied.', 'alba-board')]);
    }

    $board_id = isset($_POST['board_id']) ? absint($_POST['board_id']) : 0;

    ob_start();
    alba_render_admin_board_view_content($board_id);
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}