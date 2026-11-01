<?php
/** One owner for a shared move transaction, advisory lock and deferred cache cleanup. */
if ( ! defined( 'ABSPATH' ) ) exit;

final class Alba_Board_Move_Transaction {
    private static $current;
    private $active = false;
    private $failed = false;
    private $lock;
    private $locked = false;
    private $changed = [];

    private function __construct() {}

    public static function begin( $with_receipt = false ) {
        global $wpdb;
        if ( self::$current || '0' !== (string) $wpdb->get_var( 'SELECT @@in_transaction' ) ) return alba_board_move_error( 'alba_move_transaction_busy', __( 'Another card operation is in progress.', 'alba-board' ), 409 );
        $owner = new self();
        $owner->lock = 'alba_move_' . md5( $wpdb->posts );
        if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,5)', $owner->lock ) ) ) return alba_board_move_error( 'alba_move_busy', __( 'Move in progress. Try again.', 'alba-board' ), 409 );
        $owner->locked = true;
        $names = [ $wpdb->posts, $wpdb->postmeta ];
        if ( $with_receipt ) $names[] = $wpdb->options;
        $tables = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name IN (' . implode( ',', array_fill( 0, count( $names ), '%s' ) ) . ')', $names ) );
        if ( ! is_array( $tables ) || count( $tables ) !== count( $names ) ) {
            $owner->finish();
            return alba_board_move_error( 'alba_move_storage', __( 'Move unavailable. Contact your administrator.', 'alba-board' ), 503 );
        }
        foreach ( $tables as $table ) {
            if ( ! isset( $table->Engine ) || 'innodb' !== strtolower( $table->Engine ) ) {
                $owner->finish();
                return alba_board_move_error( 'alba_move_storage', __( 'Move unavailable. Contact your administrator.', 'alba-board' ), 503 );
            }
        }
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
            $owner->finish();
            return alba_board_move_error( 'alba_move_failed', __( 'Move not confirmed. Reload.', 'alba-board' ), 500 );
        }
        $owner->active = true;
        self::$current = $owner;
        return $owner;
    }

    public function is_active() { return $this->active && self::$current === $this; }
    public function mark_failed() { $this->failed = true; }
    public function track( $ids ) {
        foreach ( (array) $ids as $id ) $this->changed[ (int) $id ] = true;
    }

    public function commit() {
        global $wpdb;
        if ( ! $this->is_active() ) return alba_board_move_error( 'alba_move_transaction', __( 'Card operation is no longer active.', 'alba-board' ), 409 );
        if ( $this->failed || false === $wpdb->query( 'COMMIT' ) ) {
            $this->rollback();
            return alba_board_move_error( 'alba_move_failed', __( 'Move not confirmed. Reload.', 'alba-board' ), 500 );
        }
        $this->active = false;
        $this->finish();
        return true;
    }

    public function rollback() {
        global $wpdb;
        if ( $this->active ) $wpdb->query( 'ROLLBACK' );
        $this->active = false;
        $this->finish();
    }

    private function finish() {
        global $wpdb;
        if ( ! $this->locked ) return;
        try {
            foreach ( $this->changed as $id => $unused ) {
                clean_post_cache( $id );
                delete_transient( 'alba_card_live_admin_' . $id );
                delete_transient( 'alba_card_live_frontend_' . $id );
            }
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $this->lock ) );
            $this->locked = false;
            $this->changed = [];
            if ( self::$current === $this ) self::$current = null;
        }
    }

    public function __destruct() { $this->rollback(); }
}
