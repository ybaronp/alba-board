<?php
/** Shared UI/MCP creation service for the free, built-in template catalog. */
if ( ! defined( 'ABSPATH' ) ) exit;

function alba_board_can_create_from_template() {
    foreach ( [ 'edit_boards', 'publish_boards', 'edit_lists', 'publish_lists', 'edit_cards', 'publish_cards' ] as $cap ) if ( ! current_user_can( $cap ) ) return false;
    return is_user_logged_in();
}

function alba_board_template_creation_form( $template ) {
    if ( ! alba_board_can_create_from_template() ) return;
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    wp_nonce_field( 'alba_create_board_from_template' );
    foreach ( [ 'action' => 'alba_create_board_from_template', 'template_id' => $template['id'], 'template_version' => $template['version'], 'request_id' => wp_generate_uuid4() ] as $name => $value ) echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
    echo '<h3>' . esc_html__( 'Create your board', 'alba-board' ) . '</h3><p><label for="alba-template-board-name">' . esc_html__( 'Board name', 'alba-board' ) . '</label><br><input type="text" id="alba-template-board-name" name="board_title" maxlength="200" required value="' . esc_attr( $template['name'] ) . '"></p>';
    echo '<p><label><input type="checkbox" name="confirmed" value="1" required> ' . esc_html__( 'Create a new board with these lists and starter tasks.', 'alba-board' ) . '</label></p>';
    submit_button( __( 'Create this board', 'alba-board' ) );
    echo '</form>';
}

function alba_board_create_from_builtin_template( $template_id, $version, $title, $request_id, $context = 'ui' ) {
    if ( ! function_exists( 'alba_board_get_builtin_template' ) ) return new WP_Error( 'alba_template_catalog_unavailable', __( 'Board templates are not available yet.', 'alba-board' ) );
    if ( ! is_scalar( $version ) || is_bool( $version ) || (string) (int) $version !== (string) $version ) return new WP_Error( 'alba_template_invalid', __( 'Review the template version.', 'alba-board' ) );
    return alba_board_create_clean_structure( $title, $request_id, $context, [ $template_id, (int) $version ], function() use ( $template_id, $version ) { $template = alba_board_get_builtin_template( $template_id, $version ); if ( ! is_wp_error( $template ) ) $template['_source'] = 'builtin'; return $template; } );
}

