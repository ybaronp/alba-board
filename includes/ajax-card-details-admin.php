<?php
// includes/ajax-card-details-admin.php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Helper to display human-readable time or a formatted date.
 */
function alba_board_time_ago_or_date( $datetime ) {
    $timestamp = strtotime( $datetime );
    $now = current_time( 'timestamp' );
    $diff = $now - $timestamp;
    
    if ( $diff < 60 ) { 
        return __( 'Just now', 'alba-board' ); 
    } elseif ( $diff < 3600 ) { 
        $mins = floor( $diff / 60 ); 
        return sprintf( _n( '%d minute ago', '%d minutes ago', $mins, 'alba-board' ), $mins ); 
    } elseif ( $diff < 86400 ) { 
        $hours = floor( $diff / 3600 ); 
        return sprintf( _n( '%d hour ago', '%d hours ago', $hours, 'alba-board' ), $hours ); 
    } else { 
        return date_i18n( 'Y-m-d H:i', $timestamp ); 
    }
}

/**
 * Renders the full HTML for the backend card details modal.
 */
function alba_output_card_details_admin_modal( $card_id, $force_author = null ) {
    $card_id = absint( $card_id );
    
    if ( ! $card_id || get_post_type( $card_id ) !== 'alba_card' ) { 
        echo esc_html__( 'Invalid card.', 'alba-board' ); 
        return; 
    }

    $card = get_post( $card_id );
    $author_id = $force_author !== null ? absint( $force_author ) : intval( $card->post_author );
    
    echo '<form id="alba-card-details-form" class="alba-card-details-form">';
    echo '<input type="hidden" id="alba-current-card-id" name="card_id" value="' . esc_attr( $card_id ) . '">';

    // Title
    echo '<div class="alba-form-group">';
    echo '<label>' . esc_html__( 'Title:', 'alba-board' ) . '</label>';
    echo '<input type="text" name="card_title" class="alba-form-input-text" value="' . esc_attr( $card->post_title ) . '" required>';
    echo '</div>';

    // Assignee
    echo '<div class="alba-form-group">';
    echo '<label>' . esc_html__( 'Assignee:', 'alba-board' ) . '</label>';
    echo '<select name="card_author" class="alba-select2">';
    $users = get_users( [ 'fields' => [ 'ID', 'display_name' ] ] );
    foreach ( $users as $user ) {
        echo '<option value="' . esc_attr( $user->ID ) . '" ' . selected($user->ID, $author_id, false) . '>' . esc_html( $user->display_name ) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // Due Date (Flatpickr compatible)
    echo '<div class="alba-form-group" style="margin-bottom: 15px;">';
    echo '<label for="alba-card-due-date" style="font-weight: 600; display: block; margin-bottom: 5px;">' . esc_html__( 'Due Date:', 'alba-board' ) . '</label>';
    $due_date = get_post_meta($card_id, 'alba_due_date', true);
    echo '<div style="display: flex; align-items: center; gap: 10px;">';
    echo '<input type="text" id="alba-card-due-date" name="due_date" class="alba-form-input" value="' . esc_attr($due_date) . '" placeholder="' . esc_attr__('Select a date...', 'alba-board') . '" style="width: 100%; max-width: 200px; border-radius: 6px; border: 1px solid var(--alba-shadow-dark); padding: 6px 10px; background: var(--alba-input-bg); color: var(--alba-text-main);">';
    if (!empty($due_date)) {
        echo '<button type="button" id="alba-clear-date" style="background: none; border: none; color: var(--alba-danger); cursor: pointer; text-decoration: underline; font-size: 0.85em;">' . esc_html__('Clear', 'alba-board') . '</button>';
    }
    echo '</div></div>';

    // Description
    echo '<div class="alba-form-group">';
    echo '<label>' . esc_html__( 'Description:', 'alba-board' ) . '</label>';
    echo '<textarea name="card_content" class="alba-form-input-text" rows="3">' . esc_textarea( $card->post_content ) . '</textarea>';
    echo '</div>';

    // Hook for Add-ons (e.g., Tags)
    do_action('alba_admin_card_modal_after_description', $card_id);

    // Attachments
    echo '<div class="alba-form-group" style="margin-bottom: 15px;">';
    echo '<label>' . esc_html__( 'Attachments:', 'alba-board' ) . '</label>';
    $attachments = get_post_meta( $card_id, 'alba_card_attachments' );
    echo '<div id="alba-attachments-list" style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 10px;">';
    if ( ! empty( $attachments ) ) {
        foreach ( $attachments as $att_id ) {
            $file_url = wp_get_attachment_url( $att_id );
            if ($file_url) {
                echo '<div class="alba-attachment-item" id="alba-attachment-' . esc_attr($att_id) . '" style="display: flex; justify-content: space-between; align-items: center; background: var(--alba-card-bg); padding: 8px 14px; border-radius: 12px; box-shadow: 2px 2px 6px var(--alba-shadow-dark), -2px -2px 6px var(--alba-shadow-light);">';
                echo '<a href="' . esc_url($file_url) . '" target="_blank" style="text-decoration: none; color: var(--alba-text-main); font-weight: 600; font-size: 0.95em;">📎 ' . esc_html(get_the_title($att_id)) . '</a>';
                echo '<button type="button" class="alba-delete-attachment-btn" data-attachment-id="' . esc_attr($att_id) . '" style="background: none; border: none; color: var(--alba-danger); cursor: pointer; font-size: 1.2em;">&times;</button>';
                echo '</div>';
            }
        }
    } else {
        echo '<div id="alba-no-attachments-msg" style="color: var(--alba-text-muted); font-size: 0.9em; font-style: italic;">' . esc_html__( 'No files attached.', 'alba-board' ) . '</div>';
    }
    echo '</div>'; 
    echo '<input type="file" id="alba-file-upload-input" style="display: none;">';
    echo '<button type="button" id="alba-trigger-upload-btn" class="alba-btn-cancel">+ ' . esc_html__( 'Add File', 'alba-board' ) . '</button>';
    echo '<div id="alba-upload-feedback" style="margin-top: 8px; font-size: 0.9em; font-weight: 600;"></div>';
    echo '</div>'; 

    // Comments list
    $comments = get_post_meta( $card_id, 'alba_comments', true );
    if ( ! is_array( $comments ) ) { $comments = @unserialize($comments) ?: []; }

    echo '<div class="alba-form-group">';
    echo '<label class="alba-comment-label">' . esc_html__( 'Activity & Comments:', 'alba-board' ) . '</label>';
    echo '<div class="alba-card-comments-scrollable" id="alba-comments-list" style="background: var(--alba-input-bg); padding: 10px; border-radius: 12px; box-shadow: inset 2px 2px 5px var(--alba-shadow-dark); max-height: 150px; overflow-y: auto;">';
    if ( $comments ) {
        foreach ( $comments as $c ) {
            echo '<div class="alba-board-comment" style="margin-bottom: 10px; border-bottom: 1px solid var(--alba-shadow-dark); padding-bottom: 8px;">';
            echo '<strong style="color: var(--alba-text-title);">' . esc_html( $c['author'] ) . '</strong> ';
            echo '<span class="alba-comment-date" style="font-size: 0.8em; color: var(--alba-text-muted);">' . esc_html( alba_board_time_ago_or_date($c['date']) ) . '</span>';
            echo '<div class="alba-comment-text" style="margin-top: 4px; color: var(--alba-text-main);">' . esc_html( $c['text'] ) . '</div>';
            echo '</div>';
        }
    } else {
        echo '<div style="color: var(--alba-text-muted); font-style: italic;">' . esc_html__( 'No activity yet.', 'alba-board' ) . '</div>';
    }
    echo '</div></div>';

    // New Comment
    echo '<div class="alba-form-group">';
    echo '<label class="alba-comment-label">' . esc_html__( 'Write a comment:', 'alba-board' ) . '</label>';
    echo '<textarea id="alba-new-comment" name="new_comment" class="alba-form-input-text" rows="2" placeholder="' . esc_attr__('Type here...', 'alba-board') . '"></textarea>';
    echo '</div>';

    // Footer
    echo '<button type="submit" id="alba-card-save-btn" class="alba-btn-neumorphic alba-btn-compact">' . esc_html__( 'Save Changes', 'alba-board' ) . '</button>';
    echo '</form>';
}