<?php
// includes/ajax-save-card-details-admin.php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('wp_ajax_alba_save_card_details_admin', 'alba_ajax_save_card_details_admin');

function alba_ajax_save_card_details_admin() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'alba_save_card_details_admin')) {
        wp_send_json_error(['message' => esc_html__('Invalid security token.', 'alba-board')]);
    }
    
    $card_id = isset($_POST['card_id']) ? absint($_POST['card_id']) : 0;
    $card = $card_id ? get_post($card_id) : false;
    if (!$card || 'alba_card' !== $card->post_type) {
        wp_send_json_error(['message' => esc_html__('Invalid card.', 'alba-board')], 400);
    }
    if (!current_user_can('edit_card', $card_id)) {
        wp_send_json_error(['message' => esc_html__('Permission denied.', 'alba-board')], 403);
    }

    $post_data = [ 
        'ID' => $card_id, 
        'post_title' => isset($_POST['card_title']) ? sanitize_text_field(wp_unslash($_POST['card_title'])) : '', 
        'post_content' => isset($_POST['card_content']) ? sanitize_textarea_field(wp_unslash($_POST['card_content'])) : '' 
    ];
    
    if (isset($_POST['card_author'])) {
        $author_id = absint($_POST['card_author']);
        if ($author_id && !get_userdata($author_id)) {
            wp_send_json_error(['message' => esc_html__('Invalid assignee.', 'alba-board')], 400);
        }
        $post_data['post_author'] = $author_id;
    }

    $due_date = null;
    if (isset($_POST['due_date'])) {
        $due_date = sanitize_text_field(wp_unslash($_POST['due_date']));
        if ('' !== $due_date && (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date) ||
            !checkdate((int) substr($due_date, 5, 2), (int) substr($due_date, 8, 2), (int) substr($due_date, 0, 4))
        )) {
            wp_send_json_error(['message' => esc_html__('Enter a valid due date.', 'alba-board')], 400);
        }
    }
    
    $updated = wp_update_post($post_data, true);
    if (is_wp_error($updated) || !$updated) {
        wp_send_json_error(['message' => esc_html__('Error updating card.', 'alba-board')], 500);
    }

    if (null !== $due_date) {
        if ('' === $due_date) {
            delete_post_meta($card_id, 'alba_due_date');
        } else {
            update_post_meta($card_id, 'alba_due_date', $due_date);
        }
    }

    // Guest Assignee strict capture and event trigger
    if (isset($_POST['guest_assignee'])) {
        $guest_val = sanitize_text_field(wp_unslash($_POST['guest_assignee']));
        $old_guest_val = get_post_meta($card_id, 'alba_guest_assignee', true);

        if (!empty($guest_val)) {
            update_post_meta($card_id, 'alba_guest_assignee', $guest_val);
        } else {
            delete_post_meta($card_id, 'alba_guest_assignee');
        }

        // Fire action only if the string changed, passing both new and old values
        if ($guest_val !== $old_guest_val) {
            do_action('alba_board_guest_assigned', $card_id, $guest_val, $old_guest_val);
        }
    }

    do_action('alba_save_card_details_admin', $card_id, wp_unslash($_POST));

    if (isset($_POST['new_comment']) && '' !== trim(sanitize_textarea_field(wp_unslash($_POST['new_comment'])))) {
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

    delete_transient('alba_card_live_admin_' . $card_id);
    delete_transient('alba_card_live_frontend_' . $card_id);

    ob_start();
    alba_output_card_details_admin_modal($card_id);
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

// ==========================================
// UPLOAD / DELETE ATTACHMENTS (V1.3.0 CORE)
// ==========================================

add_action('wp_ajax_alba_upload_attachment', 'alba_board_ajax_upload_attachment');
function alba_board_ajax_upload_attachment() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'alba_upload_attachment_nonce')) {
        wp_send_json_error(['message' => esc_html__('Invalid security token.', 'alba-board')]);
    }

    $card_id = isset($_POST['card_id']) ? absint($_POST['card_id']) : 0;
    if (!$card_id || 'alba_card' !== get_post_type($card_id)) {
        wp_send_json_error(['message' => esc_html__('Invalid card.', 'alba-board')], 400);
    }
    if (!current_user_can('edit_card', $card_id)) {
        wp_send_json_error(['message' => esc_html__('Permission denied.', 'alba-board')], 403);
    }
    
    if (!isset($_FILES['file']['error']) || UPLOAD_ERR_OK !== (int) $_FILES['file']['error']) {
        if (!isset($_FILES['file']['error'])) {
            wp_send_json_error(['message' => esc_html__('No file was received.', 'alba-board')], 400);
        }
        wp_send_json_error(['message' => sprintf(esc_html__('Upload failed (error %d). Check the file size and server upload limits.', 'alba-board'), absint($_FILES['file']['error']))], 400);
    }

    $options = get_option('alba_board_uploads', []);
    $options = is_array($options) ? $options : [];
    $max_files = isset($options['max_files']) ? intval($options['max_files']) : 3;
    $max_size_mb = isset($options['max_size']) ? intval($options['max_size']) : 20;
    $allowed_formats_str = isset($options['allowed_formats']) ? $options['allowed_formats'] : 'jpg,png,pdf,docx,csv';
    
    if ($max_files <= 0) {
        wp_send_json_error(['message' => esc_html__('File uploads are disabled.', 'alba-board')]);
    }

    $current_attachments = get_post_meta($card_id, 'alba_card_attachments');
    if (count($current_attachments) >= $max_files) {
        wp_send_json_error(['message' => sprintf(esc_html__('Maximum of %d files allowed.', 'alba-board'), $max_files)]);
    }

    $file_size = isset($_FILES['file']['size']) ? absint($_FILES['file']['size']) : 0;
    if ($file_size > ($max_size_mb * 1024 * 1024)) {
        wp_send_json_error(['message' => sprintf(esc_html__('File exceeds the maximum limit of %d MB.', 'alba-board'), $max_size_mb)]);
    }

    $file_name = isset($_FILES['file']['name']) ? sanitize_file_name(wp_unslash($_FILES['file']['name'])) : '';
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    $allowed_formats = array_filter(array_map('trim', explode(',', $allowed_formats_str)));
    
    if (!in_array($file_ext, $allowed_formats)) {
        wp_send_json_error(['message' => esc_html__('Format not allowed. Active formats: ', 'alba-board') . implode(', ', $allowed_formats)]);
    }

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $custom_mimes = function($mimes) use ($allowed_formats) {
        if (in_array('csv', $allowed_formats)) {
            $mimes['csv'] = 'text/csv';
            $mimes['txt'] = 'text/plain'; 
        }
        if (in_array('json', $allowed_formats)) $mimes['json'] = 'application/json';
        return $mimes;
    };
    
    add_filter('upload_mimes', $custom_mimes, 99);
    $attachment_id = media_handle_upload('file', $card_id);
    remove_filter('upload_mimes', $custom_mimes, 99);

    if (is_wp_error($attachment_id)) {
        wp_send_json_error(['message' => esc_html__('WordPress could not process this upload. Check that the file type is allowed and try again.', 'alba-board')], 400);
    }

    add_post_meta($card_id, 'alba_card_attachments', $attachment_id);

    delete_transient('alba_card_live_admin_' . $card_id);
    delete_transient('alba_card_live_frontend_' . $card_id);

    wp_send_json_success([
        'attachment_id' => $attachment_id,
        'file_url'      => wp_get_attachment_url($attachment_id),
        'file_name'     => get_the_title($attachment_id),
        'file_ext'      => $file_ext
    ]);
}

