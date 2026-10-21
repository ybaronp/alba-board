<?php
/** Frontend interactions included in the free plugin. */
if ( ! defined( 'ABSPATH' ) ) exit;

function alba_board_core_frontend_comment() {
    alba_board_authorize_frontend_card_action();
    // alba_board_authorize_frontend_card_action() validates the nonce and object permissions above.
    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $card_id = isset($_POST['card_id']) ? absint($_POST['card_id']) : 0;
    // alba_board_authorize_frontend_card_action() validates the nonce and object permissions above.
    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $text = isset($_POST['comment']) && is_string($_POST['comment']) ? trim(sanitize_textarea_field(wp_unslash($_POST['comment']))) : '';
    if ('' === $text) {
        wp_send_json_error(['message' => __('Write a comment first.', 'alba-board')], 400);
    }
    $user = wp_get_current_user();
    $comments = get_post_meta($card_id, 'alba_comments', true);
    if (!is_array($comments)) $comments = [];
    $comments[] = ['author' => $user->display_name ?: $user->user_login, 'date' => current_time('Y-m-d H:i'), 'text' => $text];
    update_post_meta($card_id, 'alba_comments', $comments);
    foreach ( $comments as &$comment ) $comment['segments'] = alba_board_card_comment_segments( $comment['text'], $card_id );
    unset( $comment );
    wp_send_json_success(['comments' => $comments]);
}
add_action('wp_ajax_alba_add_card_comment', 'alba_board_core_frontend_comment', 5);

function alba_board_core_frontend_delete() {
    alba_board_authorize_frontend_card_action();
    // alba_board_authorize_frontend_card_action() validates the nonce and object permissions above.
    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $card_id = isset($_POST['card_id']) ? absint($_POST['card_id']) : 0;
    if (!EMPTY_TRASH_DAYS) {
        wp_send_json_error(['message' => __('WordPress Trash is disabled. Enable it to delete cards safely.', 'alba-board')], 400);
    }
    if (!wp_trash_post($card_id) || 'trash' !== get_post_status($card_id)) {
        wp_send_json_error(['message' => __('Could not move this card to Trash.', 'alba-board')], 400);
    }
    wp_send_json_success(['card_id' => $card_id]);
}
add_action('wp_ajax_alba_delete_card', 'alba_board_core_frontend_delete', 5);

/** Replace the legacy add-on client even when it remains active. */
function alba_board_enqueue_core_interactions() {
    wp_dequeue_script('alba-board-interactions-script');
    wp_deregister_script('alba-board-interactions-script');
    if (!wp_script_is('alba-kanban', 'enqueued')) return;
    $path = plugin_dir_path(dirname(__FILE__)) . 'assets/js/alba-frontend-interactions.js';
    wp_enqueue_script('alba-core-interactions', plugin_dir_url(dirname(__FILE__)) . 'assets/js/alba-frontend-interactions.js', ['alba-kanban'], (string) filemtime($path), true);
    wp_localize_script('alba-core-interactions', 'albaBoardFrontend', alba_board_interaction_config());
}
add_action('wp_enqueue_scripts', 'alba_board_enqueue_core_interactions', 100);

function alba_board_interaction_config() {
    return [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => current_user_can('edit_cards') ? wp_create_nonce('alba_frontend_nonce') : '',
        'can_create' => current_user_can('edit_cards'),
        'strings' => [
            'newCard' => __('+ New Card', 'alba-board'), 'title' => __('Card title', 'alba-board'),
            'create' => __('Create', 'alba-board'), 'error' => __('Could not complete the request. Please refresh and try again.', 'alba-board'),
            'commentRequired' => __('Write a comment first.', 'alba-board'),
            'confirmDelete' => __('Move this card to WordPress Trash? You can restore it under Alba Board > Cards > Trash.', 'alba-board'),
        ],
    ];
}
