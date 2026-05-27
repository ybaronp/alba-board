<?php
/**
 * includes/enqueue-frontend.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function alba_board_enqueue_assets() {
    $plugin_url = plugin_dir_url(dirname(__FILE__));
    $plugin_version = '2.1.3'; // Cache busting

    wp_enqueue_script(
        'sortablejs',
        $plugin_url . 'assets/js/Sortable.min.js',
        [],
        '1.15.0',
        true
    );

    wp_enqueue_script(
        'alba-kanban',
        $plugin_url . 'assets/js/alba-board-frontend.js',
        ['sortablejs', 'jquery'],
        $plugin_version,
        true
    );

    wp_enqueue_style(
        'alba-board-style',
        $plugin_url . 'assets/css/alba-board-style.css',
        [],
        $plugin_version
    );

    $localize_data = [
        'ajaxurl'                 => admin_url('admin-ajax.php'),
        'rest_url'                => esc_url_raw( rest_url() ), 
        'rest_nonce'              => wp_create_nonce( 'wp_rest' ), 
        'nonce'                   => wp_create_nonce('alba_move_card_nonce'),
        'move_error'              => __('Could not move the card.', 'alba-board'),
        'loading'                 => __('Loading...', 'alba-board'),
        'confirm_delete'          => __('Are you sure you want to delete this card?', 'alba-board'),
        'delete_error'            => __('Error deleting card', 'alba-board'),
    ];

    // STRICT NONCE GATE
    if ( current_user_can('edit_cards') ) {
        $localize_data['get_card_details_nonce'] = wp_create_nonce('alba_get_card_details');
    }

    wp_localize_script('alba-kanban', 'albaBoard', $localize_data);
}
add_action('wp_enqueue_scripts', 'alba_board_enqueue_assets');