<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Register the "Card" custom post type
function alba_board_register_card_post_type() {
    register_post_type('alba_card', [
        'labels' => [
            'name'                  => esc_html__('Cards', 'alba-board'),
            'singular_name'         => esc_html__('Card', 'alba-board'),
            'add_new'               => esc_html__('Add New', 'alba-board'),
            'add_new_item'          => esc_html__('Add New Card', 'alba-board'),
            'edit_item'             => esc_html__('Edit Card', 'alba-board'),
            'new_item'              => esc_html__('New Card', 'alba-board'),
            'view_item'             => esc_html__('View Card', 'alba-board'),
            'search_items'          => esc_html__('Search Cards', 'alba-board'),
            'not_found'             => esc_html__('No cards found', 'alba-board'),
            'not_found_in_trash'    => esc_html__('No cards found in Trash', 'alba-board'),
        ],
        'public'            => false,
        'show_ui'           => true,
        'show_in_menu'      => false,
        'capability_type'   => [ 'card', 'cards' ],
        'capabilities'      => [
            'edit_post'             => 'edit_card',
            'read_post'             => 'read_card',
            'delete_post'           => 'delete_card',
            'edit_posts'            => 'edit_cards',
            'edit_others_posts'     => 'edit_others_cards',
            'delete_posts'          => 'delete_cards',
            'delete_others_posts'   => 'delete_others_cards',
            'publish_posts'         => 'publish_cards',
            'read_private_posts'    => 'read_private_cards'
        ],
        'map_meta_cap'      => true,
        'hierarchical'      => false,
        'supports'          => ['title', 'editor', 'author', 'custom-fields'],
    ]);
}
add_action('init', 'alba_board_register_card_post_type');

// Save card-list relationship via custom field
function alba_board_save_card_relationship($post_id, $post, $update) {
    if (get_post_type($post_id) !== 'alba_card') return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['alba_list_parent']) && isset($_POST['alba_card_list_nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['alba_card_list_nonce']));
        if (wp_verify_nonce($nonce, 'alba_card_list_action')) {
            update_post_meta($post_id, 'alba_list_parent', absint($_POST['alba_list_parent']));
        }
    }
}
add_action('save_post', 'alba_board_save_card_relationship', 10, 3);

// Add metabox to select related list
function alba_board_card_meta_box() {
    add_meta_box(
        'alba_board_card_list_meta',
        esc_html__('Related List', 'alba-board'),
        'alba_board_card_list_meta_callback',
        'alba_card',
        'side'
    );
}
add_action('add_meta_boxes', 'alba_board_card_meta_box');

function alba_board_card_list_meta_callback($post) {
    $selected_list = get_post_meta($post->ID, 'alba_list_parent', true);
    $lists = get_posts(['post_type' => 'alba_list', 'numberposts' => -1]);
    wp_nonce_field('alba_card_list_action', 'alba_card_list_nonce');
    echo '<select name="alba_list_parent">';
    echo '<option value="">' . esc_html__('-- Select list --', 'alba-board') . '</option>';
    foreach ($lists as $list) {
        echo '<option value="' . esc_attr($list->ID) . '"';
        if ($selected_list == $list->ID) {
            echo ' selected="selected"';
        }
        echo '>' . esc_html($list->post_title) . '</option>';
    }
    echo '</select>';
}

// Board Comments (Kanban popup) metabox (admin only, not Kanban popup)
function alba_card_comments_metabox() {
    add_meta_box(
        'alba_card_comments_meta',
        esc_html__('Board Comments', 'alba-board'),
        'alba_card_comments_meta_callback',
        'alba_card',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'alba_card_comments_metabox');

function alba_card_comments_meta_callback($post) {
    $comments = get_post_meta($post->ID, 'alba_comments', true);
    if (!is_array($comments)) {
        $comments = @unserialize($comments);
        if (!is_array($comments)) $comments = [];
    }
    echo '<div id="alba-comments-list">';
    if (!empty($comments)) {
        foreach ($comments as $c) {
            echo '<div class="alba-card-comments-admin-list">';
            echo '<strong>' . esc_html(isset($c['author']) ? $c['author'] : '') . '</strong>';
            echo '<span class="alba-card-comment-date">' . esc_html(isset($c['date']) ? $c['date'] : '') . '</span><br>';
            echo '<span class="alba-card-comment-text">' . esc_html(isset($c['text']) ? $c['text'] : '') . '</span>';
            echo '</div>';
        }
    } else {
        echo '<div class="alba-card-no-comments">' . esc_html__('No board comments yet.', 'alba-board') . '</div>';
    }
    echo '</div>';
    echo '<p class="alba-card-comments-note">' . esc_html__('Board Comments are added from the Board View popup (Kanban), not from this screen.', 'alba-board') . '</p>';
}

// Author selector with Select2 (enqueue scripts only)
add_filter('wp_dropdown_users_args', function($args, $r) {
    global $post_type;
    if ($post_type === 'alba_card') {
        $args['who'] = '';
    }
    return $args;
}, 10, 2);

add_action('admin_enqueue_scripts', function($hook) {
    global $post_type;
    if ($post_type === 'alba_card' && in_array($hook, ['post-new.php', 'post.php'])) {
        wp_enqueue_script('select2');
        wp_enqueue_style('select2');
        $plugin_url = plugin_dir_url(dirname(__FILE__));
        wp_enqueue_script(
            'alba-card-author-select2',
            $plugin_url . 'assets/js/alba-card-author-select2.js',
            ['jquery', 'select2'],
            '1.0.0',
            true
        );
        wp_enqueue_style(
            'alba-card-admin-style',
            $plugin_url . 'assets/css/alba-card-admin.css',
            [],
            '1.0.0'
        );
        wp_localize_script(
            'alba-card-author-select2',
            'albaCardSelect2',
            [
                'placeholder' => esc_html__('Select a user', 'alba-board'),
            ]
        );
    }
});