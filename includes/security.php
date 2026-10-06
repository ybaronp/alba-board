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

/** Enforce core object permissions before legacy frontend add-on handlers run. */
function alba_board_authorize_frontend_card_action() {
    check_ajax_referer( 'alba_frontend_nonce', 'nonce' );
    $hook = current_filter();
    if ( false !== strpos( $hook, 'alba_create_card' ) ) {
        $list_id = isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0;
        if ( ! current_user_can( 'edit_cards' ) || 'alba_list' !== get_post_type( $list_id ) || 'publish' !== get_post_status( $list_id ) || ! current_user_can( 'edit_list', $list_id ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to create cards in this list.', 'alba-board' ) ], 403 );
        }
        $limits = get_option( 'alba_board_limits', [] );
        $limit = isset( $limits['limit_cards'] ) ? absint( $limits['limit_cards'] ) : 0;
        if ( $limit > 0 ) {
            $cards = get_posts( [ 'post_type' => 'alba_card', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'alba_list_parent', 'meta_value' => $list_id ] );
            if ( count( $cards ) >= $limit ) wp_send_json_error( [ 'message' => __( 'The card limit for this list has been reached.', 'alba-board' ) ], 400 );
        }
        return;
    }
    $card_id = isset( $_POST['card_id'] ) ? absint( $_POST['card_id'] ) : 0;
    $capability = false !== strpos( $hook, 'alba_delete_card' ) ? 'delete_card' : 'edit_card';
    if ( 'alba_card' !== get_post_type( $card_id ) || 'publish' !== get_post_status( $card_id ) || ! current_user_can( $capability, $card_id ) ) {
        wp_send_json_error( [ 'message' => __( 'You do not have permission to manage this card.', 'alba-board' ) ], 403 );
    }
}
foreach ( [ 'alba_create_card', 'alba_add_card_comment', 'alba_delete_card' ] as $alba_frontend_action ) {
    add_action( 'wp_ajax_' . $alba_frontend_action, 'alba_board_authorize_frontend_card_action', 1 );
    add_action( 'wp_ajax_nopriv_' . $alba_frontend_action, 'alba_board_authorize_frontend_card_action', 1 );
}
