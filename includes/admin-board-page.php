<?php
/**
 * includes/admin-board-page.php
 * Handles the logic and rendering of the Alba Board admin page.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Prevent direct access

/**
 * 1. APPLICATION LAYER (State Mutation & Redirects)
 */
add_action('admin_init', 'alba_board_handle_form_submissions');

function alba_board_handle_form_submissions() {
    if ( ! isset($_GET['page']) || $_GET['page'] !== 'alba-board-visual' ) {
        return;
    }

    foreach ( [ 'alba_create_demo_board' => 'edit_boards', 'alba_create_board' => 'edit_boards', 'alba_create_list' => 'edit_lists', 'alba_create_card' => 'edit_cards' ] as $field => $capability ) {
        if ( isset( $_POST[$field] ) && ! current_user_can( $capability ) ) wp_die( esc_html__( 'Permission denied.', 'alba-board' ), '', [ 'response' => 403 ] );
    }
    if ( isset( $_POST['alba_create_card'] ) ) {
        $parent_list = isset( $_POST['current_list_id'] ) ? absint( $_POST['current_list_id'] ) : 0;
        if ( 'alba_list' !== get_post_type( $parent_list ) || 'publish' !== get_post_status( $parent_list ) || ! current_user_can( 'edit_list', $parent_list ) ) wp_die( esc_html__( 'Invalid list or permission denied.', 'alba-board' ), '', [ 'response' => 403 ] );
    }

    if ( isset($_POST['alba_create_demo_board']) && check_admin_referer('alba_create_demo_board_action') ) {
        $board_id = wp_insert_post([
            'post_type'   => 'alba_board', 
            'post_title'  => __('Project Alpha (Demo)', 'alba-board'), 
            'post_status' => 'publish', 
            'post_author' => get_current_user_id()
        ]);

        if ($board_id && !is_wp_error($board_id)) {
            $lists_names = [__('To Do', 'alba-board'), __('In Progress', 'alba-board'), __('Done', 'alba-board')];
            $list_ids = []; 
            $menu_order = 0;
            
            foreach ($lists_names as $list_title) { 
                $list_ids[] = wp_insert_post([
                    'post_type'   => 'alba_list', 
                    'post_title'  => $list_title, 
                    'post_status' => 'publish', 
                    'post_author' => get_current_user_id(), 
                    'menu_order'  => $menu_order++, 
                    'meta_input'  => ['alba_board_parent' => $board_id]
                ]); 
            }
            
            if (count($list_ids) >= 3) {
                $cards_data = [ 
                    ['title' => __('👋 Welcome!', 'alba-board'), 'content' => '', 'list_id' => $list_ids, 'order' => 0], 
                    ['title' => __('🖱️ Drag me', 'alba-board'), 'content' => '', 'list_id' => $list_ids, 'order' => 1] 
                ];
                foreach ($cards_data as $c) { 
                    wp_insert_post([
                        'post_type'   => 'alba_card', 
                        'post_title'  => $c['title'], 
                        'post_content'=> $c['content'], 
                        'post_status' => 'publish', 
                        'post_author' => get_current_user_id(), 
                        'menu_order'  => $c['order'], 
                        'meta_input'  => ['alba_list_parent' => $c['list_id']]
                    ]); 
                }
            }
            wp_safe_redirect(admin_url('admin.php?page=alba-board-visual&board_id=' . $board_id)); 
            exit;
        }
    }

    if ( isset($_POST['alba_create_board']) && !empty($_POST['alba_new_board_title']) && check_admin_referer('alba_create_board_action') ) {
        $board_id = wp_insert_post([
            'post_type'   => 'alba_board', 
            'post_title'  => sanitize_text_field(wp_unslash($_POST['alba_new_board_title'])), 
            'post_status' => 'publish', 
            'post_author' => get_current_user_id()
        ]);
        if ( $board_id && ! is_wp_error($board_id) ) { 
            wp_safe_redirect(admin_url('admin.php?page=alba-board-visual&board_id=' . $board_id)); 
            exit; 
        }
    }

    if ( isset($_POST['alba_create_list']) && !empty($_POST['alba_new_list_title']) && !empty($_POST['current_board_id']) && check_admin_referer('alba_create_list_action') ) {
        $board_id = absint($_POST['current_board_id']); 
        global $wpdb;
        $max_list_order = $wpdb->get_var( $wpdb->prepare("SELECT MAX(menu_order) FROM $wpdb->posts WHERE post_type = %s AND post_status = 'publish' AND ID IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'alba_board_parent' AND meta_value = %d)", 'alba_list', $board_id) );
        $new_list_order = (($max_list_order !== null) ? intval($max_list_order) + 1 : 0);
        
        $list_id = wp_insert_post([
            'post_type'   => 'alba_list', 
            'post_title'  => sanitize_text_field(wp_unslash($_POST['alba_new_list_title'])), 
            'post_status' => 'publish', 
            'post_author' => get_current_user_id(), 
            'menu_order'  => $new_list_order, 
            'meta_input'  => ['alba_board_parent' => $board_id]
        ]);
        if ( $list_id && ! is_wp_error($list_id) ) { 
            wp_safe_redirect(admin_url('admin.php?page=alba-board-visual&board_id=' . $board_id)); 
            exit; 
        }
    }

    if ( isset($_POST['alba_create_card'], $_POST['alba_new_card_title'], $_POST['current_list_id']) && !empty($_POST['alba_new_card_title']) && !empty($_POST['current_list_id']) && check_admin_referer('alba_create_card_action') ) {
        $list_id = absint($_POST['current_list_id']); 
        $board_id = isset($_POST['current_board_id']) ? absint($_POST['current_board_id']) : 0; 
        global $wpdb;
        $max_menu_order = $wpdb->get_var( $wpdb->prepare("SELECT MAX(menu_order) FROM $wpdb->posts WHERE post_type = %s AND post_status = 'publish' AND ID IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'alba_list_parent' AND meta_value = %d)", 'alba_card', $list_id));
        
        $card_id = wp_insert_post([
            'post_type'   => 'alba_card', 
            'post_title'  => sanitize_text_field(wp_unslash($_POST['alba_new_card_title'])), 
            'post_status' => 'publish', 
            'post_author' => get_current_user_id(), 
            'menu_order'  => (($max_menu_order !== null) ? intval($max_menu_order) + 1 : 0), 
            'meta_input'  => ['alba_list_parent' => $list_id]
        ]);
        if ( $card_id && ! is_wp_error($card_id) ) { 
            wp_safe_redirect(admin_url('admin.php?page=alba-board-visual' . ($board_id ? '&board_id=' . $board_id : ''))); 
            exit; 
        }
    }
}

