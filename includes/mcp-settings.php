<?php
/** Optional AI access. Credentials remain in WordPress/Application Passwords. */
if ( ! defined( 'ABSPATH' ) ) exit;

function alba_board_mcp_settings() {
    $settings = get_option( 'alba_board_mcp', [] );
    return is_array( $settings ) ? $settings : [];
}

function alba_board_mcp_sanitize_settings( $input ) {
    $input = is_array( $input ) ? $input : [];
    $boards = isset( $input['boards'] ) && is_array( $input['boards'] ) ? $input['boards'] : [];
    $boards = array_values( array_unique( array_filter( array_map( 'absint', array_filter( $boards, 'is_scalar' ) ), function( $id ) {
        return 'alba_board' === get_post_type( $id ) && 'publish' === get_post_status( $id );
    } ) ) );
    return [ 'enabled' => ! empty( $input['enabled'] ), 'create_boards' => ! empty( $input['create_boards'] ), 'boards' => $boards ];
}

add_action( 'admin_init', function() {
    register_setting( 'alba_board_mcp_group', 'alba_board_mcp', [ 'type' => 'array', 'sanitize_callback' => 'alba_board_mcp_sanitize_settings' ] );
} );
add_action( 'admin_menu', function() {
    add_submenu_page( 'alba-board-visual', __( 'AI connections', 'alba-board' ), __( 'AI connections', 'alba-board' ), 'manage_options', 'alba-board-ai', 'alba_board_mcp_settings_page' );
} );

function alba_board_mcp_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $settings = alba_board_mcp_settings();
    $ready = function_exists( 'wp_register_ability' ) && defined( 'WP_MCP_VERSION' ) && version_compare( WP_MCP_VERSION, '0.7.1', '>=' );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'AI connections', 'alba-board' ); ?></h1>
        <p><?php esc_html_e( 'Connect a compatible AI assistant to selected boards using the official WordPress MCP Adapter. Access is off until you enable it. Your assistant acts with its WordPress user’s existing permissions.', 'alba-board' ); ?></p>
        <p><?php echo esc_html( $ready ? __( 'WordPress Abilities API and MCP Adapter detected.', 'alba-board' ) : __( 'Requires WordPress 6.9 or newer and official MCP Adapter 0.7.1 or newer. Regular boards work without them.', 'alba-board' ) ); ?></p>
        <form method="post" action="options.php">
            <?php settings_fields( 'alba_board_mcp_group' ); ?>
            <p><label><input type="checkbox" name="alba_board_mcp[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php esc_html_e( 'Enable AI access to the boards selected below', 'alba-board' ); ?></label></p>
            <fieldset><legend><strong><?php esc_html_e( 'Boards available to AI assistants', 'alba-board' ); ?></strong></legend>
            <input type="hidden" name="alba_board_mcp[boards][]" value="0">
            <?php foreach ( get_posts( [ 'post_type' => 'alba_board', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] ) as $board ) : ?>
                <p><label><input type="checkbox" name="alba_board_mcp[boards][]" value="<?php echo esc_attr( $board->ID ); ?>" <?php checked( in_array( $board->ID, $settings['boards'] ?? [], true ) ); ?>> <?php echo esc_html( $board->post_title . ' (#' . $board->ID . ')' ); ?></label></p>
            <?php endforeach; ?>
            </fieldset>
            <p><label><input type="checkbox" name="alba_board_mcp[create_boards]" value="1" <?php checked( ! empty( $settings['create_boards'] ) ); ?>> <?php esc_html_e( 'Also allow users who can publish boards to create new AI-accessible boards', 'alba-board' ); ?></label></p>
            <?php submit_button(); ?>
        </form>
        <h2><?php esc_html_e( 'Connect and stay in control', 'alba-board' ); ?></h2>
        <ol>
            <li><?php esc_html_e( 'Use a dedicated Board user with the minimum permissions needed. Create an Application Password in that user’s WordPress profile on your HTTPS site.', 'alba-board' ); ?></li>
            <li><?php esc_html_e( 'Use a client that supports authenticated MCP HTTP connections, or the WordPress remote helper. Configure its credentials in the client, never in a card.', 'alba-board' ); ?></li>
            <li><?php esc_html_e( 'Ask the assistant to list Alba Board abilities, then read a test board. Review proposed changes before allowing write tools in your AI client.', 'alba-board' ); ?></li>
        </ol>
        <p><strong><?php esc_html_e( 'MCP endpoint:', 'alba-board' ); ?></strong> <code><?php echo esc_html( rest_url( 'mcp/mcp-adapter-default-server' ) ); ?></code></p>
        <p><?php esc_html_e( 'This integration provides no OAuth sign-in, AI subscription, or automatic scheduling. Some web AI clients require an additional OAuth solution. Board content sent to an external AI is subject to that provider’s privacy terms. Disable access here or revoke the Application Password to disconnect.', 'alba-board' ); ?></p>
        <p><a href="https://github.com/WordPress/mcp-adapter/blob/trunk/docs/getting-started/site-owners.md" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Official MCP Adapter connection guide', 'alba-board' ); ?></a></p>
    </div>
    <?php
}
