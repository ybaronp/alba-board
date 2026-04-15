<?php
// includes/settings.php
if ( ! defined( 'ABSPATH' ) ) exit; // Prevent direct access

function alba_board_register_settings_page() {
    add_submenu_page(
        'alba-board-visual', 
        esc_html__('Settings', 'alba-board'),
        esc_html__('Settings', 'alba-board'),
        'manage_options',
        'alba-board-settings',
        'alba_board_settings_page_callback'
    );
}
add_action('admin_menu', 'alba_board_register_settings_page');

function alba_board_register_settings() {
    register_setting('alba_board_settings_group', 'alba_board_limits', ['type' => 'array', 'sanitize_callback' => 'alba_board_sanitize_limits']);
    register_setting('alba_board_settings_group', 'alba_board_notifications', ['type' => 'array', 'sanitize_callback' => 'alba_board_sanitize_notifications']);
    register_setting('alba_board_settings_group', 'alba_delete_on_uninstall', ['type' => 'boolean', 'sanitize_callback' => 'absint', 'default' => 0]);
    register_setting('alba_board_settings_group', 'alba_board_uploads', ['type' => 'array', 'sanitize_callback' => 'alba_board_sanitize_uploads']);
    register_setting('alba_board_settings_group', 'alba_board_display', ['type' => 'array', 'sanitize_callback' => 'alba_board_sanitize_display']);

    // Display & Themes Section
    add_settings_section('alba_board_section_display', esc_html__('Display & Themes', 'alba-board'), null, 'alba-board-settings');
    add_settings_field('alba_board_theme', esc_html__('Visual Theme', 'alba-board'), 'alba_board_theme_callback', 'alba-board-settings', 'alba_board_section_display');
    add_settings_field('alba_board_show_avatars', esc_html__('User Avatars', 'alba-board'), 'alba_board_show_avatars_callback', 'alba-board-settings', 'alba_board_section_display');

    // Limits Section
    add_settings_section('alba_board_section_main', esc_html__('Limits & Parameters', 'alba-board'), null, 'alba-board-settings');
    add_settings_field('alba_board_limit_cards', esc_html__('Maximum cards per list', 'alba-board'), 'alba_board_limit_cards_callback', 'alba-board-settings', 'alba_board_section_main');

    // Uploads Section
    add_settings_section('alba_board_section_uploads', esc_html__('File Uploads (Attachments)', 'alba-board'), null, 'alba-board-settings');
    add_settings_field('alba_board_max_files', esc_html__('Max files per card', 'alba-board'), 'alba_board_max_files_callback', 'alba-board-settings', 'alba_board_section_uploads');
    add_settings_field('alba_board_max_size', esc_html__('Max file size (MB)', 'alba-board'), 'alba_board_max_size_callback', 'alba-board-settings', 'alba_board_section_uploads');
    add_settings_field('alba_board_allowed_formats', esc_html__('Allowed formats', 'alba-board'), 'alba_board_allowed_formats_callback', 'alba-board-settings', 'alba_board_section_uploads');

    // Notifications Section (Triggers & Content)
    add_settings_section('alba_board_section_notifications', esc_html__('Notifications (Triggers & Content)', 'alba-board'), 'alba_board_section_notifications_desc', 'alba-board-settings');
    add_settings_field('alba_board_notify_new', esc_html__('Notify on new card assignment', 'alba-board'), 'alba_board_notify_new_callback', 'alba-board-settings', 'alba_board_section_notifications');
    add_settings_field('alba_board_notify_template_new', esc_html__('New Card Template', 'alba-board'), 'alba_board_notify_template_new_callback', 'alba-board-settings', 'alba_board_section_notifications');
    add_settings_field('alba_board_notify_comment', esc_html__('Notify on new comment', 'alba-board'), 'alba_board_notify_comment_callback', 'alba-board-settings', 'alba_board_section_notifications');
    add_settings_field('alba_board_notify_template_comment', esc_html__('New Comment Template', 'alba-board'), 'alba_board_notify_template_comment_callback', 'alba-board-settings', 'alba_board_section_notifications');

    // Uninstall Options Section
    add_settings_section('alba_board_section_uninstall', esc_html__('Uninstall Options', 'alba-board'), null, 'alba-board-settings');
    add_settings_field('alba_delete_on_uninstall', esc_html__('Delete all Alba Board data on uninstall', 'alba-board'), 'alba_board_delete_on_uninstall_callback', 'alba-board-settings', 'alba_board_section_uninstall');
}
add_action('admin_init', 'alba_board_register_settings');

