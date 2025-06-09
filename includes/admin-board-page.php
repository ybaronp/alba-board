<?php
// includes/admin-board-page.php

add_action('admin_menu', 'alba_register_admin_board_page');

function alba_register_admin_board_page() {
    add_menu_page(
        esc_html__('Kanban View', 'alba-board'),
        esc_html__('Kanban View', 'alba-board'),
        'edit_posts',
        'alba-board-visual',
        'alba_render_admin_board_page',
        'dashicons-layout',
        30
    );
}

function alba_render_admin_board_page() {
    $boards = get_posts([
        'post_type'   => 'alba_board',
        'numberposts' => -1,
        'orderby'     => 'title',
        'order'       => 'ASC'
    ]);

    // Nonce verification before reading board_id
    $selected_board_id = 0;
    if (isset($_GET['board_id']) && isset($_GET['_wpnonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
        if (wp_verify_nonce($nonce, 'alba_select_board_nonce')) {
            $selected_board_id = intval($_GET['board_id']);
        }
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Kanban View', 'alba-board') . '</h1>';

    // FORM TO SELECT BOARD (GET)
    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="alba-board-visual">';
    echo '<label style="margin-right:8px;">' . esc_html__('Select a board:', 'alba-board') . '</label>';
    // Nonce field for GET requests
    wp_nonce_field('alba_select_board_nonce');
    echo '<select name="board_id" onchange="this.form.submit()">';
    echo '<option value="">' . esc_html__('-- Select --', 'alba-board') . '</option>';
    foreach ($boards as $board) {
        echo '<option value="' . esc_attr($board->ID) . '"';
        if ($board->ID === $selected_board_id) {
            echo ' selected="selected"';
        }
        echo '>' . esc_html($board->post_title) . '</option>';
    }
    echo '</select>';
    echo '</form>';
    echo '<br>';

    if ($selected_board_id) {
        $board = get_post($selected_board_id);
        if ($board && $board->post_type === 'alba_board') {
            echo '<h2>' . esc_html($board->post_title) . '</h2>';

            // GET LISTS FOR THIS BOARD
            $lists = get_posts([
                'post_type'   => 'alba_list',
                'numberposts' => -1,
                'meta_key'    => 'alba_board_parent',
                'meta_value'  => $board->ID,
                'orderby'     => 'menu_order',
                'order'       => 'ASC'
            ]);

            if ($lists) {
                // FLEX CONTAINER FOR COLUMNS
                echo '<div class="alba-board-wrapper">';
                foreach ($lists as $list) {
                    echo '<div class="alba-list" data-list-id="' . esc_attr($list->ID) . '">';
                    echo '<h3>' . esc_html($list->post_title) . '</h3>';

                    // GET CARDS FOR THIS LIST
                    $cards = get_posts([
                        'post_type'   => 'alba_card',
                        'numberposts' => -1,
                        'meta_key'    => 'alba_list_parent',
                        'meta_value'  => $list->ID,
                        'orderby'     => 'menu_order',
                        'order'       => 'ASC'
                    ]);

                    if ($cards) {
                        foreach ($cards as $card) {
                            echo '<div class="alba-card" data-card-id="' . esc_attr($card->ID) . '">';
                            echo '<strong>' . esc_html($card->post_title) . '</strong>';
                            // TAGS AS CHIPS (IF ANY)
                            $tags = wp_get_post_terms($card->ID, 'alba_tag');
                            if (! empty($tags) && ! is_wp_error($tags)) {
                                echo '<div class="alba-card-tags">';
                                foreach ($tags as $tag) {
                                    $bg = get_term_meta($tag->term_id, 'alba_tag_bg_color', true) ?: '#eeeeee';
                                    $text = get_term_meta($tag->term_id, 'alba_tag_text_color', true) ?: '#222';
                                    $is_white = in_array(strtolower($bg), ['#fff', '#ffffff', 'white']);
                                    $tag_shadow = $is_white ? '#e3e7ef' : $bg;
                                    $style = 'background:' . $bg . ';'
                                            . 'color:' . $text . ';'
                                            . '--tag-shadow:' . $tag_shadow . ';';
                                    echo '<span class="alba-card-tag-chip" style="' . esc_attr($style) . '
                                        display: inline-block;
                                        padding: 3.5px 15px;
                                        border-radius: 999px;
                                        font-size: 0.96em;
                                        font-weight: 600;
                                        margin: 1px 0 0 3px;
                                        letter-spacing: 0.01em;
                                        border: none;
                                        box-shadow:
                                            2.2px 2.2px 7px var(--tag-shadow, #e3e7ef),
                                            -1.5px -1.5px 6px #fff;
                                    ">' . esc_html($tag->name) . '</span>';
                                }
                                echo '</div>';
                            }
                            echo '</div>'; // .alba-card
                        }
                    } else {
                        echo '<p><em>' . esc_html__('No cards.', 'alba-board') . '</em></p>';
                    }
                    // "ADD CARD" BUTTON
                    echo '<p style="margin-top: 8px;">';
                    echo '<a href="' . esc_url(admin_url('post-new.php?post_type=alba_card&alba_list_parent=' . $list->ID)) . '" class="button button-secondary">' . esc_html__('Add card', 'alba-board') . '</a>';
                    echo '</p>';
                    echo '</div>'; // .alba-list
                }
                echo '</div>'; // .alba-board-wrapper
            } else {
                echo '<p>' . esc_html__('This board has no lists.', 'alba-board') . '</p>';
            }
        } else {
            echo '<p>' . esc_html__('Invalid board.', 'alba-board') . '</p>';
        }
    }

    // MODAL FOR CARD DETAILS (ADMIN)
    echo '<div id="alba-card-modal-admin" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); z-index:9999; justify-content:center; align-items:center;">';
    echo '<div class="alba-modal-content" style="background:#fff; padding:20px; border-radius:8px; max-width:500px; width:90%; position:relative; box-shadow:0 0 10px rgba(0,0,0,0.3);">';
    echo '<button id="alba-modal-close-admin" style="position:absolute; top:10px; right:10px; background:#ccc; border:none; padding:4px 8px; border-radius:4px; cursor:pointer;">✕</button>';
    echo '<div id="alba-modal-body-admin">' . esc_html__('Loading...', 'alba-board') . '</div>';
    echo '</div></div>';

    echo '</div>'; // .wrap
}

// ENQUEUE JS/CSS ONLY FOR THE KANBAN ADMIN PAGE
add_action('admin_enqueue_scripts', 'alba_board_backend_enqueue_scripts');
function alba_board_backend_enqueue_scripts($hook_suffix) {
    // For our "Board View" page the hook is: "toplevel_page_alba-board-visual"
    if ($hook_suffix !== 'toplevel_page_alba-board-visual') {
        return;
    }

    $plugin_url = plugin_dir_url(dirname(__FILE__));

    // 1) Local Sortable.js
    wp_enqueue_script(
        'sortablejs',
        $plugin_url . 'assets/js/Sortable.min.js',
        [],
        '1.15.0',
        true
    );

    // 2) Local backend Kanban JS
    wp_enqueue_script(
        'alba-backend-kanban',
        $plugin_url . 'assets/js/alba-backend-kanban.js',
        ['sortablejs', 'jquery', 'select2'],
        null,
        true
    );

    // 3) Local Select2
    wp_enqueue_style('select2', $plugin_url . 'assets/css/select2.min.css');
    wp_enqueue_script('select2', $plugin_url . 'assets/js/select2.min.js', ['jquery'], null, true);

    // 4) AJAX data for that JS
    wp_localize_script('alba-backend-kanban', 'albaBoard', [
        'ajaxurl' => esc_url(admin_url('admin-ajax.php')),
        'nonce'   => wp_create_nonce('alba_move_card_nonce'),
    ]);

    // Inline script to auto-init Select2 for dynamically loaded selects in modal
    add_action('admin_footer', function () {
        ?>
        <script>
        jQuery(document).on('DOMNodeInserted', function(e) {
            jQuery(e.target).find('.alba-select2').each(function() {
                if (!jQuery(this).hasClass('select2-hidden-accessible')) {
                    jQuery(this).select2({
                        width: '100%',
                        dropdownParent: jQuery('#alba-card-modal-admin .alba-modal-content')
                    });
                }
            });
        });
        </script>
        <?php
    });
}