/** Internal shared service; the structure is prepared server-side, never accepted from a request body. */
function alba_board_create_clean_structure( $title, $request_id, $context, $identity, $prepare ) {
    if ( ! alba_board_can_create_from_template() ) return new WP_Error( 'alba_template_forbidden', __( 'You do not have permission to create this board and its tasks.', 'alba-board' ) );
    if ( ! is_string( $title ) || ! is_string( $request_id ) || ! is_callable( $prepare ) ) return new WP_Error( 'alba_template_invalid', __( 'Review the board name and try again.', 'alba-board' ) );
    $title = trim( sanitize_text_field( $title ) );
    if ( ! preg_match( '/^.{1,200}$/us', $title ) || ! preg_match( '/^[A-Za-z0-9_-]{8,64}$/D', $request_id ) ) return new WP_Error( 'alba_template_invalid', __( 'Use a board name of 1–200 characters and a valid request ID.', 'alba-board' ) );

    $key = 'alba_template_request_' . hash( 'sha256', get_current_user_id() . ':' . $context . ':' . $request_id );
    $fingerprint = hash( 'sha256', wp_json_encode( array_merge( $identity, [ $title ] ) ) );
    $lock = $key . '_lock';
    global $wpdb;
    $lease = get_option( $lock );
    if ( $lease && (int) $lease < time() ) {
        $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name=%s AND option_value=%s", $lock, (string) $lease ) );
        wp_cache_delete( $lock, 'options' );
    }
    if ( ! add_option( $lock, time() + 300, '', false ) ) return new WP_Error( 'alba_template_busy', __( 'This creation request is already running. Retry with the same request ID.', 'alba-board' ) );
    $created = []; $transaction = false;
    $notify_priority = has_action( 'wp_insert_post', 'alba_board_notify_on_new_card' );
    try {
        $previous = get_option( $key );
        if ( is_array( $previous ) && $previous['expires'] > time() ) {
            if ( ! hash_equals( $previous['fingerprint'], $fingerprint ) ) return new WP_Error( 'alba_template_conflict', __( 'This request ID was already used for a different board. Review the existing result before starting a new request.', 'alba-board' ) );
            $board = get_post( $previous['result']['board_id'] );
            if ( ! $board || 'alba_board' !== $board->post_type || 'publish' !== $board->post_status || ! current_user_can( 'edit_board', $board->ID ) ) return new WP_Error( 'alba_template_result_unavailable', __( 'The previous board is no longer available to you. This request will not create another copy.', 'alba-board' ) );
            $result = $previous['result']; $result['reused'] = true; return $result;
        }
        if ( $previous ) delete_option( $key );
        $template = call_user_func( $prepare );
        if ( is_wp_error( $template ) ) return $template;
        $total_cards = 0; $total_bytes = 0;
        if ( ! is_array( $template ) || ! isset( $template['id'], $template['version'], $template['lists'] ) || ! is_array( $template['lists'] ) || count( $template['lists'] ) > 50 ) return new WP_Error( 'alba_template_structure', __( 'This structure cannot be created in one request.', 'alba-board' ) );
        foreach ( $template['lists'] as $list ) {
            if ( ! isset( $list['title'], $list['cards'] ) || ! is_string( $list['title'] ) || ! is_array( $list['cards'] ) ) return new WP_Error( 'alba_template_structure', __( 'Invalid list structure.', 'alba-board' ) );
            $total_cards += count( $list['cards'] ); $total_bytes += strlen( $list['title'] );
            foreach ( $list['cards'] as $card ) {
                if ( ! isset( $card['title'], $card['description'] ) || ! is_string( $card['title'] ) || ! is_string( $card['description'] ) ) return new WP_Error( 'alba_template_structure', __( 'Invalid task structure.', 'alba-board' ) );
                $total_bytes += strlen( $card['title'] ) + strlen( $card['description'] );
            }
        }
        if ( $total_cards > 500 || $total_bytes > 2 * MB_IN_BYTES ) return new WP_Error( 'alba_template_structure_limit', __( 'This board is too large to copy safely in one request.', 'alba-board' ) );
        $limit = absint( get_option( 'alba_board_limits', [] )['limit_cards'] ?? 0 );
        foreach ( $template['lists'] as $list ) if ( $limit && count( $list['cards'] ) > $limit ) return new WP_Error( 'alba_template_card_limit', __( 'This template has more starter tasks in one list than your current card limit allows. Choose another template or ask your administrator to review the limit.', 'alba-board' ) );
        // A complete board and its retry record commit together; never publish a half-created project.
        foreach ( [ $wpdb->posts, $wpdb->postmeta, $wpdb->options ] as $table ) {
            $status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name=%s', $table ) );
            if ( ! $status || 'innodb' !== strtolower( $status->Engine ) ) return new WP_Error( 'alba_template_storage', __( 'Your database needs transactional table support before boards can be created safely from templates. Ask your hosting administrator for help.', 'alba-board' ) );
        }
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) throw new RuntimeException( 'transaction' );
        $transaction = true;
        // Starter tasks are generic setup content, not new assignments; avoid mail before a commit.
        if ( false !== $notify_priority ) remove_action( 'wp_insert_post', 'alba_board_notify_on_new_card', $notify_priority );
        $insert = function( $type, $name, $description = '', $parent_key = '', $parent = 0, $order = 0 ) use ( &$created ) {
            $data = [ 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $name, 'post_content' => $description, 'post_author' => get_current_user_id(), 'menu_order' => $order ];
            if ( $parent_key ) $data['meta_input'] = [ $parent_key => $parent ];
            $id = wp_insert_post( wp_slash( $data ), true );
            if ( is_wp_error( $id ) || ! $id ) throw new RuntimeException( 'insert' );
            $created[] = $id; return $id;
        };
        $board_id = $insert( 'alba_board', $title );
        $result = [ 'board_id' => $board_id, 'title' => $title, 'template_id' => $template['id'], 'template_version' => (int) $template['version'], 'lists' => [], 'reused' => false, 'url' => admin_url( 'admin.php?page=alba-board-visual&board_id=' . $board_id ) ];
        foreach ( $template['lists'] as $position => $list ) {
            $list_id = $insert( 'alba_list', $list['title'], '', 'alba_board_parent', $board_id, $position );
            $row = [ 'id' => $list_id, 'title' => $list['title'], 'cards' => [] ];
            foreach ( $list['cards'] as $index => $card ) $row['cards'][] = [ 'id' => $insert( 'alba_card', $card['title'], $card['description'], 'alba_list_parent', $list_id, $index ), 'title' => $card['title'] ];
            $result['lists'][] = $row;
        }
        if ( ! add_post_meta( $board_id, ( $template['_source'] ?? '' ) === 'builtin' ? 'alba_builtin_template' : 'alba_clean_duplicate', ( $template['_source'] ?? '' ) === 'builtin' ? [ 'id' => $template['id'], 'version' => (int) $template['version'] ] : true, true ) ) throw new RuntimeException( 'metadata' );
        if ( ! add_option( $key, [ 'expires' => time() + DAY_IN_SECONDS, 'fingerprint' => $fingerprint, 'result' => $result ], '', false ) ) throw new RuntimeException( 'record' );
        if ( false === $wpdb->query( 'COMMIT' ) ) throw new RuntimeException( 'commit' );
        $transaction = false;
        return $result;
    } catch ( Throwable $error ) {
        if ( $transaction ) $wpdb->query( 'ROLLBACK' );
        foreach ( $created as $id ) clean_post_cache( $id );
        wp_cache_delete( $key, 'options' );
        return new WP_Error( 'alba_template_failed', __( 'The board could not be created. No partial board was kept. Retry with the same request ID.', 'alba-board' ) );
    } finally {
        if ( false !== $notify_priority ) add_action( 'wp_insert_post', 'alba_board_notify_on_new_card', $notify_priority, 3 );
        delete_option( $lock );
    }
}

