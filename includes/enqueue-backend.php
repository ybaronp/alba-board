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
    $plugin_version = '2.2.0'; 
    $backend_script_path = plugin_dir_path(dirname(__FILE__)) . 'assets/js/alba-backend-kanban.js';
    $admin_style_path = plugin_dir_path(dirname(__FILE__)) . 'assets/css/admin-alba-board-style.css';
    $admin_style_version = file_exists($admin_style_path) ? filemtime($admin_style_path) : $plugin_version;

    wp_enqueue_script('sortablejs', $plugin_url . 'js/Sortable.min.js', [], '1.15.0', true);
    wp_enqueue_style('select2', $plugin_url . 'css/select2.min.css', [], '4.1.0');
    wp_enqueue_script('select2', $plugin_url . 'js/select2.min.js', ['jquery'], '4.1.0', true);

    wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
    wp_enqueue_style('flatpickr-dark-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css', [], '4.6.13');
    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true);

    $backend_script_version = file_exists($backend_script_path) ? filemtime($backend_script_path) : $plugin_version;
    wp_enqueue_script('alba-backend-kanban', $plugin_url . 'js/alba-backend-kanban.js', ['sortablejs', 'jquery', 'select2', 'flatpickr-js'], $backend_script_version, true);
    wp_enqueue_style('alba-board-admin-neomorphism', $plugin_url . 'css/admin-alba-board-style.css', [], $admin_style_version);

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
        'manage_card_archive_nonce' => wp_create_nonce('alba_manage_card_archive'),
        'trash_card_nonce'        => wp_create_nonce( 'alba_trash_card' ),
        'trash_card_confirm'      => __( 'Move this card to Trash? You can restore it from Alba Board > Cards > Trash.', 'alba-board' ),
        'trash_card_error'        => __( 'Could not move this card to Trash. Please try again.', 'alba-board' ),
        'move_list_nonce'         => wp_create_nonce('alba_move_list_nonce'),
        'can_move_cards'          => current_user_can('edit_cards') || current_user_can('edit_others_cards'),
        'can_move_lists'          => current_user_can('edit_lists') || current_user_can('edit_others_lists'),
        'loading'                 => __('Loading...', 'alba-board'),
        'uploading'               => __('Uploading...', 'alba-board'),
        'fetch_error'             => __('Error communicating with server.', 'alba-board')
    ]);
}
add_action('admin_enqueue_scripts', 'alba_board_enqueue_admin_assets');
