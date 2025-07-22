<?php
// includes/shortcodes.php

if ( ! defined( 'ABSPATH' ) ) exit;

// Render the Alba Board via shortcode [alba_board id="..."]
function alba_board_render_shortcode($atts) {
    $atts = shortcode_atts(['id' => 0], $atts);
    $board_id = intval($atts['id']);
    if (!$board_id) return '<p>' . esc_html__('Invalid board ID', 'alba-board') . '</p>';

    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    $addon_active = is_plugin_active('alba-board-frontend-interactions/alba-board-frontend-interactions.php');

    ob_start();

    // Get board lists
    $lists = get_posts([
        'post_type'   => 'alba_list',
        'numberposts' => -1,
        'orderby'     => 'menu_order',
        'order'       => 'ASC',
        'meta_key'    => 'alba_board_parent',
        'meta_value'  => $board_id
    ]);

    $options = get_option('alba_board_limits');
    $max_cards = isset($options['limit_cards']) ? intval($options['limit_cards']) : 0;

    // Board HTML wrapper
    echo '<div class="alba-board-outerwrap">';
    echo '<div class="alba-board-wrapper">';
    foreach ($lists as $list) {
        echo '<div class="alba-list-column">';
        echo '<h3>' . esc_html($list->post_title) . '</h3>';

        $cards = get_posts([
            'post_type'   => 'alba_card',
            'numberposts' => -1,
            'orderby'     => 'menu_order',
            'order'       => 'ASC',
            'meta_key'    => 'alba_list_parent',
            'meta_value'  => $list->ID
        ]);

        echo '<div class="alba-cards" data-list-id="' . esc_attr($list->ID) . '"'
            . ($max_cards > 0 ? ' data-max-cards="' . esc_attr($max_cards) . '"' : '')
            . '>';

        foreach ($cards as $card) {
            echo '<div class="alba-card" data-card-id="' . esc_attr($card->ID) . '">';
            echo '<span class="alba-card-title">' . esc_html($card->post_title) . '</span>';

            $terms = get_the_terms($card->ID, 'alba_tag');
            if (!empty($terms) && !is_wp_error($terms)) {
                echo '<div class="alba-card-tags">';
                foreach ($terms as $term) {
                    $bg   = get_term_meta($term->term_id, 'alba_tag_bg_color', true) ?: '#e3e7ef';
                    $text = get_term_meta($term->term_id, 'alba_tag_text_color', true) ?: '#222';
                    $style = '--tag-shadow:' . esc_attr($bg) . ';background:' . esc_attr($bg) . ';color:' . esc_attr($text) . ';';
                    echo '<span class="alba-card-tag-chip" style="' . esc_attr($style) . '">' . esc_html($term->name) . '</span>';
                }
                echo '</div>';
            }
            echo '</div>'; // .alba-card
        }
        echo '</div>'; // .alba-cards
        echo '</div>'; // .alba-list-column
    }
    echo '</div>'; // .alba-board-wrapper
    echo '</div>'; // .alba-board-outerwrap

    // Modal for card details (no inline style!)
    ?>
    <div id="alba-card-modal" class="alba-card-modal">
        <div class="alba-modal-content">
            <button id="alba-modal-close" title="<?php esc_attr_e('Close', 'alba-board'); ?>" class="alba-modal-close-btn">&times;</button>
            <div id="alba-modal-body"><?php esc_html_e('Loading...', 'alba-board'); ?></div>
        </div>
    </div>
    <?php

    // Enqueue frontend JS/CSS
    wp_enqueue_script(
        'alba-board-frontend',
        plugin_dir_url(__FILE__) . '../assets/js/alba-board-frontend.js',
        array('jquery'),
        '1.0.0',
        true
    );

    wp_localize_script(
        'alba-board-frontend',
        'AlbaBoardI18n',
        [
            'loading'        => esc_html__('Loading...', 'alba-board'),
            'confirm_delete' => esc_html__('Are you sure you want to delete this card?', 'alba-board'),
            'delete_error'   => esc_html__('Error deleting card', 'alba-board'),
        ]
    );    return ob_get_clean();
}
add_shortcode('alba_board', 'alba_board_render_shortcode');