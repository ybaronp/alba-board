<?php
/**
 * includes/ajax-upload-attachment.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('wp_ajax_alba_upload_attachment', 'alba_board_ajax_upload_attachment');

function alba_board_ajax_upload_attachment() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'alba_upload_attachment_nonce')) {
        wp_send_json_error(['message' => esc_html__('Invalid security token.', 'alba-board')]);
    }

    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_send_json_error(['message' => esc_html__('Permission denied.', 'alba-board')]);
    }

    $card_id = isset($_POST['card_id']) ? absint($_POST['card_id']) : 0;
    $card = get_post($card_id);
    if (!$card || $card->post_type !== 'alba_card') {
        wp_send_json_error(['message' => esc_html__('Invalid card.', 'alba-board')]);
    }

    // Standard array key verification
    $file_key = 'file';

    if (!isset($_FILES[$file_key])) {
        wp_send_json_error(['message' => esc_html__('No file payload received by the server. Check form data or server limits.', 'alba-board')]);
    }

    if ($_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        $err_code = $_FILES[$file_key]['error'];
        $err_msg = 'Upload error code: ' . $err_code;
        
        if ($err_code === UPLOAD_ERR_INI_SIZE) $err_msg = 'File exceeds upload_max_filesize in php.ini.';
        if ($err_code === UPLOAD_ERR_FORM_SIZE) $err_msg = 'File exceeds MAX_FILE_SIZE limit.';
        if ($err_code === UPLOAD_ERR_PARTIAL) $err_msg = 'File was only partially uploaded.';
        if ($err_code === UPLOAD_ERR_NO_FILE) $err_msg = 'No file was actually uploaded.';
        
        wp_send_json_error(['message' => esc_html__($err_msg, 'alba-board')]);
    }

    $options = get_option('alba_board_uploads');
    $max_files = isset($options['max_files']) ? intval($options['max_files']) : 3;
    $max_size_mb = isset($options['max_size']) ? intval($options['max_size']) : 2;
    $allowed_formats_str = isset($options['allowed_formats']) ? $options['allowed_formats'] : 'jpg,png,pdf,docx';
    
    if ($max_files <= 0) {
        wp_send_json_error(['message' => esc_html__('File uploads are disabled.', 'alba-board')]);
    }

    $current_attachments = get_post_meta($card_id, 'alba_card_attachments');
    if (count($current_attachments) >= $max_files) {
        wp_send_json_error(['message' => sprintf(esc_html__('Maximum of %d files allowed per card.', 'alba-board'), $max_files)]);
    }

    $file_size = isset($_FILES[$file_key]['size']) ? absint($_FILES[$file_key]['size']) : 0;
    $max_size_bytes = $max_size_mb * 1024 * 1024;
    
    if ($file_size > $max_size_bytes) {
        wp_send_json_error(['message' => sprintf(esc_html__('File size exceeds the maximum limit of %d MB.', 'alba-board'), $max_size_mb)]);
    }

    $file_name = isset($_FILES[$file_key]['name']) ? sanitize_file_name(wp_unslash($_FILES[$file_key]['name'])) : '';
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_formats = array_map('trim', explode(',', $allowed_formats_str));
    
    if (!in_array($file_ext, $allowed_formats)) {
        wp_send_json_error(['message' => esc_html__('Invalid file format. Allowed formats: ', 'alba-board') . $allowed_formats_str]);
    }

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $attachment_id = media_handle_upload($file_key, $card_id);

    if (is_wp_error($attachment_id)) {
        wp_send_json_error(['message' => $attachment_id->get_error_message()]);
    }

    add_post_meta($card_id, 'alba_card_attachments', $attachment_id);

    $file_url = wp_get_attachment_url($attachment_id);
    $file_title = get_the_title($attachment_id);

    wp_send_json_success([
        'message'       => esc_html__('File uploaded successfully.', 'alba-board'),
        'attachment_id' => $attachment_id,
        'file_url'      => $file_url,
        'file_name'     => $file_title,
        'file_ext'      => $file_ext
    ]);
}

add_action('wp_ajax_alba_delete_attachment', 'alba_board_ajax_delete_attachment');

function alba_board_ajax_delete_attachment() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'alba_delete_attachment_nonce')) {
        wp_send_json_error(['message' => esc_html__('Invalid security token.', 'alba-board')]);
    }

    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_send_json_error(['message' => esc_html__('Permission denied.', 'alba-board')]);
    }

    $card_id = isset($_POST['card_id']) ? absint($_POST['card_id']) : 0;
    $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;

    if (!$card_id || !$attachment_id) {
        wp_send_json_error(['message' => esc_html__('Missing data.', 'alba-board')]);
    }

    $current_attachments = get_post_meta($card_id, 'alba_card_attachments');
    if (!in_array($attachment_id, $current_attachments)) {
        wp_send_json_error(['message' => esc_html__('Attachment does not belong to this card.', 'alba-board')]);
    }

    wp_delete_attachment($attachment_id, true);
    delete_post_meta($card_id, 'alba_card_attachments', $attachment_id);

    wp_send_json_success(['message' => esc_html__('File deleted successfully.', 'alba-board')]);
}