<?php
/*
Plugin Name: Task & Project Management – Alba Board
Plugin URI: https://www.albaboard.com
Description: Organize tasks, assign work, and track project progress with visual boards in WordPress. Keep comments and files together.
Version: 2.3.0
Author: alejo30
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: alba-board
Domain Path: /languages
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

register_activation_hook(__FILE__, 'alba_board_on_activation');

function alba_board_on_activation() {
    alba_board_add_caps_to_roles();
    if ( ! get_option( 'alba_board_welcome_dismissed' ) ) {
        update_option( 'alba_board_show_welcome_notice', true );
    }
}

function alba_board_add_caps_to_roles() {
    $roles = ['administrator', 'editor'];
    $caps = [
        'edit_board', 'read_board', 'delete_board', 'edit_boards', 'edit_others_boards',
        'delete_boards', 'delete_others_boards', 'publish_boards', 'read_private_boards',
        'edit_list', 'read_list', 'delete_list', 'edit_lists', 'edit_others_lists',
        'delete_lists', 'delete_others_lists', 'publish_lists', 'read_private_lists',
        'edit_card', 'read_card', 'delete_card', 'edit_cards', 'edit_others_cards',
        'delete_cards', 'delete_others_cards', 'publish_cards', 'read_private_cards',
    ];
    // WordPress maps published/private post operations to these primitive caps.
    foreach ( [ 'boards', 'lists', 'cards' ] as $post_type_caps ) {
        $caps = array_merge( $caps, [ 'edit_published_' . $post_type_caps, 'delete_published_' . $post_type_caps, 'edit_private_' . $post_type_caps, 'delete_private_' . $post_type_caps ] );
    }

    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if (!$role) continue;
        foreach ($caps as $cap) {
            $role->add_cap($cap);
        }
    }
}

add_action('init', 'alba_board_add_caps_to_roles');

require_once plugin_dir_path(__FILE__) . 'includes/capabilities.php';
require_once plugin_dir_path(__FILE__) . 'includes/roles.php';
require_once plugin_dir_path(__FILE__) . 'includes/boards.php';
require_once plugin_dir_path(__FILE__) . 'includes/lists.php';
require_once plugin_dir_path(__FILE__) . 'includes/cards.php';
require_once plugin_dir_path(__FILE__) . 'includes/archive-cards.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-trash-card.php';
require_once plugin_dir_path(__FILE__) . 'includes/settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/product-feedback.php';
require_once plugin_dir_path(__FILE__) . 'includes/notifications.php';
require_once plugin_dir_path(__FILE__) . 'includes/card-collaboration.php';
require_once plugin_dir_path(__FILE__) . 'includes/security.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-create-card.php';
require_once plugin_dir_path(__FILE__) . 'includes/frontend-interactions.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-move-card.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-update-card-assignee.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-board-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-card-details-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-card-details.php';
require_once plugin_dir_path(__FILE__) . 'includes/enqueue-frontend.php';
require_once plugin_dir_path(__FILE__) . 'includes/enqueue-backend.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-save-card-details-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/dashboard-widget.php';
require_once plugin_dir_path(__FILE__) . 'includes/export.php';
require_once plugin_dir_path(__FILE__) . 'includes/rest-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/mcp-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/mcp-abilities.php';

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'alba_board_action_links' );
function alba_board_action_links( $links ) {
    $board_link = '<a href="' . admin_url( 'admin.php?page=alba-board-visual' ) . '" style="font-weight: bold; color: #2271b1;">' . esc_html__( 'Go to Board', 'alba-board' ) . '</a>';
    array_unshift( $links, $board_link );
    return $links;
}

add_filter( 'plugin_row_meta', 'alba_board_row_meta_links', 10, 2 );
function alba_board_row_meta_links( $links, $file ) {
    if ( strpos( $file, 'alba-board.php' ) !== false ) {
        $custom_links = array(
            '<a href="https://albaboard.com/bring-your-ideas-to-alba-board/" target="_blank" rel="noopener noreferrer">💻 ' . esc_html__( 'Live Demo', 'alba-board' ) . '</a>',
            '<a href="https://albaboard.com/docs/" target="_blank" rel="noopener noreferrer">📖 ' . esc_html__( 'Documentation', 'alba-board' ) . '</a>',
            '<a href="https://albaboard.com/contact-us/" target="_blank" rel="noopener noreferrer" style="color: #d63638; font-weight: 500;">🗣️ ' . esc_html__( 'Support & Feedback', 'alba-board' ) . '</a>',
        );
        $links = array_merge( $links, $custom_links );
    }
    return $links;
}

add_action( 'admin_notices', 'alba_board_welcome_notice' );

function alba_board_welcome_notice() {
    if ( ! get_option( 'alba_board_show_welcome_notice' ) || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $dismiss_url = wp_nonce_url(
        add_query_arg( array( 'alba_board_dismiss' => '1' ) ),
        'alba_board_dismiss_welcome'
    );
    
    ?>
    <div class="notice notice-info" style="border-left-color: #2271b1; padding: 15px; position: relative;">
        <h3 style="margin-top: 0; font-size: 1.2em;"><?php esc_html_e( '🎉 Thank you for installing Alba Board!', 'alba-board' ); ?></h3>
        <p style="font-size: 14px;"><?php esc_html_e( 'You are just one click away from starting to organize your projects visually. Don\'t lose the momentum, let\'s see what it can do!', 'alba-board' ); ?></p>
        <p style="margin-bottom: 15px;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=alba-board-visual' ) ); ?>" class="button button-primary" style="background: #2271b1; border-color: #2271b1;">
                <?php esc_html_e( '👉 Create my first board', 'alba-board' ); ?>
            </a>
            <a href="https://albaboard.com/docs/" target="_blank" rel="noopener noreferrer" class="button button-secondary" style="margin-left: 10px;">
                <?php esc_html_e( '📖 Read the quick guide', 'alba-board' ); ?>
            </a>
        </p>
        <p style="font-size: 13px; color: #646970; margin-top: 15px; margin-bottom: 0; border-top: 1px solid #c3c4c7; padding-top: 10px;">
            <?php esc_html_e( 'Not what you expected? We read all your comments to improve the product.', 'alba-board' ); ?>
            <a href="https://albaboard.com/contact-us/" target="_blank" rel="noopener noreferrer" style="color: #d63638; text-decoration: none; font-weight: 500; margin-left: 5px;">
                <?php esc_html_e( '💡 Share your feedback', 'alba-board' ); ?>
            </a>
        </p>
        <a href="<?php echo esc_url( $dismiss_url ); ?>" style="position: absolute; top: 15px; right: 15px; text-decoration: none; color: #787c82;">
            <span class="dashicons dashicons-no-alt"></span> <?php esc_html_e( 'Dismiss', 'alba-board' ); ?>
        </a>
    </div>
    <?php
}

add_action( 'admin_init', 'alba_board_dismiss_welcome_notice' );

function alba_board_dismiss_welcome_notice() {
    if ( ! isset( $_GET['alba_board_dismiss'] ) || ! is_scalar( $_GET['alba_board_dismiss'] ) || '1' !== sanitize_text_field( wp_unslash( $_GET['alba_board_dismiss'] ) ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to dismiss this notice.', 'alba-board' ), '', array( 'response' => 403 ) );
    }

    check_admin_referer( 'alba_board_dismiss_welcome' );

    delete_option( 'alba_board_show_welcome_notice' );
    update_option( 'alba_board_welcome_dismissed', true );
    wp_safe_redirect( remove_query_arg( array( 'alba_board_dismiss', '_wpnonce' ) ) );
    exit;
}

add_action( 'admin_enqueue_scripts', 'alba_board_deactivation_assets' );
function alba_board_deactivation_assets( $hook ) {
    if ( 'plugins.php' !== $hook ) return;

    wp_enqueue_style( 'alba-deactivation-style', plugin_dir_url( __FILE__ ) . 'assets/css/alba-deactivation.css', array(), (string) filemtime( plugin_dir_path( __FILE__ ) . 'assets/css/alba-deactivation.css' ) );
    wp_enqueue_script( 'alba-deactivation-script', plugin_dir_url( __FILE__ ) . 'assets/js/alba-deactivation.js', array(), (string) filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/alba-deactivation.js' ), true );
    wp_localize_script( 'alba-deactivation-script', 'albaDeactivationData', array(
        'pluginSlug'  => plugin_basename( __FILE__ ),
        'feedbackUrl' => alba_board_product_service_url( 'v1/feedback' ),
        'sendingText' => __( 'Sending...', 'alba-board' ),
        'reasonRequired' => __( 'Choose a reason, or deactivate without feedback.', 'alba-board' )
    ));
}

add_action( 'admin_footer-plugins.php', 'alba_board_deactivation_modal_html' );
function alba_board_deactivation_modal_html() {
    if ( ! current_user_can( 'activate_plugins' ) ) return;
    $reasons = [
        [ 'Just testing', __( 'I was just testing', 'alba-board' ), __( 'Anything we could make better?', 'alba-board' ) ],
        [ 'I no longer need it', __( 'I no longer need it', 'alba-board' ), __( 'Anything we could make better?', 'alba-board' ) ],
        [ 'It lacks a feature I need', __( 'A feature is missing', 'alba-board' ), __( 'Which feature would help you use Alba Board?', 'alba-board' ) ],
        [ 'I found a bug', __( 'Something did not work', 'alba-board' ), __( 'What were you trying to do, and what happened?', 'alba-board' ) ],
        [ "I couldn't understand how it works", __( 'Setup was unclear', 'alba-board' ), __( 'Which step was confusing?', 'alba-board' ) ],
        [ 'I found a better plugin', __( 'I chose another plugin', 'alba-board' ), __( 'What worked better for you?', 'alba-board' ) ],
        [ 'Other', __( 'Other', 'alba-board' ), __( 'What made you decide to deactivate?', 'alba-board' ) ],
    ];
    ?>
    <div id="alba-feedback-overlay" hidden>
        <div id="alba-feedback-modal" role="dialog" aria-modal="true" aria-labelledby="alba-feedback-title" aria-describedby="alba-feedback-intro" tabindex="-1">
            <div class="alba-modal-header">
                <h3 id="alba-feedback-title"><?php esc_html_e( 'Help improve Alba Board', 'alba-board' ); ?></h3>
                <button type="button" id="alba-cancel-deactivate" class="button" aria-label="<?php esc_attr_e( 'Cancel deactivation', 'alba-board' ); ?>"><?php esc_html_e( 'Cancel', 'alba-board' ); ?></button>
            </div>
            <form id="alba-feedback-form">
                <div class="alba-modal-body">
                    <p id="alba-feedback-intro"><?php esc_html_e( 'One short sentence can help us decide what to improve next. Feedback is optional.', 'alba-board' ); ?></p>
                    <p class="alba-feedback-proof"><?php esc_html_e( 'Recent updates include user-requested improvements to card layout, keyboard shortcuts, and card recovery. Your suggestion could help shape a future update.', 'alba-board' ); ?>
                        <a href="https://github.com/ybaronp/alba-board/blob/2.2.1/readme.txt#changelog" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'See recent improvements', 'alba-board' ); ?></a>
                    </p>
                    <fieldset class="alba-feedback-options">
                        <legend><?php esc_html_e( 'Why are you deactivating Alba Board?', 'alba-board' ); ?></legend>
                        <?php foreach ( $reasons as $reason ): ?>
                            <label><input type="radio" name="alba_reason" value="<?php echo esc_attr( $reason[0] ); ?>" data-prompt="<?php echo esc_attr( $reason[2] ); ?>"> <?php echo esc_html( $reason[1] ); ?></label>
                        <?php endforeach; ?>
                    </fieldset>
                    <div id="alba-feedback-comment" hidden>
                        <label for="alba-feedback-details"><?php esc_html_e( 'Anything we should improve? (optional)', 'alba-board' ); ?></label>
                        <p id="alba-feedback-prompt"></p>
                        <textarea id="alba-feedback-details" maxlength="2000" aria-describedby="alba-feedback-prompt"></textarea>
                    </div>
                    <p class="alba-feedback-notice"><?php esc_html_e( 'Your selected reason and comment will be sent to Alba Board.', 'alba-board' ); ?></p>
                    <p id="alba-feedback-status" role="status" aria-live="polite"></p>
                </div>
                <div class="alba-modal-footer">
                    <button type="button" id="alba-skip-deactivate" class="button-link"><?php esc_html_e( 'Deactivate without feedback', 'alba-board' ); ?></button>
                    <button type="submit" id="alba-submit-feedback" class="button button-primary"><?php esc_html_e( 'Send feedback & deactivate', 'alba-board' ); ?></button>
                </div>
            </form>
        </div>
    </div>
    <?php
}
