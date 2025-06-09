<?php
// security.php

// Restrict direct access to files
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Validate permissions when saving lists or cards
function alba_board_check_permissions_before_save($post_id, $post) {
    // Skip autosave, revisions, and autosaves
    if (
        defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ||
        defined('DOING_AJAX') && DOING_AJAX ||
        wp_is_post_revision($post_id) ||
        wp_is_post_autosave($post_id)
    ) {
        return;
    }

    $post_type = $post->post_type;
    $cap_map = [
        'alba_board' => 'edit_board',
        'alba_list'  => 'edit_list',
        'alba_card'  => 'edit_card',
    ];

    if ( ! isset($cap_map[$post_type]) ) {
        return;
    }

    // Check user capability
    if ( ! current_user_can($cap_map[$post_type], $post_id) ) {
        wp_die(
            esc_html__('You do not have permission to perform this action.', 'alba-board'),
            esc_html__('Permission denied', 'alba-board'),
            ['response' => 403]
        );
    }
}
add_action('save_post', 'alba_board_check_permissions_before_save', 1, 2);

// Validate uploaded files (optional)
function alba_board_validate_uploaded_file($file) {
    $max_size = 5 * 1024 * 1024; // 5MB
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];

    // Validate file size
    if ( isset($file['size']) && $file['size'] > $max_size ) {
        $file['error'] = esc_html__('The file exceeds the maximum allowed size (5MB).', 'alba-board');
    }

    // Validate file type
    if ( isset($file['type']) && ! in_array($file['type'], $allowed_types, true) ) {
        $file['error'] = esc_html__('File type not allowed.', 'alba-board');
    }

    return $file;
}
add_filter('wp_handle_upload_prefilter', 'alba_board_validate_uploaded_file');