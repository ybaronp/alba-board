<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Register all Alba Board post types
function alba_board_register_all_post_types() {
    // Board
    register_post_type( 'alba_board', [
        'labels' => [
            'name'               => esc_html__('Boards', 'alba-board'),
            'singular_name'      => esc_html__('Board', 'alba-board'),
            'add_new'            => esc_html__('Add New', 'alba-board'),
            'add_new_item'       => esc_html__('Add New Board', 'alba-board'),
            'edit_item'          => esc_html__('Edit Board', 'alba-board'),
            'new_item'           => esc_html__('New Board', 'alba-board'),
            'view_item'          => esc_html__('View Board', 'alba-board'),
            'search_items'       => esc_html__('Search Boards', 'alba-board'),
            'not_found'          => esc_html__('No boards found', 'alba-board'),
            'not_found_in_trash' => esc_html__('No boards found in Trash', 'alba-board')
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => false,
        'capability_type' => [ 'board', 'boards' ],
        'capabilities'  => [
            'edit_post'           => 'edit_board',
            'read_post'           => 'read_board',
            'delete_post'         => 'delete_board',
            'edit_posts'          => 'edit_boards',
            'edit_others_posts'   => 'edit_others_boards',
            'delete_posts'        => 'delete_boards',
            'delete_others_posts' => 'delete_others_boards',
            'publish_posts'       => 'publish_boards',
            'read_private_posts'  => 'read_private_boards',
        ],
        'map_meta_cap'  => true,
        'hierarchical'  => false,
        'supports'      => [ 'title', 'editor', 'author' ],
        'menu_icon'     => 'dashicons-layout',
    ] );

    // List
    register_post_type( 'alba_list', [
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
            'read_private_posts'    => 'read_private_lists',
        ],
        'map_meta_cap'      => true,
        'hierarchical'      => false,
        'supports'          => ['title'],
    ]);

    // Card
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
            'read_private_posts'    => 'read_private_cards',
        ],
        'map_meta_cap'      => true,
        'hierarchical'      => false,
        'supports'          => ['title', 'editor', 'author', 'custom-fields'],
    ]);
}
add_action( 'init', 'alba_board_register_all_post_types' );

// Admin menu & submenus
function alba_board_admin_menu() {
    add_menu_page(
        esc_html__('Boards', 'alba-board'),
        esc_html__('Boards', 'alba-board'),
        'edit_boards',
        'edit.php?post_type=alba_board',
        '',
        'dashicons-layout',
        26
    );
    add_submenu_page(
        'edit.php?post_type=alba_board',
        esc_html__('Lists', 'alba-board'),
        esc_html__('Lists', 'alba-board'),
        'edit_lists',
        'edit.php?post_type=alba_list'
    );
    add_submenu_page(
        'edit.php?post_type=alba_board',
        esc_html__('Cards', 'alba-board'),
        esc_html__('Cards', 'alba-board'),
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
            $new_columns['alba_shortcode'] = esc_html__('Shortcode', 'alba-board');
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

// Enqueue admin JS/CSS for shortcode copy (only for Boards list)
function alba_board_admin_copy_enqueue($hook) {
    if ($hook !== 'edit.php' || get_post_type() !== 'alba_board') {
        return;
    }
    $plugin_url = plugin_dir_url(dirname(__FILE__));
    wp_enqueue_script(
        'alba-board-admin-copy',
        $plugin_url . 'assets/js/alba-board-admin.js',
        [],
        '1.0.0',
        true
    );
    wp_enqueue_style(
        'alba-board-admin-copy',
        $plugin_url . 'assets/css/alba-board-admin.css',
        [],
        '1.0.0'
    );
}
add_action('admin_enqueue_scripts', 'alba_board_admin_copy_enqueue');