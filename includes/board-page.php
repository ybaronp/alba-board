<?php
/** Create a reviewable draft page for a board without publishing it. */
if ( ! defined( 'ABSPATH' ) ) exit;

function alba_board_page_contains_board( $page, $board_id ) {
    if ( ! $page instanceof WP_Post || 'page' !== $page->post_type || in_array( $page->post_status, [ 'trash', 'auto-draft' ], true ) ) return false;
    preg_match_all( '/' . get_shortcode_regex( [ 'alba_board' ] ) . '/s', $page->post_content, $matches, PREG_SET_ORDER );
    foreach ( $matches as $match ) {
        if ( '[' === $match[1] && ']' === $match[6] ) continue;
        $atts = shortcode_parse_atts( $match[3] );
        if ( is_array( $atts ) && isset( $atts['id'] ) && ctype_digit( (string) $atts['id'] ) && (int) $atts['id'] === (int) $board_id ) return true;
    }
    return false;
}

function alba_board_create_draft_page( $board_id ) {
    $board = get_post( $board_id );
    if ( ! $board || 'alba_board' !== $board->post_type || 'publish' !== $board->post_status || ! current_user_can( 'edit_board', $board_id ) || ! current_user_can( 'edit_pages' ) ) {
        return new WP_Error( 'forbidden', __( 'Permission denied.', 'alba-board' ), [ 'status' => 403 ] );
    }
    global $wpdb;
    $lock = 'alba_page_' . md5( $wpdb->prefix . ':' . $board_id );
    if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 2)', $lock ) ) ) {
        return new WP_Error( 'busy', __( 'Please try again.', 'alba-board' ), [ 'status' => 409 ] );
    }
    try {
        // Re-read after acquiring the lock so double-clicks reuse the same page.
        wp_cache_delete( $board_id, 'post_meta' );
        $page_id = (int) get_post_meta( $board_id, '_alba_board_page_id', true );
        $page = $page_id ? get_post( $page_id ) : null;
        if ( ! alba_board_page_contains_board( $page, $board_id ) ) {
            $known_url = get_post_meta( $board_id, 'alba_board_public_url', true );
            $page_id = $known_url ? url_to_postid( $known_url ) : 0;
            $page = $page_id ? get_post( $page_id ) : null;
        }
        if ( alba_board_page_contains_board( $page, $board_id ) ) {
            if ( ! current_user_can( 'edit_post', $page->ID ) ) return new WP_Error( 'forbidden', __( 'Permission denied.', 'alba-board' ), [ 'status' => 403 ] );
            update_post_meta( $board_id, '_alba_board_page_id', $page->ID );
            return $page->ID;
        }
        $page_id = wp_insert_post( [
            'post_type' => 'page', 'post_status' => 'draft',
            'post_author' => get_current_user_id(), 'post_title' => $board->post_title,
            'post_content' => '<!-- wp:shortcode -->' . "\n" . '[alba_board id="' . absint( $board_id ) . '"]' . "\n" . '<!-- /wp:shortcode -->',
        ], true );
        if ( is_wp_error( $page_id ) ) return $page_id;
        if ( ! update_post_meta( $board_id, '_alba_board_page_id', $page_id ) ) {
            wp_delete_post( $page_id, true );
            return new WP_Error( 'save_failed', __( 'Could not save the board page. Please try again.', 'alba-board' ), [ 'status' => 500 ] );
        }
        return $page_id;
    } finally {
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
    }
}

add_action( 'admin_post_alba_create_board_page', function() {
    $board_id = isset( $_POST['board_id'] ) && is_scalar( $_POST['board_id'] ) ? absint( $_POST['board_id'] ) : 0;
    check_admin_referer( 'alba_create_board_page_' . $board_id );
    $page_id = alba_board_create_draft_page( $board_id );
    if ( is_wp_error( $page_id ) ) {
        $data = $page_id->get_error_data();
        wp_die( esc_html( $page_id->get_error_message() ), '', [ 'response' => is_array( $data ) && isset( $data['status'] ) ? $data['status'] : 500 ] );
    }
    wp_safe_redirect( get_edit_post_link( $page_id, 'raw' ) );
    exit;
} );

function alba_board_render_page_action( $board_id ) {
    if ( ! current_user_can( 'edit_board', $board_id ) || ! current_user_can( 'edit_pages' ) ) return;
    $page_id = (int) get_post_meta( $board_id, '_alba_board_page_id', true );
    $page = $page_id ? get_post( $page_id ) : null;
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="alba_create_board_page"><input type="hidden" name="board_id" value="' . absint( $board_id ) . '">';
    wp_nonce_field( 'alba_create_board_page_' . $board_id );
    echo '<button type="submit" class="alba-menu-action">' . esc_html( alba_board_page_contains_board( $page, $board_id ) ? __( 'Edit board page', 'alba-board' ) : __( 'Create board page', 'alba-board' ) ) . '</button>';
    echo '<p class="description" style="padding:0 12px;max-width:240px">' . esc_html__( 'Review the draft before publishing. Existing board access rules apply.', 'alba-board' ) . '</p></form>';
}
