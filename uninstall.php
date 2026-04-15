<?php
// uninstall.php for Alba Board

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$alba_board_delete_data = get_option('alba_delete_on_uninstall', false);
if ( ! $alba_board_delete_data ) {
    return;
}

$alba_board_post_types = [ 'alba_board', 'alba_list', 'alba_card' ];
foreach ( $alba_board_post_types as $alba_board_pt ) {
    $alba_board_posts = get_posts([
        'post_type'   => $alba_board_pt,
        'numberposts' => -1,
        'post_status' => 'any'
    ]);
    foreach ( $alba_board_posts as $alba_board_post ) {
        wp_delete_post( $alba_board_post->ID, true );
    }
}

$alba_board_terms = get_terms([
    'taxonomy'   => 'alba_tag',
    'hide_empty' => false
]);
if ( ! is_wp_error( $alba_board_terms ) ) {
    foreach ( $alba_board_terms as $alba_board_term ) {
        wp_delete_term( $alba_board_term->term_id, 'alba_tag' );
    }
}

delete_option('alba_board_limits');
delete_option('alba_board_notifications');
delete_option('alba_board_uploads'); // No olvides borrar los nuevos ajustes también
delete_option('alba_delete_on_uninstall');

$alba_board_roles = [ 'administrator', 'editor' ];
$alba_board_caps = [
    'edit_board', 'read_board', 'delete_board', 'edit_boards', 'edit_others_boards', 'delete_boards', 'delete_others_boards', 'publish_boards', 'read_private_boards',
    'edit_list', 'read_list', 'delete_list', 'edit_lists', 'edit_others_lists', 'delete_lists', 'delete_others_lists', 'publish_lists', 'read_private_lists',
    'edit_card', 'read_card', 'delete_card', 'edit_cards', 'edit_others_cards', 'delete_cards', 'delete_others_cards', 'publish_cards', 'read_private_cards',
];
foreach ( $alba_board_roles as $alba_board_role_name ) {
    $alba_board_role = get_role( $alba_board_role_name );
    if ( ! $alba_board_role ) continue;
    foreach ( $alba_board_caps as $alba_board_cap ) {
        $alba_board_role->remove_cap( $alba_board_cap );
    }
}