// Sanitization Functions
function alba_board_sanitize_limits($input) { 
    $output = []; 
    $output['limit_cards'] = isset($input['limit_cards']) ? intval($input['limit_cards']) : ''; 
    return $output; 
}

function alba_board_sanitize_notifications($input) { 
    $output = []; 
    $output['notify_on_card'] = !empty($input['notify_on_card']) ? 1 : 0; 
    $output['notify_on_comment'] = !empty($input['notify_on_comment']) ? 1 : 0; 
    
    // Allow basic HTML in templates
    $allowed_html = array(
        'a' => array('href' => array(), 'title' => array(), 'style' => array()),
        'br' => array(), 'em' => array(), 'strong' => array(),
        'p' => array('style' => array()), 'div' => array('style' => array()), 'span' => array('style' => array()),
        'h1' => array('style' => array()), 'h2' => array('style' => array()), 'h3' => array('style' => array()),
    );
    $output['template_new_card'] = isset($input['template_new_card']) ? wp_kses($input['template_new_card'], $allowed_html) : '';
    $output['template_new_comment'] = isset($input['template_new_comment']) ? wp_kses($input['template_new_comment'], $allowed_html) : '';
    
    return $output; 
}

function alba_board_sanitize_uploads($input) {
    $output = [];
    $output['max_files'] = isset($input['max_files']) ? absint($input['max_files']) : 3;
    $output['max_size'] = isset($input['max_size']) ? absint($input['max_size']) : 2;
    $output['allowed_formats'] = isset($input['allowed_formats']) ? preg_replace('/[^a-zA-Z0-9,]/', '', strtolower($input['allowed_formats'])) : 'jpg,png,pdf,docx'; 
    return $output;
}

// 👉 AQUI EL CORE RECIBE PERMISO DEL ADD-ON PARA GUARDAR TEMAS PRO
function alba_board_sanitize_display($input) {
    $output = [];
    $output['show_avatars'] = isset($input['show_avatars']) ? 1 : 0;
    
    // Temas por defecto (Gratis)
    $allowed_themes = ['default', 'space', 'cosmic_dawn'];
    
    // El Add-on inyectará los nombres de los temas Pro aquí a través de este filtro:
    $allowed_themes = apply_filters('alba_board_allowed_themes', $allowed_themes);
    
    $output['theme'] = in_array($input['theme'], $allowed_themes) ? $input['theme'] : 'default';
    return $output;
}

