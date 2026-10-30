<?php
/** Safe Markdown and card-scoped, authenticated images. */
if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/vendor/parsedown/Parsedown.php';

class Alba_Board_Markdown extends \AlbaBoard\Vendor\Parsedown {
    private $card_id;
    public function __construct( $card_id ) {
        $this->card_id = absint( $card_id );
        $this->setSafeMode( true )->setMarkupEscaped( true )->setBreaksEnabled( true )->setUrlsLinked( false );
        $this->InlineTypes['@'] = [ 'Mention' ];
        $this->inlineMarkerList .= '@';
    }
    protected function inlineMention( $excerpt ) {
        $offset = strlen( $excerpt['context'] ) - strlen( $excerpt['text'] );
        if ( $offset > 0 && preg_match( '/[\w@]$/u', substr( $excerpt['context'], 0, $offset ) ) ) return;
        if ( ! preg_match( '/^@(?:\{([^}\r\n]+)\}|([A-Za-z0-9_.-]+))(?![\w@])/u', $excerpt['text'], $match ) ) return;
        $user = get_user_by( 'login', ! empty( $match[1] ) ? $match[1] : ( $match[2] ?? '' ) );
        if ( ! $user || ! alba_board_eligible_card_person( $this->card_id, $user->ID ) ) return;
        return [ 'extent' => strlen( $match[0] ), 'element' => [ 'name' => 'strong', 'text' => $user->display_name, 'attributes' => [ 'class' => 'alba-comment-mention' ] ] ];
    }
    protected function inlineImage( $excerpt ) {
        $image = parent::inlineImage( $excerpt );
        if ( ! $image ) return;
        $attributes = $image['element']['attributes'];
        $src = $attributes['src'];
        if ( preg_match( '/^alba-image:([1-9][0-9]*)$/D', $src, $match ) && alba_board_inline_image_allowed( (int) $match[1], $this->card_id ) ) {
            $image['element']['attributes'] = [
                'src' => add_query_arg( [ 'action' => 'alba_card_image', 'image_id' => (int) $match[1] ], admin_url( 'admin-ajax.php' ) ),
                'alt' => $attributes['alt'], 'loading' => 'lazy', 'referrerpolicy' => 'no-referrer',
            ];
        } else {
            // Remote images could track readers. Unknown/cross-card IDs must not reveal file details.
            $image['element'] = [ 'name' => 'span', 'text' => __( '[Image unavailable]', 'alba-board' ) ];
        }
        return $image;
    }
}

function alba_board_render_markdown( $text, $card_id ) {
    // Bound parser work on exceptionally large legacy fields without losing their visible text.
    if ( strlen( (string) $text ) > 100000 ) return '<div class="alba-markdown">' . nl2br( esc_html( $text ) ) . '</div>';
    $parser = new Alba_Board_Markdown( $card_id );
    $html = $parser->text( (string) $text );
    $allowed = [
        'p' => [], 'br' => [], 'hr' => [], 'strong' => [ 'class' => true ], 'em' => [], 'del' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'ul' => [], 'ol' => [ 'start' => true ], 'li' => [], 'blockquote' => [], 'pre' => [], 'code' => [ 'class' => true ],
        'a' => [ 'href' => true, 'title' => true ],
        'img' => [ 'src' => true, 'alt' => true, 'loading' => true, 'referrerpolicy' => true ],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
    ];
    return '<div class="alba-markdown">' . wp_kses( $html, $allowed, [ 'http', 'https', 'mailto' ] ) . '</div>';
}

add_action( 'init', function() {
    register_post_type( 'alba_card_image', [
        'public' => false, 'show_ui' => false, 'show_in_rest' => false, 'rewrite' => false, 'query_var' => false,
        'can_export' => false, 'supports' => [], 'map_meta_cap' => true,
        'capability_type' => [ 'alba_card_image', 'alba_card_images' ], 'capabilities' => [ 'create_posts' => 'do_not_allow' ],
    ] );
} );

