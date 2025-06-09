<?php
// includes/enqueue-frontend.php

if ( ! defined( 'ABSPATH' ) ) exit;

function alba_board_enqueue_assets() {
    $plugin_url = plugin_dir_url(dirname(__FILE__)); // desde /includes hacia raíz del plugin

    // Sortable.js from CDN
    wp_enqueue_script(
        'sortablejs',
        'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
        [],
        '1.15.0',
        true
    );

    // Alba Board frontend JS
    wp_enqueue_script(
        'alba-kanban',
        $plugin_url . 'assets/js/alba-board-kanban.js',
        ['sortablejs'],
        null,
        true
    );

    // Alba Board frontend CSS
    wp_enqueue_style(
        'alba-board-style',
        $plugin_url . 'assets/css/alba-board-style.css',
        [],
        '1.0.0'
    );

    // Data for AJAX frontend + i18n + nonces
    wp_localize_script('alba-kanban', 'albaBoard', [
        'ajaxurl'    => admin_url('admin-ajax.php'),
        'nonce'      => wp_create_nonce('alba_move_card_nonce'),
        'get_card_details_nonce' => wp_create_nonce('alba_get_card_details'),
        'move_error' => __('Could not move the card.', 'alba-board'),
    ]);
}
add_action('wp_enqueue_scripts', 'alba_board_enqueue_assets');