// Field Callbacks (Visuals, Limits, Uploads)
function alba_board_theme_callback() {
    $options = get_option('alba_board_display', ['show_avatars' => 1, 'theme' => 'default']);
    $theme = isset($options['theme']) ? $options['theme'] : 'default';
    
    // 👉 VERIFICAMOS SI EL ADDON ESTÁ ACTIVO
    $has_pro = apply_filters('alba_board_has_pro_themes', false);
    $disabled_attr = $has_pro ? '' : ' disabled title="Requires Customization & Smart Tags Add-on"';
    $pro_badge = $has_pro ? '' : ' 🔒 (Pro)';

    echo '<select name="alba_board_display[theme]" style="min-width: 250px;">';
    
    echo '<optgroup label="Core Themes (Free)">';
    echo '<option value="default" ' . selected('default', $theme, false) . '>' . esc_html__('Clean Canvas (Light)', 'alba-board') . '</option>';
    echo '<option value="space" ' . selected('space', $theme, false) . '>' . esc_html__('Deep Space (Dark)', 'alba-board') . '</option>';
    echo '<option value="cosmic_dawn" ' . selected('cosmic_dawn', $theme, false) . '>' . esc_html__('Cosmic Dawn (Alba Brand)', 'alba-board') . '</option>';
    echo '</optgroup>';

    echo '<optgroup label="Premium Editions (Pro)">';
    echo '<option value="alba" ' . selected('alba', $theme, false) . $disabled_attr . '>' . esc_html__('Alba Sunrise (Warm)', 'alba-board') . $pro_badge . '</option>';
    echo '<option value="stellar_earth" ' . selected('stellar_earth', $theme, false) . $disabled_attr . '>' . esc_html__('Stellar Earth (Ocean & Forest)', 'alba-board') . $pro_badge . '</option>';
    echo '<option value="luminous_aura" ' . selected('luminous_aura', $theme, false) . $disabled_attr . '>' . esc_html__('Luminous Aura (Trend Light)', 'alba-board') . $pro_badge . '</option>';
    echo '<option value="bioluminescent" ' . selected('bioluminescent', $theme, false) . $disabled_attr . '>' . esc_html__('Bioluminescent (Trend Dark)', 'alba-board') . $pro_badge . '</option>';
    echo '<option value="cyberpunk_neon" ' . selected('cyberpunk_neon', $theme, false) . $disabled_attr . '>' . esc_html__('Cyberpunk Neon (Vibrant Dark)', 'alba-board') . $pro_badge . '</option>';
    echo '<option value="vaporwave" ' . selected('vaporwave', $theme, false) . $disabled_attr . '>' . esc_html__('Cotton Candy (Pastel Glass)', 'alba-board') . $pro_badge . '</option>';
    echo '<option value="zen_monochrome" ' . selected('zen_monochrome', $theme, false) . $disabled_attr . '>' . esc_html__('Zen Monochrome (Minimalist)', 'alba-board') . $pro_badge . '</option>';
    echo '</optgroup>';
    
    echo '</select>';

    if (!$has_pro) {
        echo '<p class="description" style="color: #ea580c; font-weight: 500;">Unlock Premium Themes and Smart Tags by installing the <strong>Customization & Smart Tags</strong> Add-on.</p>';
    }
}

function alba_board_show_avatars_callback() { 
    $display_opts = get_option('alba_board_display');
    $show = !empty($display_opts['show_avatars']) ? 1 : 0;
    echo '<input type="checkbox" name="alba_board_display[show_avatars]" value="1" ' . checked(1, $show, false) . '>';
    echo '<p class="description">' . esc_html__('Display user avatars on cards.', 'alba-board') . '</p>';
}

function alba_board_limit_cards_callback() { 
    $options = get_option('alba_board_limits'); 
    $val = isset($options['limit_cards']) ? esc_attr($options['limit_cards']) : ''; 
    echo '<input type="number" name="alba_board_limits[limit_cards]" value="' . $val . '" min="0" placeholder="' . esc_attr__('No limit', 'alba-board') . '">'; 
    echo '<p class="description">' . esc_html__('Leave empty or 0 for unlimited cards per list.', 'alba-board') . '</p>';
}

function alba_board_max_files_callback() { 
    $options = get_option('alba_board_uploads'); 
    $val = isset($options['max_files']) ? esc_attr($options['max_files']) : 3; 
    echo '<input type="number" name="alba_board_uploads[max_files]" value="' . $val . '" min="0" max="20">'; 
    echo '<p class="description">' . esc_html__('Maximum number of attachments allowed per card.', 'alba-board') . '</p>';
}

function alba_board_max_size_callback() { 
    $options = get_option('alba_board_uploads'); 
    $val = isset($options['max_size']) ? esc_attr($options['max_size']) : 2; 
    echo '<input type="number" name="alba_board_uploads[max_size]" value="' . $val . '" min="1" max="100">'; 
    echo '<p class="description">' . esc_html__('Maximum file size in Megabytes (MB).', 'alba-board') . '</p>';
}

