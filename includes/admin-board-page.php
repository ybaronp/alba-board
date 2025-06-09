<?php
// admin-board-page.php

add_action('admin_menu', 'alba_register_admin_board_page');

function alba_register_admin_board_page() {
    add_menu_page(
        __('Board View', 'alba-board'),        // Page title
        __('Board View', 'alba-board'),        // Menu label
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

    $selected_board_id = isset($_GET['board_id']) ? intval($_GET['board_id']) : 0;

    echo '<div class="wrap">';
    echo '<h1>' . __('Board View', 'alba-board') . '</h1>';

    // FORM TO SELECT BOARD
    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="alba-board-visual">';
    echo '<label style="margin-right:8px;">' . __('Select a board:', 'alba-board') . '</label>';
    echo '<select name="board_id" onchange="this.form.submit()">';
    echo '<option value="">' . __('-- Select --', 'alba-board') . '</option>';
    foreach ($boards as $board) {
        $sel = ($board->ID === $selected_board_id) ? 'selected' : '';
        echo '<option value="' . $board->ID . '" ' . $sel . '>' . esc_html($board->post_title) . '</option>';
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

                                    // Detect if the background color is white (all common notations)
                                    $is_white = in_array(strtolower($bg), ['#fff', '#ffffff', 'white']);
                                    $tag_shadow = $is_white ? '#e3e7ef' : $bg;

                                    $style = 'background:' . esc_attr($bg) . ';'
                                            . 'color:' . esc_attr($text) . ';'
                                            . '--tag-shadow:' . esc_attr($tag_shadow) . ';';
                                    echo '<span class="alba-card-tag-chip" style="'
                                        . $style . '
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
                        echo '<p><em>' . __('No cards.', 'alba-board') . '</em></p>';
                    }

                    // "ADD CARD" BUTTON
                    echo '<p style="margin-top: 8px;">';
                    echo '<a href="' . admin_url('post-new.php?post_type=alba_card&alba_list_parent=' . $list->ID) . '" class="button button-secondary">' . __('Add card', 'alba-board') . '</a>';
                    echo '</p>';

                    echo '</div>'; // .alba-list
                }

                echo '</div>'; // .alba-board-wrapper
            } else {
                echo '<p>' . __('This board has no lists.', 'alba-board') . '</p>';
            }
        } else {
            echo '<p>' . __('Invalid board.', 'alba-board') . '</p>';
        }
    }

    // MODAL FOR CARD DETAILS (ADMIN)
    echo '<div id="alba-card-modal-admin" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); z-index:9999; justify-content:center; align-items:center;">';
    echo '<div class="alba-modal-content" style="background:#fff; padding:20px; border-radius:8px; max-width:500px; width:90%; position:relative; box-shadow:0 0 10px rgba(0,0,0,0.3);">';
    echo '<button id="alba-modal-close-admin" style="position:absolute; top:10px; right:10px; background:#ccc; border:none; padding:4px 8px; border-radius:4px; cursor:pointer;">✕</button>';
    echo '<div id="alba-modal-body-admin">' . __('Loading...', 'alba-board') . '</div>';
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

    // 1) Load Sortable.js
    wp_enqueue_script(
        'sortablejs',
        'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
        [],
        '1.15.0',
        true
    );

    // 2) Load our JS for initializing Sortable on .alba-list/.alba-card
    wp_enqueue_script(
        'alba-backend-kanban',
        plugins_url('assets/js/alba-backend-kanban.js', dirname(__FILE__)),
        ['sortablejs', 'jquery', 'select2'],
        null,
        true
    );

    // 3) AJAX data for that JS
    wp_localize_script('alba-backend-kanban', 'albaBoard', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('alba_move_card_nonce'),
    ]);

    // ENQUEUE Select2 CSS and JS
    wp_enqueue_style('select2', '//cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('select2', '//cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);

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