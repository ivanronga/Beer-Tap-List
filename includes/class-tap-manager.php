<?php
// File: includes/class-tap-manager.php

if (!defined('ABSPATH')) {
    exit;
}

class Tap_Manager {

    /**
     * Create the custom tap status table on plugin activation.
     */
    public static function create_tap_status_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tap_status';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            tap_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            beer_id BIGINT UNSIGNED DEFAULT NULL,
            tapped_time DATETIME DEFAULT NULL,
            active TINYINT(1) DEFAULT 1,
            category VARCHAR(50) DEFAULT NULL,
            last_updated_by BIGINT UNSIGNED DEFAULT NULL,
            last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (tap_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Assign a beer to a tap.
     */
    public static function assign_beer_to_tap($tap_id, $beer_id, $user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tap_status';
        $now = current_time('mysql');
        
        // Get current tap status
        $current_tap = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE tap_id = %d", 
            $tap_id
        ));
        
        // Only update tapped_time if beer changed
        $update_time = ($current_tap && $current_tap->beer_id != $beer_id) ? $now : ($current_tap ? $current_tap->tapped_time : $now);
    
        if ($current_tap) {
            $wpdb->update(
                $table_name,
                [
                    'beer_id' => $beer_id,
                    'tapped_time' => $update_time,
                    'active' => 1,
                    'last_updated_by' => $user_id
                ],
                ['tap_id' => $tap_id],
                ['%d', '%s', '%d', '%d'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $table_name,
                [
                    'tap_id' => $tap_id,
                    'beer_id' => $beer_id,
                    'tapped_time' => $now,
                    'active' => 1,
                    'last_updated_by' => $user_id,
                    'category' => 'Default'
                ],
                ['%d', '%d', '%s', '%d', '%d', '%s']
            );
        }
        
        do_action('beer_festival_tap_updated', $tap_id, $user_id, 'assign');
    }

    /**
     * Clear a tap (remove beer assignment).
     */
    public static function clear_tap($tap_id, $user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tap_status';
        $now = current_time('mysql');

        $wpdb->update(
            $table_name,
            [
                'beer_id' => null,
                'tapped_time' => null,
                'active' => 0,
                'last_updated_by' => $user_id,
                'last_updated' => $now
            ],
            [ 'tap_id' => $tap_id ],
            [ '%d', '%s', '%d', '%d', '%s' ],
            [ '%d' ]
        );
        do_action('beer_festival_tap_updated', $tap_id, $user_id, 'clear');
    }

    /**
     * Set the fixed category/zone for a tap, independent of the beer currently on it.
     */
    public static function set_tap_category($tap_id, $category) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tap_status';

        $current_tap = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE tap_id = %d",
            $tap_id
        ));

        if ($current_tap) {
            $wpdb->update(
                $table_name,
                ['category' => $category],
                ['tap_id' => $tap_id],
                ['%s'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $table_name,
                [
                    'tap_id' => $tap_id,
                    'active' => 0,
                    'category' => $category
                ],
                ['%d', '%d', '%s']
            );
        }
    }

    /**
     * Get all tap assignments.
     */
    public static function get_all_taps() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tap_status';
        
        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY tap_id ASC");
        
        if ($wpdb->last_error) {
            return new WP_Error('db_error', __('Database error: ', 'beer-festival-tap') . $wpdb->last_error);
        }
        
        return $results;
    }
}
