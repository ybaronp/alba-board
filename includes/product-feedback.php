<?php
/** Optional product feedback and explicitly consented aggregate usage reporting. */
if ( ! defined( 'ABSPATH' ) ) exit;

function alba_board_usage_allowed() {
    return (bool) get_option( 'alba_board_usage_consent', false ) && 2 === (int) get_option( 'alba_board_usage_consent_schema', 0 );
}

function alba_board_feedback_state() {
    $state = get_option( 'alba_board_feedback_state', [] );
    return is_array( $state ) ? $state : [];
}
function alba_board_feedback_start() {
    add_option( 'alba_board_feedback_started', time(), '', false );
    if ( alba_board_usage_allowed() && ! wp_next_scheduled( 'alba_board_usage_send' ) ) {
        wp_schedule_event( time() + WEEK_IN_SECONDS, 'alba_board_weekly', 'alba_board_usage_send' );
    }
}
add_action( 'admin_init', 'alba_board_feedback_start' );
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['alba_board_weekly'] = [ 'interval' => WEEK_IN_SECONDS, 'display' => __( 'Once a week', 'alba-board' ) ];
    return $schedules;
} );
function alba_board_usage_stop() { wp_clear_scheduled_hook( 'alba_board_usage_send' ); }
register_deactivation_hook( dirname( __DIR__ ) . '/alba-board.php', 'alba_board_usage_stop' );
/** Fresh installations use the welcome flow; upgrades receive the privacy announcement once. */
function alba_board_privacy_notice_on_activation() {
    if ( ! get_option( 'alba_board_feedback_started' ) && ! get_option( 'alba_board_welcome_dismissed' ) ) {
        add_option( 'alba_board_privacy_update_notice_230', 'new_install', '', false );
    }
}
register_activation_hook( dirname( __DIR__ ) . '/alba-board.php', 'alba_board_privacy_notice_on_activation' );
register_activation_hook( dirname( __DIR__ ) . '/alba-board.php', 'alba_board_feedback_start' );

function alba_board_usage_count( $feature ) {
    if ( ! alba_board_usage_allowed() ) return;
    $allowed = [ 'created_cards', 'moved_cards', 'comments', 'attachments', 'members', 'mentions', 'frontend', 'backend' ];
    if ( ! in_array( $feature, $allowed, true ) ) return;
    $activity = get_option( 'alba_board_usage_activity', [] );
    $activity = is_array( $activity ) ? $activity : [];
    $activity[$feature] = min( 100000, (int) ( $activity[$feature] ?? 0 ) + 1 );
    update_option( 'alba_board_usage_activity', $activity, false );
}
add_action( 'wp_insert_post', function( $id, $post, $update ) {
    if ( 'alba_card' !== $post->post_type || $update || 'publish' !== $post->post_status || ! is_user_logged_in() ) return;
    update_option( 'alba_board_feedback_used', true, false );
    alba_board_usage_count( 'created_cards' );
}, 20, 3 );
add_action( 'updated_post_meta', function( $meta_id, $id, $key, $value ) {
    if ( 'alba_card' !== get_post_type( $id ) ) return;
    if ( 'alba_list_parent' === $key ) alba_board_usage_count( 'moved_cards' );
}, 20, 4 );
/** Count actual membership changes, not the first explicit save of an unchanged selection. */
function alba_board_usage_members_changed( $card_id, $members, $previous ) {
    if ( array_diff( $members, $previous ) || array_diff( $previous, $members ) ) {
        alba_board_usage_count( 'members' );
    }
}
add_action( 'alba_board_card_members_updated', 'alba_board_usage_members_changed', 10, 3 );
// Count appended comments only; never store or send their text.
add_action( 'update_post_meta', function( $meta_id, $id, $key, $value ) {
    if ( 'alba_comments' !== $key || 'alba_card' !== get_post_type( $id ) || ! is_array( $value ) ) return;
    $old = get_post_meta( $id, $key, true );
    $old = is_array( $old ) ? $old : [];
    if ( count( $value ) > count( $old ) && array_slice( $value, 0, count( $old ) ) === $old ) {
        $GLOBALS['alba_board_usage_comments'][$id] = count( $value ) - count( $old );
    }
}, 20, 4 );
function alba_board_usage_comment_saved( $meta_id, $id, $key, $value ) {
    if ( 'alba_comments' !== $key || 'alba_card' !== get_post_type( $id ) || ! is_array( $value ) ) return;
    $count = $GLOBALS['alba_board_usage_comments'][$id] ?? ( 'added_post_meta' === current_filter() ? count( $value ) : 0 );
    unset( $GLOBALS['alba_board_usage_comments'][$id] );
    if ( $count > 0 ) {
        alba_board_usage_count( 'comments' );
        foreach ( array_slice( $value, -$count ) as $comment ) {
            if ( is_array( $comment ) && isset( $comment['text'] ) && is_string( $comment['text'] ) && preg_match( '/(?:^|\s)@(?:\{|[A-Za-z0-9_.-])/', $comment['text'] ) ) {
                alba_board_usage_count( 'mentions' ); break;
            }
        }
    }
}
add_action( 'added_post_meta', 'alba_board_usage_comment_saved', 20, 4 );
add_action( 'updated_post_meta', 'alba_board_usage_comment_saved', 20, 4 );

