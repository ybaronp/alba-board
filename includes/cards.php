<?php
// includes/cards.php

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register custom post type for Cards
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
        'show_in_menu'      => true,
        'capability_type'   => 'card',
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
    if ($post->post_type !== 'alba_card') return;
    if (isset($_POST['alba_list_parent']) && isset($_POST['alba_card_list_nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['alba_card_list_nonce']));
        if (wp_verify_nonce($nonce, 'alba_card_list_action')) {
            update_post_meta($post_id, 'alba_list_parent', intval($_POST['alba_list_parent']));
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
    // Nonce for list select/save
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

// =============================
// BOARD COMMENTS (KANBAN)
// =============================
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
    // Board comments from Kanban (stored as serialized array)
    $comments = get_post_meta($post->ID, 'alba_comments', true);
    if (!is_array($comments)) {
        $comments = @unserialize($comments);
        if (!is_array($comments)) $comments = [];
    }
    echo '<div id="alba-comments-list">';
    if (!empty($comments)) {
        foreach ($comments as $c) {
            echo '<div style="
                margin-bottom:8px; 
                padding:8px 10px; 
                border-radius:7px; 
                background:#f5f7fa; 
                border:1px solid #e0e0e0;">
                <strong>' . esc_html($c['author']) . '</strong>
                <span style="color:#888; font-size:11px; margin-left:6px;">' . esc_html($c['date']) . '</span><br>
                <span>' . esc_html($c['text']) . '</span>
            </div>';
        }
    } else {
        echo '<div style="color:#bbb;">' . esc_html__('No board comments yet.', 'alba-board') . '</div>';
    }
    echo '</div>';
    echo '<p style="margin-top:10px; color:#888;">' . esc_html__('Board Comments are added from the Board View popup (Kanban), not from this screen.', 'alba-board') . '</p>';
}

// =============================
// AUTOR SELECT2 (USER PICKER)
// =============================

// Force author selector as Select2 dropdown for cards
add_filter('wp_dropdown_users_args', function($args, $r) {
    global $post_type;
    if ($post_type === 'alba_card') {
        $args['who'] = '';
    }
    return $args;
}, 10, 2);

// Enqueue Select2 for author selector
add_action('admin_enqueue_scripts', function($hook) {
    global $post_type;
    if ($post_type === 'alba_card' && in_array($hook, ['post-new.php', 'post.php'])) {
        wp_enqueue_script('select2');
        wp_enqueue_style('select2');
        add_action('admin_footer', function() {
            ?>
            <script>
            jQuery(function($) {
                if ($('#post_author').length) {
                    $('#post_author').select2({
                        width: '100%',
                        placeholder: '<?php echo esc_js(esc_html__('Select a user', 'alba-board')); ?>'
                    });
                }
            });
            </script>
            <?php
        });
    }
});