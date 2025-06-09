<?php
// includes/enqueue-backend.php

if ( ! defined( 'ABSPATH' ) ) exit;

function alba_board_enqueue_admin_assets($hook) {
    // Only enqueue on the board visual admin page
    if ($hook !== 'toplevel_page_alba-board-visual') {
        return;
    }

    $plugin_url = plugin_dir_url(dirname(__FILE__)); // desde /includes hacia raíz del plugin

    // Sortable.js for drag and drop
    wp_enqueue_script(
        'sortablejs',
        'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
        [],
        '1.15.0',
        true
    );

    // Alba Board backend JS
    wp_enqueue_script(
        'alba-backend-kanban',
        $plugin_url . 'assets/js/alba-backend-kanban.js',
        ['sortablejs', 'jquery', 'select2'],
        null,
        true
    );

    // Alba Board backend CSS (neumorphism style)
    wp_enqueue_style(
        'alba-board-admin-neomorphism',
        $plugin_url . 'assets/css/admin-alba-board-style.css',
        [],
        '1.1.0'
    );

    // Select2 (for user selector in modal)
    wp_enqueue_style('select2', '//cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('select2', '//cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);

    // Pass AJAX URL, security nonces, and i18n strings to JS
    wp_localize_script('alba-backend-kanban', 'albaBoard', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('alba_move_card_nonce'),
        'get_card_details_nonce' => wp_create_nonce('alba_get_card_details_admin'),
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