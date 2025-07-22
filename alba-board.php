<?php
/*
Plugin Name: Alba Board
Plugin URI: https://www.albaboard.com
Description: Custom Kanban system for WordPress with boards, lists, cards, and dynamic interactions. Extendable via add-ons.
Version: 1.0.0
Author: alejo30
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

/**
 * Assign custom capabilities to admin and editor roles on plugin activation.
 * This is required so these roles can manage boards, lists and cards.
 */
register_activation_hook(__FILE__, 'alba_board_add_caps_to_roles');
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
 * This is useful for new/migrated sites or roles edited after plugin activation.
 */
add_action('init', 'alba_board_add_caps_to_roles');

/**
 * Optionally, you can remove custom capabilities on plugin deactivation.
 * (Uncomment if you want to clean up on deactivate)
 */
// register_deactivation_hook(__FILE__, 'alba_board_remove_caps_from_roles');
// function alba_board_remove_caps_from_roles() {
//     $roles = ['administrator', 'editor'];
//     $caps = [ ... ]; // same as above
//     foreach ($roles as $role_name) {
//         $role = get_role($role_name);
//         if (!$role) continue;
//         foreach ($caps as $cap) {
//             $role->remove_cap($cap);
//         }
//     }
// }

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