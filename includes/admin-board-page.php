<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Prevent direct access

// Register Board View admin menu
add_action('admin_menu', 'alba_register_admin_board_page');
function alba_register_admin_board_page() {
    add_menu_page(
        esc_html__('Board View', 'alba-board'),
        esc_html__('Board View', 'alba-board'),
        'edit_posts',
        'alba-board-visual',
        'alba_render_admin_board_page',
        'dashicons-layout',
        30
    );
}

// Main admin Kanban page renderer
function alba_render_admin_board_page() {
    // Handle create board
    if (
        isset($_POST['alba_create_board']) &&
        !empty($_POST['alba_new_board_title']) &&
        check_admin_referer('alba_create_board_action')
    ) {
        $title = sanitize_text_field($_POST['alba_new_board_title']);
        $board_id = wp_insert_post([
            'post_type'   => 'alba_board',
            'post_title'  => $title,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ]);
        if ( $board_id && ! is_wp_error($board_id) ) {
            $_GET['board_id'] = $board_id;
        }
    }

    // Handle create list
    if (
        isset($_POST['alba_create_list']) &&
        !empty($_POST['alba_new_list_title']) &&
        !empty($_POST['current_board_id']) &&
        check_admin_referer('alba_create_list_action')
    ) {
        $title = sanitize_text_field($_POST['alba_new_list_title']);
        $board_id = absint($_POST['current_board_id']);
        wp_insert_post([
            'post_type'   => 'alba_list',
            'post_title'  => $title,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
            'meta_input'  => [
                'alba_board_parent' => $board_id,
            ],
        ]);
    }

    // Handle create card (add card at end of list)
    if (
        isset($_POST['alba_create_card'], $_POST['alba_new_card_title'], $_POST['current_list_id']) &&
        !empty($_POST['alba_new_card_title']) &&
        !empty($_POST['current_list_id']) &&
        check_admin_referer('alba_create_card_action')
    ) {
        $title = sanitize_text_field($_POST['alba_new_card_title']);
        $list_id = absint($_POST['current_list_id']);
        
        global $wpdb;
        $max_menu_order = $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(menu_order) FROM $wpdb->posts WHERE post_type = %s AND post_status = 'publish' AND ID IN (
                SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'alba_list_parent' AND meta_value = %d
            )", 'alba_card', $list_id
        ));
        $next_menu_order = ( $max_menu_order !== null ) ? intval($max_menu_order) + 1 : 0;

        wp_insert_post([
            'post_type'   => 'alba_card',
            'post_title'  => $title,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
            'menu_order'  => $next_menu_order,
            'meta_input'  => [
                'alba_list_parent' => $list_id,
            ],
        ]);
    }

    $boards = get_posts([
        'post_type'   => 'alba_board',
        'numberposts' => -1,
        'orderby'     => 'title',
        'order'       => 'ASC'
    ]);

    // Selected board
    $selected_board_id = 0;
    if ( isset($_GET['board_id']) ) {
        $selected_board_id = absint($_GET['board_id']);
    } elseif ( !empty($boards) ) {
        $selected_board_id = $boards[0]->ID;
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Boards', 'alba-board') . '</h1>';

    // Board selector
    echo '<form method="get" style="display:inline-block; margin-right:12px;">';
    echo '<input type="hidden" name="page" value="alba-board-visual">';
    echo '<label style="margin-right:8px;">' . esc_html__('Select a board:', 'alba-board') . '</label>';
    echo '<select name="board_id" onchange="this.form.submit()">';
    foreach ($boards as $board) {
        echo '<option value="' . esc_attr($board->ID) . '"';
        if ($board->ID == $selected_board_id) echo ' selected="selected"';
        echo '>' . esc_html($board->post_title) . '</option>';
    }
    echo '</select>';
    echo '</form>';

    // Button: Add Board (opens form below)
    echo '<button class="button" onclick="document.getElementById(\'alba-new-board-form\').style.display=\'block\'; this.style.display=\'none\'; return false;" style="margin-right: 8px;">' . esc_html__('Add Board', 'alba-board') . '</button>';

    // Button: Add List (opens form below)
    if ( $selected_board_id ) {
        echo '<button class="button" onclick="document.getElementById(\'alba-new-list-form\').style.display=\'block\'; this.style.display=\'none\'; return false;">' . esc_html__('Add List', 'alba-board') . '</button>';
    }

    // Hidden form: Add new board
    echo '<form id="alba-new-board-form" method="post" style="display:none; margin-top:14px;">';
    wp_nonce_field('alba_create_board_action');
    echo '<input type="text" name="alba_new_board_title" placeholder="' . esc_attr__('Board title', 'alba-board') . '" required>';
    echo ' <input type="submit" class="button button-primary" name="alba_create_board" value="' . esc_attr__('Create Board', 'alba-board') . '">';
    echo ' <button type="button" class="button" onclick="this.parentNode.style.display=\'none\'; document.querySelector(\'.button[onclick*=&quot;alba-new-board-form&quot;]\').style.display=\'inline-block\';">' . esc_html__('Cancel', 'alba-board') . '</button>';
    echo '</form>';

    // Hidden form: Add new list
    if ( $selected_board_id ) {
        echo '<form id="alba-new-list-form" method="post" style="display:none; margin-top:14px;">';
        wp_nonce_field('alba_create_list_action');
        echo '<input type="hidden" name="current_board_id" value="' . absint($selected_board_id) . '">';
        echo '<input type="text" name="alba_new_list_title" placeholder="' . esc_attr__('List title', 'alba-board') . '" required>';
        echo ' <input type="submit" class="button button-primary" name="alba_create_list" value="' . esc_attr__('Create List', 'alba-board') . '">';
        echo ' <button type="button" class="button" onclick="this.parentNode.style.display=\'none\'; document.querySelector(\'.button[onclick*=&quot;alba-new-list-form&quot;]\').style.display=\'inline-block\';">' . esc_html__('Cancel', 'alba-board') . '</button>';
        echo '</form>';
    }

    echo '<br><br>';

    // Board/Lists/Cards view
    if ($selected_board_id) {
        $board = get_post($selected_board_id);
        if ($board && $board->post_type === 'alba_board') {
            echo '<h2>' . esc_html($board->post_title) . '</h2>';
            $lists = get_posts([
                'post_type'   => 'alba_list',
                'numberposts' => -1,
                'meta_key'    => 'alba_board_parent',
                'meta_value'  => $board->ID,
                'orderby'     => 'menu_order',
                'order'       => 'ASC'
            ]);
            if ($lists) {
                echo '<div class="alba-board-wrapper">';
                foreach ($lists as $list) {
                    echo '<div class="alba-list alba-list-scrollable" data-list-id="' . esc_attr($list->ID) . '">';
                    echo '<h3>' . esc_html($list->post_title) . '</h3>';
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
                            $tags = wp_get_post_terms($card->ID, 'alba_tag');
                            if (!empty($tags) && !is_wp_error($tags)) {
                                echo '<div class="alba-card-tags">';
                                foreach ($tags as $tag) {
                                    $bg   = get_term_meta($tag->term_id, 'alba_tag_bg_color', true) ?: '#eeeeee';
                                    $text = get_term_meta($tag->term_id, 'alba_tag_text_color', true) ?: '#222';
                                    $is_white = in_array(strtolower($bg), ['#fff', '#ffffff', 'white']);
                                    $tag_shadow = $is_white ? '#e3e7ef' : $bg;
                                    $style = 'background:' . $bg . ';color:' . $text . ';--tag-shadow:' . $tag_shadow . ';';
                                    echo '<span class="alba-card-tag-chip" style="' . esc_attr($style) . '">' . esc_html($tag->name) . '</span>';
                                }
                                echo '</div>';
                            }
                            echo '</div>'; // .alba-card
                        }
                    } else {
                        echo '<p><em>' . esc_html__('No cards.', 'alba-board') . '</em></p>';
                    }
                    // Show add card button per list (shows the form below it)
                    echo '<button class="button alba-show-add-card-btn" style="margin-top:10px;" onclick="var f=this.nextElementSibling;f.style.display=\'block\';this.style.display=\'none\';return false;">' . esc_html__('Add Card', 'alba-board') . '</button>';
                    echo '<form class="alba-add-card-form" method="post" style="display:none; margin-top:10px;">';
                    wp_nonce_field('alba_create_card_action');
                    echo '<input type="hidden" name="current_list_id" value="' . absint($list->ID) . '">';
                    echo '<input type="text" name="alba_new_card_title" placeholder="' . esc_attr__('Card title', 'alba-board') . '" required>';
                    echo ' <input type="submit" class="button button-primary" name="alba_create_card" value="' . esc_attr__('Create Card', 'alba-board') . '">';
                    echo ' <button type="button" class="button" onclick="this.parentNode.style.display=\'none\';this.parentNode.previousElementSibling.style.display=\'inline-block\';">' . esc_html__('Cancel', 'alba-board') . '</button>';
                    echo '</form>';
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

    // ---- THIS IS CRUCIAL: THE MODAL HTML FOR ADMIN CARD DETAILS ----
    ?>
    <div id="alba-card-modal-admin" style="display: none;">
        <div class="alba-modal-content">
            <button id="alba-modal-close-admin" type="button">✕</button>
            <div id="alba-modal-body-admin"><?php esc_html_e('Loading...', 'alba-board'); ?></div>
        </div>
    </div>
    <?php

    echo '</div>'; // .wrap
}