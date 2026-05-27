<?php
// includes/ajax-save-card-details-admin.php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('wp_ajax_alba_save_card_details_admin', 'alba_ajax_save_card_details_admin');

function alba_ajax_save_card_details_admin() {
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

    if (isset($_POST['due_date'])) {
        update_post_meta($card_id, 'alba_due_date', sanitize_text_field(wp_unslash($_POST['due_date'])));
    }

    do_action('alba_save_card_details_admin', $card_id, wp_unslash($_POST));

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

    delete_transient('alba_card_live_admin_' . $card_id);
    delete_transient('alba_card_live_frontend_' . $card_id);

    ob_start();
    alba_output_card_details_admin_modal($card_id);
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

// ==========================================
// UPLOAD / DELETE ATTACHMENTS (V1.3.0 CORE + DIAGNOSTICS)
// ==========================================

add_action('wp_ajax_alba_upload_attachment', 'alba_board_ajax_upload_attachment');
function alba_board_ajax_upload_attachment() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'alba_upload_attachment_nonce')) {
        wp_send_json_error(['message' => esc_html__('Invalid security token.', 'alba-board')]);
    }

    if (!current_user_can('edit_cards')) {
        wp_send_json_error(['message' => esc_html__('Permission denied.', 'alba-board')]);
    }

    $card_id = isset($_POST['card_id']) ? absint($_POST['card_id']) : 0;
    if (get_post_type($card_id) !== 'alba_card') {
        wp_send_json_error(['message' => esc_html__('Invalid card.', 'alba-board')]);
    }
    
    // Exact PHP Error Diagnostics
    if (!isset($_FILES['file'])) {
        wp_send_json_error(['message' => 'PHP Error: No file received. $_FILES array is completely empty.']);
    }
    
    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $err_code = $_FILES['file']['error'];
        $err_msg = 'Upload failed. PHP Error Code: ' . $err_code;
        if ($err_code == UPLOAD_ERR_INI_SIZE) $err_msg = 'The uploaded file exceeds the upload_max_filesize directive in your server php.ini.';
        if ($err_code == UPLOAD_ERR_FORM_SIZE) $err_msg = 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.';
        if ($err_code == UPLOAD_ERR_PARTIAL) $err_msg = 'The uploaded file was only partially uploaded.';
        if ($err_code == UPLOAD_ERR_NO_FILE) $err_msg = 'No file was actually sent in the request (Browser dropped it).';
        if ($err_code == UPLOAD_ERR_NO_TMP_DIR) $err_msg = 'Server Error: Missing a temporary folder.';
        if ($err_code == UPLOAD_ERR_CANT_WRITE) $err_msg = 'Server Error: Failed to write file to disk.';
        if ($err_code == UPLOAD_ERR_EXTENSION) $err_msg = 'Server Error: A PHP extension stopped the file upload.';
        
        wp_send_json_error(['message' => $err_msg]);
    }

    $options = get_option('alba_board_uploads');
    $max_files = isset($options['max_files']) ? intval($options['max_files']) : 3;
    $max_size_mb = isset($options['max_size']) ? intval($options['max_size']) : 20;
    $allowed_formats_str = isset($options['allowed_formats']) ? $options['allowed_formats'] : 'jpg,png,pdf,docx,csv';
    
    if ($max_files <= 0) {
        wp_send_json_error(['message' => esc_html__('File uploads are disabled.', 'alba-board')]);
    }

    // V1.3.0 logic
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
    
    // Clean array formats to avoid empty spaces
    $allowed_formats = array_filter(array_map('trim', explode(',', $allowed_formats_str)));
    
    if (!in_array($file_ext, $allowed_formats)) {
        wp_send_json_error(['message' => esc_html__('Format not allowed. Active formats: ', 'alba-board') . implode(', ', $allowed_formats)]);
    }

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    // Filter to allow specific extensions like CSV securely
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
        wp_send_json_error(['message' => $attachment_id->get_error_message()]);
    }

    // Save using V1.3.0 logic
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

    if (!current_user_can('edit_cards')) {
        wp_send_json_error(['message' => esc_html__('Permission denied.', 'alba-board')]);
    }

    $card_id = isset($_POST['card_id']) ? absint($_POST['card_id']) : 0;
    $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;

    if (!$card_id || !$attachment_id) {
        wp_send_json_error(['message' => esc_html__('Missing data.', 'alba-board')]);
    }

    // Verify ownership using V1.3.0 logic
    $current_attachments = get_post_meta($card_id, 'alba_card_attachments');
    if (!in_array($attachment_id, $current_attachments)) {
        wp_send_json_error(['message' => esc_html__('Attachment does not belong to this card.', 'alba-board')]);
    }

    wp_delete_attachment($attachment_id, true);
    delete_post_meta($card_id, 'alba_card_attachments', $attachment_id);

    delete_transient('alba_card_live_admin_' . $card_id);
    delete_transient('alba_card_live_frontend_' . $card_id);

    wp_send_json_success(['message' => esc_html__('File deleted successfully.', 'alba-board')]);
}