function alba_board_usage_range( $number ) {
    $number = max( 0, (int) $number );
    if ( 0 === $number ) return '0';
    if ( 1 === $number ) return '1';
    if ( $number <= 5 ) return '2-5';
    if ( $number <= 20 ) return '6-20';
    return '21+';
}
function alba_board_usage_payload() {
    global $wp_version;
    $identity = get_option( 'alba_board_usage_identity', [] );
    $activity = get_option( 'alba_board_usage_activity', [] );
    $metrics = [];
    foreach ( [ 'created_cards', 'moved_cards', 'comments', 'attachments', 'members', 'mentions', 'frontend', 'backend' ] as $key ) {
        $metrics[$key] = alba_board_usage_range( $activity[$key] ?? 0 );
    }
    foreach ( [ 'alba_board' => 'boards', 'alba_list' => 'lists', 'alba_card' => 'cards' ] as $type => $key ) {
        $counts = wp_count_posts( $type );
        $metrics[$key] = alba_board_usage_range( $counts->publish ?? 0 );
    }
    $users = count_users();
    $metrics['board_users'] = alba_board_usage_range( ( $users['avail_roles']['board_member'] ?? 0 ) + ( $users['avail_roles']['board_manager'] ?? 0 ) );
    $display = get_option( 'alba_board_display', [] );
    $theme = $display['theme'] ?? 'default';
    return [
        'schema' => 1, 'consent' => true,
        'installation_id' => $identity['id'] ?? '', 'delete_token' => $identity['token'] ?? '',
        'period' => gmdate( 'o-\WW' ), 'plugin' => '2.3.0',
        'wordpress' => $wp_version, 'php' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
        'theme' => in_array( $theme, alba_board_get_allowed_themes(), true ) ? $theme : 'default',
        'metrics' => $metrics,
    ];
}
/** Staging is an explicit server setting, restricted to non-production WordPress environments. */
function alba_board_product_is_staging() {
    return defined( 'ALBA_BOARD_REPORTING_ENVIRONMENT' ) && 'staging' === ALBA_BOARD_REPORTING_ENVIRONMENT
        && in_array( wp_get_environment_type(), [ 'local', 'development', 'staging' ], true );
}
function alba_board_product_service_url( $path = '' ) {
    $origin = alba_board_product_is_staging() ? 'https://wordpress-1474601-6713996.cloudwaysapps.com' : 'https://albaboard.com';
    return $origin . '/wp-json/alba/' . $path;
}
function alba_board_product_request( $path, $payload ) {
    // A fixed destination and neutral User-Agent avoid sending site URLs by default.
    return wp_remote_post( alba_board_product_service_url( 'v2/' . $path ), [
        // Optional reports can take longer than five seconds to acknowledge on the receiver.
        'timeout' => 20, 'redirection' => 0, 'limit_response_size' => 4096,
        'user-agent' => 'AlbaBoard/2.3.0',
        'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ], 'body' => wp_json_encode( $payload ),
    ] );
}
function alba_board_product_success( $response ) {
    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) return false;
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    return is_array( $data ) && true === ( $data['success'] ?? false );
}
function alba_board_usage_send() {
    if ( ! alba_board_usage_allowed() || ! get_option( 'alba_board_usage_identity' ) ) return;
    $response = alba_board_product_request( 'insights', alba_board_usage_payload() );
    if ( alba_board_product_success( $response ) ) {
        update_option( 'alba_board_usage_last_sent', time(), false );
    }
}
add_action( 'alba_board_usage_send', 'alba_board_usage_send' );

