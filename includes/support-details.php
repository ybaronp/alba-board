<?php
/** Optional local support summary. No automatic diagnostics or transmission. */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function() {
    $hook = add_submenu_page( 'alba-board-visual', __( 'Alba Board Help', 'alba-board' ), __( 'Help', 'alba-board' ), 'manage_options', 'alba-board-help', 'alba_board_render_support_details' );
    add_action( 'load-' . $hook, function() {
        add_action( 'admin_enqueue_scripts', 'alba_board_enqueue_support_details' );
    } );
} );

function alba_board_enqueue_support_details() {
    $root = plugin_dir_path( dirname( __FILE__ ) );
    wp_enqueue_script( 'alba-support-details', plugins_url( 'assets/js/alba-support-details.js', dirname( __DIR__ ) . '/alba-board.php' ), [], (string) filemtime( $root . 'assets/js/alba-support-details.js' ), true );
    wp_enqueue_style( 'alba-support-details', plugins_url( 'assets/css/alba-support-details.css', dirname( __DIR__ ) . '/alba-board.php' ), [], (string) filemtime( $root . 'assets/css/alba-support-details.css' ) );
}

function alba_board_support_version( $version ) {
    return is_string( $version ) && preg_match( '/^\d+(?:\.\d+){1,3}(?:[-+.][A-Za-z0-9.-]+)?$/D', $version ) ? $version : __( 'Unknown', 'alba-board' );
}

function alba_board_support_summary() {
    $plugin = get_file_data( dirname( __DIR__ ) . '/alba-board.php', [ 'Version' => 'Version' ] );
    return 'Alba Board: ' . alba_board_support_version( $plugin['Version'] ) . "\nWordPress: " . alba_board_support_version( get_bloginfo( 'version' ) ) . "\nPHP: " . alba_board_support_version( PHP_VERSION );
}

function alba_board_render_support_details() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Permission denied.', 'alba-board' ), '', [ 'response' => 403 ] );
    echo '<div class="wrap alba-support-wrap"><h1>' . esc_html__( 'Alba Board Help', 'alba-board' ) . '</h1><div class="alba-support-card">';
    echo '<p class="alba-support-kicker">' . esc_html__( 'Need a hand?', 'alba-board' ) . '</p><h2>' . esc_html__( 'Copy support details', 'alba-board' ) . '</h2><p>' . esc_html__( 'Choose what happened, review these details, then copy them into your support request.', 'alba-board' ) . '</p>';
    echo '<label for="alba-support-issue">' . esc_html__( 'What happened?', 'alba-board' ) . '</label><select id="alba-support-issue">';
    foreach ( [ 'NOT_SELECTED' => __( 'Choose a problem', 'alba-board' ), 'BOARD_LOAD_FAILED' => __( 'Board not loading', 'alba-board' ), 'CARD_SAVE_FAILED' => __( 'Changes not saving', 'alba-board' ), 'CONNECTION_LOST' => __( 'Connection lost', 'alba-board' ), 'OTHER' => __( 'Something else', 'alba-board' ) ] as $code => $label ) echo '<option value="' . esc_attr( $code ) . '">' . esc_html( $label ) . '</option>';
    echo '</select><label for="alba-support-preview">' . esc_html__( 'Preview', 'alba-board' ) . '</label><textarea id="alba-support-preview" readonly rows="6">' . esc_textarea( alba_board_support_summary() . "\nIssue: NOT_SELECTED" ) . '</textarea>';
    echo '<p class="alba-support-private">' . esc_html__( 'Only versions and your selected problem code are included. No board content, accounts, site address or plugin list. Nothing is sent automatically.', 'alba-board' ) . '</p>';
    echo '<button type="button" class="button button-primary" id="alba-support-copy">' . esc_html__( 'Copy details', 'alba-board' ) . '</button><span id="alba-support-status" role="status" aria-live="polite" data-copied="' . esc_attr__( 'Copied', 'alba-board' ) . '" data-manual="' . esc_attr__( 'Select and copy the text.', 'alba-board' ) . '"></span></div></div>';
}
