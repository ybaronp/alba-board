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

/** Authorize free frontend actions and protect older add-on handlers. */
function alba_board_authorize_frontend_card_action() {
    if (!check_ajax_referer('alba_frontend_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Security token expired. Refresh the page and try again.', 'alba-board')], 403);
    }
    $hook = current_filter();
    if ( false !== strpos( $hook, 'alba_create_card' ) ) {
        $list_id = isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0;
        if ( ! current_user_can( 'edit_cards' ) || 'alba_list' !== get_post_type( $list_id ) || 'publish' !== get_post_status( $list_id ) || ! current_user_can( 'edit_list', $list_id ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to create cards in this list.', 'alba-board' ) ], 403 );
        }
        $limits = get_option( 'alba_board_limits', [] );
        $limit = isset( $limits['limit_cards'] ) ? absint( $limits['limit_cards'] ) : 0;
        if ( $limit > 0 ) {
            $cards = get_posts( [ 'post_type' => 'alba_card', 'post_status' => 'publish', 'numberposts' => $limit, 'fields' => 'ids', 'meta_key' => 'alba_list_parent', 'meta_value' => $list_id ] );
            if ( count( $cards ) >= $limit ) wp_send_json_error( [ 'message' => __( 'The card limit for this list has been reached.', 'alba-board' ) ], 400 );
        }
        return;
    }
    $card_id = isset( $_POST['card_id'] ) ? absint( $_POST['card_id'] ) : 0;
    $can_manage = false !== strpos( $hook, 'alba_add_card_comment' )
        ? alba_board_can_comment_card( $card_id ) : current_user_can( 'delete_card', $card_id );
    if ( 'alba_card' !== get_post_type( $card_id ) || 'publish' !== get_post_status( $card_id ) || ! $can_manage ) {
        wp_send_json_error( [ 'message' => __( 'You do not have permission to manage this card.', 'alba-board' ) ], 403 );
    }
}
foreach ( [ 'alba_create_card', 'alba_add_card_comment', 'alba_delete_card' ] as $alba_frontend_action ) {
    add_action( 'wp_ajax_' . $alba_frontend_action, 'alba_board_authorize_frontend_card_action', 1 );
    add_action( 'wp_ajax_nopriv_' . $alba_frontend_action, 'alba_board_authorize_frontend_card_action', 1 );
}

/** Read/comment access is distinct from ownership-based editing. */
function alba_board_can_view_shared_card( $card_id ) {
    $card = get_post( absint( $card_id ) );
    if ( ! is_user_logged_in() || ! $card || 'alba_card' !== $card->post_type ) return false;
    if ( current_user_can( 'edit_card', $card->ID ) ) return true;
    // Existing Board roles have edit_cards; ordinary subscribers/authors do not.
    if ( ! current_user_can( 'edit_cards' ) || 'publish' !== $card->post_status ) return false;
    $list = get_post( absint( get_post_meta( $card->ID, 'alba_list_parent', true ) ) );
    $board = $list ? get_post( absint( get_post_meta( $list->ID, 'alba_board_parent', true ) ) ) : null;
    return $list && 'alba_list' === $list->post_type && 'publish' === $list->post_status
        && $board && 'alba_board' === $board->post_type && 'publish' === $board->post_status;
}

function alba_board_can_comment_card( $card_id ) {
    return 'publish' === get_post_status( $card_id ) && alba_board_can_view_shared_card( $card_id );
}

/** Cache markup per viewer and invalidate all variants together. */
function alba_board_card_modal_cache_key( $context, $card_id ) {
    $revision_key = 'alba_card_modal_revision_' . absint( $card_id );
    $revision = get_transient( $revision_key );
    if ( ! $revision ) {
        $revision = wp_generate_uuid4();
        set_transient( $revision_key, $revision, 2 * DAY_IN_SECONDS );
    }
    return 'alba_modal_v225_' . sanitize_key( $context ) . '_' . absint( $card_id ) . '_'
        . get_current_user_id() . '_' . (int) current_user_can( 'edit_card', $card_id ) . '_'
        . (int) current_user_can( 'delete_card', $card_id ) . '_' . $revision;
}
