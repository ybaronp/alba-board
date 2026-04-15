<?php
// includes/dashboard-widget.php

if ( ! defined( 'ABSPATH' ) ) exit; // Prevent direct access

// Register the widget in the WordPress Dashboard
add_action('wp_dashboard_setup', 'alba_board_add_dashboard_widget');

function alba_board_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'alba_board_user_tasks', // Widget ID
        esc_html__('My Alba Board Tasks', 'alba-board'), // Title
        'alba_board_render_dashboard_widget' // Callback function
    );
}

// Render the widget content
function alba_board_render_dashboard_widget() {
    $current_user_id = get_current_user_id();

    // 1. Get the latest 5 cards assigned to the current user globally (across all boards)
    $cards = get_posts([
        'post_type'      => 'alba_card',
        'author'         => $current_user_id,
        'numberposts'    => 5,
        'post_status'    => 'publish',
        'orderby'        => 'modified', // Show recently modified cards
        'order'          => 'DESC'
    ]);

    // Handle empty state if the user has no assigned cards
    if (empty($cards)) {
        echo '<div style="text-align: center; padding: 20px 0;">';
        echo '<p style="font-size: 1.1em; color: #646970;">' . esc_html__('You have no assigned tasks. Great job! 🎉', 'alba-board') . '</p>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=alba-board-visual')) . '" class="button">' . esc_html__('Go to Boards', 'alba-board') . '</a>';
        echo '</div>';
        return;
    }

    echo '<ul style="margin: 0; padding: 0; list-style: none;">';
    foreach ($cards as $card) {
        // 2. Resolve relationships to get List and Board context
        $list_id = get_post_meta($card->ID, 'alba_list_parent', true);
        $board_id = 0;
        $list_title = __('Unknown List', 'alba-board');
        $board_title = __('Unknown Board', 'alba-board');
        
        if ($list_id) {
            $list_post = get_post($list_id);
            if ($list_post) {
                $list_title = $list_post->post_title;
                $board_id = get_post_meta($list_id, 'alba_board_parent', true);
                
                // Fetch the Board title
                if ($board_id) {
                    $board_post = get_post($board_id);
                    if ($board_post) {
                        $board_title = $board_post->post_title;
                    }
                }
            }
        }

        // Build the direct URL to that specific board
        $board_url = admin_url('admin.php?page=alba-board-visual' . ($board_id ? '&board_id=' . absint($board_id) : ''));

        // HTML for each card item in the widget
        echo '<li style="border-bottom: 1px solid #f0f0f1; padding: 12px 0; display: flex; justify-content: space-between; align-items: center;">';
        
        // Output title and context (Board Name -> List Name)
        echo '<div>';
        echo '<strong style="display: block; font-size: 1.1em;"><a href="' . esc_url($board_url) . '" style="text-decoration: none; color: #2271b1;">' . esc_html($card->post_title) . '</a></strong>';
        
        /* translators: 1: Board Name, 2: List Name */
        $contextual_path = sprintf(__('%1$s &rarr; %2$s', 'alba-board'), $board_title, $list_title);
        echo '<span style="font-size: 0.9em; color: #646970;">' . wp_kses_post($contextual_path) . '</span>';
        echo '</div>';
        
        // Show a small visual indicator for tags (Colored circles)
        $tags = wp_get_post_terms($card->ID, 'alba_tag');
        if (!empty($tags) && !is_wp_error($tags)) {
            echo '<div style="display:flex; gap: 4px;">';
            $count = 0;
            foreach ($tags as $tag) {
                if ($count >= 3) break; // Maximum 3 colored dots per space to avoid clutter
                $bg = get_term_meta($tag->term_id, 'alba_tag_bg_color', true) ?: '#eeeeee';
                echo '<span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: ' . esc_attr($bg) . ';" title="' . esc_attr($tag->name) . '"></span>';
                $count++;
            }
            echo '</div>';
        }

        echo '</li>';
    }
    echo '</ul>';
    
    // Button to go to the full board view
    echo '<div style="margin-top: 15px; text-align: right;">';
    echo '<a href="' . esc_url(admin_url('admin.php?page=alba-board-visual')) . '" class="button button-primary">' . esc_html__('View all my tasks', 'alba-board') . '</a>';
    echo '</div>';
}