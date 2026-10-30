<?php
/** Atomic native card moves with optimistic list snapshots. */
if ( ! defined( 'ABSPATH' ) ) exit;

function alba_board_move_error( $code, $message, $status = 400 ) {
    return new WP_Error( $code, $message, [ 'status' => $status ] );
}

/** Read complete native ordering; no titles, descriptions or private fields are returned. */
function alba_board_move_ids( $list_id ) {
    global $wpdb;
    return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM $wpdb->posts p INNER JOIN $wpdb->postmeta m ON m.post_id=p.ID AND m.meta_key='alba_list_parent' WHERE p.post_type='alba_card' AND p.post_status='publish' AND m.meta_value=%s ORDER BY p.menu_order,p.ID", (string) $list_id ) ) );
}

function alba_board_move_id_array( $raw ) {
    if ( ! is_array( $raw ) || array_values( $raw ) !== $raw ) return false;
    $ids = [];
    foreach ( $raw as $id ) {
        if ( ! is_scalar( $id ) || is_bool( $id ) || ! preg_match( '/^[1-9][0-9]*$/D', (string) $id ) || (string) (int) $id !== (string) $id ) return false;
        $ids[] = (int) $id;
    }
    return count( $ids ) === count( array_unique( $ids ) ) ? $ids : false;
}

/** All move entry points share the same lock, validation, transaction and conflict policy. */
function alba_board_move_card_atomic( $card_id, $destination, $order, $snapshot ) {
    global $wpdb;
    $order = alba_board_move_id_array( $order );
    $source_order = alba_board_move_id_array( $snapshot['source'] ?? null );
    $destination_order = alba_board_move_id_array( $snapshot['destination'] ?? null );
    $source = absint( $snapshot['source_list'] ?? 0 );
    if ( false === $order || false === $source_order || false === $destination_order || ! $source || ! in_array( (int) $card_id, $source_order, true ) ) return alba_board_move_error( 'alba_move_snapshot', __( 'Reload the board.', 'alba-board' ), 409 );
    $lock = 'alba_move_' . md5( $wpdb->posts );
    if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,5)', $lock ) ) ) return alba_board_move_error( 'alba_move_busy', __( 'Move in progress. Try again.', 'alba-board' ), 409 );
    $transaction = false; $changed = [];
    try {
        foreach ( [ $wpdb->posts, $wpdb->postmeta ] as $table ) {
            $engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
            if ( 'innodb' !== strtolower( (string) $engine ) ) return alba_board_move_error( 'alba_move_storage', __( 'Move unavailable. Contact your administrator.', 'alba-board' ), 503 );
        }
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) throw new RuntimeException( 'transaction' );
        $transaction = true;
        // Lock list/card rows against cooperating and ordinary row updates while validating fresh state.
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID IN (%d,%d,%d) ORDER BY ID FOR UPDATE", $card_id, $source, $destination ), OBJECT_K );
        $card = $rows[ $card_id ] ?? null; $from = $rows[ $source ] ?? null; $to = $rows[ $destination ] ?? null;
        if ( ! $card || 'alba_card' !== $card->post_type || 'publish' !== $card->post_status || ! $from || ! $to || 'alba_list' !== $from->post_type || 'alba_list' !== $to->post_type || 'publish' !== $from->post_status || 'publish' !== $to->post_status ) return alba_board_move_error( 'alba_move_invalid', __( 'Card or list unavailable. Reload.', 'alba-board' ), 409 );
        foreach ( array_unique( [ $card_id, $source, $destination ] ) as $id ) clean_post_cache( $id );
        if ( ! current_user_can( 'edit_card', $card_id ) || ! current_user_can( 'edit_list', $destination ) || ! current_user_can( 'edit_list', $source ) ) return alba_board_move_error( 'alba_move_forbidden', __( 'You cannot move this card.', 'alba-board' ), 403 );
        $parent = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id,meta_value FROM $wpdb->postmeta WHERE post_id=%d AND meta_key='alba_list_parent' FOR UPDATE", $card_id ) );
        if ( count( $parent ) !== 1 || (int) $parent[0]->meta_value !== $source ) return alba_board_move_error( 'alba_move_conflict', __( 'Board changed. Reload.', 'alba-board' ), 409 );
        $actual_source = alba_board_move_ids( $source ); $actual_destination = alba_board_move_ids( $destination );
        if ( $wpdb->last_error ) throw new RuntimeException( 'read' );
        if ( $actual_source !== $source_order || $actual_destination !== $destination_order ) return alba_board_move_error( 'alba_move_conflict', __( 'Board changed. Reload.', 'alba-board' ), 409 );
        $expected_ids = array_values( array_unique( array_merge( $actual_destination, [ (int) $card_id ] ) ) );
        $submitted_ids = $order; sort( $expected_ids ); sort( $submitted_ids );
        if ( $expected_ids !== $submitted_ids ) return alba_board_move_error( 'alba_move_order', __( 'Invalid card order. Reload.', 'alba-board' ) );
        $fixed = function( $ids ) { return array_values( array_filter( $ids, function( $id ) { return ! current_user_can( 'edit_card', $id ); } ) ); };
        if ( $fixed( $actual_destination ) !== $fixed( $order ) ) return alba_board_move_error( 'alba_move_forbidden', __( 'You cannot reorder other users\' cards.', 'alba-board' ), 403 );
        $limit = absint( get_option( 'alba_board_limits', [] )['limit_cards'] ?? 0 );
        if ( $source !== (int) $destination && $limit && count( $order ) > $limit ) return alba_board_move_error( 'alba_move_limit', __( 'This list is full.', 'alba-board' ) );
        $changed = array_values( array_unique( array_merge( $actual_source, $order ) ) );
        if ( false === $wpdb->update( $wpdb->postmeta, [ 'meta_value' => (string) $destination ], [ 'meta_id' => $parent[0]->meta_id ], [ '%s' ], [ '%d' ] ) ) throw new RuntimeException( 'parent' );
        $positions = [ $order ];
        if ( $source !== (int) $destination ) $positions[] = array_values( array_diff( $actual_source, [ (int) $card_id ] ) );
        foreach ( $positions as $ids ) foreach ( $ids as $position => $id ) {
            // Only bookkeeping changes: no save hooks, assignment emails or partial public side effects.
            if ( false === $wpdb->update( $wpdb->posts, [ 'menu_order' => $position ], [ 'ID' => $id ], [ '%d' ], [ '%d' ] ) ) throw new RuntimeException( 'position' );
        }
        if ( false === $wpdb->query( 'COMMIT' ) ) throw new RuntimeException( 'commit' );
        $transaction = false;
        return [ 'message' => __( 'Card moved successfully.', 'alba-board' ) ];
    } catch ( Throwable $e ) {
        return alba_board_move_error( 'alba_move_failed', __( 'Move not confirmed. Reload.', 'alba-board' ), 500 );
    } finally {
        if ( $transaction ) $wpdb->query( 'ROLLBACK' );
        foreach ( $changed as $id ) { clean_post_cache( $id ); delete_transient( 'alba_card_live_admin_' . $id ); delete_transient( 'alba_card_live_frontend_' . $id ); }
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
    }
}

