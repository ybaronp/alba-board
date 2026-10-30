<?php
/** On-demand, read-only facts for AI summaries; list names never imply completion. */
if ( ! defined( 'ABSPATH' ) ) exit;
function alba_board_mcp_summary( $board_id, $maximum = 10 ) {
    if ( ! alba_board_mcp_can_read_board( $board_id ) ) return alba_board_mcp_error( 'alba_mcp_forbidden', __( 'This board is not available to your connection.', 'alba-board' ), 403 );
    global $wpdb;
    $lists = $wpdb->get_results( $wpdb->prepare( "SELECT l.ID,l.post_title FROM $wpdb->posts l INNER JOIN $wpdb->postmeta board_parent ON board_parent.post_id=l.ID AND board_parent.meta_key='alba_board_parent' AND board_parent.meta_id=(SELECT MIN(first_board.meta_id) FROM $wpdb->postmeta first_board WHERE first_board.post_id=l.ID AND first_board.meta_key='alba_board_parent') WHERE l.post_type='alba_list' AND l.post_status='publish' AND board_parent.meta_value=%s ORDER BY l.menu_order ASC,l.ID ASC", (string) $board_id ) );
    if ( $wpdb->last_error ) return alba_board_mcp_error( 'alba_mcp_summary_unavailable', __( 'The summary could not be read. Try again.', 'alba-board' ), 500 );
    $ids = array_values( array_unique( array_map( 'intval', wp_list_pluck( $lists, 'ID' ) ) ) );
    $maximum = max( 1, min( 20, (int) $maximum ) );
    $today = current_datetime()->format( 'Y-m-d' );
    $totals = [ 'overdue' => 0, 'due_today' => 0, 'upcoming' => 0, 'missing' => 0, 'invalid' => 0 ];
    $dates = [ 'overdue' => [], 'due_today' => [], 'upcoming' => [] ]; $counts = array_fill_keys( $ids, 0 );
    $result = [ 'board' => alba_board_mcp_item( get_post( $board_id ) ), 'generated_at' => gmdate( 'c' ), 'timezone' => wp_timezone_string(), 'local_date' => $today, 'list_count' => count( $ids ), 'card_count' => 0, 'lists' => [], 'completion_percent' => null, 'completion_note' => __( 'No completion status is inferred from list names. Counts cover published cards in published lists only.', 'alba-board' ) ];
    $examples = [ 'overdue' => [], 'due_today' => [], 'upcoming' => [] ];
    if ( $ids ) {
        $in = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
        $due = "(SELECT d.meta_value FROM $wpdb->postmeta d WHERE d.post_id=p.ID AND d.meta_key='alba_due_date' ORDER BY d.meta_id ASC LIMIT 1)";
        $from = " FROM $wpdb->posts p INNER JOIN $wpdb->postmeta parent ON parent.post_id=p.ID AND parent.meta_key='alba_list_parent' AND parent.meta_id=(SELECT MIN(first_parent.meta_id) FROM $wpdb->postmeta first_parent WHERE first_parent.post_id=p.ID AND first_parent.meta_key='alba_list_parent') WHERE p.post_type='alba_card' AND p.post_status='publish' AND parent.meta_value IN ($in)";
        $groups = $wpdb->get_results( $wpdb->prepare( "SELECT source.list_id,COALESCE(source.due_date,'') AS due_date,COUNT(*) AS card_count FROM (SELECT p.ID,parent.meta_value AS list_id,$due AS due_date $from) source GROUP BY source.list_id,COALESCE(source.due_date,'')", $ids ) );
        if ( $wpdb->last_error ) return alba_board_mcp_error( 'alba_mcp_summary_unavailable', __( 'The summary could not be read. Try again.', 'alba-board' ), 500 );
        foreach ( $groups as $group ) {
            $number = (int) $group->card_count; $raw = $group->due_date;
            $counts[ (int) $group->list_id ] += $number; $result['card_count'] += $number;
            if ( '' === $raw ) { $totals['missing'] += $number; continue; }
            if ( ! preg_match( '/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $raw, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) { $totals['invalid'] += $number; continue; }
            $bucket = $raw < $today ? 'overdue' : ( $raw === $today ? 'due_today' : 'upcoming' );
            $totals[ $bucket ] += $number; $dates[ $bucket ][ $raw ] = true;
        }
        foreach ( $dates as $bucket => $set ) {
            $values = array_keys( $set ); sort( $values, SORT_STRING ); $values = array_slice( $values, 0, $maximum );
            if ( ! $values ) continue;
            $date_in = implode( ',', array_fill( 0, count( $values ), '%s' ) );
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID,p.post_title,parent.meta_value AS list_id,$due AS due_date $from AND $due IN ($date_in) ORDER BY due_date ASC,p.ID ASC LIMIT %d", array_merge( $ids, $values, [ $maximum ] ) ) );
            if ( $wpdb->last_error ) return alba_board_mcp_error( 'alba_mcp_summary_unavailable', __( 'The summary could not be read. Try again.', 'alba-board' ), 500 );
            foreach ( $rows as $row ) $examples[ $bucket ][] = [ 'id' => (int) $row->ID, 'title' => $row->post_title, 'list_id' => (int) $row->list_id, 'due_date' => $row->due_date, 'url' => add_query_arg( 'alba_card', (int) $row->ID, $result['board']['url'] ) ];
        }
    }
    foreach ( $lists as $list ) $result['lists'][] = [ 'id' => (int) $list->ID, 'title' => $list->post_title, 'card_count' => $counts[ $list->ID ] ?? 0 ];
    $result['due_dates'] = $totals; $result['examples'] = $examples; $result['example_limit_per_category'] = $maximum;
    return $result;
}