/**
 * 2. UI LAYER (Rendering Only)
 */
function alba_render_admin_board_page() {
    $boards = get_posts(['post_type' => 'alba_board', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
    
    $selected_board_id = 0;
    if ( isset($_GET['board_id']) ) {
        $selected_board_id = absint($_GET['board_id']);
    } elseif ( !empty($boards) ) {
        $first_board = (object) reset($boards);
        $selected_board_id = $first_board->ID;
    }
    $is_archive_view = isset( $_GET['view'] ) && 'archived' === sanitize_key( wp_unslash( $_GET['view'] ) );
    
    $display_opts = get_option('alba_board_display', ['show_avatars' => 1, 'theme' => 'default']);
    $show_avatars = !empty($display_opts['show_avatars']);
    $theme_class  = isset($display_opts['theme']) ? 'alba-theme-' . $display_opts['theme'] : 'alba-theme-default';

    echo '<div class="alba-master-theme-wrapper ' . esc_attr($theme_class) . '">';
    echo '<div class="wrap alba-board-admin-wrap"><h1 class="alba-board-page-title">' . esc_html__('Boards', 'alba-board') . '</h1>';

    if (!empty($boards) || $is_archive_view) {
        echo '<div class="alba-top-action-bar">';
        
        if ( ! $is_archive_view && ! empty( $boards ) ) {
            echo '<form method="get" class="alba-board-select-form">';
            echo '<input type="hidden" name="page" value="alba-board-visual">';
            echo '<select name="board_id" class="alba-board-selector alba-auto-submit-select" title="'.esc_attr__('Select Board', 'alba-board').'">';
            foreach ($boards as $board) {
                $board = (object) $board;
                echo '<option value="' . esc_attr($board->ID) . '"' . ($board->ID == $selected_board_id ? ' selected' : '') . '>' . esc_html($board->post_title) . '</option>';
            }
            echo '</select></form>';
        }
        
        if ( ! $is_archive_view ) {
            echo '<div class="alba-filters-wrapper">';
            echo '<input type="search" id="alba-filter-search" class="alba-form-input-text alba-filter-input" placeholder="' . esc_attr__('Search cards', 'alba-board') . '" aria-label="' . esc_attr__('Search cards', 'alba-board') . '">';
            echo '<details class="alba-control-menu alba-filter-menu">';
            echo '<summary class="alba-control-trigger">' . esc_html__('Filters', 'alba-board') . '</summary>';
            echo '<div class="alba-control-menu-panel alba-filter-menu-panel">';
            echo '<label for="alba-filter-user">' . esc_html__('Assignee', 'alba-board') . '</label>';
            echo '<select id="alba-filter-user" class="alba-board-selector alba-filter-input">';
            echo '<option value="">' . esc_html__('All Users', 'alba-board') . '</option>';
            $users = get_users(['fields' => ['ID', 'display_name']]);
            foreach($users as $u) echo '<option value="'.esc_attr($u->ID).'">'.esc_html($u->display_name).'</option>';
            echo '</select>';

            if (taxonomy_exists('alba_tag')) {
                $tags = get_terms(['taxonomy' => 'alba_tag', 'hide_empty' => false]);
                if (!is_wp_error($tags) && !empty($tags)) {
                    echo '<label for="alba-filter-tag">' . esc_html__('Tag', 'alba-board') . '</label>';
                    echo '<select id="alba-filter-tag" class="alba-board-selector alba-filter-input">';
                    echo '<option value="">' . esc_html__('All Tags', 'alba-board') . '</option>';
                    foreach($tags as $t) echo '<option value="'.esc_attr(strtolower($t->name)).'">'.esc_html($t->name).'</option>';
                    echo '</select>';
                }
            }
            echo '</div></details></div>';
        }

        // Board management buttons
        echo '<div class="alba-top-buttons-group">';
        if ( ! $is_archive_view ) {
        echo '<button type="button" class="alba-btn-neumorphic" id="alba-show-new-board-btn">' . esc_html__('+ Board', 'alba-board') . '</button>';

        echo '<details class="alba-control-menu alba-tools-menu">';
        echo '<summary class="alba-control-trigger">' . esc_html__('More', 'alba-board') . '</summary>';
        echo '<div class="alba-control-menu-panel alba-tools-menu-panel">';
        if ( $selected_board_id ) {
            $shortcode_str = '[alba_board id="' . absint($selected_board_id) . '"]';
            echo '<button type="button" class="alba-menu-action alba-board-copy-shortcode" data-shortcode="' . esc_attr($shortcode_str) . '">' . esc_html__( 'Copy shortcode', 'alba-board' ) . '</button>';
        }

        $archive_counts = wp_count_posts( 'alba_card' );
        $archived_count = isset( $archive_counts->alba_archived ) ? absint( $archive_counts->alba_archived ) : 0;
        $archive_url = admin_url( 'admin.php?page=alba-board-visual&view=archived' );
        echo '<a href="' . esc_url( $archive_url ) . '" class="alba-menu-action">' . esc_html__( 'Archived Cards', 'alba-board' ) . '<span class="alba-menu-count">' . esc_html( $archived_count ) . '</span></a>';

        if ( $selected_board_id && current_user_can('edit_board', $selected_board_id) ) {
            $csv_url = wp_nonce_url(admin_url('admin-post.php?action=alba_export_board&board_id=' . $selected_board_id . '&format=csv'), 'alba_export_board_' . $selected_board_id);
            $json_url = wp_nonce_url(admin_url('admin-post.php?action=alba_export_board&board_id=' . $selected_board_id . '&format=json'), 'alba_export_board_' . $selected_board_id);
            echo '<a href="' . esc_url($csv_url) . '" class="alba-menu-action">' . esc_html__('Export CSV', 'alba-board') . '</a>';
            echo '<a href="' . esc_url($json_url) . '" class="alba-menu-action">' . esc_html__('Export JSON', 'alba-board') . '</a>';
        }
        echo '</div></details>';
        } else {
            $board_url = admin_url( 'admin.php?page=alba-board-visual' . ( $selected_board_id ? '&board_id=' . $selected_board_id : '' ) );
            echo '<a href="' . esc_url( $board_url ) . '" class="alba-control-trigger alba-back-to-board">' . esc_html__( 'Back to Board', 'alba-board' ) . '</a>';
        }
        echo '</div>'; 

        echo '</div>'; 

        // Inline form for board creation
        if ( ! $is_archive_view ) {
        echo '<form id="alba-new-board-form" class="alba-inline-form alba-is-hidden" method="post">'; 
        wp_nonce_field('alba_create_board_action');
        echo '<input type="hidden" name="alba_create_board" value="1">'; 
        echo '<input type="text" name="alba_new_board_title" class="alba-form-input-text" placeholder="' . esc_attr__('Board title', 'alba-board') . '" required style="max-width: 250px;">';
        echo '<input type="submit" class="alba-btn-neumorphic" value="' . esc_attr__('Create', 'alba-board') . '">';
        echo '<button type="button" class="alba-btn-cancel" id="alba-cancel-new-board-btn">' . esc_html__('Cancel', 'alba-board') . '</button>';
        echo '</form>';
        }
    } else {
        echo '<div class="alba-empty-state">';
        echo '<h2>' . esc_html__('Welcome to Alba Board! 🎉', 'alba-board') . '</h2>';
        echo '<form method="post">'; wp_nonce_field('alba_create_demo_board_action');
        echo '<button type="submit" name="alba_create_demo_board" class="button button-primary button-hero alba-demo-btn">' . esc_html__('Create a Sample Board', 'alba-board') . '</button></form></div>';
    }

    if ( $is_archive_view ) {
        alba_board_render_archived_cards();
    } elseif ($selected_board_id) {
        $board = (object) get_post($selected_board_id);
        if ($board && isset($board->post_type) && $board->post_type === 'alba_board') {
            echo '<h2 style="color: var(--alba-text-title);">' . esc_html($board->post_title) . '</h2>';
            $lists = get_posts(['post_type' => 'alba_list', 'numberposts' => -1, 'meta_key' => 'alba_board_parent', 'meta_value' => $board->ID, 'orderby' => 'menu_order', 'order' => 'ASC']);
            $cards_by_list = alba_board_get_cards_grouped_by_list( wp_list_pluck( $lists, 'ID' ) );
            
            echo '<div class="alba-board-wrapper">'; 

            if ($lists) {
                foreach ($lists as $list) {
                    $list = (object) $list;
                    echo '<div class="alba-list alba-list-scrollable" data-list-id="' . esc_attr($list->ID) . '">';
                    
                    echo '<div class="alba-list-header" style="display:flex; justify-content:space-between; align-items:center;">';
                    echo '<h3 style="margin:0;">' . esc_html($list->post_title) . '</h3>';
                    echo '<div style="display:flex; gap:5px; align-items:center;">';
                    echo '<button type="button" class="alba-list-collapse-btn" title="' . esc_attr__('Collapse/Expand', 'alba-board') . '" style="background:none; border:none; cursor:pointer; font-size:14px; color:var(--alba-text-muted);">↔</button>';
                    echo '<button type="button" class="alba-delete-list-btn" data-list-id="' . esc_attr($list->ID) . '" title="' . esc_attr__('Delete list', 'alba-board') . '">✕</button>';
                    echo '</div></div>';
                    
                    echo '<div class="alba-cards-container" data-list-id="' . esc_attr($list->ID) . '">';

                    $cards = isset( $cards_by_list[ $list->ID ] ) ? $cards_by_list[ $list->ID ] : [];
                    
                    if ($cards) {
                        foreach ($cards as $card) {
                            $card = (object) $card;
                            echo '<div class="alba-card" data-card-id="' . esc_attr($card->ID) . '" data-author="' . esc_attr($card->post_author) . '">';
                            echo '<strong class="alba-card-title">' . esc_html($card->post_title) . '</strong>';
                            
                            $due_date = get_post_meta($card->ID, 'alba_due_date', true);
                            if (!empty($due_date)) {
                                $formatted_date = date_i18n('M j', strtotime($due_date));
                                echo '<div style="font-size: 11px; color: #64748b; margin-top: 6px; display: flex; align-items: center; gap: 4px;">';
                                echo '<span class="dashicons dashicons-calendar-alt" style="font-size: 13px; height: 13px; width: 13px;"></span> ';
                                echo esc_html($formatted_date);
                                echo '</div>';
                            }

                            echo '<div class="alba-card-footer">';
                            echo '<div class="alba-card-tags-wrapper">';
                            do_action('alba_board_card_tags', $card->ID); 
                            echo '</div>';

                            if ($show_avatars) {
                                $author_id = $card->post_author;
                                if ($author_id) {
                                    $author_name = get_the_author_meta('display_name', $author_id);
                                    echo '<div class="alba-card-avatar" title="' . esc_attr(sprintf(__('Assigned to: %s', 'alba-board'), $author_name)) . '">';
                                    echo get_avatar($author_id, 26, '', $author_name, ['class' => 'alba-avatar-img']);
                                    echo '</div>';
                                } else { echo '<div></div>'; }
                            } else { echo '<div></div>'; }
                            
                            echo '</div></div>'; 
                        }
                    } 
                    
                    $no_cards_class = empty($cards) ? 'alba-no-cards-msg' : 'alba-no-cards-msg alba-is-hidden';
                    echo '<p class="' . esc_attr($no_cards_class) . '"><em>' . esc_html__('No cards.', 'alba-board') . '</em></p>';
                    echo '</div>'; 
                    
                    echo '<div class="alba-list-footer">';
                    echo '<button type="button" class="alba-show-add-card-btn">+ ' . esc_html__('Add Card', 'alba-board') . '</button>';
                    echo '<form class="alba-add-card-form alba-stacked-form alba-is-hidden" method="post">';
                    wp_nonce_field('alba_create_card_action');
                    echo '<input type="hidden" name="alba_create_card" value="1">'; 
                    echo '<input type="hidden" name="current_list_id" value="' . absint($list->ID) . '">';
                    echo '<input type="hidden" name="current_board_id" value="' . absint($selected_board_id) . '">';
                    echo '<input type="text" name="alba_new_card_title" class="alba-form-input-text alba-mb-8" placeholder="' . esc_attr__('Card title', 'alba-board') . '" required>';
                    echo '<div class="alba-actions-row">';
                    echo '<input type="submit" class="alba-btn-neumorphic" value="' . esc_attr__('Add', 'alba-board') . '">';
                    echo '<button type="button" class="alba-btn-cancel alba-cancel-new-card-btn">' . esc_html__('Cancel', 'alba-board') . '</button>';
                    echo '</div></form></div>'; 
                    echo '</div>'; 
                }
            }

            echo '<div class="alba-list alba-add-list-wrapper">';
            $add_list_text = empty($lists) ? esc_html__('Add List', 'alba-board') : esc_html__('Add another list', 'alba-board');
            echo '<button type="button" class="alba-show-add-list-btn">+ ' . $add_list_text . '</button>';
            echo '<form class="alba-new-list-form alba-stacked-form alba-is-hidden" method="post">';
            wp_nonce_field('alba_create_list_action');
            echo '<input type="hidden" name="alba_create_list" value="1">'; 
            echo '<input type="hidden" name="current_board_id" value="' . absint($selected_board_id) . '">';
            echo '<input type="text" name="alba_new_list_title" class="alba-form-input-text alba-mb-8 alba-inset-shadow" placeholder="' . esc_attr__('Enter list title...', 'alba-board') . '" required>';
            echo '<div class="alba-actions-row">';
            echo '<input type="submit" class="alba-btn-neumorphic" value="' . esc_attr__('Add List', 'alba-board') . '">';
            echo '<button type="button" class="alba-btn-cancel alba-cancel-new-list-btn">' . esc_html__('Cancel', 'alba-board') . '</button>';
            echo '</div></form></div></div>';
        }
    }

    ?>
    <div id="alba-card-modal-admin" class="alba-is-hidden"><div class="alba-modal-content"><button id="alba-modal-close-admin" type="button">✕</button><div id="alba-modal-body-admin"><?php esc_html_e('Loading...', 'alba-board'); ?></div></div></div>
    <?php 
    echo '</div>'; 
    echo '</div>'; 
}

/**
 * 3. AJAX LIST HANDLERS
 */
add_action('wp_ajax_alba_delete_list_action', function() {
    check_ajax_referer('alba_delete_list_nonce', 'nonce');
    $list_id = isset($_POST['list_id']) ? absint($_POST['list_id']) : 0;
    if (!$list_id || 'alba_list' !== get_post_type($list_id) || !current_user_can('delete_list', $list_id)) {
        wp_send_json_error(['message' => esc_html__('Permission denied.', 'alba-board')], 403);
    }

    $cards = get_posts([
        'post_type'   => 'alba_card',
        'numberposts' => -1,
        'meta_key'    => 'alba_list_parent',
        'meta_value'  => $list_id,
    ]);
    foreach ($cards as $card) {
        if (!current_user_can('edit_card', $card->ID)) {
            wp_send_json_error(['message' => esc_html__('You cannot archive every card in this list.', 'alba-board')], 403);
        }
    }
    foreach ($cards as $card) {
        if (!alba_board_archive_card($card->ID)) {
            wp_send_json_error(['message' => esc_html__('Could not archive a card in this list.', 'alba-board')], 500);
        }
    }
    if (!wp_trash_post($list_id)) {
        wp_send_json_error(['message' => esc_html__('Could not delete this list.', 'alba-board')], 500);
    }
    wp_send_json_success();
});

add_action('wp_ajax_alba_move_list_action', function() {
    check_ajax_referer('alba_move_list_nonce', 'nonce');
    $raw_orders = isset($_POST['order']) && is_array($_POST['order']) ? wp_unslash($_POST['order']) : [];
    if (!$raw_orders) {
        wp_send_json_error(['message' => esc_html__('No list order was provided.', 'alba-board')], 400);
    }

    $list_orders = [];
    $seen_lists  = [];
    foreach ($raw_orders as $position => $raw_list_id) {
        if (!is_scalar($position) || !preg_match('/^\d+$/', (string) $position) || !is_scalar($raw_list_id)) {
            wp_send_json_error(['message' => esc_html__('Invalid list order.', 'alba-board')], 400);
        }

        $position = absint($position);
        $list_id  = absint($raw_list_id);
        if (
            !$list_id ||
            'alba_list' !== get_post_type($list_id) ||
            'publish' !== get_post_status($list_id) ||
            !current_user_can('edit_list', $list_id) ||
            isset($seen_lists[$list_id]) ||
            isset($list_orders[$position])
        ) {
            wp_send_json_error(['message' => esc_html__('Invalid list order or permission denied.', 'alba-board')], 403);
        }
        $seen_lists[$list_id] = true;
        $list_orders[$position] = $list_id;
    }

    ksort($list_orders, SORT_NUMERIC);
    if (array_keys($list_orders) !== range(0, count($list_orders) - 1)) {
        wp_send_json_error(['message' => esc_html__('Invalid list order.', 'alba-board')], 400);
    }
    foreach ($list_orders as $position => $list_id) {
        $list = get_post( $list_id );
        if ( ! $list || (int) $list->menu_order === $position ) {
            continue;
        }

        $updated = wp_update_post(['ID' => $list_id, 'menu_order' => $position], true);
        if (is_wp_error($updated) || !$updated) {
            wp_send_json_error(['message' => esc_html__('Could not save the list order.', 'alba-board')], 500);
        }
    }
    wp_send_json_success();
});