function alba_board_feedback_register_page() {
    add_submenu_page( 'alba-board-visual', __( 'Feedback & Privacy', 'alba-board' ), __( 'Feedback & Privacy', 'alba-board' ), 'manage_options', 'alba-board-feedback', 'alba_board_feedback_page' );
}
add_action( 'admin_menu', 'alba_board_feedback_register_page' );
function alba_board_feedback_form_start( $block = false ) {
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:' . ( $block ? 'block' : 'inline-block' ) . ';margin:0 8px 8px 0">';
    echo '<input type="hidden" name="action" value="alba_board_product_feedback">';
    wp_nonce_field( 'alba_board_product_feedback' );
}
function alba_board_feedback_button( $action, $label ) {
    alba_board_feedback_form_start();
    echo '<button class="button" name="choice" value="' . esc_attr( $action ) . '">' . esc_html( $label ) . '</button></form>';
}
function alba_board_feedback_dismiss( $choice ) {
    if ( ! in_array( $choice, [ 'review_later', 'review_dismiss', 'survey_later', 'survey_dismiss' ], true ) ) return false;
    $state = alba_board_feedback_state();
    if ( substr( $choice, -8 ) === '_dismiss' ) $state[strpos( $choice, 'review_' ) === 0 ? 'review_done' : 'survey_done'] = true;
    $state['next'] = time() + 14 * DAY_IN_SECONDS;
    update_option( 'alba_board_feedback_state', $state, false );
    return true;
}
function alba_board_feedback_notice_return_url() {
    $admin = wp_parse_url( admin_url( 'admin.php' ) );
    $fallback = add_query_arg( 'page', 'alba-board-visual', $admin['path'] );
    $url = wp_validate_redirect( wp_get_referer() ?: '', $fallback );
    $parts = wp_parse_url( $url );
    if ( ! is_array( $parts ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) return $fallback;
    if ( isset( $parts['scheme'] ) && ! in_array( $parts['scheme'], [ 'http', 'https' ], true ) ) return $fallback;
    if ( isset( $parts['host'] ) && strtolower( $parts['host'] ) !== strtolower( $admin['host'] ) ) return $fallback;
    if ( ( $parts['path'] ?? '' ) !== $admin['path'] ) return $fallback;
    wp_parse_str( $parts['query'] ?? '', $query );
    // Relative redirects preserve the browser's origin, including Local's alternate port.
    return in_array( $query['page'] ?? '', [ 'alba-board-visual', 'alba-board-settings' ], true )
        ? $parts['path'] . '?' . $parts['query'] : $fallback;
}
function alba_board_feedback_dismiss_ajax() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( null, 403 );
    check_ajax_referer( 'alba_board_product_feedback', '_wpnonce' );
    $choice = isset( $_POST['choice'] ) && is_string( $_POST['choice'] ) ? sanitize_key( $_POST['choice'] ) : '';
    if ( ! alba_board_feedback_dismiss( $choice ) ) wp_send_json_error( null, 400 );
    wp_send_json_success();
}
add_action( 'wp_ajax_alba_board_dismiss_feedback', 'alba_board_feedback_dismiss_ajax' );
function alba_board_feedback_due( $now = null ) {
    $now = $now ?? time(); $state = alba_board_feedback_state();
    $started = (int) get_option( 'alba_board_feedback_started', $now );
    if ( ! get_option( 'alba_board_feedback_used' ) || get_option( 'alba_board_show_welcome_notice' ) || $now < (int) ( $state['next'] ?? 0 ) ) return '';
    if ( empty( $state['review_done'] ) && $now - $started >= 2 * DAY_IN_SECONDS ) return 'review';
    if ( empty( $state['survey_done'] ) && $now - $started >= 7 * DAY_IN_SECONDS ) return 'survey';
    return '';
}
/** One announcement per installation, after an existing site updates to this release. */
function alba_board_privacy_update_notice() {
    if ( ! current_user_can( 'manage_options' ) || is_network_admin() || is_user_admin() || wp_doing_ajax() ) return;
    // Claim the announcement only when it is actually rendered, without changing sharing consent.
    if ( ! add_option( 'alba_board_privacy_update_notice_230', 'shown', '', false ) ) return;
    $GLOBALS['alba_board_privacy_notice_shown'] = true;
    echo '<div class="notice notice-info is-dismissible alba-privacy-update-notice"><p><strong>' . esc_html__( 'New in Alba Board: optional usage insights.', 'alba-board' ) . '</strong> ';
    esc_html_e( 'Choose whether to share basic statistics to help improve Alba Board. No card content or personal contact details are included.', 'alba-board' );
    echo ' <a href="' . esc_url( admin_url( 'admin.php?page=alba-board-feedback' ) ) . '">' . esc_html__( 'Review sharing preferences', 'alba-board' ) . '</a></p></div>';
}
add_action( 'admin_notices', 'alba_board_privacy_update_notice', 5 );

