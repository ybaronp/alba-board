<?php
/**
 * One-time upload links for AI (MCP) clients.
 *
 * Tool calls carry text, so a client first asks for a short-lived upload link,
 * sends the file there as multipart/form-data, and then references the upload.
 * Each link is bound to one user, AI connection, card and purpose, and works once.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

const ALBA_BOARD_MCP_UPLOAD_TTL = 600;

function alba_board_mcp_upload_key( $id ) {
    return 'alba_mcp_upload_' . $id;
}

/** The connection that is acting now: an OAuth grant ID, or '' for cookie/Application Password callers. */
function alba_board_mcp_upload_connection() {
    return (string) ( $GLOBALS['alba_board_oauth_context']['id'] ?? '' );
}

/** Same permissions as the browser: comment images need comment access; attachments need card edit access and enabled uploads. */
function alba_board_mcp_upload_allowed( $card_id, $purpose ) {
    if ( 'comment-image' === $purpose ) return alba_board_mcp_permission( 'add-comment', [ 'card_id' => $card_id ] );
    if ( 'attachment' !== $purpose ) return false;
    list( $max_files ) = alba_board_upload_rules();
    return $max_files > 0 && alba_board_mcp_permission( 'update-card', [ 'card_id' => $card_id ] );
}

/** Tell the AI how to recognize and report an upload blocked by a host firewall or bot protection. */
function alba_board_mcp_blocked_upload_help() {
    return __( 'A successful upload returns JSON with status "uploaded" (HTTP 201). If you get an HTML page instead (for example "request is being verified"), the site’s security filter blocked the upload. Post the comment without the image and ask the user to allow /wp-json/alba-board/v1/mcp-upload/ (or the uploading computer’s IP) in their host firewall.', 'alba-board' );
}

/** Create a pending upload link. Called from the create-upload ability after its permission check. */
function alba_board_mcp_create_upload( $input ) {
    $purpose = $input['purpose'];
    $card_id = (int) $input['card_id'];
    if ( ! alba_board_mcp_upload_allowed( $card_id, $purpose ) ) return alba_board_mcp_error( 'alba_mcp_forbidden', __( 'AI access to this action is not allowed.', 'alba-board' ), 403 );
    list( $max_files, $max_size_mb, $formats ) = alba_board_upload_rules();
    if ( 'attachment' === $purpose ) {
        $extension = strtolower( pathinfo( sanitize_file_name( $input['filename'] ), PATHINFO_EXTENSION ) );
        if ( ! in_array( $extension, $formats, true ) ) {
            /* translators: %s: comma-separated list of allowed file extensions. */
            return alba_board_mcp_error( 'alba_mcp_upload_format', sprintf( __( 'This file type is not allowed. Allowed types: %s', 'alba-board' ), implode( ', ', $formats ) ) );
        }
        if ( count( get_post_meta( $card_id, 'alba_card_attachments' ) ) >= $max_files ) {
            /* translators: %d: maximum number of files. */
            return alba_board_mcp_error( 'alba_mcp_upload_limit', sprintf( __( 'This card already has the maximum of %d files.', 'alba-board' ), $max_files ) );
        }
    }
    $id = bin2hex( random_bytes( 16 ) );
    $secret = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
    $ticket = [
        'user_id' => get_current_user_id(), 'connection' => alba_board_mcp_upload_connection(), 'card_id' => $card_id,
        'purpose' => $purpose, 'filename' => sanitize_file_name( $input['filename'] ), 'secret_hash' => hash( 'sha256', $secret ),
        'status' => 'pending', 'object_id' => 0, 'expires' => time() + ALBA_BOARD_MCP_UPLOAD_TTL,
    ];
    if ( ! set_transient( alba_board_mcp_upload_key( $id ), $ticket, ALBA_BOARD_MCP_UPLOAD_TTL ) ) return alba_board_mcp_error( 'alba_mcp_save_failed', __( 'Could not prepare the upload. Please retry.', 'alba-board' ), 500 );
    $max_bytes = 'comment-image' === $purpose ? 2 * MB_IN_BYTES : $max_size_mb * MB_IN_BYTES;
    $item = alba_board_mcp_item( get_post( $card_id ) );
    return [
        'id' => $card_id, 'board_id' => $item['board_id'], 'upload_id' => $id, 'purpose' => $purpose,
        'upload_url' => rest_url( 'alba-board/v1/mcp-upload/' . $id . '.' . $secret ),
        'method' => 'POST', 'form_field' => 'file', 'max_bytes' => $max_bytes, 'expires_in' => ALBA_BOARD_MCP_UPLOAD_TTL,
        'blocked_upload_help' => alba_board_mcp_blocked_upload_help(),
        'next_step' => 'comment-image' === $purpose
            ? __( 'POST the image as multipart/form-data field "file" to upload_url, then call add-comment with this upload_id in image_upload_ids.', 'alba-board' )
            : __( 'POST the file as multipart/form-data field "file" to upload_url. The file is attached to the card when the upload succeeds.', 'alba-board' ),
    ];
}

