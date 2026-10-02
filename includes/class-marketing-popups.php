<?php
if (!defined('ABSPATH')) exit;

class Beer_Festival_Marketing_Popups {

    /**
     * Create/upgrade the marketing popups table on plugin activation and version bumps.
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bftl_marketing_popups';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            image_id BIGINT UNSIGNED NOT NULL,
            interval_minutes INT UNSIGNED NOT NULL DEFAULT 15,
            duration_seconds INT UNSIGNED NOT NULL DEFAULT 10,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            display_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function get_all() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bftl_marketing_popups';

        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY display_order ASC, id ASC");

        if ($wpdb->last_error) {
            return new WP_Error('db_error', __('Database error: ', 'beer-festival-tap') . $wpdb->last_error);
        }

        return $results;
    }

    public static function get_enabled_ordered() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bftl_marketing_popups';

        $results = $wpdb->get_results("SELECT * FROM $table_name WHERE enabled = 1 ORDER BY display_order ASC, id ASC");

        if ($wpdb->last_error) {
            return new WP_Error('db_error', __('Database error: ', 'beer-festival-tap') . $wpdb->last_error);
        }

        return $results;
    }

    public static function get($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bftl_marketing_popups';

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($id)));

        return $row ?: null;
    }

    public static function create($data) {
        $validated = self::validate_fields($data);
        if (is_wp_error($validated)) {
            return $validated;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'bftl_marketing_popups';

        $next_order = intval($wpdb->get_var("SELECT COALESCE(MAX(display_order), 0) + 1 FROM $table_name"));

        $inserted = $wpdb->insert(
            $table_name,
            [
                'image_id'         => $validated['image_id'],
                'interval_minutes' => $validated['interval_minutes'],
                'duration_seconds' => $validated['duration_seconds'],
                'enabled'          => $validated['enabled'],
                'display_order'    => $next_order,
            ],
            ['%d', '%d', '%d', '%d', '%d']
        );

        if ($inserted === false) {
            return new WP_Error('bftl_popup_insert_failed', __('Could not save the ad.', 'beer-festival-tap'));
        }

        return true;
    }

    public static function update($id, $data) {
        $validated = self::validate_fields($data);
        if (is_wp_error($validated)) {
            return $validated;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'bftl_marketing_popups';

        $updated = $wpdb->update(
            $table_name,
            [
                'image_id'         => $validated['image_id'],
                'interval_minutes' => $validated['interval_minutes'],
                'duration_seconds' => $validated['duration_seconds'],
                'enabled'          => $validated['enabled'],
            ],
            ['id' => intval($id)],
            ['%d', '%d', '%d', '%d'],
            ['%d']
        );

        if ($updated === false) {
            return new WP_Error('bftl_popup_update_failed', __('Could not save the ad.', 'beer-festival-tap'));
        }

        return true;
    }

    public static function delete($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bftl_marketing_popups';

        $deleted = $wpdb->delete($table_name, ['id' => intval($id)], ['%d']);

        if ($deleted === false) {
            return new WP_Error('bftl_popup_delete_failed', __('Could not delete the ad.', 'beer-festival-tap'));
        }

        return true;
    }

    public static function toggle_enabled($id) {
        $ad = self::get($id);
        if (!$ad) {
            return new WP_Error('bftl_popup_not_found', __('Ad not found.', 'beer-festival-tap'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'bftl_marketing_popups';
        $new_state = $ad->enabled ? 0 : 1;

        $wpdb->update($table_name, ['enabled' => $new_state], ['id' => intval($id)], ['%d'], ['%d']);

        return $new_state;
    }

    private static function validate_fields($data) {
        $image_id = intval($data['image_id'] ?? 0);
        if ($image_id <= 0 || !wp_attachment_is_image($image_id)) {
            return new WP_Error('bftl_popup_invalid_image', __('Please select an image for this ad.', 'beer-festival-tap'));
        }

        $interval_minutes = intval($data['interval_minutes'] ?? 0);
        if ($interval_minutes < 1) {
            return new WP_Error('bftl_popup_invalid_interval', __('Interval must be at least 1 minute.', 'beer-festival-tap'));
        }

        $duration_seconds = intval($data['duration_seconds'] ?? 0);
        if ($duration_seconds < 1) {
            return new WP_Error('bftl_popup_invalid_duration', __('Duration must be at least 1 second.', 'beer-festival-tap'));
        }

        return [
            'image_id'         => $image_id,
            'interval_minutes' => $interval_minutes,
            'duration_seconds' => $duration_seconds,
            'enabled'          => !empty($data['enabled']) ? 1 : 0,
        ];
    }
}
