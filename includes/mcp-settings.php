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

add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( 'alba-board_page_alba-board-ai' !== $hook ) return;
    foreach ( [ 'css' => 'style', 'js' => 'script' ] as $extension => $kind ) {
        $relative = 'assets/' . $extension . '/alba-mcp-settings.' . $extension;
        $path = dirname( __DIR__ ) . '/' . $relative;
        $url = plugins_url( $relative, dirname( __DIR__ ) . '/alba-board.php' );
        if ( 'style' === $kind ) wp_enqueue_style( 'alba-mcp-settings', $url, [], (string) filemtime( $path ) );
        else wp_enqueue_script( 'alba-mcp-settings', $url, [], (string) filemtime( $path ), true );
    }
} );

function alba_board_mcp_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $settings = alba_board_mcp_settings();
    $ready = function_exists( 'wp_register_ability' ) && defined( 'WP_MCP_VERSION' ) && version_compare( WP_MCP_VERSION, '0.7.1', '>=' );
    ?>
    <div class="wrap alba-ai">
        <h1><?php esc_html_e( 'AI connections', 'alba-board' ); ?></h1>
        <p class="alba-ai-intro"><?php esc_html_e( 'Let your AI assistant help with everyday work. Choose which boards it can use, then connect your app.', 'alba-board' ); ?></p>
        <div class="alba-ai-status <?php echo $ready ? 'is-ready' : ''; ?>"><?php echo esc_html( $ready ? __( 'Connection requirements ready', 'alba-board' ) : __( 'Requires WordPress 6.9+ and official MCP Adapter 0.7.1+.', 'alba-board' ) ); ?></div>
        <div class="alba-ai-layout">
        <form class="alba-ai-panel" method="post" action="options.php">
            <h2><?php esc_html_e( 'Board access', 'alba-board' ); ?></h2>
            <?php settings_fields( 'alba_board_mcp_group' ); ?>
            <p><label><input type="checkbox" name="alba_board_mcp[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php esc_html_e( 'Enable AI access', 'alba-board' ); ?></label></p>
            <p><label><input type="checkbox" name="alba_board_mcp[oauth_enabled]" value="1" <?php checked( ! empty( $settings['oauth_enabled'] ) ); ?>> <?php esc_html_e( 'Allow browser sign-in (OAuth)', 'alba-board' ); ?></label></p>
            <fieldset id="alba-mcp-boards"><legend><strong><?php esc_html_e( 'Boards available to AI assistants', 'alba-board' ); ?></strong></legend>
            <div class="alba-ai-tools">
                <label class="screen-reader-text" for="alba-board-search"><?php esc_html_e( 'Search boards by name or ID', 'alba-board' ); ?></label>
                <input type="search" id="alba-board-search" placeholder="<?php esc_attr_e( 'Search boards by name or ID…', 'alba-board' ); ?>">
                <label><input type="checkbox" id="alba-board-selected-only"> <?php esc_html_e( 'Selected only', 'alba-board' ); ?></label>
            </div>
            <div class="alba-ai-bulk">
                <button type="button" class="button" data-alba-board-selection="all"><?php esc_html_e( 'Select all boards', 'alba-board' ); ?></button>
                <button type="button" class="button" data-alba-board-selection="matching"><?php esc_html_e( 'Select matching', 'alba-board' ); ?></button>
                <button type="button" class="button" data-alba-board-selection="none"><?php esc_html_e( 'Clear selection', 'alba-board' ); ?></button>
            </div>
            <p class="alba-ai-count" role="status" aria-live="polite"><span id="alba-board-selected-count"></span> <?php esc_html_e( 'selected', 'alba-board' ); ?> · <span id="alba-board-shown-count"></span> <?php esc_html_e( 'shown', 'alba-board' ); ?> / <span id="alba-board-total-count"></span></p>
            <div class="alba-ai-board-list">
            <input type="hidden" name="alba_board_mcp[boards][]" value="0">
            <?php foreach ( get_posts( [ 'post_type' => 'alba_board', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] ) as $board ) : ?>
                <label class="alba-ai-board"><input type="checkbox" name="alba_board_mcp[boards][]" value="<?php echo esc_attr( $board->ID ); ?>" <?php checked( in_array( $board->ID, $settings['boards'] ?? [], true ) ); ?>> <span><?php echo esc_html( $board->post_title ); ?></span><small>#<?php echo esc_html( $board->ID ); ?></small></label>
            <?php endforeach; ?>
            <p id="alba-board-no-results" hidden><?php esc_html_e( 'No boards match. Try another search or turn off Selected only.', 'alba-board' ); ?></p>
            </div>
            <p class="description"><?php esc_html_e( 'Save to apply. New boards need to be selected separately. Each user’s existing board permissions still apply.', 'alba-board' ); ?></p>
            </fieldset>
            <p><label><input type="checkbox" name="alba_board_mcp[create_boards]" value="1" <?php checked( ! empty( $settings['create_boards'] ) ); ?>> <?php esc_html_e( 'Allow eligible users to create boards through AI', 'alba-board' ); ?></label></p>
            <?php submit_button(); ?>
        </form>
        <section class="alba-ai-panel alba-ai-connect">
        <h2><?php esc_html_e( 'Connect your AI app', 'alba-board' ); ?></h2>
        <ol class="alba-ai-steps">
            <li><?php esc_html_e( 'Enable access, choose boards and save.', 'alba-board' ); ?></li>
            <li><?php esc_html_e( 'Add this URL in your AI app using Streamable HTTP.', 'alba-board' ); ?></li>
            <li><?php esc_html_e( 'Click Authenticate or Log in in your AI app. Sign in here and approve the boards and permissions.', 'alba-board' ); ?></li>
        </ol>
        <p><label for="alba-mcp-url"><strong><?php esc_html_e( 'MCP server URL', 'alba-board' ); ?></strong></label></p>
        <div class="alba-ai-url"><input id="alba-mcp-url" class="large-text code" readonly value="<?php echo esc_attr( alba_board_mcp_url() ); ?>" onclick="this.select()">
        <button type="button" class="button button-primary" id="alba-copy-mcp-url"><?php esc_html_e( 'Copy URL', 'alba-board' ); ?></button></div>
        <p id="alba-copy-status" role="status" aria-live="polite" data-copied="<?php esc_attr_e( 'URL copied.', 'alba-board' ); ?>" data-fallback="<?php esc_attr_e( 'URL selected. Copy it using your keyboard or touch menu.', 'alba-board' ); ?>"></p>
        <p><?php esc_html_e( 'Try asking: “Show my boards and summarize the tasks due next.”', 'alba-board' ); ?></p>
        <p><a class="button" href="<?php echo esc_url( admin_url( 'profile.php#alba-ai-connections' ) ); ?>"><?php esc_html_e( 'Manage my connections', 'alba-board' ); ?></a></p>
        <p class="description"><?php esc_html_e( 'Requires an AI app with remote MCP and OAuth support. Your AI provider’s privacy terms apply to shared content.', 'alba-board' ); ?></p>
        </section>
        </div>
        <section class="alba-ai-panel alba-ai-advanced">
        <details><summary><?php esc_html_e( 'Advanced connection requirements and existing integrations', 'alba-board' ); ?></summary>
            <p><?php esc_html_e( 'Browser sign-in requires HTTPS. Disabling AI access or browser sign-in disconnects existing OAuth connections. You can disconnect individual apps from your profile. Your site must serve OAuth discovery under /.well-known/.', 'alba-board' ); ?></p>
            <p><?php esc_html_e( 'Remote clients must support OAuth authorization code with S256 PKCE, resource indicators and dynamic client registration. Automatic registration supplies app credentials without manual copying. Client ID Metadata Documents are not fetched. Not every AI product or account supports custom MCP connections.', 'alba-board' ); ?></p>
            <p><?php esc_html_e( 'Existing clients can continue using their dedicated Board user’s Application Password over HTTPS. Configure secrets only in the client. The official adapter’s original endpoint remains available for existing integrations; Alba Board OAuth tokens work only at the Alba Board URL above.', 'alba-board' ); ?></p>
            <p><a href="https://github.com/WordPress/mcp-adapter/blob/trunk/docs/getting-started/site-owners.md" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Official adapter documentation', 'alba-board' ); ?></a></p>
        </details>
        <?php alba_board_oauth_registered_apps(); ?>
        </section>
    </div>
    <?php
}
