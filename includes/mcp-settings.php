<?php
/** Optional AI access and browser sign-in. */
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
    $previous = alba_board_mcp_settings();
    $enabled = ! empty( $input['enabled'] ); $oauth = ! empty( $input['oauth_enabled'] );
    $epoch = $previous['oauth_epoch'] ?? wp_generate_uuid4();
    if ( ( ! $enabled && ! empty( $previous['enabled'] ) ) || ( ! $oauth && ! empty( $previous['oauth_enabled'] ) ) ) $epoch = wp_generate_uuid4();
    return [ 'enabled' => $enabled, 'oauth_enabled' => $oauth, 'oauth_epoch' => $epoch, 'create_boards' => ! empty( $input['create_boards'] ), 'boards' => $boards ];
}

// Apply revocation on every settings update, including WP-CLI and integrations that bypass the form.
add_filter( 'pre_update_option_alba_board_mcp', function( $value, $old ) {
    if ( ! is_array( $value ) ) return $value;
    $old = is_array( $old ) ? $old : [];
    $epoch = $old['oauth_epoch'] ?? wp_generate_uuid4();
    if ( ( ! empty( $old['enabled'] ) && empty( $value['enabled'] ) ) || ( ! empty( $old['oauth_enabled'] ) && empty( $value['oauth_enabled'] ) ) ) $epoch = wp_generate_uuid4();
    $value['oauth_epoch'] = $epoch;
    return $value;
}, 10, 2 );

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
            <p><label><input type="checkbox" name="alba_board_mcp[oauth_enabled]" value="1" <?php checked( ! empty( $settings['oauth_enabled'] ) ); ?>> <?php esc_html_e( 'Enable browser sign-in (OAuth) for compatible AI apps — requires HTTPS', 'alba-board' ); ?></label></p>
            <fieldset><legend><strong><?php esc_html_e( 'Boards available to AI assistants', 'alba-board' ); ?></strong></legend>
            <input type="hidden" name="alba_board_mcp[boards][]" value="0">
            <?php foreach ( get_posts( [ 'post_type' => 'alba_board', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] ) as $board ) : ?>
                <p><label><input type="checkbox" name="alba_board_mcp[boards][]" value="<?php echo esc_attr( $board->ID ); ?>" <?php checked( in_array( $board->ID, $settings['boards'] ?? [], true ) ); ?>> <?php echo esc_html( $board->post_title . ' (#' . $board->ID . ')' ); ?></label></p>
            <?php endforeach; ?>
            </fieldset>
            <p><label><input type="checkbox" name="alba_board_mcp[create_boards]" value="1" <?php checked( ! empty( $settings['create_boards'] ) ); ?>> <?php esc_html_e( 'Also allow users who can publish boards to create new AI-accessible boards', 'alba-board' ); ?></label></p>
            <?php submit_button(); ?>
        </form>
        <h2><?php esc_html_e( 'Connect your AI app', 'alba-board' ); ?></h2>
        <ol>
            <li><?php esc_html_e( 'Enable AI access and browser sign-in above, select a test board, and save.', 'alba-board' ); ?></li>
            <li><?php esc_html_e( 'In an AI app that supports remote MCP with OAuth, add a server using Streamable HTTP and the URL below. Select OAuth authentication; automatic app registration needs no client secret or Application Password.', 'alba-board' ); ?></li>
            <li><?php esc_html_e( 'Sign in on this WordPress site, check the app’s return address, choose boards and permissions, then select Allow connection.', 'alba-board' ); ?></li>
            <li><?php esc_html_e( 'Ask the assistant to read your test board, then propose a card. Use your AI app’s approval controls for changes.', 'alba-board' ); ?></li>
        </ol>
        <p><label for="alba-mcp-url"><strong><?php esc_html_e( 'MCP server URL', 'alba-board' ); ?></strong></label></p>
        <input id="alba-mcp-url" class="large-text code" readonly value="<?php echo esc_attr( alba_board_mcp_url() ); ?>" onclick="this.select()">
        <p><?php esc_html_e( 'No Node.js, terminal commands, or local helper is needed for this connection. You sign in on your own WordPress site. Connection options depend on your AI app and its plan.', 'alba-board' ); ?></p>
        <p><?php esc_html_e( 'Access and refresh tokens expire. Disconnect an individual app from your WordPress profile, or disable browser sign-in here to invalidate all its connections. Existing WordPress permissions and the board selections above always apply.', 'alba-board' ); ?> <a href="<?php echo esc_url( admin_url( 'profile.php#alba-ai-connections' ) ); ?>"><?php esc_html_e( 'Manage my connections', 'alba-board' ); ?></a></p>
        <p><?php esc_html_e( 'Alba Board does not provide an AI subscription or automatic scheduling. Content read by an external AI is subject to that provider’s privacy terms. The site must let WordPress serve OAuth discovery under /.well-known/.', 'alba-board' ); ?></p>
        <details><summary><?php esc_html_e( 'Advanced connection requirements and existing integrations', 'alba-board' ); ?></summary>
            <p><?php esc_html_e( 'Remote clients must support OAuth authorization code with S256 PKCE, resource indicators and dynamic client registration. Automatic registration supplies app credentials without manual copying. Client ID Metadata Documents are not fetched. Not every AI product or account supports custom MCP connections.', 'alba-board' ); ?></p>
            <p><?php esc_html_e( 'Existing clients can continue using their dedicated Board user’s Application Password over HTTPS. Configure secrets only in the client. The official adapter’s original endpoint remains available for existing integrations; Alba Board OAuth tokens work only at the Alba Board URL above.', 'alba-board' ); ?></p>
            <p><a href="https://github.com/WordPress/mcp-adapter/blob/trunk/docs/getting-started/site-owners.md" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Official adapter documentation', 'alba-board' ); ?></a></p>
        </details>
        <?php alba_board_oauth_registered_apps(); ?>
    </div>
    <?php
}
