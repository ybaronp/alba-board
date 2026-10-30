<?php
/** Card members and explicit comment mentions. */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Refresh cached member names after directory or role changes. */
function alba_board_collaboration_people_changed() {
    update_option( 'alba_board_people_revision', wp_generate_uuid4(), false );
}
foreach ( [ 'set_user_role', 'add_user_role', 'remove_user_role', 'deleted_user', 'profile_update' ] as $event ) {
    add_action( $event, 'alba_board_collaboration_people_changed' );
}

function alba_board_has_collaboration_role( $id ) {
    $user = get_userdata( $id );
    return $user && (bool) array_intersect( [ 'board_member', 'board_manager' ], $user->roles );
}

function alba_board_card_members( $card_id ) {
    $ids = get_post_meta( $card_id, 'alba_card_members', true );
    // Legacy cards start with their eligible assignee; saving makes membership explicit.
    if ( ! metadata_exists( 'post', $card_id, 'alba_card_members' ) ) {
        $ids = [ absint( get_post_field( 'post_author', $card_id ) ) ];
    }
    $ids = is_array( $ids ) ? array_unique( array_filter( array_map( 'absint', $ids ) ) ) : [];
    return array_values( array_filter( $ids, 'alba_board_has_collaboration_role' ) );
}

function alba_board_eligible_card_person( $card_id, $id ) {
    return alba_board_has_collaboration_role( $id ) && user_can( $id, 'edit_cards' ) && alba_board_can_view_shared_card( $card_id, $id );
}

function alba_board_validate_members( $card_id, $ids ) {
    if ( ! is_array( $ids ) || count( $ids ) > 50 ) {
        return new WP_Error( 'invalid_members', __( 'Select up to 50 card members.', 'alba-board' ), [ 'status' => 400 ] );
    }
    $members = [];
    foreach ( $ids as $id ) {
        if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || ! alba_board_eligible_card_person( $card_id, absint( $id ) ) ) {
            return new WP_Error( 'invalid_member', __( 'Each member must be a Board user who can access this card.', 'alba-board' ), [ 'status' => 400 ] );
        }
        $members[] = absint( $id );
    }
    return array_values( array_unique( $members ) );
}

function alba_board_update_card_members( $card_id, $members ) {
    $old = alba_board_card_members( $card_id );
    sort( $members ); sort( $old );
    if ( $members === $old && metadata_exists( 'post', $card_id, 'alba_card_members' ) ) return;
    update_post_meta( $card_id, 'alba_card_members', $members );
    do_action( 'alba_board_card_members_updated', $card_id, $members, $old );
    $options = get_option( 'alba_board_notifications', [] );
    if ( empty( $options['notify_on_card'] ) ) return;
    $post = get_post( $card_id );
    foreach ( array_diff( $members, $old ) as $id ) {
        if ( $id === get_current_user_id() || ! alba_board_eligible_card_person( $card_id, $id ) ) continue;
        $user = get_userdata( $id );
        /* translators: Card title. */
        $subject = sprintf( __( 'You were added to card: %s', 'alba-board' ), $post->post_title );
        alba_board_send_notification( $user->user_email, $subject, $user->display_name, $post->post_title, $post->post_content, alba_get_smart_card_url( $card_id ), 'new_card' );
    }
}