/** Load a ticket and run the request as the user and AI connection that created it, with their current access. */
function alba_board_mcp_upload_act_as( $ticket ) {
    wp_set_current_user( 0 );
    unset( $GLOBALS['alba_board_oauth_context'] );
    if ( '' !== $ticket['connection'] ) {
        $grant = get_option( 'alba_oauth_grant_' . $ticket['connection'] );
        // A disconnected or changed connection cannot finish an upload it started.
        if ( ! alba_board_oauth_grant_live( $grant ) || (int) $grant['user_id'] !== (int) $ticket['user_id'] ) return false;
        $GLOBALS['alba_board_oauth_context'] = [ 'id' => $ticket['connection'], 'grant' => $grant ];
    } elseif ( ! get_userdata( $ticket['user_id'] ) ) {
        return false;
    }
    wp_set_current_user( $ticket['user_id'] );
    return alba_board_mcp_upload_allowed( $ticket['card_id'], $ticket['purpose'] );
}

function alba_board_mcp_upload_error( $code, $message, $status ) {
    return new WP_Error( $code, $message, [ 'status' => $status ] );
}

/** REST callback: receive the file for one pending upload link. */
function alba_board_mcp_receive_upload( $request ) {
    $id = $request['id'];
    $key = alba_board_mcp_upload_key( $id );
    if ( ! alba_board_mcp_lock( $key . '_lock' ) ) return alba_board_mcp_upload_error( 'alba_mcp_upload_busy', __( 'This upload is already in progress.', 'alba-board' ), 409 );
    try {
        $ticket = get_transient( $key );
        if ( ! is_array( $ticket ) || $ticket['expires'] < time() || ! hash_equals( $ticket['secret_hash'], hash( 'sha256', (string) $request['secret'] ) ) ) {
            return alba_board_mcp_upload_error( 'alba_mcp_upload_invalid', __( 'This upload link is invalid or expired. Ask for a new one.', 'alba-board' ), 404 );
        }
        if ( 'pending' !== $ticket['status'] ) return alba_board_mcp_upload_error( 'alba_mcp_upload_used', __( 'This upload link was already used.', 'alba-board' ), 410 );
        if ( ! alba_board_mcp_upload_act_as( $ticket ) ) return alba_board_mcp_upload_error( 'alba_mcp_forbidden', __( 'AI access to this action is not allowed.', 'alba-board' ), 403 );
        $files = $request->get_file_params();
        $file = $files['file'] ?? null;
        if ( is_array( $file ) && isset( $file['error'] ) && in_array( $file['error'], [ UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ], true ) ) {
            return alba_board_mcp_upload_error( 'alba_mcp_upload_size', __( 'The file is larger than this server accepts.', 'alba-board' ), 413 );
        }
        if ( ! is_array( $file ) || ! isset( $file['error'] ) || UPLOAD_ERR_OK !== $file['error'] || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return alba_board_mcp_upload_error( 'alba_mcp_upload_missing', __( 'Send the file as multipart/form-data in the field "file".', 'alba-board' ), 400 );
        }
        $card_id = (int) $ticket['card_id'];
        if ( 'comment-image' === $ticket['purpose'] ) {
            $object_id = alba_board_store_card_image( $card_id, $file['tmp_name'], $ticket['filename'], function() use ( $ticket ) {
                return alba_board_mcp_upload_allowed( $ticket['card_id'], $ticket['purpose'] );
            } );
        } else {
            $object_id = alba_board_mcp_store_attachment( $card_id, $file, $ticket['filename'] );
        }
        if ( is_wp_error( $object_id ) ) {
            $data = $object_id->get_error_data();
            return alba_board_mcp_upload_error( $object_id->get_error_code(), $object_id->get_error_message(), (int) ( is_array( $data ) && isset( $data['status'] ) ? $data['status'] : 400 ) );
        }
        $ticket['status'] = 'uploaded';
        $ticket['object_id'] = (int) $object_id;
        // Keep the uploaded ticket until add-comment consumes it or the link expires.
        set_transient( $key, $ticket, max( 60, $ticket['expires'] - time() ) );
        $history = get_post_meta( $card_id, '_alba_mcp_activity', true );
        $history = is_array( $history ) ? $history : [];
        $history[] = [ 'user_id' => get_current_user_id(), 'action' => 'upload-' . $ticket['purpose'], 'time' => current_time( 'mysql', true ) ];
        update_post_meta( $card_id, '_alba_mcp_activity', array_slice( $history, -20 ) );
        $response = [ 'upload_id' => $id, 'status' => 'uploaded', 'purpose' => $ticket['purpose'], 'card_id' => $card_id ];
        if ( 'attachment' === $ticket['purpose'] ) $response['attachment'] = alba_board_mcp_attachment_item( (int) $object_id );
        return new WP_REST_Response( $response, 201 );
    } finally {
        delete_option( $key . '_lock' );
    }
}