add_action( 'admin_post_alba_create_board_from_template', function() {
    check_admin_referer( 'alba_create_board_from_template' );
    if ( '1' !== ( $_POST['confirmed'] ?? '' ) ) wp_die( esc_html__( 'Review the preview and confirm board creation first.', 'alba-board' ), '', [ 'response' => 400 ] );
    $value = function( $key ) { return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; };
    $result = alba_board_create_from_builtin_template( $value( 'template_id' ), $value( 'template_version' ), $value( 'board_title' ), $value( 'request_id' ) );
    if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ), '', [ 'response' => 400, 'back_link' => true ] );
    wp_safe_redirect( $result['url'] ); exit;
} );

add_action( 'alba_template_cleanup', function() {
    global $wpdb;
    $names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s", $wpdb->esc_like( 'alba_template_request_' ) . '%' ) );
    foreach ( $names as $name ) {
        $value = get_option( $name );
        if ( ( is_array( $value ) && ( $value['expires'] ?? 0 ) <= time() ) || ( is_numeric( $value ) && (int) $value < time() ) ) delete_option( $name );
    }
} );
add_action( 'init', function() { if ( ! wp_next_scheduled( 'alba_template_cleanup' ) ) wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'alba_template_cleanup' ); } );
register_deactivation_hook( dirname( __DIR__ ) . '/alba-board.php', function() { wp_clear_scheduled_hook( 'alba_template_cleanup' ); } );
