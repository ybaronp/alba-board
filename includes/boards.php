<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function alba_board_register_all_post_types() {
    // Board
    register_post_type( 'alba_board', [
        'labels' => [
            'name'               => __('Boards', 'alba-board'),
            'singular_name'      => __('Board', 'alba-board'),
            'add_new'            => __('Add New', 'alba-board'),
            'add_new_item'       => __('Add New Board', 'alba-board'),
            'edit_item'          => __('Edit Board', 'alba-board'),
            'new_item'           => __('New Board', 'alba-board'),
            'view_item'          => __('View Board', 'alba-board'),
            'search_items'       => __('Search Boards', 'alba-board'),
            'not_found'          => __('No boards found', 'alba-board'),
            'not_found_in_trash' => __('No boards found in Trash', 'alba-board')
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => false,
        'capability_type' => 'board',
        'capabilities'  => [
            'edit_post'           => 'edit_board',
            'read_post'           => 'read_board',
            'delete_post'         => 'delete_board',
            'edit_posts'          => 'edit_boards',
            'edit_others_posts'   => 'edit_others_boards',
            'delete_posts'        => 'delete_boards',
            'delete_others_posts' => 'delete_others_boards',
            'publish_posts'       => 'publish_boards',
            'read_private_posts'  => 'read_private_boards'
        ],
        'map_meta_cap'  => true,
        'hierarchical'  => false,
        'supports'      => [ 'title', 'editor', 'author' ],
        'menu_icon'     => 'dashicons-layout',
    ] );
    // List
    register_post_type( 'alba_list', [
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
        'show_in_menu'      => false,
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
    // Card
    register_post_type('alba_card', [
        'labels' => [
            'name'                  => __('Cards', 'alba-board'),
            'singular_name'         => __('Card', 'alba-board'),
            'add_new'               => __('Add New', 'alba-board'),
            'add_new_item'          => __('Add New Card', 'alba-board'),
            'edit_item'             => __('Edit Card', 'alba-board'),
            'new_item'              => __('New Card', 'alba-board'),
            'view_item'             => __('View Card', 'alba-board'),
            'search_items'          => __('Search Cards', 'alba-board'),
            'not_found'             => __('No cards found', 'alba-board'),
            'not_found_in_trash'    => __('No cards found in Trash', 'alba-board'),
        ],
        'public'            => false,
        'show_ui'           => true,
        'show_in_menu'      => false,
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
add_action( 'init', 'alba_board_register_all_post_types' );

function alba_board_admin_menu() {
    // Main menu (Boards)
    add_menu_page(
        __('Boards', 'alba-board'),
        __('Boards', 'alba-board'),
        'edit_boards',
        'edit.php?post_type=alba_board',
        '',
        'dashicons-layout',
        26
    );
    // Custom submenus
    add_submenu_page(
        'edit.php?post_type=alba_board',
        __('Lists', 'alba-board'),
        __('Lists', 'alba-board'),
        'edit_lists',
        'edit.php?post_type=alba_list'
    );
    add_submenu_page(
        'edit.php?post_type=alba_board',
        __('Cards', 'alba-board'),
        __('Cards', 'alba-board'),
        'edit_cards',
        'edit.php?post_type=alba_card'
    );
}
add_action('admin_menu', 'alba_board_admin_menu');

// Add Shortcode column to Boards admin list
function alba_board_add_shortcode_column($columns) {
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['alba_shortcode'] = __('Shortcode', 'alba-board');
        }
    }
    return $new_columns;
}
add_filter('manage_alba_board_posts_columns', 'alba_board_add_shortcode_column');

// Render Shortcode column with copy button
function alba_board_render_shortcode_column($column, $post_id) {
    if ($column === 'alba_shortcode') {
        $shortcode = '[alba_board id="' . esc_attr($post_id) . '"]';
        ?>
        <span class="alba-board-shortcode-wrapper">
            <code><?php echo esc_html($shortcode); ?></code>
            <button type="button"
                class="button alba-board-copy-shortcode"
                data-shortcode="<?php echo esc_attr($shortcode); ?>"
                style="margin-left: 6px; vertical-align: middle;"
                title="<?php esc_attr_e('Copy shortcode', 'alba-board'); ?>"
            ><?php esc_html_e('Copy', 'alba-board'); ?></button>
        </span>
        <?php
    }
}
add_action('manage_alba_board_posts_custom_column', 'alba_board_render_shortcode_column', 10, 2);

// Robust vanilla JS: prints at the very end of the admin footer, works everywhere
function alba_board_copy_button_script() {
    global $typenow, $pagenow;
    if ($pagenow === 'edit.php' && $typenow === 'alba_board') {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.alba-board-copy-shortcode').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var shortcode = this.getAttribute('data-shortcode');
                    if (navigator && navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(shortcode);
                    } else {
                        var temp = document.createElement('textarea');
                        temp.value = shortcode;
                        document.body.appendChild(temp);
                        temp.select();
                        try { document.execCommand('copy'); } catch(e){}
                        document.body.removeChild(temp);
                    }
                    var oldText = this.textContent;
                    this.textContent = 'Copied!';
                    var btn = this;
                    setTimeout(function() {
                        btn.textContent = oldText;
                    }, 1200);
                });
            });
        });
        </script>
        <style>
            .alba-board-copy-shortcode {
                padding: 0 10px;
                height: 28px;
                font-size: 13px;
                cursor: pointer;
            }
        </style>
        <?php
    }
}
add_action('admin_print_footer_scripts', 'alba_board_copy_button_script');