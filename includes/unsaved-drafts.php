<?php
if ( ! defined( 'ABSPATH' ) ) exit;
function alba_board_enqueue_draft_guard() {
    $base = plugin_dir_url( dirname( __FILE__ ) ); $path = dirname( __DIR__ ) . '/assets/';
    wp_enqueue_style( 'alba-unsaved-drafts', $base . 'assets/css/alba-unsaved-drafts.css', [], (string) filemtime( $path . 'css/alba-unsaved-drafts.css' ) );
    wp_enqueue_script( 'alba-unsaved-drafts', $base . 'assets/js/alba-unsaved-drafts.js', [], (string) filemtime( $path . 'js/alba-unsaved-drafts.js' ), true );
    wp_localize_script( 'alba-unsaved-drafts', 'albaUnsavedDrafts', [
        'title' => __( 'Keep your changes?', 'alba-board' ), 'message' => __( 'This card has unsaved text. Keep editing or discard the changes before leaving it.', 'alba-board' ),
        'keep' => __( 'Keep editing', 'alba-board' ), 'discard' => __( 'Discard changes', 'alba-board' ), 'wait' => __( 'Please wait for the current save or upload to finish.', 'alba-board' ),
    ] );
}
add_action( 'admin_enqueue_scripts', function( $hook ) { if ( 'toplevel_page_alba-board-visual' === $hook || 'alba-board_page_alba-board-visual' === $hook ) alba_board_enqueue_draft_guard(); } );
add_action( 'wp_enqueue_scripts', function() { if ( apply_filters( 'alba_board_should_enqueue_frontend_assets', alba_board_frontend_page_has_shortcode() ) ) alba_board_enqueue_draft_guard(); }, 102 );
