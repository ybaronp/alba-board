<?php
// includes/ajax-card-details.php

if ( ! defined( 'ABSPATH' ) ) exit;

// AJAX actions for logged in and guests
add_action('wp_ajax_alba_get_card_details', 'alba_board_get_card_details_ajax');
add_action('wp_ajax_nopriv_alba_get_card_details', 'alba_board_get_card_details_ajax');

function alba_board_get_card_details_ajax() {
    // Solo permite usuarios logueados
    if ( ! is_user_logged_in() ) {
        echo esc_html__('Login required.', 'alba-board');
        wp_die();
    }

    // Sanitize and verify nonce from GET
    $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'alba_get_card_details')) {
        echo esc_html__('Invalid nonce.', 'alba-board');
        wp_die();
    }

    $card_id = isset($_GET['card_id']) ? absint($_GET['card_id']) : 0;
    if (!$card_id) {
        echo esc_html__('Invalid card id.', 'alba-board');
        wp_die();
    }

    $card = get_post($card_id);
    if (!$card || $card->post_type !== 'alba_card') {
        echo esc_html__('Card not found.', 'alba-board');
        wp_die();
    }

    // Detect if frontend add-on is active
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    $addon_active = is_plugin_active('alba-board-frontend-interactions/alba-board-frontend-interactions.php');
    ?>
    <div class="alba-modal-inner">
        <h2 class="alba-modal-title"><?php echo esc_html($card->post_title); ?></h2>
        <?php
        // Assignee (Author)
        $author_id = $card->post_author;
        $user = get_user_by('ID', $author_id);
        if ($user): ?>
            <div class="alba-assignee">
                <strong><?php esc_html_e('Assignee:', 'alba-board'); ?></strong>
                <?php echo esc_html($user->display_name); ?>
                <span class="alba-assignee-email">(<?php echo esc_html($user->user_email); ?>)</span>
            </div>
        <?php endif; ?>

        <div class="alba-description">
            <?php
            $description = trim($card->post_content);
            if ($description !== '') {
                echo nl2br(esc_html($description));
            } else {
                echo '<span>' . esc_html__('No description.', 'alba-board') . '</span>';
            }
            ?>
        </div>

        <!-- Tags Section -->
        <?php
        $tags = get_the_terms($card_id, 'alba_tag');
        if (!empty($tags) && !is_wp_error($tags)): ?>
            <div class="alba-card-tags">
                <?php foreach ($tags as $tag):
                    $bg = get_term_meta($tag->term_id, 'alba_tag_bg_color', true) ?: '#eee';
                    $text = get_term_meta($tag->term_id, 'alba_tag_text_color', true) ?: '#000';
                    ?>
                    <span class="alba-card-tag-chip" style="<?php echo 'background:' . esc_attr($bg) . '; color:' . esc_attr($text) . ';'; ?>">
                        <?php echo esc_html($tag->name); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Comments Section -->
        <div class="comments-title"><?php esc_html_e('Comments:', 'alba-board'); ?></div>
        <div class="alba-card-comments-scrollable" id="alba-comments-list">
            <?php
            $comments = get_post_meta($card_id, 'alba_comments', true);
            if (!is_array($comments)) {
                $comments = @unserialize($comments);
                if (!is_array($comments)) $comments = [];
            }
            if (!empty($comments)) {
                foreach ($comments as $i => $c) {
                    $author = isset($c['author']) ? $c['author'] : '';
                    $date = isset($c['date']) ? $c['date'] : '';
                    $text = isset($c['text']) ? $c['text'] : '';
                    ?>
                    <div class="alba-board-comment" data-comment-index="<?php echo esc_attr($i); ?>">
                        <span class="alba-comment-author"><strong><?php echo esc_html($author); ?></strong></span>
                        <span class="alba-comment-date"><?php echo esc_html($date); ?></span>
                        <div class="alba-comment-text"><?php echo esc_html($text); ?></div>
                    </div>
                    <?php
                }
            } else {
                echo '<div class="alba-no-comments">' . esc_html__('No comments yet.', 'alba-board') . '</div>';
            }
            ?>
        </div>

        <!-- Add Comment (only if add-on is active) -->
        <?php if ($addon_active): ?>
            <div class="alba-add-comment-section">
                <textarea id="alba-new-comment-text" data-card-id="<?php echo esc_attr($card_id); ?>" rows="2" placeholder="<?php esc_attr_e('Write a comment...', 'alba-board'); ?>"></textarea>
                <button id="alba-add-comment-btn"><?php esc_html_e('Add comment', 'alba-board'); ?></button>
                <div id="alba-comment-feedback"></div>
            </div>
            <div class="alba-modal-delete-section">
                <button id="alba-modal-delete" class="alba-modal-delete-btn" title="<?php esc_attr_e('Delete', 'alba-board'); ?>">&#128465;</button>
            </div>
        <?php endif; ?>

        <!-- Custom fields (metadata) -->
        <div class="alba-modal-custom-fields">
        <?php
            $custom_fields = get_post_meta($card_id);
            $excluded_keys = [ '_edit_lock', '_edit_last', '_ai_suggestion', 'alba_list_parent', 'alba_comments' ];
            $output_any = false;
            foreach ($custom_fields as $key => $values) {
                if (strpos($key, '_') === 0 && !in_array($key, ['_ai_suggestion'])) continue;
                if (in_array($key, $excluded_keys)) continue;
                $output_any = true;
                $value = isset($values[0]) ? $values[0] : '';
                ?>
                <div class="alba-modal-custom-field">
                    <strong><?php echo esc_html($key); ?>:</strong> <?php echo esc_html($value); ?>
                </div>
                <?php
            }
            if (!$output_any) {
                echo '<div class="alba-no-custom-fields">' . esc_html__('No custom fields.', 'alba-board') . '</div>';
            }
        ?>
        </div>
    </div>
    <?php
    wp_die();
}