/** Only newly appended metadata comments produce mention notifications. */
function alba_board_notify_comment_mentions( $meta_id, $card_id, $key, $comments ) {
    if ( 'alba_comments' !== $key || 'alba_card' !== get_post_type( $card_id ) || ! is_array( $comments ) ) return;
    $old = $GLOBALS['alba_board_mentions_before'][ $card_id ] ?? [];
    unset( $GLOBALS['alba_board_mentions_before'][ $card_id ] );
    if ( ! alba_board_can_comment_card( $card_id ) && ! current_user_can( 'edit_card', $card_id ) ) return;
    // Updates/deletions of existing comments do not resend historical mentions.
    if ( count( $comments ) <= count( $old ) || array_slice( $comments, 0, count( $old ) ) !== $old ) return;
    $options = get_option( 'alba_board_notifications', [] );
    $notify_mentions = ! isset( $options['notify_on_mention'] ) || ! empty( $options['notify_on_mention'] );
    $notify_comments = ! isset( $options['notify_on_comment'] ) || ! empty( $options['notify_on_comment'] );
    $post = get_post( $card_id );
    foreach ( array_slice( $comments, count( $old ) ) as $comment ) {
        $text = isset( $comment['text'] ) && is_string( $comment['text'] ) ? $comment['text'] : '';
        $recipients = $notify_comments ? array_fill_keys( alba_board_card_members( $card_id ), false ) : [];
        if ( $notify_mentions ) {
            preg_match_all( '/(?<![\w@])@\{([^}\r\n]+)\}|(?<![\w@])@([A-Za-z0-9_.-]+)(?![\w@])/u', $text, $matches, PREG_SET_ORDER );
            $mentioned = [];
            foreach ( array_slice( $matches, 0, 100 ) as $match ) {
                $login = ! empty( $match[1] ) ? $match[1] : ( $match[2] ?? '' );
                $user = get_user_by( 'login', $login );
                if ( ! $user || count( $mentioned ) >= 20 || ! alba_board_eligible_card_person( $card_id, $user->ID ) ) continue;
                $mentioned[ $user->ID ] = true;
                $recipients[ $user->ID ] = true;
            }
        }
        foreach ( $recipients as $id => $is_mention ) {
            if ( (int) $id === get_current_user_id() || ! alba_board_eligible_card_person( $card_id, $id ) ) continue;
            $user = get_userdata( $id );
            /* translators: Card title. */
            $subject = sprintf( $is_mention ? __( 'You were mentioned on card: %s', 'alba-board' ) : __( 'New comment on card: %s', 'alba-board' ), $post->post_title );
            alba_board_send_notification( $user->user_email, $subject, $user->display_name, $post->post_title, $post->post_content, alba_get_smart_card_url( $card_id ), 'new_comment', $text );
        }
    }
}
// Capture the old value, then notify only after successful persistence.
add_action( 'update_post_meta', function( $meta_id, $card_id, $key, $value ) {
    if ( 'alba_comments' !== $key ) return;
    $old = get_post_meta( $card_id, $key, true );
    $GLOBALS['alba_board_mentions_before'][ $card_id ] = is_array( $old ) ? $old : [];
}, 10, 4 );
add_action( 'updated_post_meta', 'alba_board_notify_comment_mentions', 10, 4 );
add_action( 'added_post_meta', 'alba_board_notify_comment_mentions', 10, 4 );