function alba_board_inline_image_allowed( $image_id, $card_id ) {
    $image = get_post( $image_id );
    return $image && 'alba_card_image' === $image->post_type && 'private' === $image->post_status
        && (int) $image->post_parent === (int) $card_id && 'alba_card' === get_post_type( $card_id )
        && 'publish' === get_post_status( $card_id ) && alba_board_can_view_shared_card( $card_id );
}

/** Private bytes stay in the database, not in a web-accessible uploads directory. */
function alba_board_serve_card_image() {
    $id = isset( $_GET['image_id'] ) ? absint( $_GET['image_id'] ) : 0;
    $image = get_post( $id );
    if ( ! $image || ! alba_board_inline_image_allowed( $id, $image->post_parent ) ) {
        status_header( 404 ); nocache_headers(); exit;
    }
    $bytes = base64_decode( $image->post_content, true );
    if ( false === $bytes || ! in_array( $image->post_mime_type, [ 'image/png', 'image/jpeg', 'image/webp' ], true ) ) {
        status_header( 404 ); nocache_headers(); exit;
    }
    nocache_headers();
    header( 'Cache-Control: private, no-store, max-age=0' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Cross-Origin-Resource-Policy: same-origin' );
    header( 'Content-Type: ' . $image->post_mime_type );
    header( 'Content-Disposition: inline' );
    header( 'Content-Length: ' . strlen( $bytes ) );
    echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated, re-encoded image bytes.
    exit;
}
add_action( 'wp_ajax_alba_card_image', 'alba_board_serve_card_image' );
add_action( 'wp_ajax_nopriv_alba_card_image', 'alba_board_serve_card_image' );

function alba_board_upload_card_image() {
    check_ajax_referer( 'alba_card_rich_text', 'nonce' );
    $card_id = isset( $_POST['card_id'] ) ? absint( $_POST['card_id'] ) : 0;
    $target = isset( $_POST['target'] ) && is_string( $_POST['target'] ) ? sanitize_key( $_POST['target'] ) : '';
    if ( ! in_array( $target, [ 'description', 'comment' ], true ) || 'alba_card' !== get_post_type( $card_id )
        || 'publish' !== get_post_status( $card_id ) || ! alba_board_can_view_shared_card( $card_id )
        || ( 'description' === $target ? ! current_user_can( 'edit_card', $card_id ) : ! alba_board_can_comment_card( $card_id ) ) ) {
        wp_send_json_error( [ 'message' => __( 'You cannot add images to this card.', 'alba-board' ) ], 403 );
    }
    $file = $_FILES['image'] ?? [];
    if ( ! is_array( $file ) || empty( $file['tmp_name'] ) || ! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) || ! isset( $file['error'] ) || UPLOAD_ERR_OK !== $file['error']
        || filesize( $file['tmp_name'] ) > 2 * MB_IN_BYTES ) {
        wp_send_json_error( [ 'message' => __( 'Choose a PNG, JPEG or WebP image up to 2 MB.', 'alba-board' ) ], 400 );
    }
    $size = wp_getimagesize( $file['tmp_name'] );
    if ( ! $size || ! in_array( $size['mime'], [ 'image/png', 'image/jpeg', 'image/webp' ], true ) || $size[0] < 1 || $size[1] < 1
        || $size[0] > 6000 || $size[1] > 6000 || $size[0] * $size[1] > 12000000 ) {
        wp_send_json_error( [ 'message' => __( 'Use a valid PNG, JPEG or WebP image, at most 6000 pixels per side and 12 megapixels.', 'alba-board' ) ], 400 );
    }
    // Re-encode images, stripping metadata and anything appended to an uploaded file.
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $editor = wp_get_image_editor( $file['tmp_name'] );
    if ( is_wp_error( $editor ) ) wp_send_json_error( [ 'message' => __( 'This server cannot process that image format.', 'alba-board' ) ], 400 );
    $resized = ( $size[0] > 2000 || $size[1] > 2000 ) ? $editor->resize( 2000, 2000, false ) : true;
    if ( is_wp_error( $resized ) ) wp_send_json_error( [ 'message' => __( 'Could not resize the image.', 'alba-board' ) ], 400 );
    $temporary = wp_tempnam( 'alba-image' );
    if ( ! $temporary ) wp_send_json_error( [ 'message' => __( 'Could not prepare the image.', 'alba-board' ) ], 500 );
    $saved = $editor->save( $temporary, $size['mime'] );
    if ( is_wp_error( $saved ) ) { @unlink( $temporary ); wp_send_json_error( [ 'message' => __( 'Could not process the image.', 'alba-board' ) ], 400 ); }
    $bytes = file_get_contents( $saved['path'] );
    @unlink( $saved['path'] ); @unlink( $temporary );
    if ( false === $bytes || strlen( $bytes ) > 2 * MB_IN_BYTES ) wp_send_json_error( [ 'message' => __( 'The processed image is too large.', 'alba-board' ) ], 400 );
    // A database lock prevents concurrent requests bypassing the per-card image limit.
    $lock = 'alba_image_lock_' . $card_id;
    if ( ! add_option( $lock, time(), '', false ) ) {
        if ( (int) get_option( $lock ) < time() - 120 ) delete_option( $lock );
        wp_send_json_error( [ 'message' => __( 'Another image is uploading. Please retry.', 'alba-board' ) ], 409 );
    }
    $result = null;
    try {
        if ( 'publish' !== get_post_status( $card_id ) || ! alba_board_can_view_shared_card( $card_id ) || ( 'description' === $target ? ! current_user_can( 'edit_card', $card_id ) : ! alba_board_can_comment_card( $card_id ) ) ) throw new RuntimeException( 'Card access changed during upload.' );
        $ids = get_posts( [ 'post_type' => 'alba_card_image', 'post_parent' => $card_id, 'post_status' => 'private', 'fields' => 'ids', 'numberposts' => 20 ] );
        if ( count( $ids ) >= 20 ) $result = new WP_Error( 'limit', __( 'This card has reached its 20-image limit.', 'alba-board' ) );
        else $result = wp_insert_post( [ 'post_type' => 'alba_card_image', 'post_status' => 'private', 'post_parent' => $card_id,
            'post_author' => get_current_user_id(), 'post_title' => sanitize_file_name( isset( $file['name'] ) && is_string( $file['name'] ) ? $file['name'] : 'image' ),
            'post_mime_type' => $saved['mime-type'], 'post_content' => base64_encode( $bytes ),
        ], true );
    } catch ( RuntimeException $error ) { $result = new WP_Error( 'access_changed', __( 'Card access changed. Refresh and try again.', 'alba-board' ) ); }
    finally { delete_option( $lock ); }
    if ( is_wp_error( $result ) || ! $result ) wp_send_json_error( [ 'message' => is_wp_error( $result ) ? $result->get_error_message() : __( 'Could not save the image.', 'alba-board' ) ], 400 );
    wp_send_json_success( [ 'id' => (int) $result, 'markdown' => '![Screenshot](alba-image:' . (int) $result . ')' ] );
}
add_action( 'wp_ajax_alba_upload_card_image', 'alba_board_upload_card_image' );

