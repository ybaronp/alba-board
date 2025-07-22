<?php
// includes/security.php

if ( ! defined( 'ABSPATH' ) ) exit;

// Permission check when saving Boards, Lists, or Cards
function alba_board_check_permissions_before_save($post_id, $post) {
    // Skip for autosaves, AJAX, or revisions
    if (
        ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) ||
        ( defined('DOING_AJAX') && DOING_AJAX ) ||
        wp_is_post_revision($post_id) ||
        wp_is_post_autosave($post_id)
    ) {
        return;
    }

    $post_type = isset($post->post_type) ? sanitize_key($post->post_type) : '';

    // Map post type to its edit capability (singular)
    $cap_map = [
        'alba_board' => 'edit_board',
        'alba_list'  => 'edit_list',
        'alba_card'  => 'edit_card',
    ];

    // Only handle supported post types
    if ( ! isset($cap_map[$post_type]) ) {
        return;
    }

    // Always allow administrators to save
    if ( current_user_can('administrator') ) {
        return;
    }

    // ** Key change: use map_meta_cap mechanism **
    // Check capability on this object (let WP translate it via map_meta_cap)
    if ( ! current_user_can( $cap_map[$post_type], $post_id ) ) {
        wp_die(
            esc_html__('You do not have permission to perform this action.', 'alba-board'),
            esc_html__('Permission denied', 'alba-board'),
            ['response' => 403]
        );
    }
}
add_action('save_post', 'alba_board_check_permissions_before_save', 1, 2);