function alba_board_allowed_formats_callback() { 
    $options = get_option('alba_board_uploads'); 
    $val = isset($options['allowed_formats']) ? esc_attr($options['allowed_formats']) : 'jpg,png,pdf,docx'; 
    echo '<input type="text" name="alba_board_uploads[allowed_formats]" value="' . $val . '" class="regular-text">'; 
    echo '<p class="description">' . esc_html__('Comma-separated list of allowed extensions (e.g., jpg,png,pdf).', 'alba-board') . '</p>';
}

// Notification Callbacks
function alba_board_section_notifications_desc() {
    echo '<p>' . esc_html__('Configure when emails are sent and customize their content using HTML. Allowed placeholders:', 'alba-board') . '<br><code>{user_name}</code>, <code>{card_title}</code>, <code>{card_content}</code>, <code>{card_link}</code>, <code>{comment_text}</code> (only for comments).</p>';
}

function alba_board_notify_new_callback() { 
    $options = get_option('alba_board_notifications', false); 
    // Default to 1 (checked) if it has never been saved before
    $enabled = ($options === false || !empty($options['notify_on_card'])) ? 1 : 0; 
    echo '<input type="checkbox" name="alba_board_notifications[notify_on_card]" value="1" ' . checked(1, $enabled, false) . '>'; 
}

function alba_board_notify_comment_callback() { 
    $options = get_option('alba_board_notifications', false); 
    // Default to 1 (checked) if it has never been saved before
    $enabled = ($options === false || !empty($options['notify_on_comment'])) ? 1 : 0; 
    echo '<input type="checkbox" name="alba_board_notifications[notify_on_comment]" value="1" ' . checked(1, $enabled, false) . '>'; 
}

function alba_board_render_template_editor($field_name, $default_template) {
    $options = get_option('alba_board_notifications');
    $value = !empty($options[$field_name]) ? $options[$field_name] : $default_template;
    $preview_id = 'preview_' . $field_name;
    $textarea_id = 'textarea_' . $field_name;
    
    echo '<div style="display: flex; gap: 20px; align-items: flex-start; max-width: 900px;">';
    
    // Editor Side
    echo '<div style="flex: 1;">';
    echo '<textarea id="' . esc_attr($textarea_id) . '" name="alba_board_notifications[' . esc_attr($field_name) . ']" rows="14" style="width: 100%; font-family: monospace; padding: 10px; background: #f8fafc; font-size: 13px;">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">Edit the HTML. The preview will update automatically.</p>';
    echo '</div>';
    
    // Preview Side
    echo '<div style="flex: 1; padding: 0; background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; box-shadow: inset 0 2px 4px 0 rgb(0 0 0 / 0.05); overflow: hidden;">';
    echo '<h4 style="margin: 0; padding: 10px 15px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 12px; text-transform: uppercase;">Live Preview</h4>';
    echo '<div id="' . esc_attr($preview_id) . '" style="all: initial; font-family: sans-serif; color: #334155; display: block; overflow: auto; max-height: 500px;"></div>';
    echo '</div>';
    
    echo '</div>';

    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const textarea = document.getElementById('<?php echo esc_js($textarea_id); ?>');
        const preview = document.getElementById('<?php echo esc_js($preview_id); ?>');
        
        function updatePreview() {
            let html = textarea.value;
            html = html.replace(/{user_name}/g, 'John Doe');
            html = html.replace(/{card_title}/g, 'Homepage Redesign');
            html = html.replace(/{card_content}/g, 'Please make sure to review the new hero banner assets attached to this card.');
            html = html.replace(/{card_link}/g, '#');
            html = html.replace(/{comment_text}/g, 'I just finished the initial draft. Let me know what you think!');
            preview.innerHTML = html;
        }
        
        textarea.addEventListener('input', updatePreview);
        updatePreview(); // Init on load
    });
    </script>
    <?php
}