add_action( 'before_delete_post', function( $id, $post ) {
    if ( 'alba_card' !== $post->post_type ) return;
    foreach ( get_posts( [ 'post_type' => 'alba_card_image', 'post_parent' => $id, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] ) as $image_id ) wp_delete_post( $image_id, true );
}, 10, 2 );

function alba_board_rich_text_controls( $card_id, $target, $selector ) {
    echo '<div class="alba-rich-controls" data-card-id="' . esc_attr( $card_id ) . '" data-target="' . esc_attr( $target ) . '" data-selector="' . esc_attr( $selector ) . '">';
    echo '<button type="button" class="alba-rich-preview" aria-pressed="false">' . esc_html__( 'Preview', 'alba-board' ) . '</button> ';
    echo '<span class="alba-rich-feedback" role="status" aria-live="polite"></span>';
    echo '<div class="alba-rich-result" role="region" aria-label="' . esc_attr__( 'Markdown preview', 'alba-board' ) . '" hidden></div></div>';
}

add_action( 'wp_ajax_alba_preview_card_markdown', function() {
    check_ajax_referer( 'alba_card_rich_text', 'nonce' );
    $id = isset( $_POST['card_id'] ) ? absint( $_POST['card_id'] ) : 0;
    if ( ! alba_board_can_view_shared_card( $id ) ) wp_send_json_error( [], 403 );
    $text = isset( $_POST['text'] ) && is_string( $_POST['text'] ) ? wp_unslash( $_POST['text'] ) : '';
    if ( strlen( $text ) > 100000 ) wp_send_json_error( [ 'message' => __( 'The preview is limited to 100,000 bytes.', 'alba-board' ) ], 400 );
    wp_send_json_success( [ 'html' => alba_board_render_markdown( $text, $id ) ] );
} );

