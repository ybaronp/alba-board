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
        'freelance-project' => [
            'id' => 'freelance-project', 'version' => 1, 'name' => __( 'Freelance Client Project', 'alba-board' ),
            'description' => __( 'Deliver a client project from a clear brief to an agreed handover.', 'alba-board' ),
            'lists' => [
                [ 'title' => __( 'Brief and scope', 'alba-board' ), 'cards' => [
                    $card( __( 'Confirm the client brief', 'alba-board' ), __( 'Write the goal, audience, deliverables, budget and target date. Ask the client to confirm what is included and excluded.', 'alba-board' ) ),
                    $card( __( 'Collect content and access requirements', 'alba-board' ), __( 'List the files, brand material and permissions you need. Share credentials through a secure channel, never inside a card.', 'alba-board' ) ),
                ] ],
                [ 'title' => __( 'In Progress', 'alba-board' ), 'cards' => [
                    $card( __( 'Prepare the first deliverable', 'alba-board' ), __( 'Break the agreed work into a small reviewable delivery. Add acceptance criteria so both sides know what finished means.', 'alba-board' ) ),
                ] ],
                [ 'title' => __( 'Client Review', 'alba-board' ), 'cards' => [
                    $card( __( 'Collect feedback in one place', 'alba-board' ), __( 'Ask for one consolidated feedback list. Confirm changes against the agreed scope before starting extra work.', 'alba-board' ) ),
                ] ],
                [ 'title' => __( 'Handover', 'alba-board' ), 'cards' => [
                    $card( __( 'Prepare handover and invoice checklist', 'alba-board' ), __( 'Confirm approval, provide agreed files and instructions, and check invoicing. Replace these starter tasks with your own work.', 'alba-board' ) ),
                ] ],
            ],
        ],
        'wordpress-maintenance' => [
            'id' => 'wordpress-maintenance', 'version' => 1, 'name' => __( 'WordPress Maintenance', 'alba-board' ),
            'description' => __( 'A manual checklist for safer site checks, updates and verification; nothing runs automatically.', 'alba-board' ),
            'lists' => [
                [ 'title' => __( 'Prepare', 'alba-board' ), 'cards' => [
                    $card( __( 'Confirm scope and a recoverable backup', 'alba-board' ), __( 'Agree the sites and maintenance window. Verify a recent files-and-database backup and a recovery method before changing anything.', 'alba-board' ) ),
                    $card( __( 'Review updates on a test copy', 'alba-board' ), __( 'Check core, plugin and theme changes on a separate test copy. Review compatibility notes and identify a rollback plan.', 'alba-board' ) ),
                ] ],
                [ 'title' => __( 'Maintain', 'alba-board' ), 'cards' => [
                    $card( __( 'Apply the approved changes', 'alba-board' ), __( 'After approval, apply the tested changes during the agreed window. Record what changed; do not place passwords or private backup links in this card.', 'alba-board' ) ),
                ] ],
                [ 'title' => __( 'Verify', 'alba-board' ), 'cards' => [
                    $card( __( 'Check visitor and administrator journeys', 'alba-board' ), __( 'Check key pages, forms and wp-admin. If relevant, verify login and checkout with appropriate test accounts. Record errors and screenshots.', 'alba-board' ) ),
                    $card( __( 'Review security and performance signals', 'alba-board' ), __( 'Review available error logs, security alerts and page performance. Record findings before deciding further changes.', 'alba-board' ) ),
                ] ],
                [ 'title' => __( 'Report', 'alba-board' ), 'cards' => [
                    $card( __( 'Send a clear maintenance report', 'alba-board' ), __( 'Summarize completed checks, versions changed, issues and follow-up actions. Set future maintenance dates yourself; this board does not schedule or perform updates.', 'alba-board' ) ),
                ] ],
            ],
        ],
        'project-management' => [
            'id' => 'project-management', 'version' => 1, 'name' => __( 'Project Delivery', 'alba-board' ),
            'description' => __( 'Move a shared project from scope and ownership to review and handover.', 'alba-board' ),
            'lists' => [
                [ 'title' => __( 'Plan', 'alba-board' ), 'cards' => [
                    $card( __( 'Define the outcome and acceptance criteria', 'alba-board' ), __( 'Describe who benefits, the problem being solved and how the team will check success.', 'alba-board' ) ),
                    $card( __( 'Break the work into owned tasks', 'alba-board' ), __( 'Create small tasks with a clear expected result. Agree owners and dates before adding them to the real project.', 'alba-board' ) ),
                ] ],
                [ 'title' => __( 'In Progress', 'alba-board' ), 'cards' => [
                    $card( __( 'Deliver the next reviewable step', 'alba-board' ), __( 'Work on one agreed task and record decisions. If work is blocked, explain what is needed and who can help.', 'alba-board' ) ),
                ] ],
                [ 'title' => __( 'Review', 'alba-board' ), 'cards' => [
                    $card( __( 'Check the result with the intended users', 'alba-board' ), __( 'Compare the delivery with the acceptance criteria. Record test evidence and request approval before calling it complete.', 'alba-board' ) ),
                ] ],
                [ 'title' => __( 'Done', 'alba-board' ), 'cards' => [
                    $card( __( 'Record handover and follow-up work', 'alba-board' ), __( 'Confirm the result is accepted, share usage instructions and move unfinished follow-ups to separate tasks. List names alone do not mark tasks complete for AI summaries.', 'alba-board' ) ),
                ] ],
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