function alba_board_feedback_notice() {
    if ( ! current_user_can( 'manage_options' ) || ! empty( $GLOBALS['alba_board_privacy_notice_shown'] ) ) return;
    $page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
    if ( ! in_array( $page, [ 'alba-board-visual', 'alba-board-settings' ], true ) ) return;
    $kind = alba_board_feedback_due(); if ( ! $kind ) return;
    wp_enqueue_script( 'alba-product-feedback', plugins_url( 'assets/js/alba-product-feedback.js', dirname( __DIR__ ) . '/alba-board.php' ), [], (string) filemtime( dirname( __DIR__ ) . '/assets/js/alba-product-feedback.js' ), true );
    echo '<div class="notice notice-info alba-product-feedback-notice" data-ajax-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '"><p>';
    if ( 'review' === $kind ) {
        esc_html_e( 'Has Alba Board helped you? Share an honest review on WordPress.org.', 'alba-board' );
    } else {
        esc_html_e( 'What should we improve next? Share your feedback.', 'alba-board' );
    }
    echo '</p>';
    if ( 'review' === $kind ) alba_board_feedback_button( 'review_open', __( 'Leave a review', 'alba-board' ) );
    else echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=alba-board-feedback' ) ) . '">' . esc_html__( 'Share feedback', 'alba-board' ) . '</a> ';
    alba_board_feedback_button( $kind . '_later', __( 'Remind me later', 'alba-board' ) );
    alba_board_feedback_button( $kind . '_dismiss', __( 'Do not ask again', 'alba-board' ) );
    echo '</div>';
}

add_action( 'admin_notices', 'alba_board_feedback_notice' );

