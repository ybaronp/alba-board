<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Prevent direct access

// Enqueue backend assets for Alba Board admin Kanban page
function alba_board_enqueue_admin_assets($hook) {
    // Only load on the Alba Board Kanban admin page
    if ($hook !== 'toplevel_page_alba-board-visual') {
        return;
    }

    $plugin_url = plugin_dir_url(dirname(__FILE__)) . 'assets/';
    $plugin_version = '1.1.0'; // Update as needed

    // Sortable.js (local)
    wp_enqueue_script(
        'sortablejs',
        $plugin_url . 'js/Sortable.min.js',
        [],
        '1.15.0',
        true
    );

    // Select2 (local)
    wp_enqueue_style(
        'select2',
        $plugin_url . 'css/select2.min.css',
        [],
        '4.1.0'
    );
    wp_enqueue_script(
        'select2',
        $plugin_url . 'js/select2.min.js',
        ['jquery'],
        '4.1.0',
        true
    );

    // Alba Board backend JS (depends on sortablejs and select2)
    wp_enqueue_script(
        'alba-backend-kanban',
        $plugin_url . 'js/alba-backend-kanban.js',
        ['sortablejs', 'jquery', 'select2'],
        $plugin_version,
        true
    );

    // Alba Board backend CSS (neumorphism style)
    wp_enqueue_style(
        'alba-board-admin-neomorphism',
        $plugin_url . 'css/admin-alba-board-style.css',
        [],
        $plugin_version
    );

    // Pass AJAX URL, nonces, and i18n to JS
    wp_localize_script('alba-backend-kanban', 'albaBoard', [
        'ajaxurl'                 => admin_url('admin-ajax.php'),
        'nonce'                   => wp_create_nonce('alba_move_card_nonce'),
        'get_card_details_nonce'  => wp_create_nonce('alba_get_card_details_admin'),
        'save_card_details_nonce' => wp_create_nonce('alba_save_card_details_admin'),
        'move_error'     => __('Could not move card', 'alba-board'),
        'move_unknown'   => __('Unknown error.', 'alba-board'),
        'fetch_error'    => __('An error occurred while communicating with the server.', 'alba-board'),
        'loading'        => __('Loading...', 'alba-board'),
        'save_failed'    => __('Failed to save.', 'alba-board'),
        'request_failed' => __('Request failed!', 'alba-board')
    ]);
}
add_action('admin_enqueue_scripts', 'alba_board_enqueue_admin_assets');