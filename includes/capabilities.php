<?php
// includes/capabilities.php

if ( ! defined( 'ABSPATH' ) ) exit;

// Uniform meta cap mapping for Alba Board custom post types
add_filter('map_meta_cap', 'alba_board_map_meta_cap', 10, 4);

function alba_board_map_meta_cap($caps, $cap, $user_id, $args) {
    // Only for our custom meta caps (WordPress calls these in singular)
    $meta_caps = [
        'edit_board', 'delete_board', 'read_board',
        'edit_list', 'delete_list', 'read_list',
        'edit_card', 'delete_card', 'read_card',
    ];
    if ( ! in_array( $cap, $meta_caps ) ) {
        return $caps;
    }

    // Get post object for context
    $post_id = !empty($args[0]) ? intval($args[0]) : 0;
    $post = $post_id ? get_post($post_id) : false;
    if ( ! $post ) {
        return ['do_not_allow'];
    }

    // Consistent capability mapping with register_post_type
    $cap_map = [
        'alba_board' => [
            'edit'          => 'edit_boards',
            'edit_others'   => 'edit_others_boards',
            'delete'        => 'delete_boards',
            'delete_others' => 'delete_others_boards',
            'read'          => 'read_boards',
            'read_private'  => 'read_private_boards'
        ],
        'alba_list' => [
            'edit'          => 'edit_lists',
            'edit_others'   => 'edit_others_lists',
            'delete'        => 'delete_lists',
            'delete_others' => 'delete_others_lists',
            'read'          => 'read_lists',
            'read_private'  => 'read_private_lists'
        ],
        'alba_card' => [
            'edit'          => 'edit_cards',
            'edit_others'   => 'edit_others_cards',
            'delete'        => 'delete_cards',
            'delete_others' => 'delete_others_cards',
            'read'          => 'read_cards',
            'read_private'  => 'read_private_cards'
        ],
    ];
    if ( ! isset( $cap_map[$post->post_type] ) ) {
        return ['do_not_allow'];
    }
    $author_id = (int)$post->post_author;
    $cm = $cap_map[$post->post_type];

    switch ( $cap ) {
        // EDIT
        case 'edit_board':
        case 'edit_list':
        case 'edit_card':
            $caps = ( $user_id === $author_id ) ? [ $cm['edit'] ] : [ $cm['edit_others'] ];
            break;

        // DELETE
        case 'delete_board':
        case 'delete_list':
        case 'delete_card':
            $caps = ( $user_id === $author_id ) ? [ $cm['delete'] ] : [ $cm['delete_others'] ];
            break;

        // READ
        case 'read_board':
        case 'read_list':
        case 'read_card':
            if ( 'private' !== $post->post_status ) {
                $caps = [ 'read' ];
            } elseif ( $user_id === $author_id ) {
                $caps = [ $cm['read'] ];
            } else {
                $caps = [ $cm['read_private'] ];
            }
            break;
    }
    return $caps;
}