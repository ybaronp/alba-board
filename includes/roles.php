<?php
/** Board roles and migration of legacy duplicate role identifiers. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function alba_board_migrate_roles() {
    if ( '1' === get_option( 'alba_board_roles_schema' ) ) {
        return;
    }

    $aliases = [ 'alba_board_member' => 'board_member', 'alba_board_manager' => 'board_manager' ];
    $users = get_users( [ 'role__in' => array_keys( $aliases ) ] );
    $backup = [ 'roles' => wp_roles()->roles, 'users' => [] ];
    foreach ( $users as $user ) {
        $backup['users'][ $user->ID ] = $user->caps;
    }
    add_option( 'alba_board_roles_migration_backup', $backup, '', false );

    add_role( 'board_member', __( 'Board Member', 'alba-board' ), [ 'read' => true ] );
    add_role( 'board_manager', __( 'Board Manager', 'alba-board' ), [ 'read' => true ] );

    foreach ( $aliases as $old => $canonical ) {
        $old_role = get_role( $old );
        $role = get_role( $canonical );
        if ( $old_role ) {
            foreach ( $old_role->capabilities as $cap => $grant ) {
                if ( ! array_key_exists( $cap, $role->capabilities ) ) {
                    $role->add_cap( $cap, $grant );
                }
            }
        }
    }

    $member = get_role( 'board_member' );
    $manager = get_role( 'board_manager' );
    $member_caps = [ 'read', 'edit_cards', 'edit_published_cards', 'publish_cards', 'read_private_cards', 'edit_lists', 'edit_others_lists', 'edit_published_lists', 'read_private_lists' ];
    foreach ( $member_caps as $cap ) {
        $member->add_cap( $cap );
    }
    // Remove every historical and current card-deletion grant from Members.
    foreach ( array_keys( $member->capabilities ) as $cap ) {
        if ( 0 === strpos( $cap, 'delete' ) && false !== strpos( $cap, 'card' ) ) {
            $member->remove_cap( $cap );
        }
    }
    foreach ( [ 'boards', 'lists', 'cards' ] as $type ) {
        foreach ( [ 'edit_', 'edit_others_', 'edit_published_', 'edit_private_', 'publish_', 'read_private_', 'delete_', 'delete_others_', 'delete_published_', 'delete_private_' ] as $prefix ) {
            $manager->add_cap( $prefix . $type );
        }
    }
    $manager->add_cap( 'read' );

    foreach ( $users as $user ) {
        foreach ( $aliases as $old => $canonical ) {
            if ( in_array( $old, $user->roles, true ) ) {
                $user->add_role( $canonical );
                $user->remove_role( $old );
            }
        }
    }
    foreach ( array_keys( $aliases ) as $old ) {
        remove_role( $old );
    }
    $default_role = get_option( 'default_role' );
    if ( isset( $aliases[ $default_role ] ) ) {
        update_option( 'default_role', $aliases[ $default_role ] );
    }
    update_option( 'alba_board_roles_schema', '1', false );
}
add_action( 'init', 'alba_board_migrate_roles', 20 );
