<?php
/**
 * includes/enqueue-backend.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function alba_board_enqueue_admin_assets($hook) {
    if ($hook !== 'toplevel_page_alba-board-visual') {
        return;
    }

    $plugin_url = plugin_dir_url(dirname(__FILE__)) . 'assets/';
    // CACHE BUSTING: Bumped to 2.0.5 to force Safari to reload the latest sanitized JS
    $plugin_version = '2.0.5'; 

    wp_enqueue_script('sortablejs', $plugin_url . 'js/Sortable.min.js', [], '1.15.0', true);
    wp_enqueue_style('select2', $plugin_url . 'css/select2.min.css', [], '4.1.0');
    wp_enqueue_script('select2', $plugin_url . 'js/select2.min.js', ['jquery'], '4.1.0', true);

    wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
    wp_enqueue_style('flatpickr-dark-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css', [], '4.6.13');
    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true);

    wp_enqueue_script('alba-backend-kanban', $plugin_url . 'js/alba-backend-kanban.js', ['sortablejs', 'jquery', 'select2', 'flatpickr-js'], $plugin_version, true);
    wp_enqueue_style('alba-board-admin-neomorphism', $plugin_url . 'css/admin-alba-board-style.css', [], $plugin_version);

    wp_localize_script('alba-backend-kanban', 'albaBoard', [
        'ajaxurl'                 => admin_url('admin-ajax.php'),
        'rest_url'                => esc_url_raw( rest_url() ), 
        'rest_nonce'              => wp_create_nonce( 'wp_rest' ),
        'nonce'                   => wp_create_nonce('alba_move_card_nonce'),
        'get_card_details_nonce'  => wp_create_nonce('alba_get_card_details_admin'),
        'save_card_details_nonce' => wp_create_nonce('alba_save_card_details_admin'),
        'upload_attachment_nonce' => wp_create_nonce('alba_upload_attachment_nonce'),
        'delete_attachment_nonce' => wp_create_nonce('alba_delete_attachment_nonce'),
        'delete_list_nonce'       => wp_create_nonce('alba_delete_list_nonce'), 
        'move_list_nonce'         => wp_create_nonce('alba_move_list_nonce'),
        'loading'                 => __('Loading...', 'alba-board'),
        'uploading'               => __('Uploading...', 'alba-board'),
        'fetch_error'             => __('Error communicating with server.', 'alba-board')
    ]);
}
add_action('admin_enqueue_scripts', 'alba_board_enqueue_admin_assets');