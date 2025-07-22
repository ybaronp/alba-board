<?php
// uninstall.php for Alba Board

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Only proceed if the user has chosen to delete data
$delete_data = get_option('alba_delete_on_uninstall', false);
if ( ! $delete_data ) {
    return;
}

// --- Delete custom post types ---
$post_types = [ 'alba_board', 'alba_list', 'alba_card' ];
foreach ( $post_types as $pt ) {
    $posts = get_posts([
        'post_type'   => $pt,
        'numberposts' => -1,
        'post_status' => 'any'
    ]);
    foreach ( $posts as $post ) {
        wp_delete_post( $post->ID, true );
    }
}

// --- Delete all tags (custom taxonomy 'alba_tag') ---
$terms = get_terms([
    'taxonomy'   => 'alba_tag',
    'hide_empty' => false
]);
if ( ! is_wp_error( $terms ) ) {
    foreach ( $terms as $term ) {
        wp_delete_term( $term->term_id, 'alba_tag' );
    }
}

// --- Delete plugin options ---
delete_option('alba_board_limits');
delete_option('alba_board_notifications');
delete_option('alba_delete_on_uninstall');

// --- Remove custom capabilities from roles ---
$roles = [ 'administrator', 'editor' ];
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
foreach ( $roles as $role_name ) {
    $role = get_role( $role_name );
    if ( ! $role ) continue;
    foreach ( $caps as $cap ) {
        $role->remove_cap( $cap );
    }
}