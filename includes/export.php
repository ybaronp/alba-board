<?php
/**
 * Alba Board Export Logic (CSV & JSON)
 * Hook: admin_post_alba_export_board
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_post_alba_export_board', 'alba_board_export_handler');

function alba_board_export_handler() {
    $board_id = isset($_GET['board_id']) && is_scalar($_GET['board_id']) ? absint($_GET['board_id']) : 0;
    $format   = isset($_GET['format']) && is_scalar($_GET['format']) ? sanitize_key(wp_unslash($_GET['format'])) : 'csv';

    $board = get_post($board_id);
    if (!$board || $board->post_type !== 'alba_board') {
        wp_die(esc_html__('Invalid board.', 'alba-board'));
    }
    if (!current_user_can('edit_board', $board_id)) {
        wp_die(esc_html__('You do not have permission to export this board.', 'alba-board'));
    }
    if (!in_array($format, ['csv', 'json'], true)) {
        wp_die(esc_html__('Invalid export format.', 'alba-board'));
    }

    check_admin_referer('alba_export_board_' . $board_id);

    // --- SYSTEM INTEGRATION NAMING CONVENTION ---
    // Convert board title to a safe, lowercase, hyphen-separated string (slug)
    $board_slug = sanitize_title($board->post_title);
    if (empty($board_slug)) {
        $board_slug = 'board-' . $board_id; // Fallback just in case
    }
    
    // Create a precise chronological timestamp (e.g., 20260526_143000)
    $timestamp = wp_date('Ymd_His');
    
    // Assemble the robust filename
    $filename = sprintf('alba-board-%s-%s.%s', $board_slug, $timestamp, $format);
    // --------------------------------------------

    // 2. Gather Data
    $export_data = [];
    $lists = get_posts([
        'post_type'   => 'alba_list',
        'numberposts' => -1,
        'meta_key'    => 'alba_board_parent',
        'meta_value'  => $board_id,
        'orderby'     => 'menu_order',
        'order'       => 'ASC'
    ]);

    foreach ($lists as $list) {
        $cards = get_posts([
            'post_type'   => 'alba_card',
            'numberposts' => -1,
            'meta_key'    => 'alba_list_parent',
            'meta_value'  => $list->ID,
            'orderby'     => 'menu_order',
            'order'       => 'ASC'
        ]);

        foreach ($cards as $card) {
            // A. Assignee
            $assignee = '';
            if ($card->post_author) {
                $user = get_userdata($card->post_author);
                $assignee = $user ? $user->display_name : '';
            }

            // B. Due Date
            $due_date = get_post_meta($card->ID, 'alba_due_date', true);

            // C. Tags
            $tags = wp_get_post_terms($card->ID, 'alba_tag', ['fields' => 'names']);
            $tags_str = (!is_wp_error($tags) && !empty($tags)) ? implode(', ', $tags) : '';

            // D. Attachments (URLs)
            $attachments = get_post_meta($card->ID, 'alba_card_attachments');
            $att_urls = [];
            if (!empty($attachments)) {
                foreach ($attachments as $att_id) {
                    $url = wp_get_attachment_url($att_id);
                    if ($url) $att_urls[] = $url;
                }
            }
            $atts_str = implode(', ', $att_urls);

            // E. Activity and Comments (Conversations)
            $comments = get_post_meta($card->ID, 'alba_comments', true);
            if (!is_array($comments)) $comments = @unserialize($comments) ?: [];
            
            $comments_csv = [];
            foreach ($comments as $c) {
                // CSV Format: [Date] Author: Text
                $comments_csv[] = sprintf('[%s] %s: %s', $c['date'], $c['author'], $c['text']);
            }
            $comments_str = implode("  |  ", $comments_csv); // Clear separator for Excel

            // Build the complete row
            $export_data[] = [
                'Board'         => $board->post_title,
                'List'          => $list->post_title,
                'Card Title'    => $card->post_title,
                'Description'   => $card->post_content,
                'Assignee'      => $assignee,
                'Due Date'      => $due_date,
                'Tags'          => $tags_str,
                'Attachments'   => $atts_str,
                'Conversations' => $comments_str,
                '_raw_comments' => $comments // Keep raw array for JSON export only
            ];
        }
    }

    // 3. Generate and Download File
    if ($format === 'json') {
        // Clean up JSON data to pass structured comments instead of plain text
        $json_data = array_map(function($row) {
            $row['Conversations'] = $row['_raw_comments'];
            unset($row['_raw_comments']);
            return $row;
        }, $export_data);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo wp_json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;

    } else {
        // Default format: CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM so Excel reads special characters correctly
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        if (!empty($export_data)) {
            // Print Headers
            fputcsv($output, [
                'Board', 'List', 'Card Title', 'Description', 
                'Assignee', 'Due Date', 'Tags', 'Attachments (URLs)', 'Conversations'
            ]);
            
            // Print Rows
            foreach ($export_data as $row) {
                unset($row['_raw_comments']); // Remove the raw array from the CSV output
                fputcsv($output, array_map('alba_board_csv_safe_cell', array_values($row)));
            }
        } else {
            fputcsv($output, ['No cards found in this board.']);
        }
        
        fclose($output);
        exit;
    }
}

/** Prefix spreadsheet formula markers in exported CSV cell values. */
function alba_board_csv_safe_cell($value) {
    if (!is_scalar($value)) {
        return '';
    }

    $value = (string) $value;
    if (preg_match('/^[\x00-\x20]*[=+\-@]/', $value)) {
        return "'" . $value;
    }

    return $value;
}