function alba_board_notify_template_new_callback() {
    $default = <<<HTML
<div style="background-color: #f1f5f9; padding: 40px 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
    <div style="background: linear-gradient(135deg, #0f172a 0%, #3730a3 50%, #3b82f6 100%); padding: 30px; text-align: center;">
      <h2 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 800; letter-spacing: 2px;">New Card</h2>
    </div>
    <div style="padding: 40px 30px;">
      <p style="margin-top: 0; font-size: 16px; color: #334155;">Hello <strong>{user_name}</strong>,</p>
      <p style="font-size: 16px; color: #475569; margin-bottom: 25px;">You have a new task waiting on your board. Here are the main details:</p>
      <div style="background-color: #f8fafc; border-left: 4px solid #3730a3; border-radius: 6px; padding: 25px; margin-bottom: 35px;">
        <h3 style="margin-top: 0; margin-bottom: 15px; color: #0f172a; font-size: 20px;">{card_title}</h3>
        <div style="color: #475569; font-size: 15px; line-height: 1.6;">
          {card_content}
        </div>
      </div>
      <div style="text-align: center;">
        <a href="{card_link}" style="display: inline-block; background-color: #0f172a; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 600; padding: 14px 32px; border-radius: 8px; text-transform: uppercase; letter-spacing: 1px;">View Card</a>
      </div>
    </div>
    <div style="background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;">
      <p style="margin: 0; font-size: 12px; color: #94a3b8;">
        Notification automatically generated by <strong>Alba Board</strong>
      </p>
    </div>
  </div>
</div>
HTML;

    alba_board_render_template_editor('template_new_card', $default);
}

function alba_board_notify_template_comment_callback() {
    $default = <<<HTML
<div style="background-color: #f1f5f9; padding: 40px 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
    <div style="background: linear-gradient(135deg, #0f172a 0%, #3730a3 50%, #3b82f6 100%); padding: 30px; text-align: center;">
      <h2 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 800; letter-spacing: 2px;">New Comment</h2>
    </div>
    <div style="padding: 40px 30px;">
      <p style="margin-top: 0; font-size: 16px; color: #334155;">Hello <strong>{user_name}</strong>,</p>
      <p style="font-size: 16px; color: #475569; margin-bottom: 25px;">Someone left a new comment on the task <strong>{card_title}</strong>:</p>
      <div style="background-color: #f8fafc; border-left: 4px solid #3b82f6; border-radius: 6px; padding: 25px; margin-bottom: 35px; font-style: italic;">
        <div style="color: #475569; font-size: 15px; line-height: 1.6;">
          "{comment_text}"
        </div>
      </div>
      <div style="text-align: center;">
        <a href="{card_link}" style="display: inline-block; background-color: #0f172a; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 600; padding: 14px 32px; border-radius: 8px; text-transform: uppercase; letter-spacing: 1px;">Reply to Comment</a>
      </div>
    </div>
    <div style="background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;">
      <p style="margin: 0; font-size: 12px; color: #94a3b8;">
        Notification automatically generated by <strong>Alba Board</strong>
      </p>
    </div>
  </div>
</div>
HTML;

    alba_board_render_template_editor('template_new_comment', $default);
}

// Uninstall Callback
function alba_board_delete_on_uninstall_callback() { 
    $value = get_option('alba_delete_on_uninstall', 0); 
    echo '<input type="checkbox" name="alba_delete_on_uninstall" value="1" ' . checked(1, $value, false) . '>'; 
}

// Main Page Render
function alba_board_settings_page_callback() {
    echo '<div class="wrap"><h1>' . esc_html__('Alba Board Settings', 'alba-board') . '</h1><form method="post" action="options.php">';
    settings_fields('alba_board_settings_group'); 
    do_settings_sections('alba-board-settings'); 
    submit_button();
    echo '</form></div>';
}