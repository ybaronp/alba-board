<?php
// includes/ajax-upload-attachment.php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('wp_ajax_alba_upload_attachment', 'alba_board_ajax_upload_attachment');

function alba_board_ajax_upload_attachment() {
    // --- INICIO DE DEBUGGING ---
    // Si llegamos aquí, el hook sí existe y WP lo ruteó correctamente.
    error_log('ALBA BOARD DEBUG: Endpoint de subida alcanzado.');
    error_log('ALBA BOARD DEBUG $_POST: ' . print_r($_POST, true));
    error_log('ALBA BOARD DEBUG $_FILES: ' . print_r($_FILES, true));
    // --- FIN DE DEBUGGING ---

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'alba_upload_attachment_nonce')) {
        error_log('ALBA BOARD DEBUG: Fallo de Nonce.');
        wp_send_json_error(['message' => esc_html__('Invalid security token.', 'alba-board')]);
    }

    if (!current_user_can('edit_cards')) {
        error_log('ALBA BOARD DEBUG: Fallo de Permisos.');
        wp_send_json_error(['message' => esc_html__('Permission denied.', 'alba-board')]);
    }

    $card_id = isset($_POST['card_id']) ? absint($_POST['card_id']) : 0;
    if (get_post_type($card_id) !== 'alba_card') {
        error_log('ALBA BOARD DEBUG: Tarjeta inválida ID: ' . $card_id);
        wp_send_json_error(['message' => esc_html__('Invalid card.', 'alba-board')]);
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error_code = isset($_FILES['file']['error']) ? $_FILES['file']['error'] : 'No file received';
        error_log('ALBA BOARD DEBUG: Error de archivo. Código: ' . $error_code);
        wp_send_json_error(['message' => esc_html__('Error uploading file (Code: ' . $error_code . '). Check server limits.', 'alba-board')]);
    }

    $options = get_option('alba_board_uploads');
    $max_files = isset($options['max_files']) ? intval($options['max_files']) : 3;
    $max_size_mb = isset($options['max_size']) ? intval($options['max_size']) : 2;
    $allowed_formats_str = isset($options['allowed_formats']) ? $options['allowed_formats'] : 'jpg,png,pdf,docx';
    
    $current_attachments = get_attached_media('', $card_id);
    if (count($current_attachments) >= $max_files) {
        wp_send_json_error(['message' => sprintf(esc_html__('Maximum of %d files allowed.', 'alba-board'), $max_files)]);
    }

    $file_size = isset($_FILES['file']['size']) ? absint($_FILES['file']['size']) : 0;
    if ($file_size > ($max_size_mb * 1024 * 1024)) {
        wp_send_json_error(['message' => sprintf(esc_html__('File exceeds the limit of %d MB.', 'alba-board'), $max_size_mb)]);
    }

    $file_name = sanitize_file_name(wp_unslash($_FILES['file']['name']));
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_formats = array_map('trim', explode(',', $allowed_formats_str));
    
    if (!in_array($file_ext, $allowed_formats)) {
        wp_send_json_error(['message' => esc_html__('Invalid file format.', 'alba-board')]);
    }

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $attachment_id = media_handle_upload('file', $card_id);

    if (is_wp_error($attachment_id)) {
        error_log('ALBA BOARD DEBUG: Error en media_handle_upload: ' . $attachment_id->get_error_message());
        wp_send_json_error(['message' => $attachment_id->get_error_message()]);
    }

    delete_transient('alba_card_live_admin_' . $card_id);
    delete_transient('alba_card_live_frontend_' . $card_id);

    error_log('ALBA BOARD DEBUG: Subida exitosa. ID: ' . $attachment_id);
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
    $attachment = get_post($attachment_id);

    if (!$attachment || (int) $attachment->post_parent !== $card_id) {
        wp_send_json_error(['message' => esc_html__('Attachment does not belong to this card.', 'alba-board')]);
    }

    wp_delete_attachment($attachment_id, true);
    delete_transient('alba_card_live_admin_' . $card_id);
    delete_transient('alba_card_live_frontend_' . $card_id);
    wp_send_json_success(['message' => esc_html__('File deleted successfully.', 'alba-board')]);
}