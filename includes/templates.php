<?php
/** Versioned, generic built-in templates. No project/user content is copied into the catalog. */
if ( ! defined( 'ABSPATH' ) ) exit;

function alba_board_builtin_templates() {
    $card = function( $title, $description ) { return [ 'title' => $title, 'description' => $description ]; };
    return [
        'personal-tasks' => [
            'id' => 'personal-tasks', 'version' => 1, 'name' => __( 'Personal Tasks', 'alba-board' ),
            'description' => __( 'Turn a busy day into a short, manageable plan.', 'alba-board' ),
            'lists' => [
                [ 'title' => __( 'Next', 'alba-board' ), 'cards' => [
                    $card( __( 'Choose today’s three priorities', 'alba-board' ), __( 'Write down three useful things you can realistically finish today.', 'alba-board' ) ),
                    $card( __( 'Gather what you need', 'alba-board' ), __( 'Collect the information or materials needed for your first task.', 'alba-board' ) ),
                ] ],
                [ 'title' => __( 'In Progress', 'alba-board' ), 'cards' => [ $card( __( 'Focus on one small step', 'alba-board' ), __( 'Break a large task into one clear next action.', 'alba-board' ) ) ] ],
                [ 'title' => __( 'Done', 'alba-board' ), 'cards' => [] ],
            ],
        ],
        'community-event' => [
            'id' => 'community-event', 'version' => 1, 'name' => __( 'Community Event', 'alba-board' ),
            'description' => __( 'Prepare a welcoming event without losing track of the practical details.', 'alba-board' ),
            'lists' => [
                [ 'title' => __( 'Plan', 'alba-board' ), 'cards' => [
                    $card( __( 'Choose the place and date', 'alba-board' ), __( 'Compare suitable places and check availability before confirming a date.', 'alba-board' ) ),
                    $card( __( 'Check accessibility', 'alba-board' ), __( 'Check step-free access, seating and any other needs your guests may have.', 'alba-board' ) ),
                ] ],
                [ 'title' => __( 'Prepare', 'alba-board' ), 'cards' => [
                    $card( __( 'Plan refreshments', 'alba-board' ), __( 'Estimate quantities and ask about dietary requirements.', 'alba-board' ) ),
                    $card( __( 'Prepare a welcome checklist', 'alba-board' ), __( 'List the signs, supplies and information needed on the day.', 'alba-board' ) ),
                ] ],
                [ 'title' => __( 'Done', 'alba-board' ), 'cards' => [] ],
            ],
        ],
        'moving-home' => [
            'id' => 'moving-home', 'version' => 1, 'name' => __( 'Moving Home', 'alba-board' ),
            'description' => __( 'Keep packing, moving-day essentials and settling in on one board.', 'alba-board' ),
            'lists' => [
                [ 'title' => __( 'Before moving', 'alba-board' ), 'cards' => [
                    $card( __( 'Sort what to keep', 'alba-board' ), __( 'Choose what to keep, donate or recycle before packing.', 'alba-board' ) ),
                    $card( __( 'Label your boxes', 'alba-board' ), __( 'Label each box with its room and a short contents list.', 'alba-board' ) ),
                ] ],
                [ 'title' => __( 'Moving day', 'alba-board' ), 'cards' => [
                    $card( __( 'Pack an essentials bag', 'alba-board' ), __( 'Keep important documents, chargers and everyday essentials within reach.', 'alba-board' ) ),
                    $card( __( 'Check keys and access', 'alba-board' ), __( 'Confirm how and when you can enter the new home.', 'alba-board' ) ),
                ] ],
                [ 'title' => __( 'Settling in', 'alba-board' ), 'cards' => [ $card( __( 'Check essential services', 'alba-board' ), __( 'Check electricity, water and other essential services after arrival.', 'alba-board' ) ) ] ],
            ],
        ],
    ];
}