add_action( 'wp_ajax_alba_move_card', function() {
    if ( empty( $_POST['nonce'] ) || ! is_string( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'alba_move_card_nonce' ) ) wp_send_json_error( [ 'code' => 'alba_invalid_nonce', 'message' => __( 'Invalid nonce.', 'alba-board' ) ], 403 );
    $card = isset( $_POST['card_id'] ) && is_scalar( $_POST['card_id'] ) ? absint( $_POST['card_id'] ) : 0;
    $list = isset( $_POST['new_list_id'] ) && is_scalar( $_POST['new_list_id'] ) ? absint( $_POST['new_list_id'] ) : 0;
    $snapshot = isset( $_POST['move_snapshot'] ) && is_string( $_POST['move_snapshot'] ) ? json_decode( wp_unslash( $_POST['move_snapshot'] ), true ) : null;
    $order = isset( $_POST['order'] ) && is_array( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : null;
    if ( ! is_array( $snapshot ) ) wp_send_json_error( [ 'message' => __( 'Reload the board.', 'alba-board' ) ], 409 );
    $result = alba_board_move_card_atomic( $card, $list, $order, $snapshot );
    if ( is_wp_error( $result ) ) wp_send_json_error( [ 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ], $result->get_error_data()['status'] ?? 400 );
    wp_send_json_success( $result );
} );
