<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}apm_persons" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}apm_corporations" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}apm_organizations" );
