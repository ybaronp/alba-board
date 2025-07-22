<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Register the "List" custom post type
function alba_board_register_list_post_type() {
    register_post_type('alba_list', [
        'labels' => [
            'name'                  => esc_html__('Lists', 'alba-board'),
            'singular_name'         => esc_html__('List', 'alba-board'),
            'add_new'               => esc_html__('Add New', 'alba-board'),
            'add_new_item'          => esc_html__('Add New List', 'alba-board'),
            'edit_item'             => esc_html__('Edit List', 'alba-board'),
            'new_item'              => esc_html__('New List', 'alba-board'),
            'view_item'             => esc_html__('View List', 'alba-board'),
            'search_items'          => esc_html__('Search Lists', 'alba-board'),
            'not_found'             => esc_html__('No lists found', 'alba-board'),
            'not_found_in_trash'    => esc_html__('No lists found in Trash', 'alba-board')
        ],
        'public'            => false,
        'show_ui'           => true,
        'show_in_menu'      => false,
        'capability_type'   => [ 'list', 'lists' ],
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

// Save board-list relationship securely
function alba_board_save_list_relationship($post_id, $post, $update) {
    if (get_post_type($post_id) !== 'alba_list') return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (isset($_POST['alba_board_parent'], $_POST['alba_list_board_nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['alba_list_board_nonce']));
        if (wp_verify_nonce($nonce, 'alba_list_board_action')) {
            update_post_meta($post_id, 'alba_board_parent', absint($_POST['alba_board_parent']));
        }
    }
}
add_action('save_post', 'alba_board_save_list_relationship', 10, 3);

// Metabox: Select related board
function alba_board_list_meta_box() {
    add_meta_box(
        'alba_board_list_board_meta',
        esc_html__('Related Board', 'alba-board'),
        'alba_board_list_board_meta_callback',
        'alba_list',
        'side'
    );
}
add_action('add_meta_boxes', 'alba_board_list_meta_box');

function alba_board_list_board_meta_callback($post) {
    $selected_board = get_post_meta($post->ID, 'alba_board_parent', true);
    $boards = get_posts(['post_type' => 'alba_board', 'numberposts' => -1]);
    wp_nonce_field('alba_list_board_action', 'alba_list_board_nonce');
    echo '<select name="alba_board_parent">';
    echo '<option value="">' . esc_html__('-- Select board --', 'alba-board') . '</option>';
    foreach ($boards as $board) {
        echo '<option value="' . esc_attr($board->ID) . '"';
        if ($selected_board == $board->ID) echo ' selected="selected"';
        echo '>' . esc_html($board->post_title) . '</option>';
    }
    echo '</select>';
}

// Metabox: Show all cards in this list
function alba_board_add_cards_metabox() {
    add_meta_box(
        'alba_list_cards_meta',
        esc_html__('Cards in this list', 'alba-board'),
        'alba_board_cards_meta_callback',
        'alba_list',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'alba_board_add_cards_metabox');

function alba_board_cards_meta_callback($post) {
    $cards = get_posts([
        'post_type'   => 'alba_card',
        'numberposts' => -1,
        'meta_key'    => 'alba_list_parent',
        'meta_value'  => $post->ID,
        'orderby'     => 'menu_order',
        'order'       => 'ASC'
    ]);

    echo '<p><a href="' . esc_url(admin_url('post-new.php?post_type=alba_card&alba_list_parent=' . absint($post->ID))) . '" class="button button-primary">';
    esc_html_e('Add new card', 'alba-board');
    echo '</a></p>';

    if ($cards) {
        echo '<ul class="alba-list-cards-list">';
        foreach ($cards as $card) {
            $edit_link = get_edit_post_link($card->ID);
            echo '<li><a href="' . esc_url($edit_link) . '">' . esc_html($card->post_title) . '</a></li>';
        }
        echo '</ul>';
    } else {
        echo '<p class="alba-list-no-cards">' . esc_html__('No cards assigned to this list.', 'alba-board') . '</p>';
    }
}