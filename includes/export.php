<?php
// includes/export.php

if ( ! defined( 'ABSPATH' ) ) exit; // Prevent direct access

// Hook into admin-post.php to handle the file download securely
add_action('admin_post_alba_export_board', 'alba_board_export_handler');

function alba_board_export_handler() {
    // 1. Security Check: Ensure the user has permission to export
    if (!current_user_can('edit_posts')) {
        wp_die(esc_html__('Permission denied.', 'alba-board'));
    }

    // 2. Validate input parameters
    $board_id = isset($_GET['board_id']) ? absint($_GET['board_id']) : 0;
    $format = (isset($_GET['format']) && $_GET['format'] === 'json') ? 'json' : 'csv';
    
    // 3. Verify the nonce for CSRF protection
    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'alba_export_board_' . $board_id)) {
        wp_die(esc_html__('Invalid security token.', 'alba-board'));
    }

    if (!$board_id) {
        wp_die(esc_html__('Invalid board ID.', 'alba-board'));
    }

    // 4. Fetch the requested board
    $board = get_post($board_id);
    if (!$board || $board->post_type !== 'alba_board') {
        wp_die(esc_html__('Board not found.', 'alba-board'));
    }

    // 5. Fetch all lists associated with this board
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
    $lists = get_posts([
        'post_type'   => 'alba_list',
        'numberposts' => -1,
        'meta_key'    => 'alba_board_parent',
        'meta_value'  => $board_id,
        'orderby'     => 'menu_order',
        'order'       => 'ASC'
    ]);

    $export_data = [];

    if ($lists) {
        $list_ids = wp_list_pluck($lists, 'ID');
        
        $lists_map = [];
        foreach ($lists as $list) {
            $lists_map[$list->ID] = $list->post_title;
        }
        
        // 6. Fetch all cards within these lists efficiently
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        $cards = get_posts([
            'post_type'   => 'alba_card',
            'numberposts' => -1,
            'meta_query'  => [
                [
                    'key'     => 'alba_list_parent',
                    'value'   => $list_ids,
                    'compare' => 'IN'
                ]
            ],
            'orderby'     => 'menu_order',
            'order'       => 'ASC',
            'update_post_term_cache' => true // Load tags in memory
        ]);

        // 7. Format the data for export
        foreach ($cards as $card) {
            $list_id = get_post_meta($card->ID, 'alba_list_parent', true);
            $list_name = isset($lists_map[$list_id]) ? $lists_map[$list_id] : __('Unknown', 'alba-board');
            
            $author_name = '';
            if ($card->post_author) {
                $author = get_userdata($card->post_author);
                $author_name = $author ? $author->display_name : '';
            }

            $tags = get_the_terms($card->ID, 'alba_tag');
            $tag_names = [];
            if (!empty($tags) && !is_wp_error($tags)) {
                foreach ($tags as $tag) {
                    $tag_names[] = $tag->name;
                }
            }

            $export_data[] = [
                'Board'        => $board->post_title,
                'List'         => $list_name,
                'Card Title'   => $card->post_title,
                'Description'  => wp_strip_all_tags($card->post_content),
                'Assignee'     => $author_name,
                'Tags'         => implode(', ', $tag_names),
                'Date Created' => $card->post_date
            ];
        }
    }

    // 8. Output the file based on the requested format
    $filename = sanitize_title($board->post_title) . '-export-' . gmdate('Y-m-d') . '.' . $format;

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $output = fopen('php://output', 'w');
        
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputs
        fputs($output, "\xEF\xBB\xBF");
        
        if (!empty($export_data)) {
            fputcsv($output, array_keys($export_data[0])); 
            foreach ($export_data as $row) {
                fputcsv($output, $row);
            }
        } else {
            fputcsv($output, [__('No cards found in this board.', 'alba-board')]);
        }
        
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose($output);
        exit;

    } else {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo wp_json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}