// Only explicitly unused uploads can be removed; never erase an image referenced in saved content.
add_action( 'wp_ajax_alba_cleanup_card_images', function() {
    check_ajax_referer( 'alba_card_rich_text', 'nonce' );
    $id = isset( $_POST['card_id'] ) ? absint( $_POST['card_id'] ) : 0;
    if ( ! alba_board_can_view_shared_card( $id ) || ! alba_board_can_comment_card( $id ) ) wp_send_json_error( [], 403 );
    $lock = 'alba_image_lock_' . $id;
    if ( ! add_option( $lock, time(), '', false ) ) wp_send_json_error( [ 'message' => __( 'An image is uploading. Please retry.', 'alba-board' ) ], 409 );
    try {
        $text = get_post_field( 'post_content', $id );
        foreach ( (array) get_post_meta( $id, 'alba_comments', true ) as $comment ) $text .= "\n" . ( $comment['text'] ?? '' );
        foreach ( get_posts( [ 'post_type' => 'alba_card_image', 'post_parent' => $id, 'post_status' => 'private', 'numberposts' => -1, 'fields' => 'ids' ] ) as $image_id ) {
            if ( ! current_user_can( 'edit_card', $id ) && (int) get_post_field( 'post_author', $image_id ) !== get_current_user_id() ) continue;
            if ( ! preg_match( '/alba-image:' . $image_id . '(?![0-9])/', $text ) ) wp_delete_post( $image_id, true );
        }
    } finally { delete_option( $lock ); }
    wp_send_json_success( [ 'message' => __( 'Unused images removed. Images in saved descriptions and comments were kept.', 'alba-board' ) ] );
} );

function alba_board_enqueue_rich_text() {
    $base = plugin_dir_url( dirname( __FILE__ ) ); $path = dirname( __DIR__ ) . '/assets/';
    wp_enqueue_style( 'alba-rich-text', $base . 'assets/css/alba-card-rich-text.css', [], (string) filemtime( $path . 'css/alba-card-rich-text.css' ) );
    wp_enqueue_script( 'alba-rich-text', $base . 'assets/js/alba-card-rich-text.js', [ 'alba-board-session' ], (string) filemtime( $path . 'js/alba-card-rich-text.js' ), true );
    wp_localize_script( 'alba-rich-text', 'albaRichText', [ 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'alba_card_rich_text' ),
        'preview' => __( 'Preview', 'alba-board' ), 'edit' => __( 'Edit text', 'alba-board' ),
        'working' => __( 'Working…', 'alba-board' ), 'error' => __( 'Could not complete the request. Please retry.', 'alba-board' ),
        'partial' => __( '%1$d image(s) pasted; %2$d not uploaded. %3$s', 'alba-board' ),
        'inserted' => __( 'Image pasted.', 'alba-board' ),
    ] );
}
add_action( 'admin_enqueue_scripts', function( $hook ) { if ( 'alba-board_page_alba-board-visual' === $hook || 'toplevel_page_alba-board-visual' === $hook ) alba_board_enqueue_rich_text(); } );
add_action( 'wp_enqueue_scripts', function() { if ( apply_filters( 'alba_board_should_enqueue_frontend_assets', alba_board_frontend_page_has_shortcode() ) ) alba_board_enqueue_rich_text(); }, 101 );
