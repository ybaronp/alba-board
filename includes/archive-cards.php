<?php
/**
 * Archive and restore Alba Board cards.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function alba_board_register_archived_card_status() {
    register_post_status( 'alba_archived', [
        'label'                     => _x( 'Archived', 'card post status', 'alba-board' ),
        'public'                    => false,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Archived <span class="count">(%s)</span>', 'Archived <span class="count">(%s)</span>', 'alba-board' ),
    ] );
}
add_action( 'init', 'alba_board_register_archived_card_status' );

function alba_board_archive_card( $card_id ) {
    $card = get_post( $card_id );
    if ( ! $card || 'alba_card' !== $card->post_type || 'alba_archived' === $card->post_status ) {
        return false;
    }

    update_post_meta( $card_id, 'alba_archived_from_status', $card->post_status );
    $updated = wp_update_post( [
        'ID'          => $card_id,
        'post_status' => 'alba_archived',
    ], true );

    if ( is_wp_error( $updated ) ) {
        delete_post_meta( $card_id, 'alba_archived_from_status' );
        return false;
    }

    return true;
}

function alba_board_restore_card( $card_id ) {
    $card = get_post( $card_id );
    if ( ! $card || 'alba_card' !== $card->post_type || 'alba_archived' !== $card->post_status ) {
        return false;
    }

    $list_id = absint( get_post_meta( $card_id, 'alba_list_parent', true ) );
    $list    = $list_id ? get_post( $list_id ) : false;
    if ( ! $list || 'alba_list' !== $list->post_type || 'publish' !== $list->post_status ) {
        return new WP_Error( 'alba_card_list_unavailable', __( 'Restore the card’s original list before restoring this card.', 'alba-board' ) );
    }

    $previous_status = get_post_meta( $card_id, 'alba_archived_from_status', true );
    $restored_statuses = [ 'publish', 'draft', 'pending', 'private' ];
    if ( ! in_array( $previous_status, $restored_statuses, true ) ) {
        $previous_status = 'publish';
    }

    $updated = wp_update_post( [
        'ID'          => $card_id,
        'post_status' => $previous_status,
    ], true );

    if ( is_wp_error( $updated ) ) {
        return $updated;
    }

    delete_post_meta( $card_id, 'alba_archived_from_status' );
    return true;
}

function alba_board_ajax_manage_archived_card() {
    check_ajax_referer( 'alba_manage_card_archive', 'nonce' );

    $card_id = isset( $_POST['card_id'] ) ? absint( $_POST['card_id'] ) : 0;
    $action  = isset( $_POST['archive_action'] ) ? sanitize_key( wp_unslash( $_POST['archive_action'] ) ) : '';

    if ( ! $card_id || 'alba_card' !== get_post_type( $card_id ) || ! current_user_can( 'edit_card', $card_id ) ) {
        wp_send_json_error( [ 'message' => __( 'You do not have permission to manage this card.', 'alba-board' ) ], 403 );
    }

    if ( 'archive' === $action ) {
        $result = alba_board_archive_card( $card_id );
    } elseif ( 'restore' === $action ) {
        $result = alba_board_restore_card( $card_id );
    } else {
        wp_send_json_error( [ 'message' => __( 'Invalid archive action.', 'alba-board' ) ], 400 );
    }

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
    }

    if ( ! $result ) {
        wp_send_json_error( [ 'message' => __( 'Could not update this card.', 'alba-board' ) ], 400 );
    }

    wp_send_json_success( [ 'message' => __( 'Card updated.', 'alba-board' ) ] );
}
add_action( 'wp_ajax_alba_manage_archived_card', 'alba_board_ajax_manage_archived_card' );

function alba_board_render_archived_cards() {
    $cards = get_posts( [
        'post_type'      => 'alba_card',
        'post_status'    => 'alba_archived',
        'numberposts'    => -1,
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'suppress_filters' => false,
    ] );

    echo '<div class="alba-archive-view">';
    echo '<h2 style="color:var(--alba-text-title);">' . esc_html__( 'Archived Cards', 'alba-board' ) . '</h2>';
    echo '<p>' . esc_html__( 'Archived cards are hidden from active boards and can be restored here.', 'alba-board' ) . '</p>';

    if ( empty( $cards ) ) {
        echo '<p class="alba-archive-empty"><em>' . esc_html__( 'No archived cards.', 'alba-board' ) . '</em></p>';
        echo '</div>';
        return;
    }

    foreach ( $cards as $card ) {
        $list_id   = absint( get_post_meta( $card->ID, 'alba_list_parent', true ) );
        $list      = $list_id ? get_post( $list_id ) : false;
        $board_id  = $list ? absint( get_post_meta( $list_id, 'alba_board_parent', true ) ) : 0;
        $board     = $board_id ? get_post( $board_id ) : false;
        $location  = [];
        if ( $board && 'alba_board' === $board->post_type ) $location[] = $board->post_title;
        if ( $list && 'alba_list' === $list->post_type ) $location[] = $list->post_title;
        $location_label = $location ? implode( ' → ', $location ) : __( 'Original list unavailable', 'alba-board' );
        $list_available = $list && 'alba_list' === $list->post_type && 'publish' === $list->post_status;

        echo '<div class="alba-archived-card-row" style="display:flex;justify-content:space-between;align-items:center;gap:16px;padding:14px;margin:10px 0;border-radius:12px;background:var(--alba-card-bg);box-shadow:2px 2px 6px var(--alba-shadow-dark),-2px -2px 6px var(--alba-shadow-light);">';
        echo '<div><strong>' . esc_html( $card->post_title ) . '</strong><div style="font-size:12px;color:var(--alba-text-muted);margin-top:4px;">' . esc_html( $location_label ) . '</div></div>';
        if ( $list_available ) {
            echo '<button type="button" class="alba-btn-neumorphic alba-restore-card-btn" data-card-id="' . esc_attr( $card->ID ) . '">' . esc_html__( 'Restore', 'alba-board' ) . '</button>';
        } else {
            $lists_trash_url = admin_url( 'edit.php?post_type=alba_list&post_status=trash' );
            echo '<span style="font-size:12px;color:var(--alba-text-muted);">' . esc_html__( 'Restore the original list first.', 'alba-board' ) . ' <a href="' . esc_url( $lists_trash_url ) . '">' . esc_html__( 'Open Lists Trash', 'alba-board' ) . '</a></span>';
        }
        echo '</div>';
    }

    echo '</div>';
}
