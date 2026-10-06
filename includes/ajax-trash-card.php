<?php
/**
 * Recoverable card deletion from the backend board.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function alba_board_ajax_trash_card() {
    check_ajax_referer( 'alba_trash_card', 'nonce' );

    $card_id = isset( $_POST['card_id'] ) ? absint( $_POST['card_id'] ) : 0;
    $card    = $card_id ? get_post( $card_id ) : null;
    if ( ! $card || 'alba_card' !== $card->post_type || ! current_user_can( 'delete_card', $card_id ) ) {
        wp_send_json_error( [ 'message' => __( 'You do not have permission to delete this card.', 'alba-board' ) ], 403 );
    }

    // WordPress permanently deletes instead of trashing when Trash is disabled.
    if ( ! EMPTY_TRASH_DAYS ) {
        wp_send_json_error( [ 'message' => __( 'WordPress Trash is disabled. Enable it to delete cards safely.', 'alba-board' ) ], 400 );
    }
    if ( 'trash' === $card->post_status ) {
        wp_send_json_error( [ 'message' => __( 'This card is already in Trash.', 'alba-board' ) ], 409 );
    }

    $trashed = wp_trash_post( $card_id );
    if ( ! $trashed || 'trash' !== get_post_status( $card_id ) ) {
        wp_send_json_error( [ 'message' => __( 'Could not move this card to Trash.', 'alba-board' ) ], 400 );
    }

    wp_send_json_success( [ 'card_id' => $card_id ] );
}
add_action( 'wp_ajax_alba_trash_card', 'alba_board_ajax_trash_card' );