function alba_board_feedback_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    echo '<div class="wrap"><h1>' . esc_html__( 'Feedback & Privacy', 'alba-board' ) . '</h1>';
    if ( alba_board_product_is_staging() ) {
        echo '<div class="notice notice-warning"><p>' . esc_html__( 'Test mode: reports go to staging. Sharing still requires your consent.', 'alba-board' ) . '</p><details><summary>' . esc_html__( 'Test destination', 'alba-board' ) . '</summary><code>' . esc_html( alba_board_product_service_url() ) . '</code></details></div>';
    }
    $result = isset( $_GET['result'] ) && is_string( $_GET['result'] ) ? sanitize_key( $_GET['result'] ) : '';
    $messages = [ 'saved' => __( 'Your preference was saved.', 'alba-board' ), 'sent' => __( 'Thank you. Your feedback was sent.', 'alba-board' ), 'deleted' => __( 'Linked usage and lifecycle reports were deleted. Reporting is off.', 'alba-board' ), 'failed' => __( 'The service could not complete the request. Please try again later.', 'alba-board' ), 'invalid' => __( 'Please check your answers and confirm what you want to send.', 'alba-board' ) ];
    if ( isset( $messages[$result] ) ) echo '<div class="notice notice-info"><p>' . esc_html( $messages[$result] ) . '</p></div>';
    echo '<h2>' . esc_html__( 'Optional usage insights', 'alba-board' ) . '</h2><p>';
    esc_html_e( 'Off by default. Share a weekly usage summary and future activation/deactivation events to help improve Alba Board. No card content or personal contact details are included. You can stop reporting and delete linked reports.', 'alba-board' );
    echo '</p><details><summary>' . esc_html__( 'What is shared and how it is handled', 'alba-board' ) . '</summary><p>';
    esc_html_e( 'Off by default. With your permission, Alba Board reports when sharing is enabled or disabled and future plugin activations/deactivations, and sends a small weekly summary to the Alba Board reporting service: software versions, the selected Alba theme, approximate board/card/member counts, and feature activity ranges since opting in. No card content, names, emails, files, site URL, or unrelated plugin inventory is included. A random installation ID links summaries; this is pseudonymous, not anonymous.', 'alba-board' );
    echo '</p><p>';
    esc_html_e( 'Usage and activation/deactivation reports are retained for 90 days by the updated receiver. Turning reporting off sends a final reporting-disabled event and stops future sends. Lifecycle events include a random event ID, plugin version, and the server receipt date; no earlier activations are backfilled. Delivery is best effort, and missing reports do not prove deactivation. Network-wide lifecycle tracking is not supported. You can separately delete linked reports. Hosting services may retain network logs. Only enable this if you are authorized to share site usage statistics.', 'alba-board' );
    echo ' <a href="https://albaboard.com/elementor-page-220/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Privacy policy', 'alba-board' ) . '</a></p></details>';
    echo '<div class="alba-usage-preferences">';
    alba_board_feedback_form_start( true );
    echo '<input type="hidden" name="choice" value="consent"><label style="display:block;margin-bottom:12px"><input type="checkbox" name="enabled" value="1" ' . checked( alba_board_usage_allowed(), true, false ) . '> ' . esc_html__( 'Share basic usage statistics and activation/deactivation events to help improve Alba Board', 'alba-board' ) . '</label><button class="button">' . esc_html__( 'Save preference', 'alba-board' ) . '</button></form>';
    if ( get_option( 'alba_board_usage_identity' ) ) alba_board_feedback_button( 'delete_usage', __( 'Stop reporting and delete linked usage and lifecycle reports', 'alba-board' ) );
    echo '</div>';
    $preview = alba_board_usage_payload(); unset( $preview['installation_id'], $preview['delete_token'] );
    echo '<details><summary>' . esc_html__( 'Preview the summary fields (installation credentials hidden)', 'alba-board' ) . '</summary><pre style="white-space:pre-wrap">' . esc_html( wp_json_encode( $preview, JSON_PRETTY_PRINT ) ) . '</pre></details>';
    echo '<hr><h2>' . esc_html__( 'Help shape the next improvements', 'alba-board' ) . '</h2><p>';
    esc_html_e( 'Tell us what would help you. Feedback is optional; leave out passwords and confidential information.', 'alba-board' );
    echo '</p><details><summary>' . esc_html__( 'How your feedback is used', 'alba-board' ) . '</summary><p>';
    esc_html_e( 'Tell us the problem you want to solve. We use responses to prioritize future improvements; we cannot promise a feature or release date. Please leave out passwords and confidential client information.', 'alba-board' );
    echo '</p><p>' . esc_html__( 'Submitted survey answers are retained for 180 days. Providing feedback is optional and does not subscribe you to marketing.', 'alba-board' ) . '</p></details>';
    alba_board_feedback_form_start(); echo '<input type="hidden" name="choice" value="survey_send"><input type="hidden" name="event_id" value="' . esc_attr( wp_generate_uuid4() ) . '">';
    foreach ( [ 'use_case' => [ __( 'What do you use Alba Board for?', 'alba-board' ), [ 'personal' => __( 'Personal tasks', 'alba-board' ), 'team' => __( 'Team projects', 'alba-board' ), 'client' => __( 'Client work', 'alba-board' ), 'content' => __( 'Content planning', 'alba-board' ), 'other' => __( 'Other', 'alba-board' ), 'unspecified' => __( 'Prefer not to say', 'alba-board' ) ] ], 'improvement' => [ __( 'What is the main thing we should improve?', 'alba-board' ), [ 'setup' => __( 'Setup', 'alba-board' ), 'workflow' => __( 'Daily workflow', 'alba-board' ), 'feature' => __( 'Missing feature', 'alba-board' ), 'reliability' => __( 'Reliability or speed', 'alba-board' ), 'appearance' => __( 'Appearance', 'alba-board' ), 'other' => __( 'Other', 'alba-board' ) ] ] ] as $key => $field ) {
        echo '<p><label for="alba-' . esc_attr( $key ) . '">' . esc_html( $field[0] ) . '</label><br><select required id="alba-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '"><option value="">' . esc_html__( 'Choose an answer', 'alba-board' ) . '</option>';
        foreach ( $field[1] as $value => $label ) echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
        echo '</select></p>';
    }
    echo '<p><label for="alba-survey-details">' . esc_html__( 'What were you trying to do, and what would help? (optional)', 'alba-board' ) . '</label><br><textarea id="alba-survey-details" name="details" rows="4" maxlength="2000" class="large-text"></textarea></p>';
    echo '<p><label><input type="checkbox" name="send_consent" value="1" required> ' . esc_html__( 'Send my answers, optional comment, and Alba Board version to Alba Board. This does not enable automatic usage reporting.', 'alba-board' ) . '</label></p><button class="button button-primary">' . esc_html__( 'Send feedback', 'alba-board' ) . '</button></form></div>';
}
function alba_board_set_usage_consent( $enabled ) {
    $enabled = (bool) $enabled;
    $old = (bool) alba_board_usage_allowed();
    if ( $enabled && ! $old ) {
        if ( ! get_option( 'alba_board_usage_identity' ) ) update_option( 'alba_board_usage_identity', [ 'id' => wp_generate_uuid4(), 'token' => wp_generate_password( 48, false, false ) ], false );
        delete_option( 'alba_board_usage_activity' );
    }
    if ( ! $enabled && $old ) alba_board_lifecycle_send( 'reporting_disabled' );
    update_option( 'alba_board_usage_consent_schema', $enabled ? 2 : 0, false );
    update_option( 'alba_board_usage_consent', $enabled, false );
    if ( $enabled && ! $old ) alba_board_lifecycle_send( 'reporting_enabled' );
    if ( $enabled ) alba_board_feedback_start();
    else { alba_board_usage_stop(); delete_option( 'alba_board_usage_activity' ); }
}
function alba_board_product_feedback_action() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'alba-board' ), '', [ 'response' => 403 ] );
    check_admin_referer( 'alba_board_product_feedback' );
    $choice = isset( $_POST['choice'] ) && is_string( $_POST['choice'] ) ? sanitize_key( $_POST['choice'] ) : '';
    if ( alba_board_feedback_dismiss( $choice ) ) {
        wp_safe_redirect( alba_board_feedback_notice_return_url() ); exit;
    }
    $state = alba_board_feedback_state(); $result = 'saved';
    if ( 'review_open' === $choice ) {
        $state['review_done'] = true;
        $state['next'] = time() + 14 * DAY_IN_SECONDS;
        update_option( 'alba_board_feedback_state', $state, false );
        wp_redirect( 'https://wordpress.org/support/plugin/alba-board/reviews/#new-post' ); exit;
    } elseif ( 'consent' === $choice ) {
        alba_board_set_usage_consent( isset( $_POST['enabled'] ) && '1' === $_POST['enabled'] );
    } elseif ( 'delete_usage' === $choice ) {
        update_option( 'alba_board_usage_consent', false, false ); alba_board_usage_stop(); delete_option( 'alba_board_usage_activity' );
        $identity = get_option( 'alba_board_usage_identity', [] );
        $response = alba_board_product_request( 'insights/delete', [ 'installation_id' => $identity['id'] ?? '', 'delete_token' => $identity['token'] ?? '' ] );
        $result = alba_board_product_success( $response ) ? 'deleted' : 'failed';
        if ( 'deleted' === $result ) delete_option( 'alba_board_usage_identity' );
    } elseif ( 'survey_send' === $choice ) {
        $use = isset( $_POST['use_case'] ) && is_string( $_POST['use_case'] ) ? sanitize_key( $_POST['use_case'] ) : '';
        $improvement = isset( $_POST['improvement'] ) && is_string( $_POST['improvement'] ) ? sanitize_key( $_POST['improvement'] ) : '';
        $details = isset( $_POST['details'] ) && is_string( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '';
        if ( empty( $_POST['send_consent'] ) || strlen( $details ) > 8000 || ! in_array( $use, [ 'personal', 'team', 'client', 'content', 'other', 'unspecified' ], true ) || ! in_array( $improvement, [ 'setup', 'workflow', 'feature', 'reliability', 'appearance', 'other' ], true ) ) $result = 'invalid';
        elseif ( (int) ( $state['last_survey'] ?? 0 ) > time() - DAY_IN_SECONDS ) $result = 'invalid';
        else {
            $response = alba_board_product_request( 'survey', [ 'schema' => 1, 'consent' => true, 'plugin' => '2.3.0', 'use_case' => $use, 'improvement' => $improvement, 'details' => $details, 'event_id' => isset( $_POST['event_id'] ) && is_string( $_POST['event_id'] ) ? sanitize_text_field( wp_unslash( $_POST['event_id'] ) ) : '' ] );
            $result = alba_board_product_success( $response ) ? 'sent' : 'failed';
            if ( 'sent' === $result ) { $state['survey_done'] = true; $state['last_survey'] = time(); $state['next'] = time() + 14 * DAY_IN_SECONDS; update_option( 'alba_board_feedback_state', $state, false ); }
        }
    } else $result = 'invalid';
    wp_safe_redirect( add_query_arg( 'result', $result, admin_url( 'admin.php?page=alba-board-feedback' ) ) ); exit;
}
add_action( 'admin_post_alba_board_product_feedback', 'alba_board_product_feedback_action' );