/** Return plain text/name segments; never accept HTML from comment content. */
function alba_board_card_comment_segments( $text, $card_id ) {
    $text = (string) $text;
    preg_match_all( '/(?<![\w@])@\{([^}\r\n]+)\}|(?<![\w@])@([A-Za-z0-9_.-]+)(?![\w@])/u', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
    $parts = []; $offset = 0;
    foreach ( $matches as $match ) {
        $token = $match[0][0]; $at = $match[0][1];
        if ( $at > $offset ) $parts[] = [ 'text' => substr( $text, $offset, $at - $offset ), 'mention' => false ];
        $login = ! empty( $match[1][0] ) ? $match[1][0] : ( $match[2][0] ?? '' );
        $user = get_user_by( 'login', $login );
        $allowed = $user && alba_board_eligible_card_person( $card_id, $user->ID );
        $parts[] = [ 'text' => $allowed ? $user->display_name : $token, 'mention' => (bool) $allowed ];
        $offset = $at + strlen( $token );
    }
    if ( $offset < strlen( $text ) ) $parts[] = [ 'text' => substr( $text, $offset ), 'mention' => false ];
    return $parts;
}

function alba_board_format_card_comment( $text, $card_id ) {
    return alba_board_render_markdown( $text, $card_id );
}

function alba_board_render_card_members( $card_id ) {
    $can_manage = current_user_can( 'edit_card', $card_id );
    echo '<div class="alba-card-collaboration alba-form-group" data-card-id="' . esc_attr( $card_id ) . '">';
    echo ( $can_manage ? '<label for="alba-card-members">' : '<strong>' ) . esc_html__( 'Card members', 'alba-board' ) . ( $can_manage ? '</label>' : '</strong>' );
    echo '<div class="alba-card-member-list">';
    foreach ( alba_board_card_members( $card_id ) as $id ) {
        $user = get_userdata( $id );
        if ( ! $user ) continue;
        echo '<span class="alba-card-member" data-member-id="' . esc_attr( $id ) . '">' . esc_html( $user->display_name );
        if ( $can_manage ) echo '<input type="hidden" name="card_members[]" value="' . esc_attr( $id ) . '">';
        if ( $can_manage ) echo ' <button type="button" class="alba-remove-member" aria-label="' . esc_attr( sprintf( __( 'Remove %s', 'alba-board' ), $user->display_name ) ) . '">&times;</button>';
        echo '</span>';
    }
    echo '</div>';
    if ( $can_manage ) {
        echo '<input type="hidden" name="card_members_present" value="1">';
        echo '<input id="alba-card-members" class="alba-form-input-text" type="search" autocomplete="off" placeholder="' . esc_attr__( 'Search Board users…', 'alba-board' ) . '">';
        echo '<div class="alba-member-results" role="group" aria-label="' . esc_attr__( 'Matching Board users', 'alba-board' ) . '"></div>';
        echo '<button type="button" class="alba-save-members alba-btn-neumorphic">' . esc_html__( 'Save members', 'alba-board' ) . '</button>';
    }
    echo '<div class="alba-member-status" role="status"></div></div>';
}

add_action( 'rest_api_init', function() {
    register_rest_route( 'alba-board/v1', '/card/(?P<id>\d+)/people', [
        'methods' => WP_REST_Server::READABLE,
        'permission_callback' => function( $request ) { return alba_board_can_view_shared_card( absint( $request['id'] ) ); },
        'callback' => function( $request ) {
            $roles = [ 'board_member', 'board_manager' ];
            if ( ! $roles ) return [];
            $search = sanitize_text_field( (string) $request->get_param( 'search' ) );
            $users = get_users( [ 'role__in' => $roles, 'number' => 40, 'search' => $search ? '*' . $search . '*' : '', 'search_columns' => [ 'display_name', 'user_login' ], 'orderby' => 'display_name' ] );
            $out = [];
            foreach ( $users as $user ) {
                if ( alba_board_eligible_card_person( absint( $request['id'] ), $user->ID ) ) $out[] = [ 'id' => $user->ID, 'name' => $user->display_name, 'login' => $user->user_login ];
                if ( count( $out ) >= 20 ) break;
            }
            return $out;
        },
    ] );
    register_rest_route( 'alba-board/v1', '/card/(?P<id>\d+)/members', [
        'methods' => WP_REST_Server::EDITABLE,
        'permission_callback' => function( $request ) { return 'alba_card' === get_post_type( absint( $request['id'] ) ) && current_user_can( 'edit_card', absint( $request['id'] ) ); },
        'callback' => function( $request ) {
            $id = absint( $request['id'] );
            $members = alba_board_validate_members( $id, $request->get_param( 'members' ) );
            if ( is_wp_error( $members ) ) return $members;
            alba_board_update_card_members( $id, $members );
            return [ 'members' => $members ];
        },
    ] );
} );

function alba_board_enqueue_collaboration() {
    if ( ! wp_script_is( 'alba-core-interactions', 'enqueued' ) ) return;
    $base = plugin_dir_path( dirname( __FILE__ ) ); $url = plugin_dir_url( dirname( __FILE__ ) );
    wp_enqueue_script( 'alba-card-collaboration', $url . 'assets/js/alba-card-collaboration.js', [ 'alba-core-interactions' ], (string) filemtime( $base . 'assets/js/alba-card-collaboration.js' ), true );
    wp_enqueue_style( 'alba-card-collaboration', $url . 'assets/css/alba-card-collaboration.css', [], (string) filemtime( $base . 'assets/css/alba-card-collaboration.css' ) );
    wp_localize_script( 'alba-card-collaboration', 'albaCollaboration', [ 'url' => rest_url( 'alba-board/v1/card/' ), 'nonce' => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '', 'saved' => __( 'Members saved.', 'alba-board' ), 'error' => __( 'Could not update members. Please try again.', 'alba-board' ), 'hint' => __( 'Write a comment… Type @ to mention a Board Member or Board Manager.', 'alba-board' ) ] );
}
add_action( 'wp_enqueue_scripts', 'alba_board_enqueue_collaboration', 110 );
add_action( 'admin_enqueue_scripts', 'alba_board_enqueue_collaboration', 110 );