/** Store a card attachment with the same Settings rules and WordPress file checks as the browser upload. */
function alba_board_mcp_store_attachment( $card_id, $file, $filename ) {
    list( $max_files, $max_size_mb, $formats ) = alba_board_upload_rules();
    $extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
    if ( $max_files <= 0 ) return new WP_Error( 'alba_upload_disabled', __( 'File uploads are disabled.', 'alba-board' ), [ 'status' => 403 ] );
    if ( ! in_array( $extension, $formats, true ) ) return new WP_Error( 'alba_upload_format', __( 'This file type is not allowed.', 'alba-board' ), [ 'status' => 400 ] );
    if ( (int) filesize( $file['tmp_name'] ) > $max_size_mb * MB_IN_BYTES ) {
        /* translators: %d: maximum file size in megabytes. */
        return new WP_Error( 'alba_upload_size', sprintf( __( 'File exceeds the maximum limit of %d MB.', 'alba-board' ), $max_size_mb ), [ 'status' => 413 ] );
    }
    // Serialize attachment uploads per card so concurrent requests cannot exceed the limit.
    $lock = 'alba_attachment_lock_' . $card_id;
    if ( ! alba_board_mcp_lock( $lock ) ) return new WP_Error( 'alba_upload_busy', __( 'Another file is uploading. Please retry.', 'alba-board' ), [ 'status' => 409 ] );
    try {
        if ( count( get_post_meta( $card_id, 'alba_card_attachments' ) ) >= $max_files ) {
            /* translators: %d: maximum number of files. */
            return new WP_Error( 'alba_upload_limit', sprintf( __( 'This card already has the maximum of %d files.', 'alba-board' ), $max_files ), [ 'status' => 400 ] );
        }
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        $mimes = function( $list ) use ( $formats ) {
            if ( in_array( 'csv', $formats, true ) ) { $list['csv'] = 'text/csv'; $list['txt'] = 'text/plain'; }
            if ( in_array( 'json', $formats, true ) ) $list['json'] = 'application/json';
            return $list;
        };
        add_filter( 'upload_mimes', $mimes, 99 );
        // WordPress moves the temporary file and verifies its real type against the allowed list.
        $attachment_id = media_handle_sideload( [ 'name' => $filename, 'tmp_name' => $file['tmp_name'], 'error' => 0, 'size' => (int) filesize( $file['tmp_name'] ) ], $card_id );
        remove_filter( 'upload_mimes', $mimes, 99 );
        if ( is_wp_error( $attachment_id ) ) return new WP_Error( 'alba_upload_failed', __( 'WordPress could not process this upload. Check that the file type is allowed and try again.', 'alba-board' ), [ 'status' => 400 ] );
        add_post_meta( $card_id, 'alba_card_attachments', $attachment_id );
        delete_transient( 'alba_card_live_admin_' . $card_id );
        delete_transient( 'alba_card_live_frontend_' . $card_id );
        return (int) $attachment_id;
    } finally {
        delete_option( $lock );
    }
}

