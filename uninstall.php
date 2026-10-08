<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Plugin tables.
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tap_status");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}bftl_marketing_popups");

// Plugin options.
foreach ([
    'beer_festival_settings',
    'beer_festival_categories',
    'beer_festival_db_version',
    'beer_festival_marketing_settings',
    'beer_festival_edit_activity',
    'beer_festival_tap_count',
] as $option) {
    delete_option($option);
}

// Leftover CSV-import transients (and their expiry rows).
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
    $wpdb->esc_like('_transient_bftl_import_') . '%',
    $wpdb->esc_like('_transient_timeout_bftl_import_') . '%'
));

// Cached GitHub update checks and changelogs.
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
    $wpdb->esc_like('_transient_bftl_update_') . '%',
    $wpdb->esc_like('_transient_timeout_bftl_update_') . '%'
));

// Beer posts and their meta are deliberately kept, so removing or reinstalling
// the plugin never destroys the beer list.
