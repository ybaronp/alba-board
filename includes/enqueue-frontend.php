<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Prevent direct access

// Enqueue frontend assets for Alba Board
function alba_board_enqueue_assets() {
    $plugin_url = plugin_dir_url(dirname(__FILE__));
    $plugin_version = '1.0.0'; // Version for cache busting

    // Sortable.js from local assets (with version)
    wp_enqueue_script(
        'sortablejs',
        $plugin_url . 'assets/js/Sortable.min.js',
        [],
        '1.15.0',
        true
    );

    // Alba Board frontend JS (depends on sortablejs)
    wp_enqueue_script(
        'alba-kanban',
        $plugin_url . 'assets/js/alba-board-frontend.js',
        ['sortablejs'],
        $plugin_version,
        true
    );

    // Alba Board frontend CSS
    wp_enqueue_style(
        'alba-board-style',
        $plugin_url . 'assets/css/alba-board-style.css',
        [],
        $plugin_version
    );

    // Pass AJAX URL, nonces, i18n to JS
    wp_localize_script('alba-kanban', 'albaBoard', [
        'ajaxurl'                 => admin_url('admin-ajax.php'),
        'nonce'                   => wp_create_nonce('alba_move_card_nonce'),
        'get_card_details_nonce'  => wp_create_nonce('alba_get_card_details'),
        'move_error'              => __('Could not move the card.', 'alba-board'),
    ]);
}
add_action('wp_enqueue_scripts', 'alba_board_enqueue_assets');