<?php
/** One-off, clean copies. The preview is the exact server-derived structure that will be created. */
if ( ! defined( 'ABSPATH' ) ) exit;
function alba_board_duplicate_source_allowed( $id ) {
    $post = get_post( $id );
    return $post && 'alba_board' === $post->post_type && 'publish' === $post->post_status
        && current_user_can( 'read_board', $id ) && current_user_can( 'edit_cards' )
        && ( ! function_exists( 'alba_board_can_view_board' ) || alba_board_can_view_board( $id ) );
}
function alba_board_clean_copy_text( $text ) {
    $text = wp_strip_all_tags( $text );
    $lines = preg_split( '/\r\n|\r|\n/', $text ); $clean = [];
    $upload = wp_upload_dir( null, false ); $upload_url = $upload['baseurl'] ?? ''; $upload_path = wp_parse_url( $upload_url, PHP_URL_PATH );
    foreach ( $lines as $line ) {
        // Remove entire lines containing image syntax or local file references, including reference definitions.
        if ( strpos( $line, '![' ) !== false || preg_match( '/alba-image:|action=alba_card_image|action%3Dalba_card_image/i', $line ) || ( $upload_path && strpos( $line, rtrim( $upload_path, '/' ) . '/' ) !== false ) ) $clean[] = __( '[Image or file reference removed from clean copy]', 'alba-board' );
        else $clean[] = $line;
    }
    return trim( implode( "\n", $clean ) );
}
function alba_board_duplicate_preview( $id ) {
    if ( ! alba_board_duplicate_source_allowed( $id ) ) return new WP_Error( 'alba_copy_forbidden', __( 'This board is not available to copy.', 'alba-board' ) );
    $lists = get_posts( [ 'post_type' => 'alba_list', 'post_status' => 'publish', 'numberposts' => 51, 'meta_key' => 'alba_board_parent', 'meta_value' => $id, 'orderby' => [ 'menu_order' => 'ASC', 'ID' => 'ASC' ] ] );
    if ( count( $lists ) > 50 ) return new WP_Error( 'alba_copy_large', __( 'This board is too large to copy safely in one request.', 'alba-board' ) );
    $structure = [ 'id' => 'copy-board-' . $id, 'version' => 1, '_source' => 'duplicate', 'lists' => [] ]; $identity = []; $total = 0; $bytes = 0;
    foreach ( $lists as $list ) {
        if ( (int) get_post_meta( $list->ID, 'alba_board_parent', true ) !== (int) $id ) continue;
        $cards = get_posts( [ 'post_type' => 'alba_card', 'post_status' => 'publish', 'numberposts' => 501, 'meta_key' => 'alba_list_parent', 'meta_value' => $list->ID, 'orderby' => [ 'menu_order' => 'ASC', 'ID' => 'ASC' ] ] );
        $row = [ 'title' => $list->post_title, 'cards' => [] ]; $ids = [];
        foreach ( $cards as $card ) {
            if ( (int) get_post_meta( $card->ID, 'alba_list_parent', true ) !== (int) $list->ID ) continue;
            $row['cards'][] = [ 'title' => $card->post_title, 'description' => alba_board_clean_copy_text( $card->post_content ) ];
            $ids[] = $card->ID; $total++; $bytes += strlen( $card->post_title ) + strlen( $card->post_content );
        }
        if ( $total > 500 || $bytes > 2 * MB_IN_BYTES ) return new WP_Error( 'alba_copy_large', __( 'This board is too large to copy safely in one request.', 'alba-board' ) );
        $structure['lists'][] = $row; $identity[] = [ $list->ID, $ids ];
    }
    $encoded = wp_json_encode( [ $structure, $identity ] );
    if ( false === $encoded ) return new WP_Error( 'alba_copy_invalid', __( 'This board contains text that cannot be copied safely.', 'alba-board' ) );
    return [ 'structure' => $structure, 'fingerprint' => hash( 'sha256', $encoded ), 'card_count' => $total ];
}
function alba_board_duplicate_clean( $id, $fingerprint, $title, $request ) {
    if ( ! alba_board_duplicate_source_allowed( $id ) ) return new WP_Error( 'alba_copy_forbidden', __( 'This board is not available to copy.', 'alba-board' ) );
    if ( ! is_string( $fingerprint ) || ! preg_match( '/^[a-f0-9]{64}$/D', $fingerprint ) ) return new WP_Error( 'alba_copy_preview', __( 'Review the copy preview first.', 'alba-board' ) );
    $result = alba_board_create_clean_structure( $title, $request, 'duplicate:' . $id, [ $id, $fingerprint ], function() use ( $id, $fingerprint ) {
        $preview = alba_board_duplicate_preview( $id );
        if ( is_wp_error( $preview ) ) return $preview;
        if ( ! hash_equals( $preview['fingerprint'], $fingerprint ) ) return new WP_Error( 'alba_copy_changed', __( 'The source board changed. Review a fresh preview before copying.', 'alba-board' ) );
        return $preview['structure'];
    } );
    if ( ! is_wp_error( $result ) ) { unset( $result['template_id'], $result['template_version'] ); $result['source_board_id'] = (int) $id; }
    return $result;
}
add_action( 'admin_menu', function() { add_submenu_page( 'alba-board-visual', __( 'Duplicate board', 'alba-board' ), __( 'Duplicate board', 'alba-board' ), 'edit_boards', 'alba-board-duplicate', 'alba_board_duplicate_page' ); } );
function alba_board_duplicate_page() {
    if ( ! alba_board_can_create_from_template() ) { echo '<div class="wrap"><h1>' . esc_html__( 'Duplicate board', 'alba-board' ) . '</h1><p>' . esc_html__( 'You need permission to create boards, lists and tasks.', 'alba-board' ) . '</p></div>'; return; }
    echo '<div class="wrap"><h1>' . esc_html__( 'Duplicate board', 'alba-board' ) . '</h1><p>' . esc_html__( 'Make a clean one-off copy. Only published lists, task titles and cleaned descriptions are included. People, dates, comments, tags, files, images and sharing settings are not copied.', 'alba-board' ) . '</p><form method="get"><input type="hidden" name="page" value="alba-board-duplicate"><label for="alba-copy-source">' . esc_html__( 'Source board', 'alba-board' ) . '</label> <select name="source_board" id="alba-copy-source">';
    $source = isset( $_GET['source_board'] ) ? absint( $_GET['source_board'] ) : 0;
    foreach ( get_posts( [ 'post_type' => 'alba_board', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] ) as $board ) if ( alba_board_duplicate_source_allowed( $board->ID ) ) echo '<option value="' . esc_attr( $board->ID ) . '"' . selected( $source, $board->ID, false ) . '>' . esc_html( $board->post_title ) . '</option>';
    echo '</select> <button class="button" type="submit">' . esc_html__( 'Preview clean copy', 'alba-board' ) . '</button></form>';
    if ( $source ) {
        $preview = alba_board_duplicate_preview( $source );
        if ( is_wp_error( $preview ) ) echo '<div class="notice notice-error"><p>' . esc_html( $preview->get_error_message() ) . '</p></div>';
        else {
            echo '<h2>' . esc_html__( 'What will be copied', 'alba-board' ) . '</h2>';
            foreach ( $preview['structure']['lists'] as $list ) {
                echo '<section style="background:#fff;border:1px solid #dcdcde;padding:16px;margin:16px 0;max-width:850px"><h3>' . esc_html( $list['title'] ) . '</h3>';
                if ( ! $list['cards'] ) echo '<p>' . esc_html__( 'Empty list — included in the copy.', 'alba-board' ) . '</p>';
                foreach ( $list['cards'] as $card ) echo '<article><h4>' . esc_html( $card['title'] ) . '</h4><p style="white-space:pre-wrap;overflow-wrap:anywhere">' . esc_html( $card['description'] ) . '</p></article>';
                echo '</section>';
            }
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';wp_nonce_field( 'alba_duplicate_board_' . $source );
            foreach ( [ 'action' => 'alba_duplicate_clean_board', 'source_board' => $source, 'source_fingerprint' => $preview['fingerprint'], 'request_id' => wp_generate_uuid4() ] as $key => $value ) echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
            echo '<p><label for="alba-copy-name">' . esc_html__( 'New board name', 'alba-board' ) . '</label><br><input id="alba-copy-name" type="text" name="board_title" maxlength="200" required value="' . esc_attr( get_the_title( $source ) . ' — ' . __( 'copy', 'alba-board' ) ) . '" style="width:100%;max-width:500px"></p><p><label><input type="checkbox" name="confirmed" value="1" required> ' . esc_html__( 'Create this clean copy with new IDs.', 'alba-board' ) . '</label></p>';submit_button( __( 'Create clean copy', 'alba-board' ) );echo '</form>';
        }
    }
    echo '</div>';
}
add_action( 'admin_post_alba_duplicate_clean_board', function() {
    $id = isset( $_POST['source_board'] ) ? absint( $_POST['source_board'] ) : 0;check_admin_referer( 'alba_duplicate_board_' . $id );
    if ( '1' !== ( $_POST['confirmed'] ?? '' ) ) wp_die( esc_html__( 'Confirm the clean copy first.', 'alba-board' ), '', [ 'response' => 400 ] );
    $get = function( $key ) { return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; };
    $result = alba_board_duplicate_clean( $id, $get( 'source_fingerprint' ), $get( 'board_title' ), $get( 'request_id' ) );
    if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ), '', [ 'response' => 400, 'back_link' => true ] );
    wp_safe_redirect( $result['url'] );exit;
} );
