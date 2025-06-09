<?php
/*
Plugin Name: Alba Board
Plugin URI: https://www.albaboard.com
Description: Custom Kanban system for WordPress with boards, lists, cards, and dynamic interactions. Extendable via add-ons.
Version: 1.0
Author: Alejo
Author URI: https://www.albaboard.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: alba-board
Domain Path: /languages
Requires at least: 5.8
Tested up to: 6.8.1
Requires PHP: 7.2
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Load translations for i18n.
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'alba-board', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
});

// Auto-assign capabilities on plugin activation (admin & editor roles)
register_activation_hook(__FILE__, 'alba_board_add_caps_to_roles');
function alba_board_add_caps_to_roles() {
    $roles = ['administrator', 'editor']; // Puedes añadir más roles si lo deseas
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

// Plugin core includes.
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