<?php
// includes/shortcodes.php
if ( ! defined( 'ABSPATH' ) ) exit; // Prevent direct access

/**
 * Render the Alba Board shortcode.
 * Usage: [alba_board id="123"]
 */
function alba_board_render_shortcode($atts) {
    // 1. Setup attributes and validate board ID
    $atts = shortcode_atts(['id' => 0], $atts);
    $board_id = intval($atts['id']);
    
    if (!$board_id) {
        return '<p>' . esc_html__('Invalid board ID', 'alba-board') . '</p>';
    }

    // 2. Fetch Display Options
    $display_opts = get_option('alba_board_display', ['show_avatars' => 1, 'theme' => 'default']);
    $show_avatars = !empty($display_opts['show_avatars']);
    $theme_class  = isset($display_opts['theme']) ? 'alba-theme-' . $display_opts['theme'] : 'alba-theme-default';

    ob_start();

    // 3. Render "My Tasks Only" filter for logged-in users
    $current_user_id = get_current_user_id();
    if ($current_user_id) {
        echo '<div class="alba-frontend-filters" style="margin-bottom: 25px; display: flex; justify-content: flex-end; padding-right: 15px;">';
        echo '<label style="cursor: pointer; display: inline-flex; align-items: center; gap: 10px; font-weight: 600; color: var(--alba-text-main); background: var(--alba-card-bg); padding: 10px 20px; border-radius: 30px; box-shadow: 4px 4px 8px var(--alba-shadow-dark), -4px -4px 8px var(--alba-shadow-light); transition: all 0.3s ease; user-select: none;">';
        echo '<input type="checkbox" id="alba-filter-my-tasks" data-user-id="' . esc_attr($current_user_id) . '" style="cursor: pointer; width: 16px; height: 16px; accent-color: #3b82f6;">';
        echo esc_html__('My Tasks Only', 'alba-board');
        echo '</label>';
        echo '</div>';
    }

    // 4. Query all lists associated with this board
    $lists = get_posts([
        'post_type'   => 'alba_list',
        'numberposts' => -1,
        'orderby'     => 'menu_order',
        'order'       => 'ASC',
        'meta_key'    => 'alba_board_parent',
        'meta_value'  => $board_id
    ]);
    
    echo '<div class="alba-master-theme-wrapper ' . esc_attr($theme_class) . '">';
    echo '<div class="alba-board-outerwrap">';
    echo '<div class="alba-board-wrapper">';
    
    // 5. Loop through each list
    foreach ($lists as $list) {
        // The container needs the ID for the collapse logic
        echo '<div class="alba-list-column" data-list-id="' . esc_attr($list->ID) . '">';
        
        echo '<div class="alba-list-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">';
        echo '<h3 style="margin:0;">' . esc_html($list->post_title) . '</h3>';
        
        // 👉 NEW: Collapse Toggle Button for Frontend
        echo '<button type="button" class="alba-list-collapse-btn" title="' . esc_attr__('Collapse/Expand', 'alba-board') . '" style="background:none; border:none; cursor:pointer; font-size:16px; opacity:0.6; color:var(--alba-text-main);">↔</button>';
        echo '</div>';

        echo '<div class="alba-cards" data-list-id="' . esc_attr($list->ID) . '">';
        
        $cards = get_posts([
            'post_type'   => 'alba_card',
            'numberposts' => -1,
            'orderby'     => 'menu_order',
            'order'       => 'ASC',
            'meta_key'    => 'alba_list_parent',
            'meta_value'  => $list->ID
        ]);
        
        foreach ($cards as $card) {
            echo '<div class="alba-card" data-card-id="' . esc_attr($card->ID) . '" data-author="' . esc_attr($card->post_author) . '">';
            echo '<span class="alba-card-title">' . esc_html($card->post_title) . '</span>';
            
            // Preview: Due Date displayed on the card face
            $due_date = get_post_meta($card->ID, 'alba_due_date', true);
            if (!empty($due_date)) {
                $formatted_date = date_i18n('M j', strtotime($due_date)); 
                echo '<div class="alba-card-due-date-preview" style="font-size: 11px; color: #64748b; margin-top: 6px; display: flex; align-items: center; gap: 4px;">';
                echo '<span class="dashicons dashicons-calendar-alt" style="font-size: 13px; height: 13px; width: 13px;"></span> ';
                echo esc_html($formatted_date);
                echo '</div>';
            }

            echo '<div class="alba-card-footer" style="display:flex; justify-content:space-between; align-items:flex-end; margin-top:8px;">';
            echo '<div class="alba-card-tags-wrapper" style="flex-grow:1; text-align:left;">';
            do_action('alba_board_card_tags', $card->ID);
            echo '</div>'; 
            
            if ($show_avatars && $card->post_author) {
                echo '<div class="alba-card-avatar">' . get_avatar($card->post_author, 28) . '</div>';
            }
            
            echo '</div>'; 
            echo '</div>'; 
        }
        
        echo '</div>'; // End alba-cards
        echo '</div>'; // End alba-list-column
    }
    
    echo '</div>'; // End alba-board-wrapper
    echo '</div>'; // End alba-board-outerwrap
    
    // Modal Container
    ?>
    <div id="alba-card-modal" class="alba-card-modal">
        <div class="alba-modal-content">
            <button id="alba-modal-close" class="alba-modal-close-btn">&times;</button>
            <div id="alba-modal-body"><?php esc_html_e('Loading...', 'alba-board'); ?></div>
        </div>
    </div>
    </div> 
    <?php
    
    return ob_get_clean();
}
add_shortcode('alba_board', 'alba_board_render_shortcode');