<?php
/*
Plugin Name: Alba Board
Plugin URI: https://www.albaboard.com
Description: Custom Kanban system for WordPress with boards, lists, cards, and dynamic interactions. Extendable via add-ons.
Version: 2.1.1
Author: alejo30
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: alba-board
Domain Path: /languages
Requires at least: 5.8
Tested up to: 6.9.4
Requires PHP: 7.2
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Hook triggered ONLY when the plugin is activated (not on updates).
 */
register_activation_hook(__FILE__, 'alba_board_on_activation');

function alba_board_on_activation() {
    // 1. Add required capabilities
    alba_board_add_caps_to_roles();
    
    // 2. Set a flag to show the welcome notice ONLY on fresh activations
    if ( ! get_option( 'alba_board_welcome_dismissed' ) ) {
        update_option( 'alba_board_show_welcome_notice', true );
    }
}

/**
 * Assign custom capabilities to admin and editor roles.
 */
function alba_board_add_caps_to_roles() {
    $roles = ['administrator', 'editor'];
    $caps = [
        // Boards
        'edit_board', 'read_board', 'delete_board', 'edit_boards', 'edit_others_boards',
        'delete_boards', 'delete_others_boards', 'publish_boards', 'read_private_boards',
        // Lists
        'edit_list', 'read_list', 'delete_list', 'edit_lists', 'edit_others_lists',
        'delete_lists', 'delete_others_lists', 'publish_lists', 'read_private_lists',
        // Cards
        'edit_card', 'read_card', 'delete_card', 'edit_cards', 'edit_others_cards',
        'delete_cards', 'delete_others_cards', 'publish_cards', 'read_private_cards',
    ];

    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if (!$role) continue;
        foreach ($caps as $cap) {
            $role->add_cap($cap);
        }
    }
}

/**
 * Always auto-add custom capabilities to admin/editor on init.
 */
add_action('init', 'alba_board_add_caps_to_roles');

// Core plugin includes
require_once plugin_dir_path(__FILE__) . 'includes/capabilities.php';
require_once plugin_dir_path(__FILE__) . 'includes/boards.php';
require_once plugin_dir_path(__FILE__) . 'includes/lists.php';
require_once plugin_dir_path(__FILE__) . 'includes/cards.php';
require_once plugin_dir_path(__FILE__) . 'includes/settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/notifications.php';
require_once plugin_dir_path(__FILE__) . 'includes/security.php';
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
require_once plugin_dir_path(__FILE__) . 'includes/ajax-upload-attachment.php';
require_once plugin_dir_path(__FILE__) . 'includes/rest-api.php';

// ==========================================
// PLUGIN ACTION LINKS & ROW META LINKS
// ==========================================

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

// ==========================================
// WELCOME NOTICE (ONBOARDING)
// ==========================================

add_action( 'admin_notices', 'alba_board_welcome_notice' );

function alba_board_welcome_notice() {
    if ( ! get_option( 'alba_board_show_welcome_notice' ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $dismiss_url = add_query_arg( array( 'alba_board_dismiss' => '1' ) );
    
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
    if ( isset( $_GET['alba_board_dismiss'] ) && $_GET['alba_board_dismiss'] == '1' ) {
        delete_option( 'alba_board_show_welcome_notice' );
        update_option( 'alba_board_welcome_dismissed', true );
        
        $redirect_url = remove_query_arg( 'alba_board_dismiss' );
        wp_safe_redirect( $redirect_url );
        exit;
    }
}

// ==========================================
// DEACTIVATION FEEDBACK MODAL (ENQUEUE CSS/JS & HTML)
// ==========================================

// 1. Enqueue files only on the plugins page
add_action( 'admin_enqueue_scripts', 'alba_board_deactivation_assets' );
function alba_board_deactivation_assets( $hook ) {
    // If we are not on the plugins view, do not load anything
    if ( 'plugins.php' !== $hook ) {
        return;
    }

    // CSS
    wp_enqueue_style(
        'alba-deactivation-style',
        plugin_dir_url( __FILE__ ) . 'assets/css/alba-deactivation.css',
        array(),
        '1.4.0'
    );

    // JS
    wp_enqueue_script(
        'alba-deactivation-script',
        plugin_dir_url( __FILE__ ) . 'assets/js/alba-deactivation.js',
        array(),
        '1.4.0',
        true
    );

    // Pass PHP variables to Javascript securely
    wp_localize_script(
        'alba-deactivation-script',
        'albaDeactivationData',
        array(
            'pluginSlug'  => plugin_basename( __FILE__ ),
            'sendingText' => esc_html__( 'Sending...', 'alba-board' )
        )
    );
}

// 2. Print the HTML structure of the Modal in the footer
add_action( 'admin_footer-plugins.php', 'alba_board_deactivation_modal_html' );
function alba_board_deactivation_modal_html() {
    ?>
    <div id="alba-feedback-overlay">
        <div id="alba-feedback-modal">
            <div class="alba-modal-header">
                <h3><?php esc_html_e( 'We\'re sorry to see you go!', 'alba-board' ); ?></h3>
            </div>
            <div class="alba-modal-body">
                <p><?php esc_html_e( 'If you have a moment, please let us know why you are deactivating Alba Board. Your feedback helps us improve.', 'alba-board' ); ?></p>
                
                <div class="alba-feedback-options">
                    <label><input type="radio" name="alba_reason" value="I couldn't understand how it works"> <?php esc_html_e( 'I couldn\'t understand how it works', 'alba-board' ); ?></label>
                    <label><input type="radio" name="alba_reason" value="It lacks a feature I need"> <?php esc_html_e( 'It lacks a feature I need', 'alba-board' ); ?></label>
                    <label><input type="radio" name="alba_reason" value="I found a bug"> <?php esc_html_e( 'I found a bug', 'alba-board' ); ?></label>
                    <label><input type="radio" name="alba_reason" value="I found a better plugin"> <?php esc_html_e( 'I found a better plugin', 'alba-board' ); ?></label>
                    <label><input type="radio" name="alba_reason" value="Just testing / Other" checked> <?php esc_html_e( 'Just testing / Other', 'alba-board' ); ?></label>
                </div>

                <textarea id="alba-feedback-details" placeholder="<?php esc_attr_e( 'Optional: Please share more details...', 'alba-board' ); ?>"></textarea>
            </div>
            <div class="alba-modal-footer">
                <a href="#" id="alba-skip-deactivate"><?php esc_html_e( 'Skip & Deactivate', 'alba-board' ); ?></a>
                <button id="alba-submit-feedback" class="button button-primary"><?php esc_html_e( 'Submit & Deactivate', 'alba-board' ); ?></button>
            </div>
        </div>
    </div>
    <?php
}