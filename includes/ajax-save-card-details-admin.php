<?php
// includes/ajax-save-card-details-admin.php
if ( ! defined( 'ABSPATH' ) ) exit; // Prevent direct access

add_action('wp_ajax_alba_save_card_details_admin', 'alba_ajax_save_card_details_admin');

function alba_ajax_save_card_details_admin() {
    // Security & Nonce Checks
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'alba_save_card_details_admin')) {
        wp_send_json_error(['message' => esc_html__('Invalid security token.', 'alba-board')]);
    }
    
    if (!current_user_can('edit_cards')) {
        wp_send_json_error(['message' => esc_html__('Permission denied.', 'alba-board')]);
    }
    
    $card_id = isset($_POST['card_id']) ? absint($_POST['card_id']) : 0;
    if (!$card_id) {
        wp_send_json_error(['message' => esc_html__('Invalid card ID.', 'alba-board')]);
    }

    // Core Post Data
    $post_data = [ 
        'ID' => $card_id, 
        'post_title' => isset($_POST['card_title']) ? sanitize_text_field(wp_unslash($_POST['card_title'])) : '', 
        'post_content' => isset($_POST['card_content']) ? sanitize_textarea_field(wp_unslash($_POST['card_content'])) : '' 
    ];
    if (isset($_POST['card_author'])) {
        $post_data['post_author'] = absint($_POST['card_author']);
    }

    $updated = wp_update_post($post_data);
    if (is_wp_error($updated)) {
        wp_send_json_error(['message' => esc_html__('Error updating card.', 'alba-board')]);
    }

    // 👉 NEW: Save Due Date Meta
    if (isset($_POST['due_date'])) {
        update_post_meta($card_id, 'alba_due_date', sanitize_text_field(wp_unslash($_POST['due_date'])));
    }

    // 👉 HOOK FOR ADD-ONS (e.g., Tags Add-on)
    do_action('alba_save_card_details_admin', $card_id, wp_unslash($_POST));

    // Process New Comments
    if (!empty($_POST['new_comment'])) {
        $current_user = wp_get_current_user();
        $comments = get_post_meta($card_id, 'alba_comments', true);
        if (!is_array($comments)) { 
            $comments = @unserialize($comments); 
            if (!is_array($comments)) $comments = []; 
        }
        $comments[] = [ 
            'author' => $current_user->display_name, 
            'date' => current_time('mysql'), 
            'text' => sanitize_textarea_field(wp_unslash($_POST['new_comment'])) 
        ];
        update_post_meta($card_id, 'alba_comments', $comments);
    }

    // 👉 CLEAR TRANSIENTS: Ensure live UI updates correctly
    delete_transient('alba_card_live_admin_' . $card_id);
    delete_transient('alba_card_live_frontend_' . $card_id);

    // Render updated Modal HTML
    ob_start();
    alba_output_card_details_admin_modal($card_id);
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}