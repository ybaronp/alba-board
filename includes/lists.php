<?php
// lists.php

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register custom post type for Lists
function alba_board_register_list_post_type() {
    register_post_type('alba_list', [
        'labels' => [
            'name'                  => __('Lists', 'alba-board'),
            'singular_name'         => __('List', 'alba-board'),
            'add_new'               => __('Add New', 'alba-board'),
            'add_new_item'          => __('Add New List', 'alba-board'),
            'edit_item'             => __('Edit List', 'alba-board'),
            'new_item'              => __('New List', 'alba-board'),
            'view_item'             => __('View List', 'alba-board'),
            'search_items'          => __('Search Lists', 'alba-board'),
            'not_found'             => __('No lists found', 'alba-board'),
            'not_found_in_trash'    => __('No lists found in Trash', 'alba-board')
        ],
        'public'            => false,
        'show_ui'           => true,
        'show_in_menu'      => false, // IMPORTANT: Hide from main menu
        'capability_type'   => 'list',
        'capabilities'      => [
            'edit_post'             => 'edit_list',
            'read_post'             => 'read_list',
            'delete_post'           => 'delete_list',
            'edit_posts'            => 'edit_lists',
            'edit_others_posts'     => 'edit_others_lists',
            'delete_posts'          => 'delete_lists',
            'delete_others_posts'   => 'delete_others_lists',
            'publish_posts'         => 'publish_lists',
            'read_private_posts'    => 'read_private_lists'
        ],
        'map_meta_cap'      => true,
        'hierarchical'      => false,
        'supports'          => ['title'],
    ]);
}
add_action('init', 'alba_board_register_list_post_type');

// Save list-board relationship via custom field
function alba_board_save_list_relationship($post_id, $post, $update) {
    // Verify correct post type
    if ($post->post_type !== 'alba_list') return;

    if (isset($_POST['alba_board_parent'])) {
        // Sanitize and update meta
        update_post_meta($post_id, 'alba_board_parent', intval($_POST['alba_board_parent']));
    }
}
add_action('save_post', 'alba_board_save_list_relationship', 10, 3);

// Add metabox to select related board
function alba_board_list_meta_box() {
    add_meta_box(
        'alba_board_list_board_meta',
        __('Related Board', 'alba-board'),
        'alba_board_list_board_meta_callback',
        'alba_list',
        'side'
    );
}
add_action('add_meta_boxes', 'alba_board_list_meta_box');

function alba_board_list_board_meta_callback($post) {
    $selected_board = get_post_meta($post->ID, 'alba_board_parent', true);
    $boards = get_posts(['post_type' => 'alba_board', 'numberposts' => -1]);

    echo '<select name="alba_board_parent">';
    echo '<option value="">' . esc_html__('-- Select board --', 'alba-board') . '</option>';
    foreach ($boards as $board) {
        $selected = ($selected_board == $board->ID) ? 'selected' : '';
        echo '<option value="' . esc_attr($board->ID) . '" ' . esc_attr($selected) . '>' . esc_html($board->post_title) . '</option>';
    }
    echo '</select>';
}
// Agrega un metabox para mostrar las tarjetas (Cards) de esta lista
function alba_board_add_cards_metabox() {
    add_meta_box(
        'alba_list_cards_meta',
        __('Cards in this list', 'alba-board'),
        'alba_board_cards_meta_callback',
        'alba_list',
        'normal',
        'default'
    );
}
add_action( 'add_meta_boxes', 'alba_board_add_cards_metabox' );

function alba_board_cards_meta_callback( $post ) {
    // Recupera todas las tarjetas relacionadas a esta lista
    $cards = get_posts( [
        'post_type'   => 'alba_card',
        'numberposts' => -1,
        'meta_key'    => 'alba_list_parent',
        'meta_value'  => $post->ID,
        'orderby'     => 'menu_order',
        'order'       => 'ASC'
    ] );

    // Botón para añadir tarjeta rápidamente
    echo '<p>';
    echo '<a href="' . esc_url( admin_url( 'post-new.php?post_type=alba_card&alba_list_parent=' . $post->ID ) ) . '" class="button button-primary">';
    _e('Add new card', 'alba-board');
    echo '</a>';
    echo '</p>';

    if ( $cards ) {
        echo '<ul>';
        foreach ( $cards as $card ) {
            $edit_link = get_edit_post_link( $card->ID );
            echo '<li><a href="' . esc_url( $edit_link ) . '">' . esc_html( $card->post_title ) . '</a></li>';
        }
        echo '</ul>';
    } else {
        echo '<p>' . __('No cards assigned to this list.', 'alba-board') . '</p>';
    }
}