function alba_board_get_builtin_template( $id, $version = null ) {
    $templates = alba_board_builtin_templates();
    if ( ! is_string( $id ) || ! isset( $templates[ $id ] ) ) return new WP_Error( 'alba_template_missing', __( 'Choose an available template.', 'alba-board' ) );
    if ( null !== $version && $templates[ $id ]['version'] !== (int) $version ) return new WP_Error( 'alba_template_version', __( 'This template has changed. Review its current preview before creating a board.', 'alba-board' ) );
    return $templates[ $id ];
}

add_action( 'admin_menu', function() {
    add_submenu_page( 'alba-board-visual', __( 'Board templates', 'alba-board' ), __( 'Board templates', 'alba-board' ), 'edit_cards', 'alba-board-templates', 'alba_board_templates_page' );
} );
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( 'alba-board_page_alba-board-templates' !== $hook ) return;
    $path = dirname( __DIR__ ) . '/assets/css/alba-board-templates.css';
    wp_enqueue_style( 'alba-board-templates', plugins_url( 'assets/css/alba-board-templates.css', dirname( __DIR__ ) . '/alba-board.php' ), [], (string) filemtime( $path ) );
} );

function alba_board_templates_page() {
    if ( ! current_user_can( 'edit_cards' ) ) return;
    $templates = alba_board_builtin_templates();
    $selected = isset( $_GET['template_id'] ) && is_string( $_GET['template_id'] ) ? wp_unslash( $_GET['template_id'] ) : '';
    echo '<div class="wrap alba-templates"><h1>' . esc_html__( 'Board templates', 'alba-board' ) . '</h1><p>' . esc_html__( 'Start with a practical example. Preview its lists and starter tasks before making a new board.', 'alba-board' ) . '</p>';
    echo '<div class="alba-template-grid">';
    foreach ( $templates as $template ) {
        $url = add_query_arg( [ 'page' => 'alba-board-templates', 'template_id' => $template['id'] ], admin_url( 'admin.php' ) );
        $count = array_sum( array_map( function( $list ) { return count( $list['cards'] ); }, $template['lists'] ) );
        echo '<section class="alba-template-tile"><span class="alba-template-badge">' . esc_html__( 'Free template', 'alba-board' ) . '</span><h2>' . esc_html( $template['name'] ) . '</h2><p>' . esc_html( $template['description'] ) . '</p><p>' . esc_html( sprintf( __( '%1$d lists · %2$d starter tasks', 'alba-board' ), count( $template['lists'] ), $count ) ) . '</p><a class="button" href="' . esc_url( $url ) . '">' . esc_html( sprintf( __( 'Preview %s', 'alba-board' ), $template['name'] ) ) . '</a></section>';
    }
    echo '</div>';
    if ( $selected ) {
        $template = alba_board_get_builtin_template( $selected );
        if ( is_wp_error( $template ) ) echo '<div class="notice notice-error"><p>' . esc_html( $template->get_error_message() ) . '</p></div>';
        else {
            echo '<section class="alba-template-preview"><h2>' . esc_html( $template['name'] ) . '</h2><p>' . esc_html__( 'Preview only — no board has been created.', 'alba-board' ) . '</p><div class="alba-template-grid">';
            foreach ( $template['lists'] as $list ) {
                echo '<section class="alba-template-list"><h3>' . esc_html( $list['title'] ) . '</h3>';
                foreach ( $list['cards'] as $card ) echo '<article><h4>' . esc_html( $card['title'] ) . '</h4><p>' . esc_html( $card['description'] ) . '</p></article>';
                if ( ! $list['cards'] ) echo '<p>' . esc_html__( 'No starter tasks in this list.', 'alba-board' ) . '</p>';
                echo '</section>';
            }
            echo '</div>';
            if ( function_exists( 'alba_board_template_creation_form' ) ) alba_board_template_creation_form( $template );
            echo '</section>';
        }
    }
    echo '</div>';
}
