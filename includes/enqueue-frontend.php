<?php
// includes/enqueue-frontend.php
if ( ! defined( 'ABSPATH' ) ) exit; // Prevent direct access

// Enqueue frontend assets for Alba Board
function alba_board_enqueue_assets() {
    $plugin_url = plugin_dir_url(dirname(__FILE__));
    $plugin_version = '2.0.1'; // Cache busting version for production

    // Sortable.js from local assets (with version)
    wp_enqueue_script(
        'sortablejs',
        $plugin_url . 'assets/js/Sortable.min.js',
        [],
        '1.15.0',
        true
    );

    // Alba Board frontend JS (depends on sortablejs and jquery)
    wp_enqueue_script(
        'alba-kanban',
        $plugin_url . 'assets/js/alba-board-frontend.js',
        ['sortablejs', 'jquery'],
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

    // Pass AJAX URL, REST URL, nonces, and ALL i18n texts to JS safely
    wp_localize_script('alba-kanban', 'albaBoard', [
        'ajaxurl'                 => admin_url('admin-ajax.php'),
        'rest_url'                => esc_url_raw( rest_url() ), 
        'rest_nonce'              => wp_create_nonce( 'wp_rest' ), 
        'nonce'                   => wp_create_nonce('alba_move_card_nonce'),
        'get_card_details_nonce'  => wp_create_nonce('alba_get_card_details'),
        'move_error'              => __('Could not move the card.', 'alba-board'),
        'loading'                 => __('Loading...', 'alba-board'),
        'confirm_delete'          => __('Are you sure you want to delete this card?', 'alba-board'),
        'delete_error'            => __('Error deleting card', 'alba-board'),
    ]);
}
add_action('wp_enqueue_scripts', 'alba_board_enqueue_assets');