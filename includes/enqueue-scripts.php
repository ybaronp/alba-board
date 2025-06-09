<?php
// enqueue-scripts.php

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// FRONTEND ASSETS
function alba_board_enqueue_assets() {
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
        plugins_url('assets/js/alba-board-kanban.js', dirname(__FILE__)),
        ['sortablejs'],
        null,
        true
    );

    // Alba Board frontend CSS
    if (file_exists(plugin_dir_path(__FILE__) . '../assets/css/alba-board-style.css')) {
        wp_enqueue_style(
            'alba-board-style',
            plugins_url('assets/css/alba-board-style.css', dirname(__FILE__)),
            [],
            '1.0.0'
        );
    }

    // Data for AJAX frontend + i18n + nonces
    wp_localize_script('alba-kanban', 'albaBoard', [
        'ajaxurl'    => admin_url('admin-ajax.php'),
        'nonce'      => wp_create_nonce('alba_move_card_nonce'),
        'get_card_details_nonce' => wp_create_nonce('alba_get_card_details'),
        'move_error' => __('Could not move the card.', 'alba-board'),
    ]);
}
add_action('wp_enqueue_scripts', 'alba_board_enqueue_assets');

// ADMIN (BACKEND) ASSETS
function alba_board_enqueue_admin_assets($hook) {
    // Only enqueue on the board visual admin page
    if ($hook !== 'toplevel_page_alba-board-visual') {
        return;
    }

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
        plugins_url('assets/js/alba-backend-kanban.js', dirname(__FILE__)),
        ['sortablejs', 'jquery', 'select2'],
        null,
        true
    );

    // Alba Board backend CSS (neumorphism style)
    wp_enqueue_style(
        'alba-board-admin-neomorphism',
        plugins_url('assets/css/admin-alba-board-style.css', dirname(__FILE__)),
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