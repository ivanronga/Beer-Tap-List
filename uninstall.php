<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tap_status");

delete_option('beer_festival_settings');
delete_option('beer_festival_tap_count');
delete_option('beer_festival_edit_activity');