function alba_board_mcp_attachment_item( $attachment_id ) {
    return [ 'id' => (int) $attachment_id, 'name' => get_the_title( $attachment_id ), 'url' => (string) wp_get_attachment_url( $attachment_id ), 'mime_type' => (string) get_post_mime_type( $attachment_id ) ];
}

/**
 * Turn uploaded comment-image tickets into Markdown image lines. Each ticket must belong to the
 * current user and connection, target this card, and be uploaded. Tickets are consumed on success.
 *
 * @return array{markdown:string[],keys:string[]}|WP_Error
 */
function alba_board_mcp_claim_comment_images( $card_id, $upload_ids ) {
    $lines = []; $keys = [];
    foreach ( array_values( array_unique( (array) $upload_ids ) ) as $id ) {
        if ( ! is_string( $id ) || ! preg_match( '/^[a-f0-9]{32}$/', $id ) ) return alba_board_mcp_error( 'alba_mcp_upload_invalid', __( 'An image upload ID is invalid.', 'alba-board' ) );
        $ticket = get_transient( alba_board_mcp_upload_key( $id ) );
        if ( ! is_array( $ticket ) || (int) $ticket['user_id'] !== get_current_user_id() || $ticket['connection'] !== alba_board_mcp_upload_connection()
            || (int) $ticket['card_id'] !== (int) $card_id || 'comment-image' !== $ticket['purpose'] ) {
            return alba_board_mcp_error( 'alba_mcp_upload_invalid', __( 'An image upload was not found for this card. Ask for a new upload link.', 'alba-board' ), 404 );
        }
        if ( 'uploaded' !== $ticket['status'] || ! alba_board_inline_image_allowed( $ticket['object_id'], $card_id ) ) {
            return alba_board_mcp_error( 'alba_mcp_upload_pending', __( 'An image has not been uploaded yet. Send the file to its upload link first.', 'alba-board' ), 409 );
        }
        $lines[] = '![' . str_replace( [ '[', ']' ], '', pathinfo( $ticket['filename'], PATHINFO_FILENAME ) ?: 'Screenshot' ) . '](alba-image:' . (int) $ticket['object_id'] . ')';
        $keys[] = alba_board_mcp_upload_key( $id );
    }
    return [ 'markdown' => $lines, 'keys' => $keys ];
}

add_action( 'rest_api_init', function() {
    register_rest_route( 'alba-board/v1', '/mcp-upload/(?P<id>[a-f0-9]{32})\.(?P<secret>[A-Za-z0-9_-]{43})', [
        'methods' => 'POST',
        // The one-time secret in the link is the credential; access is re-checked as the creating user.
        'permission_callback' => function() {
            if ( empty( alba_board_mcp_settings()['enabled'] ) ) return new WP_Error( 'alba_mcp_disabled', __( 'AI access is turned off.', 'alba-board' ), [ 'status' => 403 ] );
            return is_ssl() || 'local' === wp_get_environment_type() ? true : new WP_Error( 'alba_mcp_https_required', __( 'Use HTTPS to upload files.', 'alba-board' ), [ 'status' => 403 ] );
        },
        'callback' => 'alba_board_mcp_receive_upload',
    ] );
} );

// Upload links authenticate with their own secret; ignore cookies so a browser session cannot be borrowed.
add_filter( 'rest_authentication_errors', function( $result ) {
    return 0 === strpos( alba_board_oauth_route(), '/alba-board/v1/mcp-upload/' ) ? true : $result;
}, 5 );
