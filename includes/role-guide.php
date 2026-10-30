<?php
/** A one-time guide for the creator of their first board. No role changes. */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'save_post_alba_board', function( $post_id, $post ) {
    $user_id = get_current_user_id();
    if ( 'publish' !== $post->post_status || ! $user_id || (int) $post->post_author !== $user_id || ! current_user_can( 'edit_board', $post_id ) || get_user_meta( $user_id, '_alba_role_guide_offered', true ) ) return;
    $boards = get_posts( [ 'post_type' => 'alba_board', 'post_status' => array_keys( get_post_stati() ), 'author' => $user_id, 'posts_per_page' => 2, 'fields' => 'ids', 'no_found_rows' => true ] );
    // Existing creators should never receive a new first-board popup after updating.
    if ( add_user_meta( $user_id, '_alba_role_guide_offered', 1, true ) && count( $boards ) === 1 ) update_user_meta( $user_id, '_alba_role_guide_pending', $post_id );
}, 10, 2 );

function alba_board_role_guide_pending() {
    if ( ! isset( $_GET['page'], $_GET['board_id'] ) || 'alba-board-visual' !== $_GET['page'] || ! is_scalar( $_GET['board_id'] ) ) return false;
    $board_id = absint( $_GET['board_id'] );
    return $board_id && $board_id === (int) get_user_meta( get_current_user_id(), '_alba_role_guide_pending', true ) && 'alba_board' === get_post_type( $board_id ) && current_user_can( 'edit_board', $board_id );
}

add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( 'toplevel_page_alba-board-visual' !== $hook || ! alba_board_role_guide_pending() ) return;
    $root = plugin_dir_path( dirname( __FILE__ ) );
    wp_enqueue_style( 'alba-role-guide', plugins_url( 'assets/css/alba-role-guide.css', dirname( __DIR__ ) . '/alba-board.php' ), [], (string) filemtime( $root . 'assets/css/alba-role-guide.css' ) );
    wp_enqueue_script( 'alba-role-guide', plugins_url( 'assets/js/alba-role-guide.js', dirname( __DIR__ ) . '/alba-board.php' ), [], (string) filemtime( $root . 'assets/js/alba-role-guide.js' ), true );
} );

add_action( 'admin_footer-toplevel_page_alba-board-visual', function() {
    if ( ! alba_board_role_guide_pending() ) return;
    $display = get_option( 'alba_board_display', [] );
    $theme = isset( $display['theme'] ) && is_string( $display['theme'] ) ? sanitize_html_class( $display['theme'] ) : 'default';
    echo '<dialog class="alba-theme-' . esc_attr( $theme ) . '" id="alba-role-guide" aria-labelledby="alba-role-guide-title" data-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'alba_dismiss_role_guide' ) ) . '">';
    echo '<button type="button" class="alba-role-guide-close" aria-label="' . esc_attr__( 'Close', 'alba-board' ) . '">×</button>';
    echo '<p class="alba-role-guide-kicker">' . esc_html__( 'Your first board', 'alba-board' ) . '</p><h2 id="alba-role-guide-title">' . esc_html__( 'Choose the right role for your team', 'alba-board' ) . '</h2>';
    echo '<div class="alba-role-guide-options"><section><h3>' . esc_html__( 'Board Member', 'alba-board' ) . '</h3><p>' . esc_html__( 'For helpers: create cards, update their own cards and join the conversation.', 'alba-board' ) . '</p></section>';
    echo '<section><h3>' . esc_html__( 'Board Manager', 'alba-board' ) . '</h3><p>' . esc_html__( 'For coordinators: manage boards, lists and other people’s cards. This role does not grant WordPress site settings.', 'alba-board' ) . '</p></section></div>';
    echo '<p>' . esc_html__( 'A guest email assignment is not a login or an invitation. Roles apply across Alba Board; they do not isolate one board. Other WordPress roles can add permissions.', 'alba-board' ) . '</p>';
    if ( current_user_can( 'create_users' ) ) echo '<p><a href="' . esc_url( admin_url( 'user-new.php' ) ) . '">' . esc_html__( 'Add a teammate in WordPress Users', 'alba-board' ) . '</a></p>';
    echo '<button type="button" class="button button-primary alba-role-guide-done">' . esc_html__( 'Got it', 'alba-board' ) . '</button></dialog>';
} );

add_action( 'wp_ajax_alba_dismiss_role_guide', function() {
    check_ajax_referer( 'alba_dismiss_role_guide', 'nonce' );
    if ( ! current_user_can( 'edit_boards' ) ) wp_send_json_error( [ 'message' => __( 'Permission denied.', 'alba-board' ) ], 403 );
    delete_user_meta( get_current_user_id(), '_alba_role_guide_pending' );
    wp_send_json_success();
} );