add_action('wp_ajax_alba_delete_attachment', 'alba_board_ajax_delete_attachment');
function alba_board_ajax_delete_attachment() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'alba_delete_attachment_nonce')) {
        wp_send_json_error(['message' => esc_html__('Invalid security token.', 'alba-board')]);
    }

    $card_id = isset($_POST['card_id']) ? absint($_POST['card_id']) : 0;
    $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
    if (!$card_id || 'alba_card' !== get_post_type($card_id)) {
        wp_send_json_error(['message' => esc_html__('Invalid card.', 'alba-board')], 400);
    }
    if (!current_user_can('edit_card', $card_id)) {
        wp_send_json_error(['message' => esc_html__('Permission denied.', 'alba-board')], 403);
    }

    if (!$card_id || !$attachment_id) {
        wp_send_json_error(['message' => esc_html__('Missing data.', 'alba-board')]);
    }

    $attachment = get_post($attachment_id);
    $current_attachments = get_post_meta($card_id, 'alba_card_attachments');
    if (!$attachment || (int) $attachment->post_parent !== $card_id || !in_array($attachment_id, $current_attachments)) {
        wp_send_json_error(['message' => esc_html__('Attachment does not belong to this card.', 'alba-board')]);
    }

    if (!wp_delete_attachment($attachment_id, true)) {
        wp_send_json_error(['message' => esc_html__('Could not delete the attachment.', 'alba-board')], 500);
    }
    delete_post_meta($card_id, 'alba_card_attachments', $attachment_id);

    delete_transient('alba_card_live_admin_' . $card_id);
    delete_transient('alba_card_live_frontend_' . $card_id);

    wp_send_json_success(['message' => esc_html__('File deleted successfully.', 'alba-board')]);
}
