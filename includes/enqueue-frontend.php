<?php
/**
 * includes/enqueue-frontend.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Detect boards in the main query so pages without boards do not load board assets.
 * Integrations that render boards outside post content can override this result.
 *
 * @return bool Whether the current request should load Alba Board front-end assets.
 */
function alba_board_frontend_page_has_shortcode() {
    $queried_posts = [];
    $queried_object = get_queried_object();

    if ( $queried_object instanceof WP_Post ) {
        $queried_posts[] = $queried_object;
    }

    global $wp_query;
    if ( $wp_query instanceof WP_Query && ! empty( $wp_query->posts ) ) {
        $queried_posts = array_merge( $queried_posts, $wp_query->posts );
    }

    foreach ( $queried_posts as $post ) {
        if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'alba_board' ) ) {
            return true;
        }
    }

    return false;
}

function alba_board_enqueue_assets() {
    $should_enqueue = alba_board_frontend_page_has_shortcode();
    $should_enqueue = (bool) apply_filters( 'alba_board_should_enqueue_frontend_assets', $should_enqueue );

    if ( ! $should_enqueue ) {
        return;
    }

    $plugin_url = plugin_dir_url(dirname(__FILE__));
    $plugin_path = plugin_dir_path(dirname(__FILE__));
    $frontend_script_path = $plugin_path . 'assets/js/alba-board-frontend.js';
    $plugin_version = '2.2.0';

    wp_enqueue_script(
        'sortablejs',
        $plugin_url . 'assets/js/Sortable.min.js',
        [],
        '1.15.0',
        true
    );

    wp_enqueue_script(
        'alba-kanban',
        $plugin_url . 'assets/js/alba-board-frontend.js',
        ['sortablejs', 'jquery'],
        file_exists($frontend_script_path) ? filemtime($frontend_script_path) : $plugin_version,
        true
    );

    wp_enqueue_style(
        'alba-board-style',
        $plugin_url . 'assets/css/alba-board-style.css',
        [],
        $plugin_version
    );

    $localize_data = [
        'ajaxurl'                 => admin_url('admin-ajax.php'),
        'rest_url'                => esc_url_raw( rest_url() ), 
        'rest_nonce'              => wp_create_nonce( 'wp_rest' ), 
        'nonce'                   => wp_create_nonce('alba_move_card_nonce'),
        'move_error'              => __('Could not move the card.', 'alba-board'),
        'loading'                 => __('Loading...', 'alba-board'),
        'confirm_delete'          => __('Are you sure you want to delete this card?', 'alba-board'),
        'delete_error'            => __('Error deleting card', 'alba-board'),
        'can_move_cards'          => current_user_can('edit_cards') || current_user_can('edit_others_cards'),
    ];

    // STRICT NONCE GATE
    if ( current_user_can('edit_cards') ) {
        $localize_data['get_card_details_nonce'] = wp_create_nonce('alba_get_card_details');
    }

    wp_localize_script('alba-kanban', 'albaBoard', $localize_data);
}
add_action('wp_enqueue_scripts', 'alba_board_enqueue_assets');