add_action( 'added_post_meta', function( $meta_id, $id, $key ) {
    if ( 'alba_card_attachments' === $key && 'alba_card' === get_post_type( $id ) ) alba_board_usage_count( 'attachments' );
}, 20, 3 );

/** Send only disclosed, consented lifecycle events; never infer activity from missing reports. */
function alba_board_lifecycle_send( $event ) {
    if ( ! alba_board_usage_allowed() || is_multisite() || ! in_array( $event, [ 'activated', 'deactivated', 'reporting_enabled', 'reporting_disabled' ], true ) ) return;
    $identity = get_option( 'alba_board_usage_identity', [] );
    if ( empty( $identity['id'] ) || empty( $identity['token'] ) ) return;
    return alba_board_product_request( 'lifecycle', [
        'schema' => 2, 'consent' => true, 'installation_id' => $identity['id'],
        'delete_token' => $identity['token'], 'event_id' => wp_generate_uuid4(),
        'event' => $event, 'plugin' => '2.3.0',
    ] );
}
function alba_board_lifecycle_transition( $plugin, $network_wide = false ) {
    if ( $plugin !== plugin_basename( dirname( __DIR__ ) . '/alba-board.php' ) || $network_wide || is_multisite() || ! alba_board_usage_allowed() ) return;
    $expected_active = 'activated_plugin' === current_filter();
    // Core writes active_plugins after the deactivation hooks. Verify the committed final state.
    add_action( 'shutdown', function() use ( $plugin, $expected_active ) {
        $active = in_array( $plugin, (array) get_option( 'active_plugins', [] ), true );
        if ( $active === $expected_active ) alba_board_lifecycle_send( $active ? 'activated' : 'deactivated' );
    }, 5 );
}
add_action( 'activated_plugin', 'alba_board_lifecycle_transition', 20, 2 );
add_action( 'deactivated_plugin', 'alba_board_lifecycle_transition', 20, 2 );
