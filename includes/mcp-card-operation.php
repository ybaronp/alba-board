<?php
/** Atomic, retryable move-with-comment for one explicitly requested card. */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Database receipt remains durable even if the response/transient cache is lost. */
function alba_board_mcp_combined_cached( $key ) {
    global $wpdb;
    $value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s", $key . '_receipt' ) );
    if ( ! is_string( $value ) || false === strpos( $value, '|' ) ) return false;
    list( $expires, $serialized ) = explode( '|', $value, 2 );
    return (int) $expires > time() ? maybe_unserialize( $serialized ) : false;
}

function alba_board_mcp_transaction_tables_ready() {
    global $wpdb;
    $tables = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name IN (%s, %s, %s)', $wpdb->posts, $wpdb->postmeta, $wpdb->options ) );
    if ( ! is_array( $tables ) || count( $tables ) !== 3 ) return false;
    foreach ( $tables as $table ) if ( ! isset( $table->Engine ) || 'innodb' !== strtolower( $table->Engine ) ) return false;
    return true;
}

function alba_board_mcp_move_with_comment( $input, $key, $fingerprint ) {
    $text = isset( $input['comment'] ) && is_string( $input['comment'] ) ? trim( sanitize_textarea_field( $input['comment'] ) ) : '';
    if ( '' === $text || strlen( $text ) > 20000 ) return alba_board_mcp_error( 'alba_mcp_empty_comment', __( 'Write a valid comment first.', 'alba-board' ) );
    // Only the transaction-aware shared mover may borrow this operation’s transaction.
    if ( function_exists( 'alba_board_move_card_atomic' ) && ! class_exists( 'Alba_Board_Move_Transaction' ) ) return alba_board_mcp_error( 'alba_mcp_move_service_incompatible', __( 'This site’s move service needs an update before changes can be combined. Use separate requests.', 'alba-board' ), 503 );
    if ( ! class_exists( 'Alba_Board_Move_Transaction' ) && ! alba_board_mcp_transaction_tables_ready() ) return alba_board_mcp_error( 'alba_mcp_transaction_unavailable', __( 'This site cannot safely combine these changes. Use separate move and comment requests.', 'alba-board' ), 503 );
    if ( isset( $GLOBALS['alba_board_mcp_notification_queue'] ) ) return alba_board_mcp_error( 'alba_mcp_busy', __( 'Another card operation is in progress.', 'alba-board' ), 409 );
    global $wpdb;
    $id = (int) $input['card_id'];
    // @@in_transaction exists only on MariaDB; MySQL cannot report an open transaction (card #190).
    if ( ! class_exists( 'Alba_Board_Move_Transaction' ) && false !== stripos( (string) $wpdb->db_server_info(), 'mariadb' ) && '0' !== (string) $wpdb->get_var( 'SELECT @@in_transaction' ) ) return alba_board_mcp_error( 'alba_mcp_busy', __( 'Another card operation is in progress.', 'alba-board' ), 409 );
    $owner = class_exists( 'Alba_Board_Move_Transaction' ) ? Alba_Board_Move_Transaction::begin( true ) : null;
    if ( is_wp_error( $owner ) ) return $owner;
    if ( ! $owner && false === $wpdb->query( 'START TRANSACTION' ) ) return alba_board_mcp_error( 'alba_mcp_save_failed', __( 'Could not start the card operation.', 'alba-board' ), 500 );
    $committed = false;
    $GLOBALS['alba_board_mcp_notification_queue'] = [];
    try {
        // A transactional unique row also serializes retries if a lease expires mid-request.
        if ( false === $wpdb->query( $wpdb->prepare( "INSERT INTO $wpdb->options (option_name, option_value, autoload) VALUES (%s, '0|pending', 'no') ON DUPLICATE KEY UPDATE option_name = VALUES(option_name)", $key . '_receipt' ) ) ) return alba_board_mcp_error( 'alba_mcp_save_failed', __( 'Could not reserve the operation receipt.', 'alba-board' ), 500 );
        $cached = alba_board_mcp_combined_cached( $key );
        if ( $cached ) return alba_board_mcp_cached_result( $cached, $fingerprint );
        if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d FOR UPDATE", $id ) ) || false === $wpdb->query( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE post_id = %d FOR UPDATE", $id ) ) ) return alba_board_mcp_error( 'alba_mcp_save_failed', __( 'Could not lock the card.', 'alba-board' ), 500 );
        clean_post_cache( $id );
        if ( ! alba_board_mcp_permission( 'move-card', $input ) || ! alba_board_mcp_permission( 'add-comment', $input ) ) return alba_board_mcp_error( 'alba_mcp_forbidden', __( 'AI access to this action is not allowed.', 'alba-board' ), 403 );
        $moved = alba_board_mcp_run( 'move-card', $input, $owner );
        if ( is_wp_error( $moved ) ) return $moved;
        $commented = alba_board_mcp_run( 'add-comment', array_merge( $input, [ 'comment' => $text ] ) );
        if ( is_wp_error( $commented ) ) return $commented;
        $result = $commented;
        $result['receipt'] = [ 'request_id' => $input['request_id'], 'action' => 'move-card', 'saved' => true, 'destination_list_id' => $result['list_id'], 'comment_added' => true, 'comment_count' => $commented['receipt']['comment_count'] ];
        $receipt = ( time() + DAY_IN_SECONDS ) . '|' . maybe_serialize( [ 'fingerprint' => $fingerprint, 'result' => $result ] );
        // Options are explicitly non-autoloaded. Do not use an external object cache inside the transaction.
        if ( 1 !== $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->options SET option_value = %s, autoload = 'no' WHERE option_name = %s", $receipt, $key . '_receipt' ) ) ) return alba_board_mcp_error( 'alba_mcp_save_failed', __( 'Could not save the operation receipt.', 'alba-board' ), 500 );
        $commit = $owner ? $owner->commit() : $wpdb->query( 'COMMIT' );
        if ( is_wp_error( $commit ) ) return $commit;
        if ( false === $commit ) return alba_board_mcp_error( 'alba_mcp_save_failed', __( 'Could not finish the card operation.', 'alba-board' ), 500 );
        $committed = true;
        $notifications = $GLOBALS['alba_board_mcp_notification_queue'];
    } finally {
        if ( ! $committed ) {
            if ( $owner ) $owner->rollback();
            else $wpdb->query( 'ROLLBACK' );
            clean_post_cache( $id );
            wp_cache_delete( 'alba_board_usage_activity', 'options' );
            wp_cache_delete( 'alloptions', 'options' );
            wp_cache_delete( 'notoptions', 'options' );
        }
        unset( $GLOBALS['alba_board_mcp_notification_queue'] );
    }
    // A notification failure cannot turn a committed operation into a failed/retryable write.
    foreach ( $notifications as $arguments ) {
        try { call_user_func_array( 'alba_board_send_notification', $arguments ); }
        catch ( Throwable $error ) { error_log( 'Alba Board: notification failed after an MCP card operation.' ); }
    }
    return $result;
}

add_action( 'alba_board_oauth_cleanup', function() {
    global $wpdb;
    $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s AND CAST(SUBSTRING_INDEX(option_value, '|', 1) AS UNSIGNED) <= %d", $wpdb->esc_like( 'alba_mcp_' ) . '%' . $wpdb->esc_like( '_receipt' ), time() ) );
} );
