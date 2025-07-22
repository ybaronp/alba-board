<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Helper: Time ago ("x minutes ago") or formatted date if > 24h
function alba_board_time_ago_or_date( $datetime ) {
    $timestamp = strtotime( $datetime );
    $now = current_time( 'timestamp' );
    $diff = $now - $timestamp;

    if ( $diff < 60 ) {
        return __( 'Just now', 'alba-board' );
    } elseif ( $diff < 3600 ) {
        $mins = floor( $diff / 60 );
        /* translators: %d is the number of minutes ago */
        return sprintf( _n( '%d minute ago', '%d minutes ago', $mins, 'alba-board' ), $mins );
    } elseif ( $diff < 86400 ) {
        $hours = floor( $diff / 3600 );
        /* translators: %d is the number of hours ago */
        return sprintf( _n( '%d hour ago', '%d hours ago', $hours, 'alba-board' ), $hours );
    } else {
        return date_i18n( 'Y-m-d H:i', $timestamp );
    }
}

// Output modal HTML for admin card details
function alba_output_card_details_admin_modal( $card_id, $force_author = null ) {
    $card_id = absint( $card_id );
    if ( ! $card_id || get_post_type( $card_id ) !== 'alba_card' ) {
        echo esc_html__( 'Invalid card.', 'alba-board' );
        return;
    }

    $card = get_post( $card_id );
    if ( ! $card ) {
        echo esc_html__( 'Card not found.', 'alba-board' );
        return;
    }

    $author_id = $force_author !== null ? absint( $force_author ) : intval( $card->post_author );
    $tags = get_the_terms( $card_id, 'alba_tag' );
    $custom_fields = get_post_meta( $card_id );

    echo '<form id="alba-card-details-form" class="alba-card-details-form">';
    echo '<input type="hidden" name="card_id" value="' . esc_attr( $card_id ) . '">';

    // Title
    echo '<div class="alba-form-group">';
    echo '<label>' . esc_html__( 'Title:', 'alba-board' ) . '</label><br>';
    echo '<input type="text" name="card_title" value="' . esc_attr( $card->post_title ) . '" required>';
    echo '</div>';

    // Author dropdown
    echo '<div class="alba-form-group">';
    echo '<label>' . esc_html__( 'Author:', 'alba-board' ) . '</label><br>';
    echo '<select name="card_author">';
    $users = get_users( [ 'fields' => [ 'ID', 'display_name' ] ] );
    foreach ( $users as $user ) {
        $selected = $user->ID == $author_id ? 'selected' : '';
        echo '<option value="' . esc_attr( $user->ID ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $user->display_name ) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // Content/description
    echo '<div class="alba-form-group">';
    echo '<label>' . esc_html__( 'Content:', 'alba-board' ) . '</label><br>';
    echo '<textarea name="card_content" rows="3">' . esc_textarea( $card->post_content ) . '</textarea>';
    echo '</div>';

    // Tags
    if ( $tags && ! is_wp_error( $tags ) ) {
        echo '<div class="alba-form-group alba-tags-row">';
        echo '<label class="alba-tags-label">' . esc_html__( 'Tags:', 'alba-board' ) . '</label>';
        echo '<div class="alba-card-tags">';
        foreach ( $tags as $tag ) {
            $bg   = get_term_meta( $tag->term_id, 'alba_tag_bg_color', true );
            $text = get_term_meta( $tag->term_id, 'alba_tag_text_color', true );
            $style = '';
            if ( $bg )   $style .= 'background:' . esc_attr( $bg ) . ';';
            if ( $text ) $style .= 'color:' . esc_attr( $text ) . ';';
            echo '<span class="alba-card-tag-chip" style="' . esc_attr( $style ) . '">' . esc_html( $tag->name ) . '</span>';
        }
        echo '</div></div>';
    }

    // Custom fields (shows List name, ignores internals)
    $excluded_keys = [ '_edit_lock', '_edit_last', '_ai_suggestion', 'alba_comments' ];
    $showed_any = false;
    foreach ( $custom_fields as $key => $values ) {
        if ( strpos( $key, '_' ) === 0 && $key !== 'alba_list_parent' ) continue;
        if ( in_array( $key, $excluded_keys ) ) continue;

        if ( $key === 'alba_list_parent' ) {
            $list_id = absint( $values[0] );
            $list_post = get_post( $list_id );
            $list_name = ( $list_post && $list_post->post_type === 'alba_list' ) ? $list_post->post_title : __( 'Unknown', 'alba-board' );
            echo '<div class="alba-custom-field-row">';
            echo '<strong>' . esc_html__( 'List', 'alba-board' ) . ':</strong> ' . esc_html( $list_name ) . '<br>';
            echo '</div>';
            $showed_any = true;
            continue;
        }

        $val = is_array( $values ) ? $values[0] : $values;
        echo '<div class="alba-custom-field-row">';
        echo '<strong>' . esc_html( $key ) . ':</strong> ' . esc_html( $val ) . '<br>';
        echo '</div>';
        $showed_any = true;
    }
    if ( ! $showed_any ) {
        echo '<div class="alba-no-custom-fields">' . esc_html__( 'No custom fields.', 'alba-board' ) . '</div>';
    }

    // Comments section
    $comments = get_post_meta( $card_id, 'alba_comments', true );
    if ( ! is_array( $comments ) ) {
        $comments = @unserialize( $comments );
        if ( ! is_array( $comments ) ) $comments = [];
    }

    echo '<div class="alba-form-group">';
    echo '<label class="alba-comment-label">' . esc_html__( 'Comments:', 'alba-board' ) . '</label>';
    echo '<div class="alba-card-comments-scrollable" id="alba-comments-list">';
    if ( $comments ) {
        foreach ( $comments as $c ) {
            $author = isset( $c['author'] ) ? $c['author'] : '';
            $when = isset( $c['date'] ) ? alba_board_time_ago_or_date( $c['date'] ) : '';
            $text = isset( $c['text'] ) ? $c['text'] : '';
            echo '<div class="alba-board-comment">';
            echo '<strong>' . esc_html( $author ) . '</strong>';
            echo '<span class="alba-comment-date">' . esc_html( $when ) . '</span>';
            echo '<div class="alba-comment-text">' . esc_html( $text ) . '</div>';
            echo '</div>';
        }
    }
    echo '</div>'; // end comments scrollable
    echo '</div>';

    // Add comment
    echo '<div class="alba-form-group">';
    echo '<label class="alba-comment-label">' . esc_html__( 'Add a comment:', 'alba-board' ) . '</label><br>';
    echo '<textarea id="alba-new-comment" name="new_comment" rows="2"></textarea>';
    echo '</div>';

    // Save button
    echo '<button type="submit" id="alba-card-save-btn" class="button button-primary">' . esc_html__( 'Save', 'alba-board' ) . '</button>';
    echo '</form>';
}

// Secure AJAX endpoint for admin modal (nonce + permission check)
add_action( 'wp_ajax_alba_get_card_details_admin', function() {
    // Always sanitize and validate input!
    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'alba_get_card_details_admin' ) ) {
        wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'alba-board' ) ] );
    }
    if ( ! current_user_can( 'edit_cards' ) ) {
        wp_send_json_error( [ 'message' => __( 'Permission denied.', 'alba-board' ) ] );
    }
    $card_id = isset( $_POST['card_id'] ) ? absint( $_POST['card_id'] ) : 0;
    $force_author = isset( $_POST['force_author'] ) ? absint( $_POST['force_author'] ) : null;
    alba_output_card_details_admin_modal( $card_id, $force_author );
    wp_die();
});