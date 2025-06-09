<?php
// settings.php

// Register the settings page
function alba_board_register_settings_page() {
    add_options_page(
        esc_html__('Alba Board Settings', 'alba-board'),
        esc_html__('Alba Board', 'alba-board'),
        'manage_options',
        'alba-board-settings',
        'alba_board_settings_page_callback'
    );
}
add_action('admin_menu', 'alba_board_register_settings_page');

// Register settings and fields
function alba_board_register_settings() {
    register_setting('alba_board_settings_group', 'alba_board_limits', [
        'type' => 'array',
        'sanitize_callback' => 'alba_board_sanitize_limits'
    ]);
    register_setting('alba_board_settings_group', 'alba_board_notifications', [
        'type' => 'array',
        'sanitize_callback' => 'alba_board_sanitize_notifications'
    ]);

    add_settings_section(
        'alba_board_section_main',
        esc_html__('Limits & Parameters', 'alba-board'),
        null,
        'alba-board-settings'
    );

    add_settings_field(
        'alba_board_limit_cards',
        esc_html__('Maximum cards per list', 'alba-board'),
        'alba_board_limit_cards_callback',
        'alba-board-settings',
        'alba_board_section_main'
    );

    add_settings_section(
        'alba_board_section_notifications',
        esc_html__('Notifications', 'alba-board'),
        null,
        'alba-board-settings'
    );

    add_settings_field(
        'alba_board_notify',
        esc_html__('Notify when creating a new card', 'alba-board'),
        'alba_board_notify_callback',
        'alba-board-settings',
        'alba_board_section_notifications'
    );
}
add_action('admin_init', 'alba_board_register_settings');

// Sanitize limits settings
function alba_board_sanitize_limits($input) {
    $output = [];
    $output['limit_cards'] = isset($input['limit_cards']) ? intval($input['limit_cards']) : '';
    return $output;
}

// Sanitize notifications settings
function alba_board_sanitize_notifications($input) {
    $output = [];
    $output['notify_on_card'] = !empty($input['notify_on_card']) ? 1 : 0;
    return $output;
}

function alba_board_limit_cards_callback() {
    $options = get_option('alba_board_limits');
    $value = isset($options['limit_cards']) ? intval($options['limit_cards']) : '';
    echo '<input type="number" name="alba_board_limits[limit_cards]" value="' . esc_attr($value) . '" min="1">';
}

function alba_board_notify_callback() {
    $options = get_option('alba_board_notifications');
    $enabled = !empty($options['notify_on_card']) ? 1 : 0;
    echo '<input type="checkbox" name="alba_board_notifications[notify_on_card]" value="1" ' . checked(1, $enabled, false) . '> ';
    esc_html_e('Enable email notifications', 'alba-board');
}

// Callback for the options page
function alba_board_settings_page_callback() {
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Alba Board Settings', 'alba-board') . '</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('alba_board_settings_group');
    do_settings_sections('alba-board-settings');
    submit_button();
    echo '</form>';
    echo '</div>';
}

// Notifications are handled in includes/notifications.php to keep things modular.